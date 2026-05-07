<?php
/**
 * Dev-only resolver: на машинах разработчика, где компонент лежит в
 * /Extras/xModExtra3/, подменяет распакованную транспортником копию
 * симлинком на исходники. На обычных сайтах (модстор-инсталл) папка
 * Extras отсутствует — резолвер тихо выходит, ничего не делает.
 *
 * Управляется флагом 'dev_symlinks' в _build/config.inc.php (build-time):
 * при false резолвер вообще не попадает в транспортник.
 *
 * Имя файла '00_*' гарантирует выполнение ПЕРВЫМ в scandir-порядке —
 * все последующие резолверы (01_setup, 02_tables, ...) работают уже
 * с подменённой файловой системой (исходники из Extras/).
 */

if (!function_exists('xmodextra3_devsymlinks_rrmdir')) {
    function xmodextra3_devsymlinks_rrmdir($dir)
    {
        if (!is_dir($dir) || is_link($dir)) {
            return @unlink($dir);
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_dir($path) && !is_link($path)) {
                xmodextra3_devsymlinks_rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }
}

if ($object->xpdo) {
    /** @var \MODX\Revolution\modX $modx */
    $modx = $object->xpdo;

    $action = $options[\xPDO\Transport\xPDOTransport::PACKAGE_ACTION] ?? null;

    if (
        $action === \xPDO\Transport\xPDOTransport::ACTION_INSTALL
        || $action === \xPDO\Transport\xPDOTransport::ACTION_UPGRADE
    ) {
        $name = 'xmodextra3';
        $extrasRoot = MODX_BASE_PATH . 'Extras/xModExtra3/';

        // Маркер dev-окружения — реально лежащий компонент с bootstrap.php.
        // На prod-сайте папки Extras нет → резолвер ничего не делает.
        if (file_exists($extrasRoot . 'core/components/' . $name . '/bootstrap.php')) {
            $targets = [
                MODX_CORE_PATH   . 'components/' . $name => $extrasRoot . 'core/components/' . $name,
                MODX_ASSETS_PATH . 'components/' . $name => $extrasRoot . 'assets/components/' . $name,
            ];

            foreach ($targets as $installed => $devSource) {
                if (!is_dir($devSource)) {
                    continue;
                }

                $installed = rtrim($installed, '/\\');
                $devSource = rtrim($devSource, '/\\');

                // Если уже симлинк и указывает куда надо — не трогаем
                if (is_link($installed) && realpath(readlink($installed)) === realpath($devSource)) {
                    continue;
                }

                if (is_link($installed)) {
                    @unlink($installed);
                } elseif (is_dir($installed)) {
                    xmodextra3_devsymlinks_rrmdir($installed);
                } elseif (file_exists($installed)) {
                    @unlink($installed);
                }

                if (@symlink($devSource, $installed)) {
                    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO, "xModExtra3 dev-symlink: {$installed} -> {$devSource}");
                } else {
                    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, "xModExtra3 dev-symlink FAILED: {$installed} -> {$devSource}");
                }
            }
        }
    }
}

return true;
