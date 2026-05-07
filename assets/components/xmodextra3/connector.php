<?php

/** @var  MODX\Revolution\modX $modx */
/** @var  xModExtra3\xModExtra3 $xModExtra3 */

if (file_exists(dirname(__FILE__, 4) . '/config.core.php')) {
    require_once dirname(__FILE__, 4) . '/config.core.php';
} else {
    require_once dirname(__FILE__, 5) . '/config.core.php';
}

require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';
$xModExtra3 = $modx->services->get('xModExtra3');
$modx->lexicon->load('xmodextra3:default');

// handle request
$path = $modx->getOption(
    'processorsPath',
    $xModExtra3->config,
    $modx->getOption('core_path') . 'components/xmodextra3/' . 'Processors/'
);
$modx->getRequest();

/** @var MODX\Revolution\modConnectorRequest $request */
$request = $modx->request;
$request->handleRequest([
    'processors_path' => $path,
    'location' => '',
]);
