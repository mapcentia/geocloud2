<?php
namespace app\controllers;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;

class Mapcachefile extends \app\inc\Controller
{
    function get_index()
    {
        $postgisObject = new Model();
        ob_start();?>

        <mapcache>
            <cache name="sqlite" type="sqlite3">
                <dbfile><?php echo App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb'] . ".sqlite3"; ?></dbfile>
                <symlink_blank/>
            </cache>
            <?php
            $arr = array();
            $table = null;
            $sql = "SELECT * FROM settings.geometry_columns_view ORDER BY sort_id";
            $result = $postgisObject->execQuery($sql);
            if ($postgisObject->PDOerror) {
                ob_get_clean();
                return false;
            }
            while ($row = $postgisObject->fetchRow($result)) {
                if ($row['f_table_schema'] != "sqlapi") {
                    $table = $row["f_table_schema"] . "." . $row["f_table_name"];
                    if (!in_array($table, $arr)) {
                        array_push($arr, $table);
                        ?>
        <source name="<?php echo $table ?>" type="wms">
              <getmap>
                     <params>
                            <FORMAT>image/png</FORMAT>
                            <LAYERS><?php echo $table ?></LAYERS>
                     </params>
              </getmap>
              <http>
                    <url><?php echo App::$param["mapCache"]["MapCacheWmsHost"] ?>/ows/<?php echo $_SESSION["screen_name"] ?>/<?php echo $row["f_table_schema"] ?>/</url>
              </http>
        </source>
        <tileset name="<?php echo $table ?>">
            <source><?php echo $table ?></source>
            <cache>sqlite</cache>
            <grid>g</grid>
            <grid>WGS84</grid>
            <format>PNG</format>
            <metatile>5 5</metatile>
            <metabuffer>10</metabuffer>
            <expires>3600</expires>
            <metadata>
                <title><?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?></title>
                 <abstract><?php echo $row['f_table_abstract']; ?></abstract>
            </metadata>
            </tileset>
            <?php
                    }
                }
            }?>
            <default_format>JPEG</default_format>

            <service type="wms" enabled="true">
                <full_wms>assemble</full_wms>
                <resample_mode>bilinear</resample_mode>
                <format allow_client_override="true">PNG</format>
                <maxsize>4096</maxsize>
            </service>
            <service type="wmts" enabled="true"/>
            <service type="tms" enabled="true"/>
            <service type="kml" enabled="true"/>
            <service type="gmaps" enabled="true"/>
            <service type="ve" enabled="true"/>
            <service type="mapguide" enabled="true"/>
            <service type="demo" enabled="true"/>

            <errors>report</errors>
            <lock_dir>/tmp</lock_dir>

            <auto_reload>true</auto_reload>
        </mapcache>
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "/app/wms/mapcache/";
        $name = Connection::$param['postgisdb'] . ".xml";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "MapCache file written", "ch" => $path . $name);
    }
}
