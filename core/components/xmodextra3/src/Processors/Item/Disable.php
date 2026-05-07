<?php

namespace xModExtra3\Processors\Item;

use xModExtra3\Model\xModExtra3Item;
use MODX\Revolution\Processors\Processor;

class Disable extends Processor
{
    public $objectType = 'xModExtra3Item';
    public $classKey = xModExtra3Item::class;
    public $languageTopics = ['xmodextra3'];
    //public $permission = 'save';


    /**
     * @return array|string
     */
    public function process()
    {
        if (!$this->checkPermissions()) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $ids = $this->modx->fromJSON($this->getProperty('ids'));
        if (empty($ids)) {
            return $this->failure($this->modx->lexicon('xmodextra3_item_err_ns'));
        }

        foreach ($ids as $id) {
            /** @var xModExtra3Item $object */
            if (!$object = $this->modx->getObject($this->classKey, $id)) {
                return $this->failure($this->modx->lexicon('xmodextra3_item_err_nf'));
            }

            $object->set('active', false);
            $object->save();
        }

        return $this->success();
    }
}
