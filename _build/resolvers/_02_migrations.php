<?php
/**
 * Resolver — run Phinx database migrations on install/upgrade.
 *
 * ALTERNATIVE to 02_tables.php (xPDO schema-driven approach).
 * If you enable this file (rename `_02_migrations.php` → `02_migrations.php`),
 * you should DISABLE 02_tables.php (rename it to `_02_tables.php`) — otherwise
 * both will try to manage the same schema.
 *
 * REQUIREMENTS (NOT bundled with MODX):
 *   1. Composer dependency in core/components/xmodextra3/composer.json:
 *        "robmorgan/phinx": "^0.16"
 *   2. Run `composer install` in core/components/xmodextra3/ before building.
 *   3. Phinx config at core/components/xmodextra3/phinx.php
 *      (must return array with 'paths.migrations' + DB connection settings).
 *   4. Migration files in core/components/xmodextra3/db/migrations/
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 * @var MODX\Revolution\modX $modx
 */

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/**
 * Reconnect MySQL between resolvers.
 *
 * Long operations in earlier resolvers (e.g. 01_setup downloading deps)
 * can exceed wait_timeout — xPDO doesn't auto-reconnect because the dead
 * PDO object is not null.
 */
$reconnect = function (modX $modx): void {
    if ($modx->pdo === null) {
        $modx->connect();
        return;
    }
    try {
        if (@$modx->pdo->query('SELECT 1') !== false) {
            return;
        }
    } catch (\PDOException $e) {
        // fall through to reconnect
    }
    $modx->log(modX::LOG_LEVEL_WARN, '[xModExtra3] DB connection lost, reconnecting...');
    $modx->pdo = null;
    if ($modx->connection) {
        $modx->connection->pdo = null;
    }
    $modx->connect();
};

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        @ini_set('max_execution_time', 300);
        @ini_set('memory_limit', '256M');

        $componentPath = MODX_CORE_PATH . 'components/xmodextra3/';
        $vendorAutoload = $componentPath . 'vendor/autoload.php';
        $phinxConfig    = $componentPath . 'phinx.php';

        if (!file_exists($vendorAutoload)) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] vendor/autoload.php not found at: ' . $vendorAutoload);
            $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] Run "composer install" in: ' . $componentPath);
            break;
        }
        if (!file_exists($phinxConfig)) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] Phinx config not found at: ' . $phinxConfig);
            break;
        }

        $reconnect($modx);

        try {
            if (!class_exists('Phinx\\Config\\Config')) {
                require_once $vendorAutoload;
            }

            // $modx is auto-available in scope of phinx.php
            $configArray = require $phinxConfig;

            if (!isset($configArray['paths']['migrations'])) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] Invalid Phinx config: missing paths.migrations');
                break;
            }

            $config  = new Config($configArray);
            $input   = new StringInput('');
            $output  = new BufferedOutput();
            $manager = new Manager($config, $input, $output);

            $modx->log(modX::LOG_LEVEL_INFO, '[xModExtra3] Starting database migrations...');
            $manager->migrate('production');

            foreach (explode("\n", $output->fetch()) as $line) {
                if (trim($line) !== '') {
                    $modx->log(modX::LOG_LEVEL_INFO, '  ' . $line);
                }
            }
            $modx->log(modX::LOG_LEVEL_INFO, '[xModExtra3] Database migrations completed');
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] Migration error: ' . $e->getMessage());
            $modx->log(modX::LOG_LEVEL_ERROR, '[xModExtra3] Trace: ' . $e->getTraceAsString());
            // Do not abort install — just log.
        }

        $reconnect($modx);
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // Tables are preserved on uninstall by default (data safety).
        // To roll back manually: php vendor/bin/phinx rollback -e production -t 0
        $modx->log(modX::LOG_LEVEL_INFO, '[xModExtra3] DB tables preserved on uninstall');
        break;
}

return true;
