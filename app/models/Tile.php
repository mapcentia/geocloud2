<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Tile extends Model
{
    var $table;

    function __construct($table)
    {
        parent::__construct();
        $this->table = $table;
    }

    public function get()
    {
        $sql = "SELECT def FROM settings.geometry_columns_join WHERE _key_='{$this->table}'";
        $row = $this->fetchRow($this->execQuery($sql), "assoc");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $arr = (array)json_decode($row['def']); // Cast stdclass to array
            foreach ($arr as $key => $value) {
                if ($value === null) { // Never send null to client
                    $arr[$key] = "";
                }
            }
            $response['data'] = array($arr);
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 406;
        }
        return $response;
    }

    public function update($data)
    {
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
        );
        $oldData = $this->get();
        $newData = array();
        foreach ($schema as $k) {
            $newData[$k] = (isset($data->$k) || (isset($data->$k) && $data->$k === false) || (isset($data->$k) && $data->$k === "")) ? $data->$k : $oldData["data"][0][$k];
            //$newData[$k] = !empty($data->$k) ? $data->$k : !empty($oldData["data"][0][$k]) ? $oldData["data"][0][$k] : "";
        }
        $newData = json_encode($newData);
        $sql = "UPDATE settings.geometry_columns_join SET def='{$newData}' WHERE _key_='{$this->table}'";
        $this->execQuery($sql, "PDO", "transaction");

        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Def updated";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 406;
        }
        return $response;
    }
}