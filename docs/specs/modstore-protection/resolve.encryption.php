<?php
/**
 * Encryption Resolver for MODX Revolution 3.x
 *
 * Loads EncryptedVehicle class during package installation/uninstallation.
 * This resolver MUST be executed BEFORE any encrypted vehicles are processed.
 *
 * USAGE:
 * 1. Copy this file to: _build/resolvers/resolve.encryption.php
 * 2. Change COMPONENT_NAME constant below
 * 3. Ensure this resolver is added FIRST in build.php (before encrypted category)
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 */

use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

/**
 * CONFIGURATION
 * Change this to match your component's lowercase name
 */
define('COMPONENT_NAME', 'yourcomponent');

$success = true;

if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
        case xPDOTransport::ACTION_UNINSTALL:
            // Path to EncryptedVehicle class
            $vehiclePath = MODX_CORE_PATH . 'components/' . COMPONENT_NAME . '/src/Transport/EncryptedVehicle.php';

            if (file_exists($vehiclePath)) {
                require_once $vehiclePath;
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_INFO,
                    '[' . COMPONENT_NAME . '] EncryptedVehicle class loaded'
                );
            } else {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    '[' . COMPONENT_NAME . '] EncryptedVehicle class not found: ' . $vehiclePath
                );
                $success = false;
            }
            break;
    }
}

return $success;
