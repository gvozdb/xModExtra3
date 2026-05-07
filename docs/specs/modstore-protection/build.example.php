<?php
/**
 * Example Build Script with Encryption for MODX Revolution 3.x
 *
 * This file demonstrates how to integrate package encryption into your build script.
 * It's not meant to be used as-is — copy the relevant sections into your existing build.php.
 *
 * @package YourComponent
 */

use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOTransport;

// =============================================================================
// CONFIGURATION
// =============================================================================

$config = [
    'name' => 'YourComponent',
    'name_lower' => 'yourcomponent',
    'version' => '1.0.0',
    'release' => 'pl',

    // Enable encryption (requires modstore.pro provider configured)
    'encrypt' => true,

    // Other settings...
    'install' => false,
    'update' => [
        'snippets' => true,
        'chunks' => true,
        'plugins' => true,
        'settings' => false,
    ],
];

// =============================================================================
// PATHS
// =============================================================================

$root = dirname(dirname(__FILE__)) . '/';
$sources = [
    'root' => $root,
    'build' => $root . '_build/',
    'source' => $root . '_build/source/',        // Element source files (encrypted)
    'elements' => $root . '_build/elements/',
    'resolvers' => $root . '_build/resolvers/',
    'core' => $root . 'core/components/' . $config['name_lower'] . '/',
    'assets' => $root . 'assets/components/' . $config['name_lower'] . '/',
];

// =============================================================================
// INITIALIZE MODX & BUILDER
// =============================================================================

require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$builder = new modPackageBuilder($modx);
$builder->createPackage($config['name_lower'], $config['version'], $config['release']);
$builder->registerNamespace(
    $config['name_lower'],
    false,
    true,
    '{core_path}components/' . $config['name_lower'] . '/',
    '{assets_path}components/' . $config['name_lower'] . '/'
);

$modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package');

// =============================================================================
// ENCRYPTION SETUP
// =============================================================================

if (!empty($config['encrypt'])) {
    $modx->log(modX::LOG_LEVEL_INFO, 'Encryption enabled, requesting key from modstore.pro...');

    // Find modstore.pro provider
    /** @var modTransportProvider $provider */
    $provider = $modx->getObject(modTransportProvider::class, ['name' => 'modstore.pro']);

    if (!$provider) {
        // Try by ID (modstore.pro is usually ID 2)
        $provider = $modx->getObject(modTransportProvider::class, 2);
    }

    if ($provider) {
        $provider->xpdo->setOption('contentType', 'default');

        $params = [
            'package' => $config['name_lower'],
            'version' => $config['version'] . '-' . $config['release'],
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => '3.0.0',
        ];

        $response = $provider->request('package/encode', 'POST', $params);

        // MODX3 returns PSR-7 Response or false on connection error
        if ($response === false) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to connect to modstore.pro. Check SSL certificates (curl.cainfo in php.ini)');
        } elseif ($response->getStatusCode() >= 400) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to get encryption key: HTTP ' . $response->getStatusCode());
        } else {
            // Parse XML response
            $body = (string) $response->getBody();
            $data = @simplexml_load_string($body);

            if ($data === false) {
                $modx->log(modX::LOG_LEVEL_ERROR, 'Invalid XML response from modstore.pro');
            } elseif (!empty($data->key)) {
                define('PKG_ENCODE_KEY', (string) $data->key);
                $modx->log(modX::LOG_LEVEL_INFO, 'Encryption key received successfully');
            } elseif (!empty($data->message)) {
                $modx->log(modX::LOG_LEVEL_ERROR, 'API error: ' . $data->message);
            } else {
                $modx->log(modX::LOG_LEVEL_ERROR, 'Invalid response from modstore.pro');
            }
        }
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, 'modstore.pro provider not found. Please configure it first.');
    }

    // Only add encryption files if key was successfully obtained
    if (defined('PKG_ENCODE_KEY')) {
        // Load EncryptedVehicle class for build process
        require_once $sources['core'] . 'src/Transport/EncryptedVehicle.php';

        // ---------------------------------------------------------------------
        // STEP 1: Add EncryptedVehicle.php FIRST (unencrypted)
        // This file must be available before encrypted vehicles are processed
        // ---------------------------------------------------------------------
        $builder->package->put(
            [
                'source' => $sources['core'] . 'src/Transport/EncryptedVehicle.php',
                'target' => "return MODX_CORE_PATH . 'components/" . $config['name_lower'] . "/src/Transport/';",
            ],
            [
                'vehicle_class' => \xPDO\Transport\xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,  // Keep file on uninstall for proper decryption
            ]
        );
        $modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle class to package');

        // ---------------------------------------------------------------------
        // STEP 2: Add encryption resolver FIRST
        // Loads the EncryptedVehicle class before encrypted vehicles
        // ---------------------------------------------------------------------
        $builder->putVehicle($builder->createVehicle(
            ['source' => $sources['resolvers'] . 'resolve.encryption.php'],
            ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
        ));
        $modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver');
    } else {
        $modx->log(modX::LOG_LEVEL_WARN, 'Encryption key not obtained - package will be built WITHOUT encryption');
    }
}

// =============================================================================
// CATEGORY WITH ELEMENTS (ENCRYPTED)
// =============================================================================

$category = $modx->newObject(\MODX\Revolution\modCategory::class);
$category->set('category', $config['name']);

// Add snippets from _build/source/
$snippets = include $sources['elements'] . 'snippets.php';
if (is_array($snippets) && !empty($snippets)) {
    $category->addMany($snippets, 'Snippets');
    $modx->log(modX::LOG_LEVEL_INFO, 'Added ' . count($snippets) . ' snippets');
}

// Add chunks from _build/source/
$chunks = include $sources['elements'] . 'chunks.php';
if (is_array($chunks) && !empty($chunks)) {
    $category->addMany($chunks, 'Chunks');
    $modx->log(modX::LOG_LEVEL_INFO, 'Added ' . count($chunks) . ' chunks');
}

// Add plugins from _build/source/
$plugins = include $sources['elements'] . 'plugins.php';
if (is_array($plugins) && !empty($plugins)) {
    $category->addMany($plugins, 'Plugins');
    $modx->log(modX::LOG_LEVEL_INFO, 'Added ' . count($plugins) . ' plugins');
}

// Category vehicle attributes
$attr = [
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
        'Snippets' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $config['update']['snippets'],
            xPDOTransport::UNIQUE_KEY => 'name',
        ],
        'Chunks' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $config['update']['chunks'],
            xPDOTransport::UNIQUE_KEY => 'name',
        ],
        'Plugins' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => $config['update']['plugins'],
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                ],
            ],
        ],
    ],
];

// ---------------------------------------------------------------------
// STEP 3: Use EncryptedVehicle for category when encryption is enabled
// ---------------------------------------------------------------------
if (!empty($config['encrypt']) && defined('PKG_ENCODE_KEY')) {
    // IMPORTANT: Change namespace to match your component!
    $attr['vehicle_class'] = 'YourComponent\\Transport\\EncryptedVehicle';
    $attr[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
}

$vehicle = $builder->createVehicle($category, $attr);

// Add file resolvers
$vehicle->resolve('file', [
    'source' => $sources['core'],
    'target' => "return MODX_CORE_PATH . 'components/';",
]);
$vehicle->resolve('file', [
    'source' => $sources['assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/';",
]);

// Add other resolvers (skip encryption resolver - added separately)
if (is_dir($sources['resolvers'])) {
    $resolvers = scandir($sources['resolvers']);
    foreach ($resolvers as $resolver) {
        if (strpos($resolver, '.php') === false) {
            continue;
        }
        // Skip encryption resolver - it's added separately for proper order
        if ($resolver === 'resolve.encryption.php') {
            continue;
        }
        $vehicle->resolve('php', [
            'source' => $sources['resolvers'] . $resolver,
        ]);
        $modx->log(modX::LOG_LEVEL_INFO, 'Added resolver: ' . $resolver);
    }
}

$builder->putVehicle($vehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Added category vehicle');

// =============================================================================
// OTHER ELEMENTS (System Settings, Menus, etc.)
// =============================================================================

// Add system settings, menus, events here...
// (These are NOT encrypted - they don't contain sensitive code)

// =============================================================================
// FINALIZE
// =============================================================================

// ---------------------------------------------------------------------
// STEP 4: Add encryption resolver at the END for proper uninstall
// During uninstall, operations run in reverse order
// ---------------------------------------------------------------------
if (!empty($config['encrypt']) && defined('PKG_ENCODE_KEY')) {
    $builder->putVehicle($builder->createVehicle(
        ['source' => $sources['resolvers'] . 'resolve.encryption.php'],
        ['vehicle_class' => \xPDO\Transport\xPDOScriptVehicle::class]
    ));
    $modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (for uninstall)');
}

// Set package attributes
$attributes = [];
$docsPath = $sources['core'] . 'docs/';
if (file_exists($docsPath . 'changelog.txt')) {
    $attributes['changelog'] = file_get_contents($docsPath . 'changelog.txt');
}
if (file_exists($docsPath . 'license.txt')) {
    $attributes['license'] = file_get_contents($docsPath . 'license.txt');
}
if (file_exists($docsPath . 'readme.txt')) {
    $attributes['readme'] = file_get_contents($docsPath . 'readme.txt');
}
$builder->setPackageAttributes($attributes);

// Build package
$modx->log(modX::LOG_LEVEL_INFO, 'Packing transport package...');
$builder->pack();

$modx->log(modX::LOG_LEVEL_INFO, 'Package built successfully!');
