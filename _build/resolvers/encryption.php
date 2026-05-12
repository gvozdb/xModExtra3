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
define('COMPONENT_VEHICLE_CLASS', 'xModExtra3\\Transport\\EncryptedVehicle');

$success = true;

if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
        case xPDOTransport::ACTION_UNINSTALL:
            // If build & install run in the same PHP process (config has install=true),
            // EncryptedVehicle.php has already been required from the /Extras/... source
            // by setupEncryption(). Requiring the target copy too triggers a fatal
            // "Cannot declare class ... already in use" because require_once dedupes
            // by absolute path, not by class identity. Skip when class is already loaded.
            if (class_exists(COMPONENT_VEHICLE_CLASS, false)) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_INFO,
                    '[' . COMPONENT_NAME . '] EncryptedVehicle already loaded — skipping require'
                );
                break;
            }

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
