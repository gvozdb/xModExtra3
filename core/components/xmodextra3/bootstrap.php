<?php

/**
 * @var \MODX\Revolution\modX $modx
 * @var array $namespace
 */

// Load the classes
$modx->addPackage('xModExtra3\Model', $namespace['path'] . 'src/', null, 'xModExtra3\\');

$modx->services->add('xModExtra3', function ($c) use ($modx) {
    return new xModExtra3\xModExtra3($modx);
});
