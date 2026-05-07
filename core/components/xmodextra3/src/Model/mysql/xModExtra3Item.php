<?php

namespace xModExtra3\Model\mysql;

use xPDO\xPDO;

class xModExtra3Item extends \xModExtra3\Model\xModExtra3Item
{
    public static $metaMap = array(
        'package' => 'xModExtra3\\Model',
        'version' => '3.0',
        'table' => 'superpack_items',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' =>
            array(
                'engine' => 'InnoDB',
            ),
        'fields' =>
            array(
                'name' => '',
                'description' => '',
                'active' => 1,
            ),
        'fieldMeta' =>
            array(
                'name' =>
                    array(
                        'dbtype' => 'varchar',
                        'precision' => '100',
                        'phptype' => 'string',
                        'null' => false,
                        'default' => '',
                    ),
                'description' =>
                    array(
                        'dbtype' => 'text',
                        'phptype' => 'string',
                        'null' => true,
                        'default' => '',
                    ),
                'active' =>
                    array(
                        'dbtype' => 'tinyint',
                        'precision' => '1',
                        'phptype' => 'boolean',
                        'null' => true,
                        'default' => 1,
                    ),
            ),
        'indexes' =>
            array(
                'name' =>
                    array(
                        'alias' => 'name',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'name' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
                'active' =>
                    array(
                        'alias' => 'active',
                        'primary' => false,
                        'unique' => false,
                        'type' => 'BTREE',
                        'columns' =>
                            array(
                                'active' =>
                                    array(
                                        'length' => '',
                                        'collation' => 'A',
                                        'null' => false,
                                    ),
                            ),
                    ),
            ),
    );
}
