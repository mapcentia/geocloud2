<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
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
     * @return array
     */
    public function create(string $table, string $extent, int $size): array
    {
        $this->connect("PG");
        $tempTable = "_" . md5(rand(1, 999999999) . microtime());
        $pl = file_get_contents(__DIR__ . "/../scripts/sql/st_fishnet.sql");
        $this->execQuery($pl, "PG");
        $this->connect();
        // The temp table only lives for the session/transaction that created it.
        // Under PgBouncer transaction pooling each autocommit statement can land
        // on a different backend, so the CREATE TEMP TABLE and every statement that
        // reads it must run inside a single transaction on the same connection.
        $this->withTransaction(function () use ($table, $tempTable, $extent, $size) {
            $this->execQuery("DROP TABLE IF EXISTS {$table}", "PDO", "transaction");
            $this->execQuery("CREATE TEMP TABLE {$tempTable} AS SELECT st_fishnet('{$extent}','the_geom',{$size}, 25832)", "PDO", "transaction");
            $this->execQuery("ALTER TABLE {$tempTable} ADD gid serial", "PDO", "transaction");
            $this->execQuery("ALTER TABLE {$tempTable} ALTER st_fishnet TYPE geometry('Polygon', 25832)", "PDO", "transaction");
            $this->execQuery("CREATE TABLE {$table} AS SELECT {$tempTable}.*
            FROM
              {$tempTable} LEFT JOIN
              {$extent} AS ext ON
              st_intersects(st_fishnet,ext.the_geom)
            WHERE ext.gid IS NOT NULL", "PDO", "transaction");
        });
        return [
            "success" => true,
        ];
    }
}
