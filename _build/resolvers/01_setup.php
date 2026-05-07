<?php

use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

/**
 * Auto-install required dependencies from modstore.pro on install/upgrade.
 *
 * Why this lives in a resolver and NOT in package manifest `requires`:
 * - manifest `requires` is validated by MODX BEFORE vehicles run, so a hard
 *   requirement on pdoTools/VueTools would block the install if they are
 *   missing — the user would have to install them by hand first.
 * - This resolver runs DURING install and pulls missing/outdated deps from
 *   modstore.pro automatically.
 *
 * @var xPDOTransport $transport
 * @var array $options
 * @var modX $modx
 */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;

$packages = [
    'pdoTools' => [
        'version' => '3.0.2-pl',
        'service_url' => 'modstore.pro',
    ],
    'VueTools' => [
        'version' => '1.0.0-pl',
        'service_url' => 'modstore.pro',
    ],
];

$downloadPackage = function ($src, $dst) {
    if (ini_get('allow_url_fopen')) {
        $file = @file_get_contents($src);
    } else {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $src);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            $safeMode = @ini_get('safe_mode');
            $openBasedir = @ini_get('open_basedir');
            if (empty($safeMode) && empty($openBasedir)) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            }

            $file = curl_exec($ch);
            curl_close($ch);
        } else {
            return false;
        }
    }
    file_put_contents($dst, $file);

    return file_exists($dst);
};

$installPackage = function ($packageName, $options = []) use ($modx, $downloadPackage) {
    /** @var modTransportProvider $provider */
    $provider = null;
    if (!empty($options['service_url'])) {
        $provider = $modx->getObject(modTransportProvider::class, [
            'service_url:LIKE' => '%' . $options['service_url'] . '%',
        ]);
    }
    if (empty($provider)) {
        $provider = $modx->getObject(modTransportProvider::class, 1);
    }
    if (empty($provider)) {
        return [
            'success' => 0,
            'message' => "No transport provider configured for <b>{$packageName}</b>",
        ];
    }

    $modx->getVersionData();
    $productVersion = $modx->version['code_name'] . '-' . $modx->version['full_version'];

    $response = $provider->request('package', 'GET', [
        'supports' => $productVersion,
        'query' => $packageName,
    ]);

    if (empty($response)) {
        return [
            'success' => 0,
            'message' => "Could not find <b>{$packageName}</b> in MODX repository",
        ];
    }

    $foundPackages = simplexml_load_string($response->getBody()->getContents());
    foreach ($foundPackages as $foundPackage) {
        /** @var modTransportPackage $foundPackage */
        if ((string)$foundPackage->name === $packageName) {
            /** @var modTransportPackage $package */
            $package = $provider->transfer((string)$foundPackage->signature);
            if ($package && $package->install()) {
                return [
                    'success' => 1,
                    'message' => "<b>{$packageName}</b> was successfully installed",
                ];
            }
            return [
                'success' => 0,
                'message' => "Could not save package <b>{$packageName}</b>",
            ];
        }
    }

    return [
        'success' => 0,
        'message' => "Package <b>{$packageName}</b> not found at provider",
    ];
};

$success = false;
switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        foreach ($packages as $name => $data) {
            if (!is_array($data)) {
                $data = ['version' => $data];
            }
            // If a satisfying version is already installed — skip.
            $installed = $modx->getIterator(modTransportPackage::class, ['package_name' => $name]);
            /** @var modTransportPackage $package */
            foreach ($installed as $package) {
                if ($package->compareVersion($data['version'], '<=')) {
                    continue(2);
                }
            }
            $modx->log(modX::LOG_LEVEL_INFO, "Trying to install <b>{$name}</b>. Please wait...");
            $response = $installPackage($name, $data);
            if (is_array($response)) {
                $level = $response['success']
                    ? modX::LOG_LEVEL_INFO
                    : modX::LOG_LEVEL_ERROR;
                $modx->log($level, $response['message']);
            }
        }
        $success = true;
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // Do not auto-remove dependencies on uninstall — they may be used by other components.
        $success = true;
        break;
}

return $success;
