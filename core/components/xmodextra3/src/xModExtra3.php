<?php

namespace xModExtra3;

use MODX\Revolution\modX;

class xModExtra3
{
    /** @var \modX $modx */
    public $modx;
    /** @var array $config */
    public $config = [];

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $corePath = MODX_CORE_PATH . 'components/xmodextra3/';
        $assetsUrl = MODX_ASSETS_URL . 'components/xmodextra3/';

        $this->config = array_merge([
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'processorsPath' => $corePath . 'Processors/',

            'connectorUrl' => $assetsUrl . 'connector.php',
            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . 'css/',
            'jsUrl' => $assetsUrl . 'js/',
        ], $config);

        $this->modx->lexicon->load('xmodextra3:default');
    }
}
