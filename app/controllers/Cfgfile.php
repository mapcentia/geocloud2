<?php
namespace app\controllers;
error_reporting(0);

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;

class Cfgfile extends \app\inc\Controller
{
    function get_index()
    {
        $postgisObject = new Model();
        ob_start();
        echo "[cache]\n";
        echo "type=Disk\n";
        echo "base=" . App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb'] . "\n\n";

        //echo "type=AWSS3\n";
        //echo "access_key=*****\n";
        //echo "secret_access_key=******\n";
        //echo "db={$user}\n";

        $sql = "SELECT * FROM settings.geometry_columns_view ORDER BY sort_id";
        $result = $postgisObject->execQuery($sql);
        if ($postgisObject->PDOerror) {
            ob_get_clean();
            return false;
        }
        $layerArr = array();
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['f_table_schema'] != "sqlapi") {
                $layerArr[$row['f_table_schema']][] = $row['f_table_schema'] . "." . $row['f_table_name'];
                $def = json_decode($row['def']);
                $def->meta_tiles == true ? $meta_tiles = "yes" : $meta_tiles = "no";
                $meta_size = ($def->meta_size) ?: $meta_size = 3;
                $meta_buffer = ($def->meta_buffer) ?: $meta_buffer = 0;
                $def->ttl < 30 ? $expire = 30 : $expire = $def->ttl;
                echo "[{$row['f_table_schema']}.{$row['f_table_name']}]\n";
                echo "type=WMS\n";
                echo "url=" . "http://127.0.0.1" . "/wms/" . Connection::$param['postgisdb'] . "/{$row['f_table_schema']}/?";
                echo "debug=no\n";
                echo "extension=png\n";
                echo "bbox=-20037508.3427892,-20037508.3427892,20037508.3427892,20037508.3427892\n";
                echo "maxResolution=156543.0339\n";
                echo "levels=22\n";
                echo "metaBuffer={$meta_buffer}\n";
                echo "metaTile={$meta_tiles}\n";
                echo "metaSize={$meta_size},{$meta_size}\n";
                echo "srs=EPSG:900913\n";
                //echo "tms_type=google\n";
                echo "expire={$expire}\n\n";
            }
        }
        foreach ($layerArr as $k => $v) {
            if (sizeof($v) > 0) {
                echo "[" . $k . "]\n";
                echo "layers=" . implode(",", $v) . "\n";
                echo "type=MapServerLayer\n";
                echo "mapfile=" . App::$param['path'] . "/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $k . ".map\n";
                echo "extension=png\n";
                echo "bbox=-20037508.3427892,-20037508.3427892,20037508.3427892,20037508.3427892\n";
                echo "maxResolution=156543.0339\n";
                echo "levels=22\n";
                echo "metaBuffer=20\n";
                echo "metaTile=yes\n";
                echo "metaSize=5,5\n";
                echo "srs=EPSG:900913\n";
                //echo "tms=google\n";
                echo "expire=60\n\n";
            }
        }
        $data = ob_get_clean();
        $path = App::$param['path'] . "/app/wms/cfgfiles/";
        $name = Connection::$param['postgisdb'] . ".tilecache.cfg";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Cfgfile written", "ch" => $path . $name);
    }
}
