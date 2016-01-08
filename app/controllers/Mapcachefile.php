<?php
namespace app\controllers;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;

class Mapcachefile extends \app\inc\Controller
{
    private function checkSum($file)
    {
        return md5_file($file);
    }

    public function get_index()
    {
        $postgisObject = new Model(App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb'] . ".sqlite3");
        ob_start();?>

        <mapcache>
            <metadata>
                <title>my mapcache service</title>
                <abstract>woot! this is a service abstract!</abstract>
                <url><?php echo App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/mapcache/<?php echo Connection::$param['postgisdb']; ?></url>
            </metadata>
            <cache name="sqlite" type="sqlite3">
                <dbfile><?php echo App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb'] . ".sqlite3"; ?></dbfile>
                <symlink_blank/>
            </cache>

            <cache name="disk" type="disk">
                <base><?php echo App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb'] . "/"; ?></base>
            </cache>

            <format name="jpeg_low" type="JPEG">
                <quality>60</quality>
                <photometric>ycbcr</photometric>
            </format>

            <?php
            $gridNames = array();
            $pathToGrids = App::$param['path'] . "app/conf/grids/";
            $grids = scandir($pathToGrids);
            foreach ($grids as $grid) {
                $bits = explode(".", $grid);
                if ($bits[1] == "xml") {
                    array_push($gridNames, $bits[0]);
                    $res = file_get_contents($pathToGrids . $grid);
                    echo $res . "\n";
                }
            }
            ?>

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
                        $def = json_decode($row['def']);
                        $meta_size = $def->meta_size ?: "1";
                        $meta_size = $def->meta_tiles ? $meta_size : "1";
                        $meta_buffer = $def->meta_buffer ?: 0;
                        $expire = ($def->ttl < 30) ?  30 : $def->ttl;
                        ?>

            <!-- <?php echo $table ?> -->
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
                  <getfeatureinfo>
                            <!-- info_formats: comma separated list of wms info_formats supported by the source WMS.
                            you can get this list by studying the source WMS capabilities document.
                            -->
                            <info_formats>text/plain,application/vnd.ogc.gml</info_formats>

                            <!-- KVP params to pass with the request. QUERY_LAYERS is mandatory -->
                            <params>
                                <QUERY_LAYERS><?php echo $table ?></QUERY_LAYERS>
                            </params>
                  </getfeatureinfo>
            </source>
            <tileset name="<?php echo $table ?>">
                <source><?php echo $table ?></source>
                <cache>disk</cache>
                <grid>g</grid>
                <grid>WGS84</grid>
                <?php
                        foreach ($gridNames as $gridName) {
                            echo "<grid>{$gridName}</grid>\n";
                        }
                        ?>
                <format>PNG</format>
                <metatile><?php echo $meta_size ?> <?php echo $meta_size ?></metatile>
                <metabuffer><?php echo $meta_buffer ?></metabuffer>
                <expires><?php echo $expire ?></expires>
                <metadata>
                    <title><?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?></title>
                     <abstract><?php echo $row['f_table_abstract']; ?></abstract>
                </metadata>
                </tileset>
            <?php
                    }
                }
            }?>
            <default_format>PNG</default_format>

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
        </mapcache>
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "/app/wms/mapcache/";
        $name = Connection::$param['postgisdb'] . ".xml";
        $checkSum1 = $this->checkSum($path . $name);
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        $checkSum2 = $this->checkSum($path . $name);
        if ($checkSum1 == $checkSum2) {
            $changed = false;
            $reloaded = false;
        } else {
            $changed = true;
            $res = json_decode(\app\controllers\Mapcache::reload());
            if ($res->success == true) {
                $reloaded = true;
            }
        }
        return array("success" => true, "message" => "MapCache file written", "changed" => $changed, "reloaded" => $reloaded, "ch" => $path . $name);
    }
}
