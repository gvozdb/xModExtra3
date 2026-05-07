<?php

use MODX\Revolution\modX;
use MODX\Revolution\modCategory;
use MODX\Revolution\Transport\modPackageBuilder;
use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modMenu;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modDashboardWidget;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modChunk;
use MODX\Revolution\modTemplate;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPermission;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\Transport\modTransportProvider;

class xModExtra3Package
{
    private $modx;
    private $config = [];
    private $category;
    private $category_attributes = [];

    public $builder;

    /**
     * xModExtra3Package constructor.
     *
     * @param modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $this->modx->initialize('mgr');

        $root = dirname(__FILE__, 2) . '/';
        $core = $root . 'core/components/' . $config['name_lower'] . '/';
        $assets = $root . 'assets/components/' . $config['name_lower'] . '/';

        $this->config = array_merge([
            'log_level' => modX::LOG_LEVEL_INFO,
            'log_target' => XPDO_CLI_MODE ? 'ECHO' : 'HTML',

            'root' => $root,
            'build' => $root . '_build/',
            'elements' => $root . '_build/elements/',
            'resolvers' => $root . '_build/resolvers/',
            'source' => $root . '_build/source/',
            'core' => $core,
            'assets' => $assets,
        ], $config);
        $this->modx->setLogLevel($this->config['log_level']);
        $this->modx->setLogTarget($this->config['log_target']);

        $this->initialize();
    }

    /**
     * @return modPackageBuilder
     */
    public function process()
    {
        $this->buildModel();
        $this->assets();

        // Add elements
        $elements = scandir($this->config['elements']);
        foreach ($elements as $element) {
            if (in_array($element[0], ['_', '.'])) {
                continue;
            }
            $name = preg_replace('#\.php$#', '', $element);
            if (method_exists($this, $name)) {
                $this->{$name}();
            }
        }

        // Setup encryption BEFORE creating the main category vehicle —
        // must put EncryptedVehicle class file and loader resolver first,
        // and override category vehicle_class once the key is obtained.
        $this->setupEncryption();

        // Create main vehicle
        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);

        // Files resolvers
        $vehicle->resolve('file', [
            'source' => $this->config['core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ]);
        $vehicle->resolve('file', [
            'source' => $this->config['assets'],
            'target' => "return MODX_ASSETS_PATH . 'components/';",
        ]);

        // Add resolvers into vehicle
        $resolvers = scandir($this->config['resolvers']);
        foreach ($resolvers as $resolver) {
            if (in_array($resolver[0], ['_', '.'])) {
                continue;
            }
            // Encryption resolver is handled manually in setupEncryption()
            // and finalizeEncryption() — skip it here to avoid duplication.
            if ($resolver === 'encryption.php') {
                continue;
            }
            // Dev-symlinks resolver controlled by 'dev_symlinks' flag in config.inc.php.
            // When false — resolver is excluded from the transport package entirely.
            if ($resolver === '00_devsymlinks.php' && empty($this->config['dev_symlinks'])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Skipped dev-symlinks resolver (disabled in config)');
                continue;
            }
            if ($vehicle->resolve('php', ['source' => $this->config['resolvers'] . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver ' . preg_replace('#\.php$#', '', $resolver));
            }
        }

        $this->builder->putVehicle($vehicle);

        // Encryption resolver must also run at the END so that on uninstall
        // (operations run in reverse order) the class is loaded before
        // the encrypted category vehicle is processed.
        $this->finalizeEncryption();

        $this->builder->setPackageAttributes([
            'changelog' => file_get_contents($this->config['core'] . 'docs/changelog.txt'),
            'license' => file_get_contents($this->config['core'] . 'docs/license.txt'),
            'readme' => file_get_contents($this->config['core'] . 'docs/readme.txt'),
            'requires' => [
                'php' => '>=7.2.0',
                'modx' => '>=3.0.0',
                // pdoTools / VueTools are auto-installed by _build/resolvers/setup.php.
                // Do NOT add them here — manifest `requires` is validated BEFORE
                // resolvers run, and would block install if dependencies are missing.
            ],
        ]);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added package attributes and setup options.');

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        if (!empty($this->config['install'])) {
            $this->install();
        }

        return $this->builder;
    }


    /**
     * Initialize package builder
     */
    private function initialize()
    {
        $this->builder = new modPackageBuilder($this->modx);
        $this->builder->createPackage($this->config['name_lower'], $this->config['version'], $this->config['release']);
        $this->builder->registerNamespace($this->config['name_lower'], false, true, '{core_path}components/' . $this->config['name_lower'] . '/');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package and Namespace.');

        $this->category = $this->modx->newObject(modCategory::class);
        $this->category->set('category', $this->config['name']);
        $this->category_attributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [],
        ];
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created main Category.');
    }


    /**
     * Request encryption key from modstore.pro and register EncryptedVehicle class
     * and its loader resolver in the package. Must be called BEFORE the main
     * category vehicle is created.
     *
     * If encryption is disabled or the key cannot be obtained, the build
     * continues without encryption (package is still produced as plain).
     */
    private function setupEncryption()
    {
        if (empty($this->config['encrypt'])) {
            return;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Encryption enabled, requesting key from modstore.pro...');

        /** @var modTransportProvider $provider */
        $provider = $this->modx->getObject(modTransportProvider::class, ['name' => 'modstore.pro']);
        if (!$provider) {
            // Fallback — modstore.pro is typically provider ID 2
            $provider = $this->modx->getObject(modTransportProvider::class, 2);
        }
        if (!$provider) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'modstore.pro provider not found. Please configure it first.');
            return;
        }

        $provider->xpdo->setOption('contentType', 'default');

        $params = [
            'package' => $this->config['name_lower'],
            'version' => $this->config['version'] . '-' . $this->config['release'],
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => '3.0.0',
        ];

        $response = $provider->request('package/encode', 'POST', $params);

        if ($response === false) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Failed to connect to modstore.pro. Check SSL certificates (curl.cainfo in php.ini)');
            return;
        }
        if ($response->getStatusCode() >= 400) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Failed to get encryption key: HTTP ' . $response->getStatusCode());
            return;
        }

        $body = (string) $response->getBody();
        $data = @simplexml_load_string($body);

        if ($data === false) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Invalid XML response from modstore.pro');
            return;
        }
        if (!empty($data->key)) {
            if (!defined('PKG_ENCODE_KEY')) {
                define('PKG_ENCODE_KEY', (string) $data->key);
            }
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Encryption key received successfully');
        } elseif (!empty($data->message)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'API error: ' . $data->message);
            return;
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Invalid response from modstore.pro');
            return;
        }

        if (!defined('PKG_ENCODE_KEY')) {
            $this->modx->log(modX::LOG_LEVEL_WARN, 'Encryption key not obtained - package will be built WITHOUT encryption');
            return;
        }

        // Load the class locally so createVehicle(...) can instantiate it during build.
        require_once $this->config['core'] . 'src/Transport/EncryptedVehicle.php';

        // Step 1: Add EncryptedVehicle.php as a plain file vehicle (not encrypted).
        // UNINSTALL_FILES = false so the class stays available during decryption on uninstall.
        $this->builder->package->put(
            [
                'source' => $this->config['core'] . 'src/Transport/EncryptedVehicle.php',
                'target' => "return MODX_CORE_PATH . 'components/" . $this->config['name_lower'] . "/src/Transport/';",
            ],
            [
                'vehicle_class' => \xPDO\Transport\xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle class to package');

        // Step 2: Add script resolver that loads the class before encrypted vehicles.
        $this->builder->putVehicle($this->builder->createVehicle(
            ['source' => $this->config['resolvers'] . 'encryption.php'],
            ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
        ));
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver');

        // Step 3: Swap vehicle_class for the main category to the encrypted one
        // and abort install if decryption fails.
        $this->category_attributes['vehicle_class'] = 'xModExtra3\\Transport\\EncryptedVehicle';
        $this->category_attributes[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
    }


    /**
     * Add encryption resolver at the END of the package.
     * During uninstall operations run in reverse order, so the resolver
     * placed last here fires first and loads the class before the
     * encrypted category is decrypted.
     */
    private function finalizeEncryption()
    {
        if (empty($this->config['encrypt']) || !defined('PKG_ENCODE_KEY')) {
            return;
        }

        $this->builder->putVehicle($this->builder->createVehicle(
            ['source' => $this->config['resolvers'] . 'encryption.php'],
            ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
        ));
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (for uninstall)');
    }


    /**
     * Update the model
     */
    private function buildModel()
    {
        $schemaFile = $this->config['core'] . 'schema/' . $this->config['name_lower'] . '.mysql.schema.xml';
        $outputDir = $this->config['core'] . 'src/';
        if (!file_exists($schemaFile) || empty(file_get_contents($schemaFile))) {
            return;
        }

        $manager = $this->modx->getManager();
        $generator = $manager->getGenerator();
        $generator->parseSchema(
            $schemaFile,
            $outputDir,
            [
                "compile" => 0,
                "update" => 1,
                "regenerate" => 1,
                "namespacePrefix" => "xModExtra3\\"
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Model updated');
    }


    /**
     * Install nodejs and update assets
     */
    protected function assets()
    {
        $output = [];
        if (!file_exists($this->config['build'] . 'node_modules')) {
            putenv('PATH=' . trim(shell_exec('echo $PATH')) . ':' . dirname(MODX_BASE_PATH) . '/');
            if (file_exists($this->config['build'] . 'package.json')) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Trying to install or update nodejs dependencies');
                $output = [
                    shell_exec('cd ' . $this->config['build'] . ' && npm config set scripts-prepend-node-path true && npm install'),
                ];
            }
            if (file_exists($this->config['build'] . 'gulpfile.js')) {
                $output = array_merge($output, [
                    shell_exec('cd ' . $this->config['build'] . ' && npm link gulp'),
                    shell_exec('cd ' . $this->config['build'] . ' && gulp copy'),
                ]);
            }
            if ($output) {
                $this->modx->log(xPDO::LOG_LEVEL_INFO, implode("\n", array_map('trim', $output)));
            }
        }
        if (file_exists($this->config['build'] . 'gulpfile.js')) {
            $output = shell_exec('cd ' . $this->config['build'] . ' && gulp default 2>&1');
            $this->modx->log(xPDO::LOG_LEVEL_INFO, 'Compile scripts and styles ' . trim($output));
        }
    }

    /**
     *  Install package
     */
    private function install()
    {
        $signature = $this->builder->getSignature();
        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);

        /** @var modTransportPackage $package */
        $package = $this->modx->getObject(modTransportPackage::class, ['signature' => $signature]);
        if (!$package) {
            $package = $this->modx->newObject(modTransportPackage::class);
            $package->set('signature', $signature);
            $package->fromArray([
                'created' => date('Y-m-d h:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $this->config['name'],
                'version_major' => $versionSignature[0],
                'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
            ]);
            if (!empty($sig[2])) {
                $r = preg_split('#([0-9]+)#', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                } else {
                    $package->set('release', $sig[2]);
                }
            }
            $package->save();
        }
        $package->xpdo->packages['MODX\Revolution\\'] = $package->xpdo->packages['Revolution'];
        if ($package->install()) {
            $this->modx->runProcessor('System/ClearCache');
        }
    }

    /**
     * Add settings
     */
    private function settings()
    {
        /** @noinspection PhpIncludeInspection */
        $settings = include($this->config['elements'] . 'settings.php');
        if (!is_array($settings)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in System Settings');
            return;
        }
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['settings']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        foreach ($settings as $name => $data) {
            /** @var modSystemSetting $setting */
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->fromArray(array_merge([
                'key' => $this->config['name_lower'] . '_' . $name,
                'namespace' => $this->config['name_lower'],
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($setting, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings');
    }


    /**
     * Add menus
     */
    private function menus()
    {
        /** @noinspection PhpIncludeInspection */
        $menus = include($this->config['elements'] . 'menus.php');
        if (!is_array($menus)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Menus');

            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['menus']),
            xPDOTransport::UNIQUE_KEY => 'text',
            xPDOTransport::RELATED_OBJECTS => true,
        ];
        if (is_array($menus)) {
            foreach ($menus as $name => $data) {
                /** @var modMenu $menu */
                $menu = $this->modx->newObject(modMenu::class);
                $menu->fromArray(array_merge([
                    'text' => $name,
                    'parent' => 'components',
                    'namespace' => $this->config['name_lower'],
                    'icon' => '',
                    'menuindex' => 0,
                    'params' => '',
                    'handler' => '',
                ], $data), '', true, true);
                $vehicle = $this->builder->createVehicle($menu, $attributes);
                $this->builder->putVehicle($vehicle);
            }
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($menus) . ' Menus');
    }


    /**
     * Add Dashboard Widgets
     */
    private function widgets()
    {
        /** @noinspection PhpIncludeInspection */
        $widgets = include($this->config['elements'] . 'widgets.php');
        if (!is_array($widgets)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Dashboard Widgets');

            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['widgets']),
            xPDOTransport::UNIQUE_KEY => 'name',
        ];
        foreach ($widgets as $name => $data) {
            /** @var modDashboardWidget $widget */
            $widget = $this->modx->newObject(modDashboardWidget::class);
            $widget->fromArray(array_merge([
                'name' => $name,
                'namespace' => 'core',
                'lexicon' => 'core:dashboards',
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($widget, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($widgets) . ' Dashboard Widgets');
    }


    /**
     * Add resources
     */
    private function resources()
    {
        /** @noinspection PhpIncludeInspection */
        $resources = include($this->config['elements'] . 'resources.php');
        if (!is_array($resources)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Resources');

            return;
        }
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'id',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['resources']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        $objects = [];
        foreach ($resources as $context => $items) {
            $menuindex = 0;
            foreach ($items as $alias => $item) {
                if (!isset($item['id'])) {
                    $item['id'] = $this->_idx++;
                }
                $item['alias'] = $alias;
                $item['context_key'] = $context;
                $item['menuindex'] = $menuindex++;
                $objects = array_merge(
                    $objects,
                    $this->createResource($item, $alias)
                );
            }
        }

        /** @var modResource $resource */
        foreach ($objects as $resource) {
            $vehicle = $this->builder->createVehicle($resource, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Resources');
    }


    /**
     * Add plugins
     */
    private function plugins()
    {
        /** @noinspection PhpIncludeInspection */
        $plugins = include($this->config['elements'] . 'plugins.php');
        if (!is_array($plugins)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Plugins');

            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Plugins'] = [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['plugins']),
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                ],
            ],
        ];
        $objects = [];
        foreach ($plugins as $name => $data) {
            /** @var modPlugin $plugin */
            $plugin = $this->modx->newObject(modPlugin::class);
            $plugin->fromArray(array_merge([
                'name' => $name,
                'category' => 0,
                'description' => @$data['description'],
                'plugincode' => $this::getFileContent($this->config['source'] . 'plugins/' . $data['file'] . '.php'),
                'static' => !empty($this->config['static']['plugins']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/plugins/' . $data['file'] . '.php',
            ], $data), '', true, true);

            $events = [];
            if (!empty($data['events'])) {
                foreach ($data['events'] as $event_name => $event_data) {
                    /** @var modPluginEvent $event */
                    $event = $this->modx->newObject(modPluginEvent::class);
                    $event->fromArray(array_merge([
                        'event' => $event_name,
                        'priority' => 0,
                        'propertyset' => 0,
                    ], $event_data), '', true, true);
                    $events[] = $event;
                }
            }
            if (!empty($events)) {
                $plugin->addMany($events);
            }
            $objects[] = $plugin;
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Plugins');
    }


    /**
     * Add snippets
     */
    private function snippets()
    {
        /** @noinspection PhpIncludeInspection */
        $snippets = include($this->config['elements'] . 'snippets.php');
        if (!is_array($snippets)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Snippets');
            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Snippets'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['snippets']),
            xPDOTransport::UNIQUE_KEY => 'name',
        ];
        $objects = [];
        foreach ($snippets as $name => $data) {
            /** @var modSnippet $snippet */
            $objects[$name] = $this->modx->newObject(modSnippet::class);
            $objects[$name]->fromArray(array_merge([
                'id' => 0,
                'name' => $name,
                'description' => @$data['description'],
                'snippet' => $this::getFileContent($this->config['source'] . 'snippets/' . $data['file'] . '.php'),
                'static' => !empty($this->config['static']['snippets']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/snippets/' . $data['file'] . '.php',
            ], $data), '', true, true);
            $properties = [];
            foreach (@$data['properties'] as $k => $v) {
                $properties[] = array_merge([
                    'name' => $k,
                    'desc' => $this->config['name_lower'] . '_prop_' . $k,
                    'lexicon' => $this->config['name_lower'] . ':properties',
                ], $v);
            }
            $objects[$name]->setProperties($properties);
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Snippets');
    }


    /**
     * Add chunks
     */
    private function chunks()
    {
        /** @noinspection PhpIncludeInspection */
        $chunks = include($this->config['elements'] . 'chunks.php');
        if (!is_array($chunks)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Chunks');

            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Chunks'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['chunks']),
            xPDOTransport::UNIQUE_KEY => 'name',
        ];
        $objects = [];
        foreach ($chunks as $name => $data) {
            /** @var modChunk[] $objects */
            $objects[$name] = $this->modx->newObject(modChunk::class);
            $objects[$name]->fromArray(array_merge([
                'id' => 0,
                'name' => $name,
                'description' => @$data['description'],
                'snippet' => $this::getFileContent($this->config['source'] . 'chunks/' . $data['file'] . '.tpl'),
                'static' => !empty($this->config['static']['chunks']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/chunks/' . $data['file'] . '.tpl',
            ], $data), '', true, true);
            $objects[$name]->setProperties(@$data['properties']);
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Chunks');
    }


    /**
     * Add templates
     */
    private function templates()
    {
        /** @noinspection PhpIncludeInspection */
        $templates = include($this->config['elements'] . 'templates.php');
        if (!is_array($templates)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Templates');

            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Templates'] = [
            xPDOTransport::UNIQUE_KEY => 'templatename',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['templates']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        $objects = [];
        foreach ($templates as $name => $data) {
            /** @var modTemplate[] $objects */
            $objects[$name] = $this->modx->newObject(modTemplate::class);
            $objects[$name]->fromArray(array_merge([
                'templatename' => $name,
                'description' => $data['description'],
                'content' => $this::getFileContent($this->config['core'] . 'elements/templates/' . $data['file'] . '.tpl'),
                'static' => !empty($this->config['static']['templates']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/templates/' . $data['file'] . '.tpl',
            ], $data), '', true, true);
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Templates');
    }


    /**
     * Add access policy
     */
    private function policies()
    {
        /** @noinspection PhpIncludeInspection */
        $policies = include($this->config['elements'] . 'policies.php');
        if (!is_array($policies)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Access Policies');
            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => array('name'),
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['policies']),
        ];
        foreach ($policies as $name => $data) {
            if (isset($data['data'])) {
                $data['data'] = json_encode($data['data']);
            }
            /** @var $policy modAccessPolicy */
            $policy = $this->modx->newObject(modAccessPolicy::class);
            $policy->fromArray(array_merge(array(
                    'name' => $name,
                    'lexicon' => $this->config['name_lower'] . ':permissions',
                ), $data)
                , '', true, true);
            $vehicle = $this->builder->createVehicle($policy, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policies) . ' Access Policies');
    }


    /**
     * Add policy templates
     */
    private function policy_templates()
    {
        /** @noinspection PhpIncludeInspection */
        $policy_templates = include($this->config['elements'] . 'policy_templates.php');
        if (!is_array($policy_templates)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Policy Templates');
            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => array('name'),
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['policy_templates']),
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                'Permissions' => array(
                    xPDOTransport::PRESERVE_KEYS => false,
                    xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['permission']),
                    xPDOTransport::UNIQUE_KEY => array('template', 'name'),
                ),
            ),
        ];
        foreach ($policy_templates as $name => $data) {
            $permissions = array();
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $name2 => $data2) {
                    /** @var $permission modAccessPermission */
                    $permission = $this->modx->newObject(modAccessPermission::class);
                    $permission->fromArray(array_merge(array(
                            'name' => $name2,
                            'description' => $name2,
                            'value' => true,
                        ), $data2)
                        , '', true, true);
                    $permissions[] = $permission;
                }
            }
            /** @var $permission modAccessPolicyTemplate */
            $permission = $this->modx->newObject(modAccessPolicyTemplate::class);
            $permission->fromArray(array_merge(array(
                    'name' => $name,
                    'lexicon' => $this->config['name_lower'] . ':permissions',
                ), $data)
                , '', true, true);
            if (!empty($permissions)) {
                $permission->addMany($permissions);
            }
            $vehicle = $this->builder->createVehicle($permission, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policy_templates) . ' Access Policy Templates');
    }

    /**
     * @param $filename
     *
     * @return string
     */
    private function getFileContent($filename)
    {
        if (file_exists($filename)) {
            $file = trim(file_get_contents($filename));

            return preg_match('#\<\?php(.*)#is', $file, $data)
                ? rtrim(rtrim(trim(@$data[1]), '?>'))
                : $file;
        }

        return '';
    }

    /**
     * @param array $data
     * @param string $uri
     * @param int $parent
     *
     * @return array
     */
    protected function createResource(array $data, $uri, $parent = 0)
    {
        $file = $data['context_key'] . '/' . $uri;
        /** @var modResource $resource */
        $resource = $this->modx->newObject(modResource::class);
        $resource->fromArray(array_merge([
            'parent' => $parent,
            'published' => true,
            'deleted' => false,
            'hidemenu' => false,
            'createdon' => time(),
            'template' => 1,
            'isfolder' => !empty($data['isfolder']) || !empty($data['resources']),
            'uri' => $uri,
            'uri_override' => false,
            'richtext' => false,
            'searchable' => true,
            'content' => $this::getFileContent($this->config['core'] . 'elements/resources/' . $file . '.tpl'),
        ], $data), '', true, true);

        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $resource->joinGroup($group);
            }
        }
        $resources[] = $resource;

        if (!empty($data['resources'])) {
            $menuindex = 0;
            foreach ($data['resources'] as $alias => $item) {
                if (!isset($item['id'])) {
                    $item['id'] = $this->_idx++;
                }
                $item['alias'] = $alias;
                $item['context_key'] = $data['context_key'];
                $item['menuindex'] = $menuindex++;
                $resources = array_merge(
                    $resources,
                    $this->createResource($item, $uri . '/' . $alias, $data['id'])
                );
            }
        }

        return $resources;
    }
}

/** @var array $config */
if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    exit('Could not load MODX config. Please specify correct MODX_CORE_PATH constant in config file!');
}
$config = require(dirname(__FILE__) . '/config.inc.php');
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
$install = new xModExtra3Package($modx, $config);
$builder = $install->process();

if (!empty($config['download'])) {
    $name = $builder->getSignature() . '.transport.zip';
    if ($content = file_get_contents(MODX_CORE_PATH . '/packages/' . $name)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        exit($content);
    }
}
