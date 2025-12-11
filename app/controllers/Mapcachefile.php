<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\conf\Connection;
use app\inc\Controller;
use app\inc\Model;

/**
 * Class Mapcachefile
 * @package app\controllers
 */
class Mapcachefile extends Controller
{
    /**
     * @param $file string
     * @return bool|null|string
     */
    private function checkSumFile($file)
    {
        if (file_exists($file)) {
            $md5 = md5_file($file);

        } else {
            $md5 = "";
        }
        return $md5;
    }

    /**
     * @param $str
     * @return string
     */
    private function checkSumStr($str)
    {
        $md5 = md5($str);

        return $md5;
    }

    /**
     * @return array
     */
    public function get_index()
    {
        $layerArr = [];
        $postgisObject = new Model();
        $formats = App::$param['mapCache']['formats'] ?? null;
        ob_start(); ?>

        <mapcache>

            <locker type="disk">
                <directory>/tmp</directory>
                <timeout>30</timeout>
                <retry>0.6</retry>
            </locker>

            <metadata>
                <title>my mapcache service</title>
                <abstract>woot! this is a service abstract!</abstract>
                <url>http://<?php echo $_SERVER['HTTP_HOST']; ?>/mapcache/<?php echo Connection::$param['postgisdb']; ?></url>
            </metadata>

            <cache name="sqlite" type="sqlite3">
                <dbfile><?php echo App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param['postgisdb'] . "/{tileset}.sqlite3" ?></dbfile>
                <symlink_blank/>
                <creation_retry>3</creation_retry>
            </cache>

            <cache name="disk" type="disk">
                <base><?php echo App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param['postgisdb'] . "/"; ?></base>
                <symlink_blank/>
                <creation_retry>3</creation_retry>
            </cache>

            <cache name="s3" type="s3">
                <url>https://<?php echo App::$param["s3"]["host"] . "/" . Connection::$param['postgisdb'] ?>/{tileset}/{grid}/{z}/{x}/{y}/{ext}</url>
                <headers>
                    <Host><?php echo App::$param["s3"]["host"] ?></Host>
                </headers>
                <id><?php echo App::$param["s3"]["id"] ?></id>
                <secret><?php echo App::$param["s3"]["secret"] ?></secret>
                <region><?php echo App::$param["s3"]["region"] ?></region>
                <operation type="put">
                    <headers>
                        <x-amz-storage-class>REDUCED_REDUNDANCY</x-amz-storage-class>
                        <x-amz-acl>public-read</x-amz-acl>
                    </headers>
                </operation>
                <symlink_blank/>
                <creation_retry>3</creation_retry>
            </cache>

            <cache name="memcache" type="memcache">
                <server>
                    <host>memcached</host>
                    <port>11211</port>
                </server>
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

            <format name="MVT" type="RAW">
                <extension>mvt</extension>
                <mime_type>application/vnd.mapbox-vector-tile</mime_type>
            </format>

            <format name="JSON" type="RAW">
                <extension>json</extension>
                <mime_type>application/json</mime_type>
            </format>

            <grid name="g20">
                <metadata>
                    <title>GoogleMapsCompatible</title>
                    <WellKnownScaleSet>urn:ogc:def:wkss:OGC:1.0:GoogleMapsCompatible</WellKnownScaleSet>
                </metadata>
                <extent>-20037508.3427892480 -20037508.3427892480 20037508.3427892480 20037508.3427892480</extent>
                <srs>EPSG:3857</srs>
                <srsalias>EPSG:900913</srsalias>
                <units>m</units>
                <size>256 256</size>
                <resolutions>156543.0339280410 78271.51696402048 39135.75848201023 19567.87924100512 9783.939620502561
                    4891.969810251280 2445.984905125640 1222.992452562820 611.4962262814100 305.7481131407048
                    152.8740565703525 76.43702828517624 38.21851414258813 19.10925707129406 9.554628535647032
                    4.777314267823516 2.388657133911758 1.194328566955879 0.5971642834779395 0.298582141739
                    0.149291070869 0.074645535435 0.0373227677175 0.018661384 0.009330692 0.004665346 0.002332673
                    0.001166337
                </resolutions>
            </grid>
            <?php
            $grids = Mapcache::getGrids();
            foreach ($grids as $grid) {
                echo $grid . "\n";
            }
            $arr = array();
            $includeSchemas = '';
            if (!empty(App::$param['mapCache']['include'])) {
                $includeSchemas = "AND f_table_schema in ('" . implode("','", App::$param['mapCache']['include']) . "')";
            }
            $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_ NOTNULL $includeSchemas ORDER BY sort_id";
            $result = $postgisObject->execQuery($sql);
            while ($row = $postgisObject->fetchRow($result)) {
                if ($row['f_table_schema'] != "sqlapi" && $row['enableows']) {
                    $layerArr[$row['f_table_schema']][] = $row['f_table_schema'] . "." . $row['f_table_name'];
                    $groups[$row['f_table_schema']][] = strtolower($row['layergroup']);
                    $groupArr[$row['f_table_schema']][$row['f_table_schema'] . "." . $row['f_table_name']] = strtolower($row['layergroup']);

                    $table = $row["f_table_schema"] . "." . $row["f_table_name"];
                    if (!in_array($table, $arr)) {
                        $arr[] = $table;
                        $def = json_decode($row['def']);
                        $meta_size = !empty($def->meta_size) ? $def->meta_size : null;
                        $meta_buffer = !empty($def->meta_buffer) ? $def->meta_buffer : 0;
                        $expire = !empty($def->ttl) ? ($def->ttl < 30 ? 30 : $def->ttl) : 30;
                        // It seems that auto expire makes the server hang!
                        //$auto_expire = $def->lock ? null : ($def->auto_expire ?: ($row['filesource'] ? null : 3600));
                        $auto_expire = !empty($def->auto_expire) ?$def->auto_expire: null;
                        $format = !empty($def->format) ? $def->format : "PNG";
                        $cache = !empty($def->cache) ? $def->cache : App::$param["mapCache"]["type"];
                        $layers = !empty($def->layers) ? "," . $def->layers : "";

                        if (strpos($row["wmssource"], "qgis_mapserv.fcgi")) {
                            parse_str(parse_url($row["wmssource"])["query"], $getArr);
                            $QGISLayers = $getArr["LAYER"];
                        } else {
                            $QGISLayers = null;
                        }
                        ?>

                        <!-- <?php echo $table ?> -->

                        <?php

                        if ($cache == "bdb") {

                            $cache = "bdb_" . $table;

                            $bdbBase = App::$param['path'] . "app/wms/mapcache/bdb/";

                            if (!file_exists($bdbBase)) {
                                @mkdir($bdbBase);
                            }

                            $bdbBase = App::$param['path'] . "app/wms/mapcache/bdb/" . Connection::$param['postgisdb'] . "/";

                            if (!file_exists($bdbBase)) {
                                @mkdir($bdbBase);
                            }

                            $bdbBase = App::$param['path'] . "app/wms/mapcache/bdb/" . Connection::$param['postgisdb'] . "/" . $table;

                            if (!file_exists($bdbBase)) {
                                @mkdir($bdbBase);
                            }


                            ?>

                            <cache name="<?php echo $cache ?>" type="bdb">
                                <base><?php echo $bdbBase ?></base>
                                <symlink_blank/>
                                <creation_retry>3</creation_retry>
                            </cache>

                        <?php }

                        if ($cache == "s3" && $def->s3_tile_set) {


                            ?>

                            <cache name="s3_<?php echo $table ?>" type="s3">
                                <url>https://<?php echo App::$param["s3"]["host"] . "/" . $def->s3_tile_set ?>/{grid}/{z}/{x}/{y}/{ext}</url>
                                <headers>
                                    <Host><?php echo App::$param["s3"]["host"] ?></Host>
                                </headers>
                                <id><?php echo App::$param["s3"]["id"] ?></id>
                                <secret><?php echo App::$param["s3"]["secret"] ?></secret>
                                <region><?php echo App::$param["s3"]["region"] ?></region>
                                <operation type="put">
                                    <headers>
                                        <x-amz-storage-class>REDUCED_REDUNDANCY</x-amz-storage-class>
                                        <x-amz-acl>public-read</x-amz-acl>
                                    </headers>
                                </operation>
                                <symlink_blank/>
                                <creation_retry>3</creation_retry>
                            </cache>

                        <?php } ?>

                        <source name="<?php echo $table ?>" type="wms">
                            <getmap>
                                <params>
                                    <FORMAT>PNG</FORMAT>
                                    <LAYERS><?php echo $QGISLayers ?: $table ?><?php echo $layers ?></LAYERS>
                                </params>
                            </getmap>
                            <http>
                                <url><?php
                                    if ($QGISLayers) { // If layer is QGIS WMS source, then get map directly from qgis_mapserv
                                        echo explode("&", $row["wmssource"])[0] . "&transparent=true&DPI_=96&";
                                    } else {
                                        echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $row["f_table_schema"] . "_wms.map";
                                    }
                                    ?></url>
                            </http>
                            <getfeatureinfo>
                                <info_formats>text/plain,application/vnd.ogc.gml</info_formats>
                                <params>
                                    <QUERY_LAYERS><?php echo $table ?></QUERY_LAYERS>
                                </params>
                            </getfeatureinfo>
                        </source>
                        <tileset name="<?php echo $table ?>">
                            <source><?php echo $table ?></source>
                            <cache><?php if ($cache == "s3" && $def->s3_tile_set) { echo "s3_{$table}";} else {echo $cache;} ?></cache>
                            <grid>g20</grid>
                            <?php
                            foreach ($grids as $k => $v) {
                                echo "<grid>{$k}</grid>\n";
                            }
                            ?>
                            <format><?php echo $format ?></format>
                            <?php if ($meta_size) echo "<metatile>" . $meta_size . " " . $meta_size . "</metatile>\n" ?>
                            <metabuffer><?php echo $meta_buffer ?></metabuffer>
                            <expires><?php echo $expire ?></expires>
                            <?php if ($auto_expire) echo "<auto_expire>" . $auto_expire . "</auto_expire>\n" ?>
                            <metadata>
                                <title>
                                    <![CDATA[<?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?>
                                    ]]></title>
                                <abstract><![CDATA[<?php echo $row['f_table_abstract']; ?>]]></abstract>
                                <wgs84boundingbox><?php if (!empty(App::$param["wgs84boundingbox"])) echo implode(" ", App::$param["wgs84boundingbox"]); else echo "-180 -90 180 90"; ?></wgs84boundingbox>
                            </metadata>
                        </tileset>

                        <?php if (empty($formats) || is_array($formats) && in_array('mvt', $formats)) { ?>
                        <source name="<?php echo $table ?>.mvt" type="wms">
                            <getmap>
                                <params>
                                    <FORMAT>mvt</FORMAT>
                                    <LAYERS><?php echo $table ?><?php echo $layers ?></LAYERS>
                                </params>
                            </getmap>
                            <http>
                                <url><?php echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $row["f_table_schema"] . "_wfs.map&"; ?></url>
                            </http>
                        </source>
                        <tileset name="<?php echo $table ?>.mvt">
                            <source><?php echo $table ?>.mvt</source>
                            <cache><?php echo $cache ?></cache>
                            <grid>g20</grid>
                            <?php
                            foreach ($grids as $k => $v) {
                                echo "<grid>{$k}</grid>\n";
                            }
                            ?>
                            <format>MVT</format>
                            <expires><?php echo $expire ?></expires>
                            <?php if ($auto_expire) echo "<auto_expire>" . $auto_expire . "</auto_expire>\n" ?>
                            <metadata>
                                <title>
                                    <![CDATA[<?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?>
                                    ]]></title>
                                <abstract><![CDATA[<?php echo $row['f_table_abstract']; ?>]]></abstract>
                                <wgs84boundingbox><?php if (!empty(App::$param["wgs84boundingbox"])) echo implode(" ", App::$param["wgs84boundingbox"]); else echo "-180 -90 180 90"; ?></wgs84boundingbox>
                            </metadata>
                        </tileset>
                        <?php   } ?>

                        <?php if (empty($formats) || (is_array($formats) && in_array('json', $formats))) { ?>
                        <source name="<?php echo $table ?>.json" type="wms">
                        <getmap>
                            <params>
                                <FORMAT>json</FORMAT>
                                <LAYERS><?php echo $table ?><?php echo $layers ?></LAYERS>
                            </params>
                        </getmap>
                        <http>
                            <url><?php echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $row["f_table_schema"] . "_wfs.map&"; ?></url>
                        </http>
                        </source>
                        <tileset name="<?php echo $table ?>.json">
                            <source><?php echo $table ?>.json</source>
                            <cache><?php echo $cache ?></cache>
                            <grid>g20</grid>
                            <?php
                            foreach ($grids as $k => $v) {
                                echo "<grid>{$k}</grid>\n";
                            }
                            ?>
                            <format>JSON</format>
                            <expires><?php echo $expire ?></expires>
                            <?php if ($auto_expire) echo "<auto_expire>" . $auto_expire . "</auto_expire>\n" ?>
                            <metadata>
                                <title>
                                    <![CDATA[<?php echo $row['f_table_title'] ? $row['f_table_title'] : $row['f_table_name']; ?>
                                    ]]></title>
                                <abstract><![CDATA[<?php echo $row['f_table_abstract']; ?>]]></abstract>
                                <wgs84boundingbox><?php if (!empty(App::$param["wgs84boundingbox"])) echo implode(" ", App::$param["wgs84boundingbox"]); else echo "-180 -90 180 90"; ?></wgs84boundingbox>
                            </metadata>
                        </tileset>

                        <?php }
                    }
                }
            }

            /**
             * Schema start
             */

            foreach ($layerArr as $k => $v) {
                if (sizeof($v) > 0) {

                    $cache = App::$param["mapCache"]["type"] ?: "sqlite";
                    //$cache = "disk";

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
                            <url><?php

                                if (empty(App::$param["useQgisForMergedLayers"][$k])) {
                                    echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $k . "_wms.map&";
                                } else {
                                    echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2/app/wms/qgsfiles/parsed_" . App::$param["useQgisForMergedLayers"][$k] . "&transparent=true";
                                }
                                ?></url>

                        </http>
                    </source>
                    <tileset name="<?php echo $k ?>">
                        <source><?php echo $k ?></source>
                        <cache><?php echo $cache ?></cache>
                        <grid>g20</grid>
                        <?php
                        foreach ($grids as $k2 => $v2) {
                            echo "<grid>{$k2}</grid>\n";
                        }
                        ?>
                        <format>PNG</format>
                        <metatile>3 3</metatile>
                        <metabuffer>0</metabuffer>
                        <expires>60</expires>
                        <metadata>
                            <title><?php echo $k; ?></title>
                            <abstract></abstract>
                        </metadata>
                    </tileset>

                    <?php if (empty($formats) || is_array($formats) && in_array('mvt', $formats)) { ?>
                    <source name="<?php echo $k ?>.mvt" type="wms">
                    <getmap>
                        <params>
                            <FORMAT>mvt</FORMAT>
                            <LAYERS><?php echo implode(",", $v) ?></LAYERS>
                        </params>
                    </getmap>
                    <http>
                        <url><?php
                                echo App::$param["mapCache"]["wmsHost"] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/" . Connection::$param['postgisdb'] . "_" . $k . "_wfs.map&";
                            ?></url>

                    </http>
                    </source>
                    <tileset name="<?php echo $k ?>.mvt">
                        <source><?php echo $k ?>.mvt</source>
                        <cache><?php echo $cache ?></cache>
                        <grid>g20</grid>
                        <?php
                        foreach ($grids as $k2 => $v2) {
                            echo "<grid>{$k2}</grid>\n";
                        }
                        ?>
                        <format>MVT</format>
                        <expires>60</expires>
                        <metadata>
                            <title><?php echo $k; ?></title>
                            <abstract></abstract>
                        </metadata>
                    </tileset>
                    <?php } ?>
                    <?php
                }
            }
            ?>
            <default_format>PNG</default_format>

            <service type="wms" enabled="true">
                <full_wms>assemble</full_wms>
                <resample_mode>bilinear</resample_mode>
                <format allow_client_override="true">PNG</format>
                <maxsize>16384</maxsize>
            </service>

            <service type="wmts" enabled="true"/>
            <service type="tms" enabled="true"/>
            <service type="kml" enabled="true"/>
            <service type="gmaps" enabled="true"/>
            <service type="ve" enabled="true"/>
            <errors>report</errors>
            <lock_dir>/tmp</lock_dir>
            <lock_retry>10000</lock_retry>
            <log_level>warn</log_level>
            <!-- start extra -->

            <?php
            $sources = Mapcache::getSources();
            foreach ($sources as $source) {
                echo $source . "\n";
            }

            $tileSets = Mapcache::getTileSets();
            foreach ($tileSets as $tileSet) {
                echo $tileSet . "\n";
            }
            ?>

            <!-- end extra -->

        </mapcache>
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "app/wms/mapcache/";
        $name = Connection::$param['postgisdb'] . ".xml";
        $checkSum1 = $this->checkSumFile($path . $name);
        $checkSum2 = $this->checkSumStr($data);

        if ($checkSum1 == $checkSum2) {
            $changed = false;
        } else {
            @unlink($path . $name);
            $fh = fopen($path . $name, 'w');
            fwrite($fh, $data);
            fclose($fh);
            $changed = true;
        }
        return array("success" => true, "message" => "MapCache file written", "changed" => $changed, "ch" => $path . $name);
    }
}
