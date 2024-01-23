<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use app\inc\Cache;


/**
 * Class Tile
 * @package app\models
 */
class Tile extends Model
{
    public string $table;

    /**
     * Tile constructor.
     * @param string $table
     */
    function __construct(string $table)
    {
        parent::__construct();
        $this->table = $table;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        $sql = "SELECT def FROM settings.geometry_columns_join WHERE _key_='$this->table'";
        $row = $this->fetchRow($this->execQuery($sql));
        $response['success'] = true;
        $arr = (array)json_decode($row['def']); // Cast stdclass to array
        foreach ($arr as $key => $value) {
            if ($value === null) { // Never send null to client
                $arr[$key] = "";
            }
        }
        $response['data'] = array($arr);
        return $response;
    }

    /**
     * @param object $data
     * @return array
     */
    public function update(object $data): array
    {
        Cache::clear();
        $schema = array(
            "theme_column",
            "label_column",
            "opacity",
            "label_max_scale",
            "label_min_scale",
            "cluster",
            "meta_tiles",
            "meta_size",
            "meta_buffer",
            "ttl",
            "auto_expire",
            "maxscaledenom",
            "minscaledenom",
            "symbolscaledenom",
            "geotype",
            "offsite",
            "format",
            "lock",
            "layers",
            "bands",
            "cache",
            "s3_tile_set",
            "label_no_clip",
            "polyline_no_clip",
        );
        $oldData = $this->get();
        $newData = array();
        foreach ($schema as $k) {
            $newData[$k] = (isset($data->$k) || (property_exists($data, $k) && $data->$k === null)) ? $data->$k : $oldData["data"][0][$k];
        }
        $newData = json_encode($newData);
        $sql = "UPDATE settings.geometry_columns_join SET def='$newData' WHERE _key_='$this->table'";
        $this->execQuery($sql, "PDO", "transaction");
        $response['success'] = true;
        $response['message'] = "Def updated";
        return $response;
    }
}