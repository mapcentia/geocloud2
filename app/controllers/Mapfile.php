<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\conf\Connection;
use app\inc\Controller;
use app\inc\Model;
use app\inc\Util;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Mapfile
 * @package app\controllers
 */
class Mapfile extends Controller
{

    /**
     * @var Model
     */
    private $postgisObject;

    /**
     * @var float[]
     */
    private $bbox;

    /**
     * Mapfile constructor.
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct()
    {
        parent::__construct();
        $this->postgisObject = new Model();
        $settings = new \app\models\Setting();
        $extents = $settings->get()["data"]->extents ?? null;
        $schema = Connection::$param['postgisschema'];
        $this->bbox = is_object($extents) && property_exists($extents, $schema) ? $extents->$schema : [-20037508.34, -20037508.34, 20037508.34, 20037508.34]; // Is in EPSG:3857
    }

    /**
     * @return array<array<bool|string>>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        $res = [];
        $res[] = $this->writeWms();
        $res[] = $this->writeWfs();
        return $res;
    }

    /**
     * @return array<bool|string>|bool
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function writeWms()
    {
        $postgisObject = new Model();
        $user = Connection::$param['postgisdb'];

        $sql = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$this->bbox[0]},{$this->bbox[1]},{$this->bbox[2]},{$this->bbox[3]},3857),4326)) AS a) select ST_xmin(a) as xmin,ST_ymin(a) as ymin,ST_xmax(a) as xmax,ST_ymax(a) as ymax  from box";
        $resultExtent = $postgisObject->execQuery($sql);
        $row = $postgisObject->fetchRow($resultExtent);
        $extent = [$row["xmin"], $row["ymin"], $row["xmax"], $row["ymax"]];

        ob_start();
        ?>
        MAP
        #
        # Start of map file
        #
        NAME "<?php echo $user; ?>"
        STATUS on
        EXTENT <?php echo implode(" ", $extent) . "\n" ?>
        SIZE 2000 1500
        MAXSIZE 16384
        FONTSET "/var/www/geocloud2/app/wms/fonts/fonts.txt"
        IMAGECOLOR 255 2 255
        UNITS METERS
        #INTERLACE OFF

        OUTPUTFORMAT
        NAME "png"
        DRIVER AGG/PNG
        MIMETYPE "image/png"
        IMAGEMODE RGBA
        EXTENSION "png"
        TRANSPARENT ON
        FORMATOPTION "GAMMA=0.75"
        END

        OUTPUTFORMAT
        NAME "utfgrid"
        DRIVER UTFGRID
        MIMETYPE "application/json"
        EXTENSION "json"
        FORMATOPTION "UTFRESOLUTION=4"
        FORMATOPTION "DUPLICATES=false"
        END

        #CONFIG "MS_ERRORFILE" "/var/www/geocloud2/app/wms/mapfiles/ms_error.txt"
        #DEBUG 5

        WEB
        IMAGEPATH "<?php echo App::$param['path']; ?>/tmp"
        IMAGEURL "<?php echo App::$param['host']; ?>/tmp"
        METADATA
        "wms_title"    "<?php echo $user; ?>'s OWS"
        "wms_srs"    <?php echo "\"" . (!empty(App::$param['advertisedSrs']) ? implode(" ", App::$param['advertisedSrs']) : "EPSG:4326 EPSG:3857 EPSG:3044 EPSG:25832") . "\"\n" ?>
        "wms_name"    "<?php echo $user; ?>"
        "wms_format"    "image/png"
        "wms_onlineresource"    "<?php echo App::$param['host']; ?>/ows/<?php echo Connection::$param['postgisdb']; ?>/<?php echo Connection::$param['postgisschema']; ?>/"
        "wms_enable_request" "*"
        "ows_encoding" "UTF-8"
        "wms_extent" "<?php echo implode(" ", $extent) ?>"
        END
        END

        #
        # Start of reference map
        #

        PROJECTION
        "init=EPSG:4326"
        END

        #
        # Start of legend
        #

        LEGEND
        STATUS off
        IMAGECOLOR 255 255 255
        KEYSIZE 18 12
        LABEL
        WRAP "#"
        TYPE truetype
        FONT "arialnormal"
        SIZE 8
        COLOR 0 0 0
        END
        END

        #
        # Start of scalebar
        #

        SCALEBAR
        STATUS off
        COLOR 255 255 255
        OUTLINECOLOR 0 0 0
        BACKGROUNDCOLOR 0 0 0
        IMAGECOLOR 255 255 255
        UNITS METERS
        INTERVALS 3
        SIZE 150 5
        LABEL
        FONT "courierb"
        SIZE SMALL
        COLOR 0 0 0
        SHADOWSIZE 2 2
        END
        END

        #
        # Vector Line Types
        #

        Symbol
        Name 'triangle'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        .5 0
        1 1
        0 1
        END
        END

        SYMBOL
        NAME "circle"
        TYPE ellipse
        FILLED true
        POINTS
        1 1
        END
        END

        Symbol
        Name 'square'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        0 0
        1 0
        1 1
        0 1
        END
        END

        Symbol
        Name 'star'
        Type VECTOR
        Filled TRUE
        Points
        0 .375
        .35 .375
        .5 0
        .65 .375
        1 .375
        .75 .625
        .875 1
        .5 .75
        .125 1
        .25 .625
        END
        END

        SYMBOL
        NAME "hatch1"
        TYPE VECTOR
        POINTS
        0 1 1 0
        END
        END

        SYMBOL
        NAME "dashed1"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        #STYLE 4 2 END
        END

        SYMBOL

        NAME "arrow"
        TYPE vector
        FILLED true
        POINTS
        0 0.4
        3 0.4
        3 0
        5 0.8
        3 1.6
        3 1.2
        0 1.2
        0 0.4
        END
        ANCHORPOINT 0 0.5
        END # SYMBOL

        SYMBOL
        NAME "arrow2"
        TYPE vector
        FILLED true
        POINTS
        0 0.8
        1 0.4
        0 0
        0 0.8
        END
        ANCHORPOINT 0 0.5
        END

        #
        # Vector Line Types
        #

        SYMBOL
        NAME "continue"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dashed-line-short"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 1
        END
        END

        SYMBOL
        NAME "dashed-line-long"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 10
        END
        END

        SYMBOL
        NAME "dash-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        20 6 2 6
        END
        END

        SYMBOL
        NAME "dash-dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END


        #
        # Start of layers
        #
        <?php
        $sql = "SELECT * FROM settings.getColumns('f_table_schema=''" . Connection::$param['postgisschema'] . "'' AND enableows=true','raster_columns.r_table_schema=''" . Connection::$param['postgisschema'] . "'' AND enableows=true') ORDER BY sort_id";
        $result = $postgisObject->execQuery($sql);
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['srid'] > 1) {

                $sql = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$this->bbox[0]},{$this->bbox[1]},{$this->bbox[2]},{$this->bbox[3]},3857),{$row['srid']})) AS a) select ST_xmin(a) as xmin,ST_ymin(a) as ymin,ST_xmax(a) as xmax,ST_ymax(a) as ymax  from box";
                $resultExtent = $postgisObject->prepare($sql);
                try {
                    $resultExtent->execute();
                    $rowExtent = $postgisObject->fetchRow($resultExtent);
                    $extent = [$rowExtent["xmin"], $rowExtent["ymin"], $rowExtent["xmax"], $rowExtent["ymax"]];
                } catch (PDOException $e) {
                    $extent = $this->bbox;
                }

                $rel = "{$row['f_table_schema']}.{$row['f_table_name']}";
                $meta = $postgisObject->getMetaData($rel);
                $arr = !empty($row['def']) ? json_decode($row['def'], true) : [];
                $props = array("label_column", "theme_column");
                foreach ($props as $field) {
                    if (empty($arr[$field]) || $arr[$field] == false) {
                        $arr[$field] = "";
                    }
                }
                $layerArr = array("data" => array($arr));
                $sortedArr = array();

                // Sort classes
                $arr = $arr2 = !empty($row['class']) ? !empty(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [] : [];
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $last = 100000;
                    foreach ($arr2 as $key => $value) {
                        if ($value["sortid"] < $last) {
                            $temp = $value;
                            $del = $key;
                            $last = $value["sortid"];
                        }
                    }
                    $sortedArr[] = $temp;
                    unset($arr2[$del]);
                    $temp = null;
                }
                $arr = $sortedArr;
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $arrNew[$i] = (array)Util::casttoclass('stdClass', $arr[$i]);
                    $arrNew[$i]['id'] = $i;
                }
                $classArr = array("data" => !empty($arrNew) ? $arrNew : null);
                $primeryKey = $postgisObject->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
                if (!empty($arrNew)) unset($arrNew);
                ?>
                LAYER
                <?php $layerName = $row['f_table_schema'] . "." . $row['f_table_name']; ?>
                NAME "<?php echo $layerName; ?>"
                STATUS off
                <?php if ($row['filter']) { ?>
                    PROCESSING "NATIVE_FILTER=<?php echo $row['filter']; ?>"
                <?php } ?>
                <?php
                if (!empty($layerArr['data'][0]['geotype']) && $layerArr['data'][0]['geotype'] != "Default") {
                    $type = $layerArr['data'][0]['geotype'];
                } else {
                    switch ($row['type']) {
                        case "GEOMETRY":
                        case "POINT":
                        case "MULTIPOINT":
                            $type = "POINT";
                            break;
                        case "LINESTRING":
                        case "MULTILINESTRING":
                            $type = "LINE";
                            break;
                        case "POLYGON":
                        case "MULTIPOLYGON":
                        case "MULTISURFACE":
                            $type = "POLYGON";
                            break;
                        case "RASTER":
                            $type = "RASTER";
                            break;
                    }
                }
                if ($row['wmssource']) {
                    ?>
                    TYPE RASTER
                    CONNECTIONTYPE WMS
                    CONNECTION "<?php echo $row['wmssource']; ?>"
                    PROCESSING "LOAD_WHOLE_IMAGE=YES"
                    PROCESSING "LOAD_FULL_RES_IMAGE=YES"
                    PROCESSING "RESAMPLE=BILINEAR"
                    <?php
                } elseif ($row['bitmapsource']) {
                    ?>
                    TYPE RASTER
                    DATA "<?php echo App::$param['path'] . "/app/wms/files/" . Connection::$param["postgisdb"] . "/__bitmaps/" . $row['bitmapsource']; ?>"
                    PROCESSING "RESAMPLE=AVERAGE"
                    <?php
                    if (!empty($layerArr['data'][0]['bands'])) {
                        echo "PROCESSING \"BANDS={$layerArr['data'][0]['bands']}\"\n";
                    }
                    ?>
                    <?php
                } else {
                    if ($type != "RASTER") {
                        if (!$row['data']) {
                            if (preg_match('/[A-Z]/', $row['f_geometry_column'])) {
                                $dataSql = "SELECT *,\\\"{$row['f_geometry_column']}\\\" as " . strtolower($row['f_geometry_column']) . " FROM \\\"{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                            } else {
                                $dataSql = "SELECT * FROM \\\"" . "{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                            }
                        } else {
                            $dataSql = $row['data'];
                        }
                        $fieldConf = !empty($row['fieldconf']) ? json_decode($row['fieldconf'], true) : [];
                        $includeItemsStr = "all";
                        uksort($meta, function ($a, $b) use ($fieldConf) {
                            if (isset($fieldConf[$a]) && isset($fieldConf[$b])) {
                                $sortIdA = $fieldConf[$a]['sort_id'];
                                $sortIdB = $fieldConf[$b]['sort_id'];
                                return $sortIdA - $sortIdB;
                            }
                            return 0;
                        });
                        // We always want to select all fields
                        if (sizeof($meta) > 0) {
                            $selectStr = implode("\\\",\\\"", array_keys($meta));
                        }
                        // Filter out ignored fields
                        $meta = array_filter($meta, function ($item, $key) use (&$fieldConf) {
                            if (empty($fieldConf[$key]['ignore'])) {
                                return $item;
                            }
                        }, ARRAY_FILTER_USE_BOTH);
                        if (sizeof($meta) > 0) {
                            $includeItemsStr = implode(",", array_keys($meta));
                        }
                        echo "DATA \"" . strtolower($row['f_geometry_column']) . " FROM (SELECT \\\"$selectStr\\\" FROM ($dataSql /*FILTER_$layerName*/) as bar) as foo USING UNIQUE {$primeryKey['attname']} USING srid={$row['srid']}\"\n";
                        ?>
                        CONNECTIONTYPE POSTGIS
                        CONNECTION "user=<?php echo Connection::$param['postgisuser']; ?> dbname=<?php echo Connection::$param['postgisdb']; ?><?php if (Connection::$param['postgishost']) echo " host=" . (!empty(Connection::$param['mapserverhost']) ? Connection::$param['mapserverhost'] : Connection::$param['postgishost']); ?><?php echo " port=" . ((!empty(Connection::$param['mapserverport']) ? Connection::$param['mapserverport'] : Connection::$param['postgisport']) ?: "5432") ?><?php if (Connection::$param['postgispw']) echo " password=" . Connection::$param['postgispw']; ?><?php if (!Connection::$param['pgbouncer']) echo " options='-c client_encoding=UTF8'" ?>"
                        <?php if (!empty($layerArr['data'][0]['label_no_clip'])) echo "PROCESSING \"LABEL_NO_CLIP=True\"\n"; ?>
                        <?php if (!empty($layerArr['data'][0]['polyline_no_clip'])) echo "PROCESSING \"POLYLINE_NO_CLIP=True\"\n"; ?>

                        <?php
                    } else {
                        echo "DATA \"PG:host=" . (Connection::$param['mapserverhost'] ?: Connection::$param['postgishost']);
                        echo " port=" . (!empty(Connection::$param['mapserverport']) ? Connection::$param['mapserverport'] : (!empty(Connection::$param['postgisport']) ? Connection::$param['postgisport'] : "5432"));
                        echo " dbname='" . Connection::$param['postgisdb'] . "' user='" . Connection::$param['postgisuser'] . "' password='" . Connection::$param['postgispw'] . "'
		                    schema='{$row['f_table_schema']}' table='{$row['f_table_name']}' mode='2'\"\n";
                        echo "PROCESSING \"CLOSE_CONNECTION=ALWAYS\" \n";
                    }
                    ?>
                    TYPE <?php echo $type . "\n"; ?>

                <?php } ?>
                #OFFSITE
                <?php if (!empty($layerArr['data'][0]['offsite'])) echo "OFFSITE " . $layerArr['data'][0]['offsite'] . "\n"; ?>

                #CLASSITEM
                <?php if (!empty($layerArr['data'][0]['theme_column'])) echo "CLASSITEM '" . $layerArr['data'][0]['theme_column'] . "'\n"; ?>

                #LABELITEM
                <?php if (!empty($layerArr['data'][0]['label_column'])) echo "LABELITEM '" . $layerArr['data'][0]['label_column'] . "'\n"; ?>

                #LABELMAXSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['label_max_scale'])) echo "LABELMAXSCALEDENOM " . $layerArr['data'][0]['label_max_scale'] . "\n"; ?>

                #LABELMINSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['label_min_scale'])) echo "LABELMINSCALEDENOM " . $layerArr['data'][0]['label_min_scale'] . "\n"; ?>

                COMPOSITE
                #OPACITY
                <?php if (!empty($layerArr['data'][0]['opacity'])) echo "OPACITY  " . $layerArr['data'][0]['opacity'] . "\n"; ?>
                END

                #MAXSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['maxscaledenom'])) echo "MAXSCALEDENOM  " . $layerArr['data'][0]['maxscaledenom'] . "\n"; ?>

                #MINSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['minscaledenom'])) echo "MINSCALEDENOM  " . $layerArr['data'][0]['minscaledenom'] . "\n"; ?>

                #SYMBOLSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['symbolscaledenom'])) echo "SYMBOLSCALEDENOM " . $layerArr['data'][0]['symbolscaledenom'] . "\n"; ?>

                #MINSCALEDENOM
                <?php if (!empty($layerArr['data'][0]['cluster'])) {
                    echo "CLUSTER\n";
                    echo "MAXDISTANCE {$layerArr['data'][0]['cluster']}\n";
                    echo "REGION \"ellipse\"\n";
                    //echo "PROCESSING \"CLUSTER_GET_ALL_SHAPES=false\"\n";
                    echo "END\n";
                }
                ?>

                #LABELMAXSCALE
                METADATA
                "ows_title"    "<?php if ($row['f_table_title']) echo addslashes($row['f_table_title']); else echo $row['f_table_name'] ?>"
                "wms_group_title" "<?php echo $row['layergroup'] ?>"
                "wms_group_abstract" "<?php echo $row['layergroup'] ?>"
                "ows_srs"    "EPSG:<?php echo "{$row['srid']} {$row['wmsclientepsgs']}" ?>"
                "ows_name"    "<?php echo $layerName; ?>"
                "ows_abstract"    "<?php echo !empty($row['f_table_abstract']) ? addslashes($row['f_table_abstract']) : ""; ?>"
                "wms_format"    "image/png"
                "wms_extent" "<?php echo implode(" ", $extent) ?>"
                "wms_enable_request"    "*"
                "wms_include_items" "<?php echo $includeItemsStr ?>"
                "wms_exceptions_format" "application/vnd.ogc.se_xml"
                <?php if ($row['wmssource'] && empty($row['legend_url'])) {
                    $wmsCon = str_replace(array("layers", "LAYERS"), "LAYER", $row['wmssource']);
                    echo "\"wms_get_legend_url\" \"{$wmsCon}&REQUEST=getlegendgraphic\"\n";
                } ?>
                <?php if (!empty($row['legend_url'])) {
                    echo "\"wms_get_legend_url\" \"{$row['legend_url']}\"\n";
                } ?>
                <?php if (!empty($layerArr['data'][0]['query_buffer'])) echo "\"appformap_query_buffer\" \"" . $layerArr['data'][0]['query_buffer'] . "\"\n"; ?>
                END

                PROJECTION
                "init=EPSG:<?php echo $row['srid']; ?>"
                END
                TEMPLATE "test"
                <?php
                if (is_array($classArr['data']) and (!$row['wmssource'])) {
                    foreach ($classArr['data'] as $class) {
                        ?>
                        CLASS
                        #NAME
                        <?php if (!empty($class['name'])) echo "NAME '" . addslashes($class['name']) . "'\n"; ?>

                        #EXPRESSION
                        <?php if (!empty($class['expression'])) {
                            if (!empty($layerArr['data'][0]['theme_column'])) echo "EXPRESSION \"" . $class['expression'] . "\"\n";
                            else echo "EXPRESSION (" . $class['expression'] . ")\n";
                        } elseif (empty($class['expression']) and !empty($layerArr['data'][0]['theme_column'])) echo "EXPRESSION ''\n";
                        ?>

                        #MAXSCALEDENOM
                        <?php if (!empty($class['class_maxscaledenom'])) echo "MAXSCALEDENOM {$class['class_maxscaledenom']}\n"; ?>

                        #MINSCALEDENOM
                        <?php if (!empty($class['class_minscaledenom'])) echo "MINSCALEDENOM {$class['class_minscaledenom']}\n"; ?>

                        STYLE
                        #SYMBOL
                        <?php
                        if (!empty($class['symbol'])) {
                            $d = "'";
                            if (substr($class['symbol'], 0, 1) == "[") $d = "";
                            echo "SYMBOL $d" . $class['symbol'] . "$d\n";
                        }
                        ?>
                        #PATTERN
                        <?php if (!empty($class['pattern'])) echo "PATTERN " . $class['pattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if (!empty($class['linecap'])) echo "LINECAP " . $class['linecap'] . "\n"; ?>

                        #WIDTH
                        <?php if (!empty($class['width'])) echo "WIDTH " . $class['width'] . "\n"; ?>

                        #COLOR
                        <?php if (!empty($class['color'])) echo "COLOR " . Util::hex2RGB($class['color'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if (!empty($class['outlinecolor'])) echo "OUTLINECOLOR " . Util::hex2RGB($class['outlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if (!empty($class['style_opacity'])) echo "OPACITY " . $class['style_opacity'] . "\n"; ?>

                        #SIZE
                        <?php
                        if (!empty($class['size'])) {
                            if (is_numeric($class['size']))
                                echo "SIZE " . $class['size'];
                            else
                                echo "SIZE [{$class['size']}]";
                        }
                        echo "\n";
                        ?>

                        #ANGLE
                        <?php
                        if (!empty($class['angle'])) {
                            if (is_numeric($class['angle']) || strtolower($class['angle']) == "auto")
                                echo "ANGLE " . $class['angle'];
                            else
                                echo "ANGLE [{$class['angle']}]";
                        }
                        echo "\n";
                        ?>
                        #GEOMTRANSFORM
                        <?php
                        if (!empty($class['geomtransform'])) {

                            echo "GEOMTRANSFORM '{$class['geomtransform']}'";
                        }
                        echo "\n";
                        ?>

                        #MINSIZE
                        <?php
                        if (!empty($class['minsize'])) {

                            echo "MINSIZE {$class['minsize']}";
                        }
                        echo "\n";
                        ?>

                        #MAXSIZE
                        <?php
                        if (!empty($class['maxsize'])) {

                            echo "MAXSIZE {$class['maxsize']}";
                        }
                        echo "\n";
                        ?>

                        #OFFSET
                        <?php
                        echo "OFFSET " . (!empty($class['style_offsetx']) ? is_numeric($class['style_offsetx']) ? $class['style_offsetx'] : "[" . $class['style_offsetx'] . "]" : "0") . " " .
                            (!empty($class['style_offsety']) ? is_numeric($class['style_offsety']) ? $class['style_offsety'] : "[" . $class['style_offsety'] . "]" : "0") . "\n"
                        ?>

                        #POLAROFFSET
                        <?php
                        echo "POLAROFFSET " . (!empty($class['style_polaroffsetr']) ? is_numeric($class['style_polaroffsetr']) ? $class['style_polaroffsetr'] : "[" . $class['style_polaroffsetr'] . "]" : "0") . " " .
                            (!empty($class['style_polaroffsetd']) ? is_numeric($class['style_polaroffsetd']) ? $class['style_polaroffsetd'] : "[" . $class['style_polaroffsetd'] . "]" : "0") . "\n"
                        ?>


                        END # style

                        STYLE
                        #SYMBOL
                        <?php
                        if (!empty($class['overlaysymbol'])) {
                            $d = "'";
                            if (substr($class['overlaysymbol'], 0, 1) == "[") $d = "";
                            echo "SYMBOL $d" . $class['overlaysymbol'] . "$d\n";
                        }
                        ?>

                        #PATTERN
                        <?php if (!empty($class['overlaypattern'])) echo "PATTERN " . $class['overlaypattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if (!empty($class['overlaylinecap'])) echo "LINECAP " . $class['overlaylinecap'] . "\n"; ?>

                        #WIDTH
                        <?php if (!empty($class['overlaywidth'])) echo "WIDTH " . $class['overlaywidth'] . "\n"; ?>

                        #COLOR
                        <?php if (!empty($class['overlaycolor'])) echo "COLOR " . Util::hex2RGB($class['overlaycolor'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if (!empty($class['overlayoutlinecolor'])) echo "OUTLINECOLOR " . Util::hex2RGB($class['overlayoutlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if (!empty($class['overlaystyle_opacity'])) echo "OPACITY " . $class['overlaystyle_opacity'] . "\n"; ?>
                        #SIZE
                        <?php
                        if (!empty($class['overlaysize'])) {
                            if (is_numeric($class['overlaysize']))
                                echo "SIZE " . $class['overlaysize'];
                            else
                                echo "SIZE [{$class['overlaysize']}]";
                        }
                        echo "\n";
                        ?>
                        #ANGLE
                        <?php
                        if (!empty($class['overlayangle'])) {
                            if (is_numeric($class['overlayangle']) || strtolower($class['overlayangle']) == "auto")
                                echo "ANGLE " . $class['overlayangle'];
                            else
                                echo "ANGLE [{$class['overlayangle']}]";
                        }
                        echo "\n";
                        ?>
                        #GEOMTRANSFORM
                        <?php
                        if (!empty($class['overlaygeomtransform'])) {
                            echo "GEOMTRANSFORM '{$class['overlaygeomtransform']}'";
                        }
                        echo "\n";
                        ?>

                        #OFFSET
                        <?php
                        echo "OFFSET " . (!empty($class['overlaystyle_offsetx']) ? is_numeric($class['overlaystyle_offsetx']) ? $class['overlaystyle_offsetx'] : "[" . $class['overlaystyle_offsetx'] . "]" : "0") . " " .
                            (!empty($class['overlaystyle_offsety']) ? is_numeric($class['overlaystyle_offsety']) ? $class['overlaystyle_offsety'] : "[" . $class['overlaystyle_offsety'] . "]" : "0") . "\n"
                        ?>

                        #POLAROFFSET
                        <?php
                        echo "POLAROFFSET " . (!empty($class['overlaystyle_polaroffsetr']) ? is_numeric($class['overlaystyle_polaroffsetr']) ? $class['overlaystyle_polaroffsetr'] : "[" . $class['overlaystyle_polaroffsetr'] . "]" : "0") . " " .
                            (!empty($class['overlaystyle_polaroffsetd']) ? is_numeric($class['overlaystyle_polaroffsetd']) ? $class['overlaystyle_polaroffsetd'] : "[" . $class['overlaystyle_polaroffsetd'] . "]" : "0") . "\n"
                        ?>

                        END # style

                        #TEMPLATE "ttt"
                        <?php if (!empty($class['label'])) { ?>

                            #START_LABEL1_<?php echo $layerName . "\n" ?>

                            LABEL
                            <?php if (!empty($class['label_text'])) echo "TEXT '" . $class['label_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label_font'] ?: "arial") . ($class['label_fontweight'] ?: "normal") . "\n" ?>
                            SIZE <?php
                            if (!empty($class['label_size'])) {
                                if (is_numeric($class['label_size']))
                                    echo $class['label_size'];
                                else
                                    echo "[{$class['label_size']}]";
                            } else {
                                echo "11";
                            }
                            echo "\n";
                            ?>
                            COLOR <?php echo (!empty($class['label_color'])) ? Util::hex2RGB($class['label_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            OUTLINECOLOR <?php echo (!empty($class['label_outlinecolor'])) ? Util::hex2RGB($class['label_outlinecolor'], true, " ") : "255 255 255";
                            echo "\n"; ?>
                            SHADOWSIZE 2 2
                            ANTIALIAS true
                            FORCE <?php echo (!empty($class['label_force'])) ? "true" : "false";
                            echo "\n"; ?>
                            POSITION <?php echo (!empty($class['label_position'])) ? $class['label_position'] : "auto";
                            echo "\n"; ?>
                            PARTIALS false
                            MINSIZE 1

                            #MAXSIZE
                            <?php
                            if (!empty($class['label_maxsize'])) {

                                echo "MAXSIZE {$class['label_maxsize']}";
                            }
                            echo "\n";
                            ?>
                            <?php if (!empty($class['label_maxscaledenom'])) echo "MAXSCALEDENOM {$class['label_maxscaledenom']}\n"; ?>
                            <?php if (!empty($class['label_minscaledenom'])) echo "MINSCALEDENOM {$class['label_minscaledenom']}\n"; ?>
                            <?php if (!empty($class['label_buffer'])) echo "BUFFER {$class['label_buffer']}\n"; ?>
                            <?php if (!empty($class['label_repeatdistance'])) echo "REPEATDISTANCE {$class['label_repeatdistance']}\n"; ?>
                            <?php if (!empty($class['label_minfeaturesize'])) echo "MINFEATURESIZE {$class['label_minfeaturesize']}\n"; ?>

                            <?php if (!empty($class['label_expression'])) {
                                echo "EXPRESSION (" . $class['label_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if (!empty($class['label_angle'])) {
                                if (is_numeric($class['label_angle']) or $class['label_angle'] == 'auto' or $class['label_angle'] == 'auto2'
                                    or $class['label_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label_angle'];
                                else
                                    echo "ANGLE [{$class['label_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"

                            OFFSET <?php echo (!empty($class['label_offsetx']) ? $class['label_offsetx'] : "0") . " " . (!empty($class['label_offsety']) ? $class['label_offsety'] : "0") . "\n" ?>


                            STYLE
                            <?php if (!empty($class['label_backgroundcolor'])) {
                                $labelBackgroundColor = Util::hex2RGB($class['label_backgroundcolor'], true, " ");
                                echo
                                    "GEOMTRANSFORM 'labelpoly'\n" .
                                    "COLOR {$labelBackgroundColor}\n";

                                echo
                                    "OUTLINECOLOR {$labelBackgroundColor}\n" .
                                    "WIDTH " . ($class['label_backgroundpadding'] ?: "1") . "\n";

                            }
                            ?>
                            END # STYLE
                            END
                            #END_LABEL1_<?php echo $layerName . "\n" ?>
                        <?php } ?>
                        #LABEL2
                        <?php if (!empty($class['label2'])) { ?>
                            #START_LABEL2_<?php echo $layerName . "\n" ?>
                            LABEL
                            <?php if (!empty($class['label2_text'])) echo "TEXT '" . $class['label2_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label2_font'] ?: "arial") . ($class['label2_fontweight'] ?: "normal") . "\n" ?>
                            SIZE <?php
                            if ($class['label2_size']) {
                                if (is_numeric($class['label2_size']))
                                    echo $class['label2_size'];
                                else
                                    echo "[{$class['label2_size']}]";
                            } else {
                                echo "11";
                            }
                            echo "\n";
                            ?>
                            COLOR <?php echo !empty($class['label2_color']) ? Util::hex2RGB($class['label2_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            OUTLINECOLOR <?php echo ($class['label2_outlinecolor']) ? Util::hex2RGB($class['label2_outlinecolor'], true, " ") : "255 255 255";
                            echo "\n"; ?>
                            SHADOWSIZE 2 2
                            ANTIALIAS true
                            FORCE <?php echo ($class['label2_force']) ? "true" : "false";
                            echo "\n"; ?>
                            POSITION <?php echo ($class['label2_position']) ?: "auto";
                            echo "\n"; ?>
                            PARTIALS false
                            MINSIZE 1
                            #MAXSIZE
                            <?php
                            if (!empty($class['label2_maxsize'])) {

                                echo "MAXSIZE {$class['label2_maxsize']}";
                            }
                            echo "\n";
                            ?>
                            <?php if (!empty($class['label2_maxscaledenom'])) echo "MAXSCALEDENOM {$class['label2_maxscaledenom']}\n"; ?>
                            <?php if (!empty($class['label2_minscaledenom'])) echo "MINSCALEDENOM {$class['label2_minscaledenom']}\n"; ?>
                            <?php if (!empty($class['label2_buffer'])) echo "BUFFER {$class['label2_buffer']}\n"; ?>
                            <?php if (!empty($class['label2_repeatdistance'])) echo "REPEATDISTANCE {$class['label2_repeatdistance']}\n"; ?>
                            <?php if (!empty($class['label2_minfeaturesize'])) echo "MINFEATURESIZE {$class['label2_minfeaturesize']}\n"; ?>

                            <?php if (!empty($class['label2_expression'])) {
                                echo "EXPRESSION (" . $class['label2_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if (!empty($class['label2_angle'])) {
                                if (is_numeric($class['label2_angle']) or $class['label2_angle'] == 'auto' or $class['label2_angle'] == 'auto2'
                                    or $class['label2_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label2_angle'];
                                else
                                    echo "ANGLE [{$class['label2_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"

                            OFFSET <?php echo (!empty($class['label2_offsetx']) ? $class['label2_offsetx'] : "0") . " " . ($class['label2_offsety'] ?: "0") . "\n" ?>

                            STYLE
                            <?php if (!empty($class['label2_backgroundcolor'])) {
                                $labelBackgroundColor = Util::hex2RGB($class['label2_backgroundcolor'], true, " ");
                                echo
                                    "GEOMTRANSFORM 'labelpoly'\n" .
                                    "COLOR {$labelBackgroundColor}\n";

                                if (!empty($class['label2_backgroundpadding'])) {
                                    echo
                                        "OUTLINECOLOR {$labelBackgroundColor}\n" .
                                        "WIDTH {$class['label2_backgroundpadding']}\n";
                                }
                            }
                            ?>
                            END # STYLE
                            END
                            #END_LABEL2_<?php echo $layerName . "\n" ?>

                        <?php } ?>

                        <?php if (!empty($class['leader'])) { ?>
                            LEADER
                            GRIDSTEP <?php echo ($class['leader_gridstep']) ? $class['leader_gridstep'] : "5";
                            echo "\n"; ?>
                            MAXDISTANCE <?php echo ($class['leader_maxdistance']) ? $class['leader_maxdistance'] : "30";
                            echo "\n"; ?>
                            STYLE
                            COLOR <?php echo ($class['leader_color']) ? Util::hex2RGB($class['leader_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            WIDTH 1
                            END
                            END
                        <?php } ?>
                        END # Class
                        <?php
                    }
                }
                ?>
                END #Layer
                <?php
            }
        } ?>
        END #MapFile
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . "_wms.map";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Mapfile written", "ch" => $path . $name);
    }

    /**
     * @return array<bool|string>
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function writeWfs()
    {
        $postgisObject = new Model();
        $user = Connection::$param['postgisdb'];

        $sql = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$this->bbox[0]},{$this->bbox[1]},{$this->bbox[2]},{$this->bbox[3]},3857),4326)) AS a) select ST_xmin(a) as xmin,ST_ymin(a) as ymin,ST_xmax(a) as xmax,ST_ymax(a) as ymax  from box";
        $resultExtent = $postgisObject->execQuery($sql);
        $row = $postgisObject->fetchRow($resultExtent);
        $extent = [$row["xmin"], $row["ymin"], $row["xmax"], $row["ymax"]];

        ob_start();
        ?>
        MAP
        #
        # Start of map file
        #
        NAME "<?php echo $user; ?>"
        STATUS on
        EXTENT <?php echo implode(" ", $extent) . "\n" ?>
        SIZE 2000 1500
        MAXSIZE 16384
        UNITS METERS
        FONTSET "/var/www/geocloud2/app/wms/fonts/fonts.txt"

        #
        # Vector Line Types
        #

        Symbol
        Name 'triangle'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        .5 0
        1 1
        0 1
        END
        END

        SYMBOL
        NAME "circle"
        TYPE ellipse
        FILLED true
        POINTS
        1 1
        END
        END

        Symbol
        Name 'square'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        0 0
        1 0
        1 1
        0 1
        END
        END

        Symbol
        Name 'star'
        Type VECTOR
        Filled TRUE
        Points
        0 .375
        .35 .375
        .5 0
        .65 .375
        1 .375
        .75 .625
        .875 1
        .5 .75
        .125 1
        .25 .625
        END
        END

        SYMBOL
        NAME "hatch1"
        TYPE VECTOR
        POINTS
        0 1 1 0
        END
        END

        SYMBOL
        NAME "dashed1"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        #STYLE 4 2 END
        END

        SYMBOL

        NAME "arrow"
        TYPE vector
        FILLED true
        POINTS
        0 0.4
        3 0.4
        3 0
        5 0.8
        3 1.6
        3 1.2
        0 1.2
        0 0.4
        END
        ANCHORPOINT 0 0.5
        END # SYMBOL

        SYMBOL
        NAME "arrow2"
        TYPE vector
        FILLED true
        POINTS
        0 0.8
        1 0.4
        0 0
        0 0.8
        END
        ANCHORPOINT 0 0.5
        END

        #
        # Vector Line Types
        #

        SYMBOL
        NAME "continue"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dashed-line-short"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 1
        END
        END

        SYMBOL
        NAME "dashed-line-long"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 10
        END
        END

        SYMBOL
        NAME "dash-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        20 6 2 6
        END
        END

        SYMBOL
        NAME "dash-dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        #
        # Vector Line Types
        #

        Symbol
        Name 'triangle'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        .5 0
        1 1
        0 1
        END
        END

        SYMBOL
        NAME "circle"
        TYPE ellipse
        FILLED true
        POINTS
        1 1
        END
        END

        Symbol
        Name 'square'
        Type VECTOR
        Filled TRUE
        Points
        0 1
        0 0
        1 0
        1 1
        0 1
        END
        END

        Symbol
        Name 'star'
        Type VECTOR
        Filled TRUE
        Points
        0 .375
        .35 .375
        .5 0
        .65 .375
        1 .375
        .75 .625
        .875 1
        .5 .75
        .125 1
        .25 .625
        END
        END

        SYMBOL
        NAME "hatch1"
        TYPE VECTOR
        POINTS
        0 1 1 0
        END
        END

        SYMBOL
        NAME "dashed1"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        #STYLE 4 2 END
        END

        SYMBOL

        NAME "arrow"
        TYPE vector
        FILLED true
        POINTS
        0 0.4
        3 0.4
        3 0
        5 0.8
        3 1.6
        3 1.2
        0 1.2
        0 0.4
        END
        ANCHORPOINT 0 0.5
        END # SYMBOL

        SYMBOL
        NAME "arrow2"
        TYPE vector
        FILLED true
        POINTS
        0 0.8
        1 0.4
        0 0
        0 0.8
        END
        ANCHORPOINT 0 0.5
        END

        #
        # Vector Line Types
        #

        SYMBOL
        NAME "continue"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dashed-line-short"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 1
        END
        END

        SYMBOL
        NAME "dashed-line-long"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        10 10
        END
        END

        SYMBOL
        NAME "dash-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        20 6 2 6
        END
        END

        SYMBOL
        NAME "dash-dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        SYMBOL
        NAME "dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS
        1 1
        END
        END

        OUTPUTFORMAT
        NAME "utfgrid"
        DRIVER UTFGRID
        MIMETYPE "application/json"
        EXTENSION "json"
        FORMATOPTION "UTFRESOLUTION=4"
        FORMATOPTION "DUPLICATES=false"
        END

        OUTPUTFORMAT
        NAME kml
        DRIVER "OGR/KML"
        MIMETYPE "application/vnd.google-earth.kml+xml"
        IMAGEMODE FEATURE
        EXTENSION "kml"
        FORMATOPTION "FORM=simple"
        FORMATOPTION 'FILENAME=igmap75.kml'
        FORMATOPTION "maxfeaturestodraw=1000"
        END

        #CONFIG "MS_ERRORFILE" "/var/www/geocloud2/app/wms/mapfiles/ms_error.txt"
        #DEBUG 5

        WEB
        METADATA
        "ows_title"    "<?php echo $user; ?>'s OWS"
        "ows_srs"    <?php echo "\"" . (!empty(App::$param['advertisedSrs']) ? implode(" ", App::$param['advertisedSrs']) : "EPSG:4326 EPSG:3857 EPSG:3044 EPSG:25832") . "\"\n" ?>
        "ows_name"    "<?php echo $user; ?>"
        "ows_onlineresource"    "<?php echo App::$param['host']; ?>/ows/<?php echo Connection::$param['postgisdb']; ?>/<?php echo Connection::$param['postgisschema']; ?>/"
        "ows_enable_request" "*"
        "ows_encoding" "UTF-8"
        "ows_namespace_prefix" "<?php echo $user; ?>"
        "ows_namespace_uri" "<?php echo App::$param['host']; ?>"
        "wfs_getfeature_formatlist" "kml,kmz"
        END
        END

        #
        # Start of reference map
        #

        PROJECTION
        "init=EPSG:4326"
        END


        #
        # Start of layers
        #
        <?php
        $sql = "SELECT * FROM settings.getColumns('f_table_schema=''" . Connection::$param['postgisschema'] . "'' AND enableows=true','raster_columns.r_table_schema=''" . Connection::$param['postgisschema'] . "'' AND enableows=true') ORDER BY sort_id";
        $result = $postgisObject->execQuery($sql);
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['srid'] > 1) {

                $sql = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$this->bbox[0]},{$this->bbox[1]},{$this->bbox[2]},{$this->bbox[3]},3857),{$row['srid']})) AS a) select ST_xmin(a) as xmin,ST_ymin(a) as ymin,ST_xmax(a) as xmax,ST_ymax(a) as ymax  from box";
                $resultExtent = $postgisObject->prepare($sql);
                try {
                    $resultExtent->execute();
                    $rowExtent = $postgisObject->fetchRow($resultExtent);
                    $extent = [$rowExtent["xmin"], $rowExtent["ymin"], $rowExtent["xmax"], $rowExtent["ymax"]];
                } catch (PDOException $e) {
                    $extent = $this->bbox;
                }

                $rel = "{$row['f_table_schema']}.{$row['f_table_name']}";
                $meta = $postgisObject->getMetaData($rel);
                $arr = !empty($row['def']) ? (array)json_decode($row['def']) : []; // Cast stdclass to array
                $props = array("label_column", "theme_column");
                foreach ($props as $field) {
                    if (empty($arr[$field])) {
                        $arr[$field] = "";
                    }
                }
                $layerArr = array("data" => array($arr));
                $sortedArr = array();

                // Sort classes
                $arr = $arr2 = !empty($row['class']) ? !empty(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [] : [];
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $last = 100000;
                    foreach ($arr2 as $key => $value) {
                        if ($value["sortid"] < $last) {
                            $temp = $value;
                            $del = $key;
                            $last = $value["sortid"];
                        }
                    }
                    $sortedArr[] = $temp;
                    unset($arr2[$del]);
                    $temp = null;
                }
                $arr = $sortedArr;
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $arrNew[$i] = (array)Util::casttoclass('stdClass', $arr[$i]);
                    $arrNew[$i]['id'] = $i;
                }
                $classArr = array("data" => !empty($arrNew) ? $arrNew : null);
                $primeryKey = $postgisObject->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
                if (!empty($arrNew)) unset($arrNew);
                ?>
                LAYER
                <?php $layerName = $row['f_table_schema'] . "." . $row['f_table_name']; ?>
                NAME "<?php echo $layerName; ?>"
                STATUS off
                <?php
                if (!empty($layerArr['data'][0]['geotype']) && $layerArr['data'][0]['geotype'] != "Default") {
                    $type = $layerArr['data'][0]['geotype'];
                } else {
                    switch ($row['type']) {
                        case "MULTIPOINT":
                        case "POINT":
                            $type = "POINT";
                            break;
                        case "MULTILINESTRING":
                        case "GEOMETRY":
                        case "LINESTRING":
                            $type = "LINE";
                            break;
                        case "MULTIPOLYGON":
                        case "POLYGON":
                            $type = "POLYGON";
                            break;
                        case "RASTER":
                            $type = "RASTER";
                            break;
                    }
                }
                if (!$row['data']) {
                    if (preg_match('/[A-Z]/', $row['f_geometry_column'])) {
                        $dataSql = "SELECT *,\\\"{$row['f_geometry_column']}\\\" as " . strtolower($row['f_geometry_column']) . " FROM \\\"{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                    } else {
                        $dataSql = "SELECT * FROM \\\"" . "{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                    }
                } else {
                    $dataSql = $row['data'];
                }
                $fieldConf = !empty($row['fieldconf']) ? json_decode($row['fieldconf'], true) : [];
                $includeItemsStr = "all";
                uksort($meta, function ($a, $b) use ($fieldConf) {
                    if (isset($fieldConf[$a]) && isset($fieldConf[$b])) {
                        $sortIdA = $fieldConf[$a]['sort_id'];
                        $sortIdB = $fieldConf[$b]['sort_id'];
                        return $sortIdA - $sortIdB;
                    }
                    return 0;
                });
                // We always want to select all fields
                if (sizeof($meta) > 0) {
                    $selectStr = implode("\\\",\\\"", array_keys($meta));
                }
                // Filter out ignored fields
                $meta = array_filter($meta, function ($item, $key) use (&$fieldConf) {
                    if (empty($fieldConf[$key]['ignore'])) {
                        return $item;
                    }
                }, ARRAY_FILTER_USE_BOTH);
                if (sizeof($meta) > 0) {
                    $includeItemsStr = implode(",", array_keys($meta));
                }
                echo "DATA \"" . strtolower($row['f_geometry_column']) . " FROM (SELECT \\\"$selectStr\\\" FROM ($dataSql /*FILTER_$layerName*/) as bar) as foo USING UNIQUE {$primeryKey['attname']} USING srid={$row['srid']}\"\n";
                ?>
                CONNECTIONTYPE POSTGIS
                CONNECTION "user=<?php echo Connection::$param['postgisuser']; ?> dbname=<?php echo Connection::$param['postgisdb']; ?><?php if (Connection::$param['postgishost']) echo " host=" . (!empty(Connection::$param['mapserverhost']) ? Connection::$param['mapserverhost'] : Connection::$param['postgishost']); ?><?php echo " port=" . ((!empty(Connection::$param['mapserverport']) ? Connection::$param['mapserverport'] : Connection::$param['postgisport']) ?: "5432") ?><?php if (Connection::$param['postgispw']) echo " password=" . Connection::$param['postgispw']; ?><?php if (!Connection::$param['pgbouncer']) echo " options='-c client_encoding=UTF8'" ?>"
                <?php ?>
                TYPE <?php echo $type . "\n"; ?>
                METADATA
                "wfs_title"    "<?php if ($row['f_table_title']) echo(!empty($row['f_table_title']) ? addslashes($row['f_table_title']) : ""); else echo $row['f_table_name'] ?>"
                "wfs_srs"    "EPSG:<?php echo "{$row['srid']} {$row['wmsclientepsgs']}" ?>"
                "wfs_name"    "<?php echo $layerName; ?>"
                "wfs_abstract"    "<?php echo(!empty($row['f_table_abstract']) ? addslashes($row['f_table_abstract']) : ""); ?>"
                "wfs_extent" "<?php echo implode(" ", $extent) ?>"
                "gml_include_items" "<?php echo $includeItemsStr ?>"
                "wfs_featureid" "<?php echo $primeryKey['attname'] ?>"
                "gml_types" "auto"
                "wfs_geomtype" "<?php echo $row['type'];
                echo $row['coord_dimension'] == 3 ? "25D" : ""; ?>"
                "gml_geometries"    "<?php echo $row['f_geometry_column']; ?>"
                "gml_<?php echo $row['f_geometry_column'] ?>_type" "<?php echo (substr($row['type'], 0, 5) == "MULTI" ? "multi" : "") . strtolower($type); ?>"
                "wfs_getfeature_formatlist" "kml,kmz"
                END
                UTFITEM   "<?php echo $primeryKey['attname'] ?>"
                <?php $fields = !empty($row['fieldconf']) ? json_decode($row['fieldconf'], true) : null;
                if (!empty($fields)) {
                    foreach ($fields as $field => $name) {
                        if (isset($meta[$field]) && !empty($name["mouseover"])) {
                            $fieldsArr[] = "\\\"{$field}\\\":\\\"[{$field}]\\\"";
                        }
                    }
                }
                ?>
                UTFDATA "<?php echo "{" . implode(",", (!empty($fieldsArr) ? $fieldsArr : [])) . "}";
                $fieldsArr = [];
                ?>"

                PROJECTION
                "init=EPSG:<?php echo $row['srid']; ?>"
                END

                TEMPLATE "test"
                <?php
                if (is_array($classArr['data'])) {
//                    print_r($classArr['data']);
//                    die();
                    foreach ($classArr['data'] as $class) {
                        ?>
                        CLASS
                        #NAME
                        <?php if (!empty($class['name'])) echo "NAME '" . addslashes($class['name']) . "'\n"; ?>

                        #EXPRESSION
                        <?php if (!empty($class['expression'])) {
                            if (!empty($layerArr['data'][0]['theme_column'])) echo "EXPRESSION \"" . $class['expression'] . "\"\n";
                            else echo "EXPRESSION (" . $class['expression'] . ")\n";
                        } elseif (empty($class['expression']) and !empty($layerArr['data'][0]['theme_column'])) echo "EXPRESSION ''\n";
                        ?>

                        #MAXSCALEDENOM
                        <?php if (!empty($class['class_maxscaledenom'])) echo "MAXSCALEDENOM {$class['class_maxscaledenom']}\n"; ?>

                        #MINSCALEDENOM
                        <?php if (!empty($class['class_minscaledenom'])) echo "MINSCALEDENOM {$class['class_minscaledenom']}\n"; ?>

                        STYLE
                        #SYMBOL
                        <?php if (!empty($class['symbol'])) echo "SYMBOL '" . $class['symbol'] . "'\n"; ?>

                        #PATTERN
                        <?php if (!empty($class['pattern'])) echo "PATTERN " . $class['pattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if (!empty($class['linecap'])) echo "LINECAP " . $class['linecap'] . "\n"; ?>

                        #WIDTH
                        <?php if (!empty($class['width'])) echo "WIDTH " . $class['width'] . "\n"; ?>

                        #COLOR
                        <?php if (!empty($class['color'])) echo "COLOR " . Util::hex2RGB($class['color'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if (!empty($class['outlinecolor'])) echo "OUTLINECOLOR " . Util::hex2RGB($class['outlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if (!empty($class['style_opacity'])) echo "OPACITY " . $class['style_opacity'] . "\n"; ?>

                        #SIZE
                        <?php
                        if (!empty($class['size'])) {
                            if (is_numeric($class['size']))
                                echo "SIZE " . $class['size'];
                            else
                                echo "SIZE [{$class['size']}]";
                        }
                        echo "\n";
                        ?>

                        #ANGLE
                        <?php
                        if (!empty($class['angle'])) {
                            if (is_numeric($class['angle']) || strtolower($class['angle']) == "auto")
                                echo "ANGLE " . $class['angle'];
                            else
                                echo "ANGLE [{$class['angle']}]";
                        }
                        echo "\n";
                        ?>
                        #GEOMTRANSFORM
                        <?php
                        if (!empty($class['geomtransform'])) {

                            echo "GEOMTRANSFORM '{$class['geomtransform']}'";
                        }
                        echo "\n";
                        ?>

                        #MINSIZE
                        <?php
                        if (!empty($class['minsize'])) {

                            echo "MINSIZE {$class['minsize']}";
                        }
                        echo "\n";
                        ?>

                        #MAXSIZE
                        <?php
                        if (!empty($class['maxsize'])) {

                            echo "MAXSIZE {$class['maxsize']}";
                        }
                        echo "\n";
                        ?>

                        #OFFSET
                        <?php
                        echo "OFFSET " . (!empty($class['style_offsetx']) ? is_numeric($class['style_offsetx']) ? $class['style_offsetx'] : "[" . $class['style_offsetx'] . "]" : "0") . " " .
                            (!empty($class['style_offsety']) ? is_numeric($class['style_offsety']) ? $class['style_offsety'] : "[" . $class['style_offsety'] . "]" : "0") . "\n"
                        ?>

                        #POLAROFFSET
                        <?php
                        echo "POLAROFFSET " . (!empty($class['style_polaroffsetr']) ? is_numeric($class['style_polaroffsetr']) ? $class['style_polaroffsetr'] : "[" . $class['style_polaroffsetr'] . "]" : "0") . " " .
                            (!empty($class['style_polaroffsetd']) ? is_numeric($class['style_polaroffsetd']) ? $class['style_polaroffsetd'] : "[" . $class['style_polaroffsetd'] . "]" : "0") . "\n"
                        ?>


                        END # style

                        STYLE
                        #SYMBOL
                        <?php if (!empty($class['overlaysymbol'])) echo "SYMBOL '" . $class['overlaysymbol'] . "'\n"; ?>

                        #PATTERN
                        <?php if (!empty($class['overlaypattern'])) echo "PATTERN " . $class['overlaypattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if (!empty($class['overlaylinecap'])) echo "LINECAP " . $class['overlaylinecap'] . "\n"; ?>

                        #WIDTH
                        <?php if (!empty($class['overlaywidth'])) echo "WIDTH " . $class['overlaywidth'] . "\n"; ?>

                        #COLOR
                        <?php if (!empty($class['overlaycolor'])) echo "COLOR " . Util::hex2RGB($class['overlaycolor'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if (!empty($class['overlayoutlinecolor'])) echo "OUTLINECOLOR " . Util::hex2RGB($class['overlayoutlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if (!empty($class['overlaystyle_opacity'])) echo "OPACITY " . $class['overlaystyle_opacity'] . "\n"; ?>
                        #SIZE
                        <?php
                        if (!empty($class['overlaysize'])) {
                            if (is_numeric($class['overlaysize']))
                                echo "SIZE " . $class['overlaysize'];
                            else
                                echo "SIZE [{$class['overlaysize']}]";
                        }
                        echo "\n";
                        ?>
                        #ANGLE
                        <?php
                        if (!empty($class['overlayangle'])) {
                            if (is_numeric($class['overlayangle']) || strtolower($class['overlayangle']) == "auto")
                                echo "ANGLE " . $class['overlayangle'];
                            else
                                echo "ANGLE [{$class['overlayangle']}]";
                        }
                        echo "\n";
                        ?>
                        #GEOMTRANSFORM
                        <?php
                        if (!empty($class['overlaygeomtransform'])) {
                            echo "GEOMTRANSFORM '{$class['overlaygeomtransform']}'";
                        }
                        echo "\n";
                        ?>

                        #OFFSET
                        <?php
                        echo "OFFSET " . (!empty($class['overlaystyle_offsetx']) ? is_numeric($class['overlaystyle_offsetx']) ? $class['overlaystyle_offsetx'] : "[" . $class['overlaystyle_offsetx'] . "]" : "0") . " " .
                            (!empty($class['overlaystyle_offsety']) ? is_numeric($class['overlaystyle_offsety']) ? $class['overlaystyle_offsety'] : "[" . $class['overlaystyle_offsety'] . "]" : "0") . "\n"
                        ?>

                        #POLAROFFSET
                        <?php
                        echo "POLAROFFSET " . (!empty($class['overlaystyle_polaroffsetr']) ? is_numeric($class['overlaystyle_polaroffsetr']) ? $class['overlaystyle_polaroffsetr'] : "[" . $class['overlaystyle_polaroffsetr'] . "]" : "0") . " " .
                            (!empty($class['overlaystyle_polaroffsetd']) ? is_numeric($class['overlaystyle_polaroffsetd']) ? $class['overlaystyle_polaroffsetd'] : "[" . $class['overlaystyle_polaroffsetd'] . "]" : "0") . "\n"
                        ?>

                        END # style

                        #TEMPLATE "ttt"
                        <?php if (!empty($class['label'])) { ?>

                            #START_LABEL1_<?php echo $layerName . "\n" ?>

                            LABEL
                            <?php if (!empty($class['label_text'])) echo "TEXT '" . $class['label_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label_font'] ?: "arial") . ($class['label_fontweight'] ?: "normal") . "\n" ?>
                            SIZE <?php
                            if (!empty($class['label_size'])) {
                                if (is_numeric($class['label_size']))
                                    echo $class['label_size'];
                                else
                                    echo "[{$class['label_size']}]";
                            } else {
                                echo "11";
                            }
                            echo "\n";
                            ?>
                            COLOR <?php echo (!empty($class['label_color'])) ? Util::hex2RGB($class['label_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            OUTLINECOLOR <?php echo (!empty($class['label_outlinecolor'])) ? Util::hex2RGB($class['label_outlinecolor'], true, " ") : "255 255 255";
                            echo "\n"; ?>
                            SHADOWSIZE 2 2
                            ANTIALIAS true
                            FORCE <?php echo (!empty($class['label_force'])) ? "true" : "false";
                            echo "\n"; ?>
                            POSITION <?php echo (!empty($class['label_position'])) ? $class['label_position'] : "auto";
                            echo "\n"; ?>
                            PARTIALS false
                            MINSIZE 1

                            #MAXSIZE
                            <?php
                            if (!empty($class['label_maxsize'])) {

                                echo "MAXSIZE {$class['label_maxsize']}";
                            }
                            echo "\n";
                            ?>
                            <?php if (!empty($class['label_maxscaledenom'])) echo "MAXSCALEDENOM {$class['label_maxscaledenom']}\n"; ?>
                            <?php if (!empty($class['label_minscaledenom'])) echo "MINSCALEDENOM {$class['label_minscaledenom']}\n"; ?>
                            <?php if (!empty($class['label_buffer'])) echo "BUFFER {$class['label_buffer']}\n"; ?>
                            <?php if (!empty($class['label_repeatdistance'])) echo "REPEATDISTANCE {$class['label_repeatdistance']}\n"; ?>
                            <?php if (!empty($class['label_minfeaturesize'])) echo "MINFEATURESIZE {$class['label_minfeaturesize']}\n"; ?>

                            <?php if (!empty($class['label_expression'])) {
                                echo "EXPRESSION (" . $class['label_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if (!empty($class['label_angle'])) {
                                if (is_numeric($class['label_angle']) or $class['label_angle'] == 'auto' or $class['label_angle'] == 'auto2'
                                    or $class['label_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label_angle'];
                                else
                                    echo "ANGLE [{$class['label_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"

                            OFFSET <?php echo (!empty($class['label_offsetx']) ? $class['label_offsetx'] : "0") . " " . (!empty($class['label_offsety']) ? $class['label_offsety'] : "0") . "\n" ?>


                            STYLE
                            <?php if (!empty($class['label_backgroundcolor'])) {
                                $labelBackgroundColor = Util::hex2RGB($class['label_backgroundcolor'], true, " ");
                                echo
                                    "GEOMTRANSFORM 'labelpoly'\n" .
                                    "COLOR {$labelBackgroundColor}\n";

                                echo
                                    "OUTLINECOLOR {$labelBackgroundColor}\n" .
                                    "WIDTH " . ($class['label_backgroundpadding'] ?: "1") . "\n";

                            }
                            ?>
                            END # STYLE
                            END
                            #END_LABEL1_<?php echo $layerName . "\n" ?>
                        <?php } ?>
                        #LABEL2
                        <?php if (!empty($class['label2'])) { ?>
                            #START_LABEL2_<?php echo $layerName . "\n" ?>
                            LABEL
                            <?php if (!empty($class['label2_text'])) echo "TEXT '" . $class['label2_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label2_font'] ?: "arial") . ($class['label2_fontweight'] ?: "normal") . "\n" ?>
                            SIZE <?php
                            if ($class['label2_size']) {
                                if (is_numeric($class['label2_size']))
                                    echo $class['label2_size'];
                                else
                                    echo "[{$class['label2_size']}]";
                            } else {
                                echo "11";
                            }
                            echo "\n";
                            ?>
                            COLOR <?php echo !empty($class['label2_color']) ? Util::hex2RGB($class['label2_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            OUTLINECOLOR <?php echo ($class['label2_outlinecolor']) ? Util::hex2RGB($class['label2_outlinecolor'], true, " ") : "255 255 255";
                            echo "\n"; ?>
                            SHADOWSIZE 2 2
                            ANTIALIAS true
                            FORCE <?php echo ($class['label2_force']) ? "true" : "false";
                            echo "\n"; ?>
                            POSITION <?php echo ($class['label2_position']) ?: "auto";
                            echo "\n"; ?>
                            PARTIALS false
                            MINSIZE 1
                            #MAXSIZE
                            <?php
                            if (!empty($class['label2_maxsize'])) {

                                echo "MAXSIZE {$class['label2_maxsize']}";
                            }
                            echo "\n";
                            ?>
                            <?php if (!empty($class['label2_maxscaledenom'])) echo "MAXSCALEDENOM {$class['label2_maxscaledenom']}\n"; ?>
                            <?php if (!empty($class['label2_minscaledenom'])) echo "MINSCALEDENOM {$class['label2_minscaledenom']}\n"; ?>
                            <?php if (!empty($class['label2_buffer'])) echo "BUFFER {$class['label2_buffer']}\n"; ?>
                            <?php if (!empty($class['label2_repeatdistance'])) echo "REPEATDISTANCE {$class['label2_repeatdistance']}\n"; ?>
                            <?php if (!empty($class['label2_minfeaturesize'])) echo "MINFEATURESIZE {$class['label2_minfeaturesize']}\n"; ?>

                            <?php if (!empty($class['label2_expression'])) {
                                echo "EXPRESSION (" . $class['label2_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if (!empty($class['label2_angle'])) {
                                if (is_numeric($class['label2_angle']) or $class['label2_angle'] == 'auto' or $class['label2_angle'] == 'auto2'
                                    or $class['label2_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label2_angle'];
                                else
                                    echo "ANGLE [{$class['label2_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"

                            OFFSET <?php echo (!empty($class['label2_offsetx']) ? $class['label2_offsetx'] : "0") . " " . ($class['label2_offsety'] ?: "0") . "\n" ?>

                            STYLE
                            <?php if (!empty($class['label2_backgroundcolor'])) {
                                $labelBackgroundColor = Util::hex2RGB($class['label2_backgroundcolor'], true, " ");
                                echo
                                    "GEOMTRANSFORM 'labelpoly'\n" .
                                    "COLOR {$labelBackgroundColor}\n";

                                if (!empty($class['label2_backgroundpadding'])) {
                                    echo
                                        "OUTLINECOLOR {$labelBackgroundColor}\n" .
                                        "WIDTH {$class['label2_backgroundpadding']}\n";
                                }
                            }
                            ?>
                            END # STYLE
                            END
                            #END_LABEL2_<?php echo $layerName . "\n" ?>

                        <?php } ?>

                        <?php if (!empty($class['leader'])) { ?>
                            LEADER
                            GRIDSTEP <?php echo ($class['leader_gridstep']) ? $class['leader_gridstep'] : "5";
                            echo "\n"; ?>
                            MAXDISTANCE <?php echo ($class['leader_maxdistance']) ? $class['leader_maxdistance'] : "30";
                            echo "\n"; ?>
                            STYLE
                            COLOR <?php echo ($class['leader_color']) ? Util::hex2RGB($class['leader_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            WIDTH 1
                            END
                            END
                        <?php } ?>
                        END # Class
                        <?php
                    }
                }
                ?>
                END #Layer
            <?php } ?>
        <?php } ?>
        END #MapFile
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . "_wfs.map";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Mapfile written", "ch" => $path . $name);
    }
}
