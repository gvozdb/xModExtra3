<?php

namespace xModExtra3\Processors\Item;

use MODX\Revolution\Processors\Model\GetProcessor;
use xModExtra3\Model\xModExtra3Item;

class Get extends GetProcessor
{
    public $objectType = 'xModExtra3Item';
    public $classKey = xModExtra3Item::class;
    public $languageTopics = ['xmodextra3:default'];
    //public $permission = 'view';


    /**
     * We doing special check of permission
     * because of our objects is not an instances of modAccessibleObject
     *
     * @return mixed
     */
    public function process()
    {
        if (!$this->checkPermissions()) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        return parent::process();
    }
}
