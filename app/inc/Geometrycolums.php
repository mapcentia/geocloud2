<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


class Geometrycolums
{
    static public $geometry = array(
        'f_table_schema' =>
            array(
                'num' => 1,
                'type' => 'name',
                'full_type' => 'name',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'f_table_name' =>
            array(
                'num' => 2,
                'type' => 'name',
                'full_type' => 'name',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'f_geometry_column' =>
            array(
                'num' => 3,
                'type' => 'name',
                'full_type' => 'name',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'coord_dimension' =>
            array(
                'num' => 4,
                'type' => 'integer',
                'full_type' => 'integer',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'srid' =>
            array(
                'num' => 5,
                'type' => 'integer',
                'full_type' => 'integer',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'type' =>
            array(
                'num' => 6,
                'type' => 'character varying',
                'full_type' => 'character varying',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
    );
    static public $join = array(
        '_key_' =>
            array(
                'num' => 7,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'f_table_abstract' =>
            array(
                'num' => 8,
                'type' => 'character varying',
                'full_type' => 'character varying(256)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'f_table_title' =>
            array(
                'num' => 9,
                'type' => 'character varying',
                'full_type' => 'character varying(256)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'tweet' =>
            array(
                'num' => 10,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'editable' =>
            array(
                'num' => 11,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'created' =>
            array(
                'num' => 12,
                'type' => 'timestamp with time zone',
                'full_type' => 'timestamp with time zone',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'lastmodified' =>
            array(
                'num' => 13,
                'type' => 'timestamp with time zone',
                'full_type' => 'timestamp with time zone',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'authentication' =>
            array(
                'num' => 14,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'fieldconf' =>
            array(
                'num' => 15,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'meta_url' =>
            array(
                'num' => 16,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'layergroup' =>
            array(
                'num' => 17,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'def' =>
            array(
                'num' => 18,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'class' =>
            array(
                'num' => 19,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'wmssource' =>
            array(
                'num' => 20,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'baselayer' =>
            array(
                'num' => 21,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'sort_id' =>
            array(
                'num' => 22,
                'type' => 'integer',
                'full_type' => 'integer',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'tilecache' =>
            array(
                'num' => 23,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'data' =>
            array(
                'num' => 24,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'not_querable' =>
            array(
                'num' => 25,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'single_tile' =>
            array(
                'num' => 26,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'cartomobile' =>
            array(
                'num' => 27,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'filter' =>
            array(
                'num' => 28,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'bitmapsource' =>
            array(
                'num' => 29,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'privileges' =>
            array(
                'num' => 30,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'enablesqlfilter' =>
            array(
                'num' => 31,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'triggertable' =>
            array(
                'num' => 32,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'classwizard' =>
            array(
                'num' => 33,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'extra' =>
            array(
                'num' => 34,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'skipconflict' =>
            array(
                'num' => 35,
                'type' => 'boolean',
                'full_type' => 'boolean',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'roles' =>
            array(
                'num' => 36,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'elasticsearch' =>
            array(
                'num' => 37,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'uuid' =>
            array(
                'num' => 38,
                'type' => 'uuid',
                'full_type' => 'uuid',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'tags' =>
            array(
                'num' => 39,
                'type' => 'jsonb',
                'full_type' => 'jsonb',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'meta' =>
            array(
                'num' => 40,
                'type' => 'jsonb',
                'full_type' => 'jsonb',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'wmsclientepsgs' =>
            array(
                'num' => 41,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'featureid' =>
            array(
                'num' => 42,
                'type' => 'character varying',
                'full_type' => 'character varying(255)',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'note' =>
            array(
                'num' => 43,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
        'legend_url' =>
            array(
                'num' => 16,
                'type' => 'text',
                'full_type' => 'text',
                'is_nullable' => true,
                'restriction' => NULL,
            ),
    );
}