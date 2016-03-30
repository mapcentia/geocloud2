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
        //$cache = "disk";
        $cache = "sqlite";
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
            <format name="jpeg_medium" type="JPEG">
                <quality>75</quality>
                <photometric>ycbcr</photometric>
            </format>
            <format name="jpeg_high" type="JPEG">
                <quality>95</quality>
                <photometric>ycbcr</photometric>
            </format>
            <grid name="g20">
                <metadata>
                    <title>GoogleMapsCompatible</title>
                    <WellKnownScaleSet>urn:ogc:def:wkss:OGC:1.0:GoogleMapsCompatible</WellKnownScaleSet>
                </metadata>
                <extent>-20037508.3427892480 -20037508.3427892480 20037508.3427892480 20037508.3427892480</extent>
                <srs>EPSG:900913</srs>
                <srsalias>EPSG:3857</srsalias>
                <units>m</units>
                <size>256 256</size>
                <resolutions>156543.0339280410 78271.51696402048 39135.75848201023 19567.87924100512 9783.939620502561 4891.969810251280 2445.984905125640 1222.992452562820 611.4962262814100 305.7481131407048 152.8740565703525 76.43702828517624 38.21851414258813 19.10925707129406 9.554628535647032 4.777314267823516 2.388657133911758 1.194328566955879 0.5971642834779395 0.298582141739 0.149291070869 0.074645535435</resolutions>
            </grid>
            <?php
            $grids = \app\controllers\Mapcache::getGrids();
            foreach ($grids as $grid) {
                echo $grid . "\n";
            }
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
                    $layerArr[$row['f_table_schema']][] = $row['f_table_schema'] . "." . $row['f_table_name'];
                    $groups[$row['f_table_schema']][] = $row['layergroup'];
                    $groupArr[$row['f_table_schema']][$row['f_table_schema'] . "." . $row['f_table_name']] = $row['layergroup'];

                    $table = $row["f_table_schema"] . "." . $row["f_table_name"];
                    if (!in_array($table, $arr)) {
                        array_push($arr, $table);
                        $def = json_decode($row['def']);
                        $meta_size = $def->meta_size ?: "1";
                        $meta_size = $def->meta_tiles ? $meta_size : "1";
                        $meta_buffer = $def->meta_buffer ?: 0;
                        $expire = ($def->ttl < 30) ? 30 : $def->ttl;
                        $auto_expire = $def->auto_expire ?: null;
                        $format = $def->format ?: "PNG";
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
                        <url><?php echo App::$param["mapCache"]["wmsHost"] ?>/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/<?php echo Connection::$param['postgisdb'] ?>_<?php echo $row["f_table_schema"] ?>.map&</url>
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
                <cache><?php echo $cache ?></cache>
                <grid>g20</grid>
                <grid>g</grid>
                <grid>WGS84</grid>
                <?php
                        foreach ($grids as $k => $v) {
                            echo "<grid>{$k}</grid>\n";
                        }
                        ?>
                <format><?php echo $format ?></format>
                <metatile><?php echo $meta_size ?> <?php echo $meta_size ?></metatile>
                <metabuffer><?php echo $meta_buffer ?></metabuffer>
                <expires><?php echo $expire ?></expires>
                <?php if ($auto_expire) echo "<auto_expire>" . $auto_expire . "</auto_expire>\n" ?>
                <metadata>
                    <title><?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?></title>
                     <abstract><?php echo $row['f_table_abstract']; ?></abstract>
                </metadata>
            </tileset>
            <?php
                    }
                }
            }

            foreach ($layerArr as $k => $v) {
                if (sizeof($v) > 0) {
                    ?>
            <!-- <?php echo $k ?> -->
            <source name="<?php echo $k ?>" type="wms">
                  <getmap>
                         <params>
                                <FORMAT>image/png</FORMAT>
                                <LAYERS><?php echo implode(",", $v) ?></LAYERS>
                         </params>
                  </getmap>
                  <http>
                        <url><?php echo App::$param["mapCache"]["wmsHost"] ?>/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/<?php echo Connection::$param['postgisdb'] ?>_<?php echo $k ?>.map&</url>
                  </http>
            </source>
            <tileset name="<?php echo $k ?>">
                <source><?php echo $k ?></source>
                <cache><?php echo $cache ?></cache>
                <grid>g20</grid>
                <grid>g</grid>
                <grid>WGS84</grid>
                <?php
                    foreach ($grids as $k2 => $v2) {
                        echo "<grid>{$k2}</grid>\n";
                    }
                    ?>
                <format>jpeg_low</format>
                <metatile>3 3</metatile>
                <metabuffer>0</metabuffer>
                <expires>60</expires>
                <metadata>
                    <title><?php echo $k; ?></title>
                     <abstract></abstract>
                </metadata>
            </tileset>
                    <?php
                }
            }

            foreach ($groupArr as $k => $v) {
                $unique = array_unique($groups[$k]);
                foreach ($unique as $v2) {
                    $layers = array();
                    $tileSetName =  "gc2_group." . $k . "." . ($v2 ? \app\inc\Model::toAscii($v2, array(), "_") : "ungrouped");
                    foreach ($groupArr[$k] as $h => $j) {
                        if ($j == $v2) {
                            $layers[] = $h;
                        }
                    }
                    $layersStr = implode(",", $layers);
                    ?>
            <!-- <?php echo $tileSetName ?> -->
            <source name="<?php echo $tileSetName ?>" type="wms">
                  <getmap>
                         <params>
                                <FORMAT>image/png</FORMAT>
                                <LAYERS><?php echo $layersStr ?></LAYERS>
                         </params>
                  </getmap>
                  <http>
                        <url><?php echo App::$param["mapCache"]["wmsHost"] ?>/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/<?php echo Connection::$param['postgisdb'] ?>_<?php echo $k ?>.map&</url>
                  </http>
            </source>
            <tileset name="<?php echo $tileSetName ?>">
                <source><?php echo $tileSetName ?></source>
                <cache><?php echo $cache ?></cache>
                <grid>g20</grid>
                <grid>g</grid>
                <grid>WGS84</grid>
                <?php
                    foreach ($grids as $k2 => $v2) {
                        echo "<grid>{$k2}</grid>\n";
                    }
                    ?>
                <format>PNG</format>
                <metatile>1 1</metatile>
                <metabuffer>0</metabuffer>
                <expires>60</expires>
                <metadata>
                    <title><?php echo $tileSetName; ?></title>
                     <abstract></abstract>
                </metadata>
            </tileset>
                    <?php

                }
            }

            ?>
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
