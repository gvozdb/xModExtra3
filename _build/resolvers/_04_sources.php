<?php
/**
 * Resolver — create/update a default Media Source for the component.
 *
 * Typical use case: components that store user-uploaded files (images,
 * docs) want their own modMediaSource pointing to a dedicated subfolder
 * under MODX_ASSETS_PATH, with thumbnail presets and upload limits.
 *
 * The source ID is then persisted in a System Setting so the component
 * can resolve it at runtime.
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 * @var MODX\Revolution\modX $modx
 */

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Sources\modMediaSource;
use xPDO\Transport\xPDOTransport;

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        // Strip "/assets/" → "assets" for use inside basePath/baseUrl.
        $tmp    = explode('/', MODX_ASSETS_URL);
        $assets = $tmp[count($tmp) - 2];

        $properties = [
            'name'        => 'xModExtra3 Files',
            'description' => 'Default media source for xModExtra3 uploads',
            'class_key'   => 'sources.modFileMediaSource',
            'is_stream'   => 1,
            'properties'  => [
                'basePath' => [
                    'name'    => 'basePath',
                    'desc'    => 'prop_file.basePath_desc',
                    'type'    => 'textfield',
                    'lexicon' => 'core:source',
                    'value'   => $assets . '/images/xmodextra3/',
                ],
                'baseUrl' => [
                    'name'    => 'baseUrl',
                    'desc'    => 'prop_file.baseUrl_desc',
                    'type'    => 'textfield',
                    'lexicon' => 'core:source',
                    'value'   => $assets . '/images/xmodextra3/',
                ],
                'imageExtensions' => [
                    'name'    => 'imageExtensions',
                    'desc'    => 'prop_file.imageExtensions_desc',
                    'type'    => 'textfield',
                    'lexicon' => 'core:source',
                    'value'   => 'jpg,jpeg,png,gif,webp',
                ],
                'allowedFileTypes' => [
                    'name'    => 'allowedFileTypes',
                    'desc'    => 'prop_file.allowedFileTypes_desc',
                    'type'    => 'textfield',
                    'lexicon' => 'core:source',
                    'value'   => 'jpg,jpeg,png,gif,webp',
                ],
                'thumbnailType' => [
                    'name'    => 'thumbnailType',
                    'desc'    => 'prop_file.thumbnailType_desc',
                    'type'    => 'list',
                    'lexicon' => 'core:source',
                    'options' => [
                        ['text' => 'Png',  'value' => 'png'],
                        ['text' => 'Jpg',  'value' => 'jpg'],
                        ['text' => 'Webp', 'value' => 'webp'],
                    ],
                    'value'   => 'jpg',
                ],
            ],
        ];

        // System setting that holds the resolved source id.
        $settingKey = ['key' => 'xmodextra3_default_source'];

        /** @var modSystemSetting $setting */
        $setting = $modx->getObject(modSystemSetting::class, $settingKey)
            ?: $modx->newObject(modSystemSetting::class, array_merge($settingKey, [
                'namespace' => 'xmodextra3',
                'area'      => 'xmodextra3_main',
                'xtype'     => 'numberfield',
                'value'     => 0,
            ]));

        // Look up existing source by id (from setting) or by name.
        $c = $modx->newQuery(modMediaSource::class);
        $c->where(['id' => $setting->get('value')]);
        $c->orCondition(['name' => $properties['name']]);

        /** @var modMediaSource $source */
        $source = $modx->getObject(modMediaSource::class, $c);
        if (!$source) {
            $source = $modx->newObject(modMediaSource::class, $properties);
        } else {
            // Merge: keep existing user-tuned values, add missing keys from defaults.
            $current = $source->get('properties') ?: [];
            foreach ($properties['properties'] as $k => $v) {
                if (!array_key_exists($k, $current)) {
                    $current[$k] = $v;
                }
            }
            foreach ($current as $k => $prop) {
                if (is_array($prop) && !array_key_exists('desc', $prop)) {
                    $current[$k]['desc'] = '';
                }
            }
            $source->set('properties', $current);
        }
        $source->save();

        $setting->set('value', $source->get('id'));
        $setting->save();

        // Make sure target dirs exist so uploads don't 500 on first use.
        @mkdir(MODX_ASSETS_PATH . 'images/', 0755, true);
        @mkdir(MODX_ASSETS_PATH . 'images/xmodextra3/', 0755, true);
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // Do NOT delete the media source — it may contain user files
        // that the user still needs. Just remove the binding setting.
        // $modx->removeCollection(modSystemSetting::class, ['key' => 'xmodextra3_default_source']);
        break;
}

return true;
