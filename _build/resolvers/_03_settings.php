<?php
/**
 * Resolver — post-install seeding & settings tweaks.
 *
 * Use this for things that can't be expressed declaratively in
 * _build/elements/*.php — e.g.:
 *   - seed initial DB rows after migrations/tables created
 *   - patch existing modSystemSetting values on upgrade
 *   - fill chunk descriptions from lexicon entries
 *   - update snippet default properties
 *
 * Runs AFTER schema is in place (02_tables.php / _02_migrations.php).
 *
 * @var xPDO\Transport\xPDOTransport $transport
 * @var array $options
 * @var MODX\Revolution\modX $modx
 */

use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;

if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;
$prefix = $modx->config['table_prefix'];

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $modx->lexicon->load('xmodextra3:default');

        // ──────────────────────────────────────────────────────────────
        // Example 1: seed an initial row via raw SQL
        // (use raw SQL for tables whose models are not loaded yet)
        // ──────────────────────────────────────────────────────────────
        // $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}xmodextra3_items WHERE id = 1");
        // $stmt->execute();
        // if ((int)$stmt->fetchColumn() === 0) {
        //     $stmt = $modx->prepare(
        //         "INSERT INTO {$prefix}xmodextra3_items (id, name, active) VALUES (1, :name, 1)"
        //     );
        //     $stmt->execute([':name' => $modx->lexicon('xmodextra3_default_item_name')]);
        // }

        // ──────────────────────────────────────────────────────────────
        // Example 2: bind a System Setting to a Category id once
        // ──────────────────────────────────────────────────────────────
        // /** @var modSystemSetting $setting */
        // $setting = $modx->getObject(modSystemSetting::class, ['key' => 'xmodextra3_default_category']);
        // if ($setting && !$setting->get('editedon')) {
        //     /** @var modCategory $category */
        //     if ($category = $modx->getObject(modCategory::class, ['category' => 'xModExtra3'])) {
        //         $setting->set('value', $category->get('id'));
        //         $setting->save();
        //     }
        // }

        // ──────────────────────────────────────────────────────────────
        // Example 3: backfill chunk descriptions from lexicon
        // ──────────────────────────────────────────────────────────────
        // $chunks = [
        //     'tpl.xmodextra3.row' => $modx->lexicon('xmodextra3_chunk_row_desc'),
        // ];
        // foreach ($chunks as $name => $description) {
        //     /** @var modChunk $chunk */
        //     if ($chunk = $modx->getObject(modChunk::class, ['name' => $name])) {
        //         if (!$chunk->get('locked') && empty($chunk->get('description'))) {
        //             $chunk->set('description', $description);
        //             $chunk->save();
        //         }
        //     }
        // }

        // ──────────────────────────────────────────────────────────────
        // Example 4: update a snippet's default properties on upgrade
        // ──────────────────────────────────────────────────────────────
        // /** @var modSnippet $snippet */
        // $snippet = $modx->getObject(modSnippet::class, ['name' => 'xmeSomething']);
        // if ($snippet) {
        //     $properties = $snippet->get('properties') ?: [];
        //     $properties['tpl'] = [
        //         'name'  => 'tpl',
        //         'type'  => 'textfield',
        //         'value' => 'tpl.xmodextra3.default',
        //     ];
        //     $snippet->set('properties', $properties);
        //     $snippet->save();
        // }

        break;

    case xPDOTransport::ACTION_UNINSTALL:
        // Wipe namespaced settings on uninstall.
        // Comment out if you want to preserve user-tuned values.
        // $modx->removeCollection(modSystemSetting::class, ['namespace' => 'xmodextra3']);
        break;
}

return true;
