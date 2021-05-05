<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\inc\Model;


/**
 * Class Job
 * @package app\models
 */
class Grid extends Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $table
     * @param string $extent
     * @param int $size
     * @return array<mixed>
     */
    public function create(string $table, string $extent, int $size): array
    {

        $this->connect();
        $tempTable = "_" . md5(rand(1, 999999999) . microtime());
        $pl = file_get_contents(App::$param["path"] . "app/scripts/sql/st_fishnet.sql");
        $this->execQuery($pl, "PG");

        $sql = "DROP TABLE IF EXISTS {$table}";
        $this->execQuery($sql);

        $sql = "CREATE TEMP TABLE {$tempTable} AS SELECT st_fishnet('{$extent}','the_geom',{$size}, 25832)";
        $this->execQuery($sql);

        $sql = "ALTER TABLE {$tempTable} ADD gid serial";
        $this->execQuery($sql);

        $sql = "ALTER TABLE {$tempTable} ALTER st_fishnet TYPE geometry('Polygon', 25832)";
        $this->execQuery($sql);

        $sql = "CREATE TABLE {$table} AS SELECT {$tempTable}.*
            FROM
              {$tempTable} LEFT JOIN
              {$extent} AS ext ON
              st_intersects(st_fishnet,ext.the_geom)
            WHERE ext.gid IS NOT NULL";
        $this->execQuery($sql);

        if (isset($this->PDOerror)) {
            return [
                "success" => false,
                "message" => $this->PDOerror,
            ];
        }

        return [
            "success" => true,
        ];
    }
}
