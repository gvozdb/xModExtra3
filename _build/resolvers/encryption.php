<?php
/**
 * Encryption Resolver for MODX Revolution 3.x
 *
 * Loads EncryptedVehicle class during package installation/uninstallation.
 * Must run BEFORE any encrypted vehicles are processed.
 *
 * NOTE: This resolver is added to the package manually by build.php
 * (it is intentionally excluded from the auto-scanned resolvers loop).
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 */

use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

define('COMPONENT_NAME', 'xmodextra3');

$success = true;

if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
        case xPDOTransport::ACTION_UNINSTALL:
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
