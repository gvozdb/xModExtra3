<?php

namespace xModExtra3\Processors\Item;

use MODX\Revolution\Processors\Model\CreateProcessor;
use xModExtra3\Model\xModExtra3Item;

class Create extends CreateProcessor
{
    public $objectType = 'xModExtra3Item';
    public $classKey = xModExtra3Item::class;
    public $languageTopics = ['xmodextra3'];
    //public $permission = 'create';


    /**
     * @return bool
     */
    public function beforeSet()
    {
        $name = trim($this->getProperty('name'));
        if (empty($name)) {
            $this->modx->error->addField('name', $this->modx->lexicon('xmodextra3_item_err_name'));
        } elseif ($this->modx->getCount($this->classKey, ['name' => $name])) {
            $this->modx->error->addField('name', $this->modx->lexicon('xmodextra3_item_err_ae'));
        }

        return parent::beforeSet();
    }
}
