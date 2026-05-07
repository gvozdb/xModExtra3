<?php

if (!defined('MODX_CORE_PATH')) {
    $path = dirname(__FILE__);
    while (!file_exists($path . '/core/config/config.inc.php') && (strlen($path) > 1)) {
        $path = dirname($path);
    }
    define('MODX_CORE_PATH', $path . '/core/');
}

// Single source of truth for version: ../VERSION at repo root.
// Bump it there — nowhere else. Fallback ниже используется только если файл
// внезапно отсутствует (например, пакет распакован без VERSION).
$versionFile = dirname(__FILE__) . '/../VERSION';
$version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
if ($version === '') {
    $version = '1.0.0';
}

return [
    'name' => 'xModExtra3',
    'name_lower' => 'xmodextra3',
    'version' => $version,
    'release' => 'beta',

    // Enable modstore.pro encryption for paid-component protection.
    // Requires modstore.pro provider configured in MODX Package Management.
    'encrypt' => false,

    // Dev-symlinks resolver: при сборке включает в транспортник resolvers/05_devsymlinks.php,
    // который на dev-машинах (есть папка /Extras/xModExtra3/) подменяет распакованную
    // копию компонента симлинком на исходники. На prod-инсталлах резолвер ничего
    // не делает (Extras отсутствует). false → резолвер вообще не попадёт в пакет.
    // НЕ РАБОТАЕТ, НЕ ВКЛЮЧАТЬ! ПОЧИНЮ ПОЗЖЕ!
    'dev_symlinks' => false,

    // Install package to site right after build
    'install' => true,
    
    // Which elements should be updated on package upgrade
    'update' => [
        'chunks' => false,
        'menus' => true,
        'permission' => true,
        'plugins' => true,
        'policies' => true,
        'policy_templates' => true,
        'resources' => false,
        'settings' => false,
        'snippets' => true,
        'templates' => false,
        'widgets' => false,
    ],
    // Which elements should be static by default
    'static' => [
        'plugins' => false,
        'snippets' => false,
        'chunks' => false,
    ],
    // Log settings
    'log_level' => !empty($_REQUEST['download']) ? 0 : 3,
    'log_target' => php_sapi_name() == 'cli' ? 'ECHO' : 'HTML',
    // Download transport.zip after build
    'download' => !empty($_REQUEST['download']),
];
