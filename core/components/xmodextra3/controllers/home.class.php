<?php

use MODX\Revolution\modExtraManagerController;

/**
 * The home manager controller for xModExtra3.
 *
 */
class xModExtra3HomeManagerController extends modExtraManagerController
{
    /** @var xModExtra3\xModExtra3 $xModExtra3 */
    public $xModExtra3;


    /**
     *
     */
    public function initialize()
    {
        $this->xModExtra3 = $this->modx->services->get('xModExtra3');
        parent::initialize();
    }


    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['xmodextra3:default'];
    }


    /**
     * @return bool
     */
    public function checkPermissions()
    {
        return true;
    }


    /**
     * @return null|string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('xmodextra3');
    }


    /**
     * @return void
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->xModExtra3->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/xmodextra3.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/misc/utils.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/misc/combo.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/widgets/items.grid.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/widgets/items.windows.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/widgets/home.panel.js');
        $this->addJavascript($this->xModExtra3->config['jsUrl'] . 'mgr/sections/home.js');

        $this->addHtml('<script type="text/javascript">
        xModExtra3.config = ' . json_encode($this->xModExtra3->config) . ';
        xModExtra3.config.connector_url = "' . $this->xModExtra3->config['connectorUrl'] . '";
        Ext.onReady(function() {MODx.load({ xtype: "xmodextra3-page-home"});});
        </script>');
    }


    /**
     * @return string
     */
    public function getTemplateFile()
    {
        $this->content .= '<div id="xmodextra3-panel-home-div"></div>';
        return '';
    }
}
