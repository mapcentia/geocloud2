<?php
namespace app\controllers;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Util;

class Mapfile extends \app\inc\Controller
{
    private $fonts;

    function __construct()
    {
    }

    function get_index()
    {
        $postgisObject = new \app\inc\Model();
        $user = Connection::$param['postgisdb'];
        ob_start();
        ?>
        MAP
        #
        # Start of map file
        #
        NAME "<?php echo $user; ?>"
        STATUS on
        EXTENT <?php if (isset(App::$param["wgs84boundingbox"])) echo implode(" ", App::$param["wgs84boundingbox"]); else echo "-180 -90 180 90"; ?>
        SIZE 2000 1500
        MAXSIZE 4096
        #SYMBOLSET "../etc/symbols.sym"
        FONTSET "../fonts/fonts.txt"
        IMAGECOLOR 255 2 255
        UNITS METERS
        INTERLACE OFF
        OUTPUTFORMAT
        NAME "png"
        DRIVER AGG/PNG
        MIMETYPE "image/png"
        IMAGEMODE RGBA
        EXTENSION "png"
        TRANSPARENT ON
        FORMATOPTION "GAMMA=0.75"
        END
        #CONFIG "MS_ERRORFILE" "/srv/www/sites/betamygeocloud/wms/mapfiles/ms_error.txt"
        #DEBUG 5
        WEB
        IMAGEPATH "<?php echo App::$param['path']; ?>/tmp"
        IMAGEURL "<?php echo App::$param['host']; ?>/tmp"
        METADATA
        "wms_title"    "<?php echo $user; ?>'s awesome WMS"
        "wfs_title"    "<?php echo $user; ?>'s awesome WFS"
        "wms_srs"    "EPSG:4326 EPSG:3857 EPSG:900913 EPSG:3044 EPSG:25832"
        "wfs_srs"    "EPSG:4326 EPSG:3857 EPSG:900913 EPSG:3044 EPSG:25832"
        "wms_name"    "<?php echo $user; ?>"
        "wfs_name"    "<?php echo $user; ?>"
        "wms_format"    "image/png"
        "wms_onlineresource"    "<?php echo App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/ows/<?php echo Connection::$param['postgisdb']; ?>/<?php echo Connection::$param['postgisschema']; ?>/"
        "wfs_onlineresource"    "<?php echo App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/ows/<?php echo Connection::$param['postgisdb']; ?>/<?php echo Connection::$param['postgisschema']; ?>/"
        "ows_enable_request" "*"
        "wms_enable_request" "*"
        "wms_encoding" "UTF-8"
        "wfs_encoding" "UTF-8"
        "wfs_namespace_prefix" "<?php echo $user; ?>"
        "wfs_namespace_uri" "<?php echo App::$param['host']; ?>"
        END
        END
        #
        # Start of reference map
        #

        PROJECTION
        "init=epsg:4326"
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
        #SHADOWSIZE 2 2
        #BACKGROUNDSHADOWSIZE 1 1
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
        POINTS 1 1 END
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
        END # POINTS
        ANCHORPOINT 0 0.5
        END # SYMBOL

        # ============================================================================
        # Vector Line Types
        # ============================================================================

        SYMBOL
        NAME "continue"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 1 1 END
        END

        # --------------------

        SYMBOL
        NAME "dashed-line-short"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 10 1 END
        #STYLE 5 5 END
        END

        # --------------------

        SYMBOL
        NAME "dashed-line-long"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 10 10 END
        #STYLE 10 10 END
        END

        # --------------------

        SYMBOL
        NAME "dash-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 20 6 2 6 END
        #STYLE 20 6 2 6 END
        END

        # --------------------

        SYMBOL
        NAME "dash-dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 1 1 END
        #STYLE 10 6 2 6 2 6 END
        END

        # --------------------

        SYMBOL
        NAME "dot-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 1 1 END
        #STYLE 2 2 END
        END


        #
        # Start of layers
        #
        <?php

        $sql = "SELECT * FROM settings.getColumns('f_table_schema=''" . Connection::$param['postgisschema'] . "''','raster_columns.r_table_schema=''" . Connection::$param['postgisschema'] . "''') ORDER BY sort_id";

        $result = $postgisObject->execQuery($sql);
        if ($postgisObject->PDOerror) {
            ob_get_clean();
            return false;
        }
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['srid'] > 1) {
                $versioning = $postgisObject->doesColumnExist("{$row['f_table_schema']}.{$row['f_table_name']}","gc2_version_gid");
                $versioning = $versioning["exists"];

                $workflow = $postgisObject->doesColumnExist("{$row['f_table_schema']}.{$row['f_table_name']}","gc2_status");
                $workflow = $workflow["exists"];

                $arr = (array)json_decode($row['def']); // Cast stdclass to array
                $props = array("label_column", "theme_column");
                foreach ($props as $field) {
                    if (!$arr[$field]) {
                        $arr[$field] = "";
                    }
                }
                $layerArr = array("data" => array($arr));
                $sortedArr = array();

                // Sort classes
                $arr = $arr2 = (array)json_decode($row['class']);
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $last = 1000;
                    foreach ($arr2 as $key => $value) {
                        if ($value->sortid < $last) {
                            $temp = $value;
                            $del = $key;
                            $last = $value->sortid;
                        }
                    }
                    array_push($sortedArr, $temp);
                    unset($arr2[$del]);
                    $temp = null;
                }
                $arr = $sortedArr;
                for ($i = 0; $i < sizeof($arr); $i++) {
                    $arrNew[$i] = (array)\app\inc\Util::casttoclass('stdClass', $arr[$i]);
                    $arrNew[$i]['id'] = $i;
                }
                $classArr = array("data" => $arrNew);
                $primeryKey = $postgisObject->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
                unset($arrNew);
                ?>
                LAYER
                NAME "<?php echo $row['f_table_schema']; ?>.<?php echo $row['f_table_name']; ?>"
                STATUS off


                <?php if ($row['filter']) { ?>
                    FILTER "<?php echo $row['filter']; ?>"
                <?php } ?>
                <?php
                if (($layerArr['data'][0]['geotype']) && $layerArr['data'][0]['geotype'] != "Default") {
                    $type = $layerArr['data'][0]['geotype'];
                } else {
                    switch ($row['type']) {
                        case "POINT":
                            $type = "POINT";
                            break;
                        case "LINESTRING":
                            $type = "LINE";
                            break;
                        case "POLYGON":
                            $type = "POLYGON";
                            break;
                        case "MULTIPOINT":
                            $type = "POINT";
                            break;
                        case "MULTILINESTRING":
                            $type = "LINE";
                            break;
                        case "MULTIPOLYGON":
                            $type = "POLYGON";
                            break;
                        case "GEOMETRY":
                            $type = "LINE";
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
                    PROCESSING "RESAMPLE=AVERAGE"

                <?php
                } elseif ($row['bitmapsource']) {
                    ?>
                    TYPE RASTER
                    DATA "<?php echo App::$param['path'] . "/app/tmp/" . Connection::$param["postgisdb"] . "/__bitmaps/" . $row['bitmapsource']; ?>"
                    #PROCESSING "LOAD_WHOLE_IMAGE=YES"
                    PROCESSING "RESAMPLE=AVERAGE"
                <?php
                } else {
                    if ($type != "RASTER") {
                        if (!$row['data']) {
                            if (preg_match('/[A-Z]/', $row['f_geometry_column'])) {
                                $dataSql = "SELECT *,\\\"{$row['f_geometry_column']}\\\" as " . strtolower($row['f_geometry_column']) . " FROM \\\"{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                            } else {
                                $dataSql = "SELECT * FROM \\\"" . "{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
                            }
                            if ($versioning || $workflow) {
                                $dataSql .= " WHERE 1=1";
                            }
                            if ($versioning) {
                                $dataSql .= " AND gc2_version_end_date IS NULL";
                            }
                            if ($workflow) {
                                //$dataSql .= " AND gc2_status = 3";
                            }
                        } else {
                            $dataSql = $row['data'];
                        }
                        echo "DATA \"" . strtolower($row['f_geometry_column']) . " FROM ({$dataSql}) as foo USING UNIQUE {$primeryKey['attname']} USING srid={$row['srid']}\"\n";
                        ?>
                        CONNECTIONTYPE POSTGIS
                        CONNECTION "user=<?php echo Connection::$param['postgisuser']; ?> dbname=<?php echo Connection::$param['postgisdb']; ?><?php if (Connection::$param['postgishost']) echo " host=" . Connection::$param['postgishost']; ?><?php if (Connection::$param['postgisport']) echo " port=" . Connection::$param['postgisport']; ?><?php if (Connection::$param['postgispw']) echo " password=" . Connection::$param['postgispw']; ?> <?php if (!Connection::$param['pgbouncer']) echo "options='-c client_encoding=UTF8'" ?>"
                    <?php
                    } else {
                        echo "DATA \"PG:host=" . (Connection::$param['mapserverhost'] ?: Connection::$param['postgishost']);
                        echo " port=" . (Connection::$param['mapserverport'] ?: (Connection::$param['postgisport']) ?: "5432");
                        echo " dbname='" . Connection::$param['postgisdb'] . "' user='" . Connection::$param['postgisuser'] . "' password='" . Connection::$param['postgispw'] . "'
		                    schema='{$row['f_table_schema']}' table='{$row['f_table_name']}' mode='2'\"\n";
                        echo "PROCESSING \"CLOSE_CONNECTION=ALWAYS\" \n";
                    }
                    ?>
                    TYPE <?php echo $type . "\n"; ?>

                <?php } ?>
                #OFFSITE
                <?php if ($layerArr['data'][0]['offsite']) echo "OFFSITE " . $layerArr['data'][0]['offsite'] . "\n"; ?>

                #CLASSITEM
                <?php if ($layerArr['data'][0]['theme_column']) echo "CLASSITEM '" . $layerArr['data'][0]['theme_column'] . "'\n"; ?>

                #LABELITEM
                <?php if ($layerArr['data'][0]['label_column']) echo "LABELITEM '" . $layerArr['data'][0]['label_column'] . "'\n"; ?>

                #LABELMAXSCALEDENOM
                <?php if ($layerArr['data'][0]['label_max_scale']) echo "LABELMAXSCALEDENOM " . $layerArr['data'][0]['label_max_scale'] . "\n"; ?>

                #LABELMINSCALEDENOM
                <?php if ($layerArr['data'][0]['label_min_scale']) echo "LABELMINSCALEDENOM " . $layerArr['data'][0]['label_min_scale'] . "\n"; ?>


                #OPACITY
                <?php if ($layerArr['data'][0]['opacity']) echo "OPACITY  " . $layerArr['data'][0]['opacity'] . "\n"; ?>

                #MAXSCALEDENOM
                <?php if ($layerArr['data'][0]['maxscaledenom']) echo "MAXSCALEDENOM  " . $layerArr['data'][0]['maxscaledenom'] . "\n"; ?>

                #MINSCALEDENOM
                <?php if ($layerArr['data'][0]['minscaledenom']) echo "MINSCALEDENOM  " . $layerArr['data'][0]['minscaledenom'] . "\n"; ?>

                #SYMBOLSCALEDENOM
                <?php if ($layerArr['data'][0]['symbolscaledenom']) echo "SYMBOLSCALEDENOM " . $layerArr['data'][0]['symbolscaledenom'] . "\n"; ?>

                #MINSCALEDENOM
                <?php if ($layerArr['data'][0]['cluster']) {
                    echo "CLUSTER\n";
                    echo "MAXDISTANCE {$layerArr['data'][0]['cluster']}\n";
                    echo "REGION \"ellipse\"\n";
                    //echo "PROCESSING \"CLUSTER_GET_ALL_SHAPES=false\"\n";
                    echo "END\n";
                }
                ?>

                #LABELMAXSCALE
                METADATA
                "wms_title"    "<?php if ($row['f_table_title']) echo $row['f_table_title']; else echo $row['f_table_name'] ?>"
                "wfs_title"    "<?php if ($row['f_table_title']) echo $row['f_table_title']; else echo $row['f_table_name'] ?>"
                "wms_srs"    "EPSG:<?php echo $row['srid']; ?>"
                "wfs_srs"    "EPSG:<?php echo $row['srid']; ?>"
                "wms_name"    "<?php echo $row['f_table_name']; ?>"
                "wfs_name"    "<?php echo $row['f_table_name']; ?>"
                "wms_abstract"    "<?php echo $row['f_table_abstract']; ?>"
                "wfs_abstract"    "<?php echo $row['f_table_abstract']; ?>"
                "wms_format"    "image/png"
                #"wms_extent" "-180 -90 180 90"
                "appformap_group"  "<?php if ($row['layergroup']) echo $row['layergroup']; else echo "Default group" ?>"
                "appformap_queryable"    "true"
                "appformap_loader"    "true"
                "wms_enable_request"    "*"
                "gml_include_items" "all"
                "wms_include_items" "all"
                "wfs_featureid" "<?php echo $primeryKey['attname'] ?>"
                "gml_geometries"    "<?php echo $row['f_geometry_column']; ?>"
                "gml_<?php echo $row['f_geometry_column'] ?>_type" "<?php echo (substr($row['type'], 0, 5) == "MULTI" ? "multi" : "") . strtolower($type); ?>"
                <?php if ($row['wmssource']) {
                    $wmsCon = str_replace(array("layers", "LAYERS"), "LAYER", $row['wmssource']);
                    echo "\"wms_get_legend_url\" \"{$wmsCon}&REQUEST=getlegendgraphic\"\n";
                } ?>
                <?php if ($layerArr['data'][0]['query_buffer']) echo "\"appformap_query_buffer\" \"" . $layerArr['data'][0]['query_buffer'] . "\"\n"; ?>
                END
                PROJECTION
                "init=epsg:<?php echo $row['srid']; ?>"
                END
                TEMPLATE "test"
                <?php
                if (is_array($classArr['data'])) {
                    foreach ($classArr['data'] as $class) {
                        ?>
                        CLASS
                        #NAME
                        <?php if ($class['name']) echo "NAME '" . $class['name'] . "'\n"; ?>

                        #EXPRESSION
                        <?php if ($class['expression']) {
                            if ($layerArr['data'][0]['theme_column']) echo "EXPRESSION \"" . $class['expression'] . "\"\n";
                            else echo "EXPRESSION (" . $class['expression'] . ")\n";
                        } elseif ((!$class['expression']) AND ($layerArr['data'][0]['theme_column'])) echo "EXPRESSION ''\n";
                        ?>

                        #MAXSCALEDENOM
                        <?php if ($class['class_maxscaledenom']) echo "MAXSCALEDENOM {$class['class_maxscaledenom']}\n"; ?>

                        #MINSCALEDENOM
                        <?php if ($class['class_minscaledenom']) echo "MINSCALEDENOM {$class['class_minscaledenom']}\n"; ?>

                        STYLE
                        #SYMBOL
                        <?php if ($class['symbol']) echo "SYMBOL '" . $class['symbol'] . "'\n"; ?>

                        #PATTERN
                        <?php if ($class['pattern']) echo "PATTERN " . $class['pattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if ($class['linecap']) echo "LINECAP " . $class['linecap'] . "\n"; ?>

                        #WIDTH
                        <?php if ($class['width']) echo "WIDTH " . $class['width'] . "\n"; ?>

                        #COLOR
                        <?php if ($class['color']) echo "COLOR " . Util::hex2RGB($class['color'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if ($class['outlinecolor']) echo "OUTLINECOLOR " . Util::hex2RGB($class['outlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if ($class['style_opacity']) echo "OPACITY " . $class['style_opacity'] . "\n"; ?>

                        #SIZE
                        <?php
                        if ($class['size']) {
                            if (is_numeric($class['size']))
                                echo "SIZE " . $class['size'];
                            else
                                echo "SIZE [{$class['size']}]";
                        }
                        echo "\n";
                        ?>

                        #ANGLE
                        <?php
                        if ($class['angle']) {
                            if (is_numeric($class['angle']))
                                echo "ANGLE " . $class['angle'];
                            else
                                echo "ANGLE [{$class['angle']}]";
                        }
                        echo "\n";
                        ?>

                        END # style

                        STYLE
                        #SYMBOL
                        <?php if ($class['overlaysymbol']) echo "SYMBOL '" . $class['overlaysymbol'] . "'\n"; ?>

                        #PATTERN
                        <?php if ($class['overlaypattern']) echo "PATTERN " . $class['overlaypattern'] . " END\n"; ?>

                        #LINECAP
                        <?php if ($class['overlaylinecap']) echo "LINECAP " . $class['overlaylinecap'] . "\n"; ?>

                        #WIDTH
                        <?php if ($class['overlaywidth']) echo "WIDTH " . $class['overlaywidth'] . "\n"; ?>

                        #COLOR
                        <?php if ($class['overlaycolor']) echo "COLOR " . Util::hex2RGB($class['overlaycolor'], true, " ") . "\n"; ?>

                        #OUTLINECOLOR
                        <?php if ($class['overlayoutlinecolor']) echo "OUTLINECOLOR " . Util::hex2RGB($class['overlayoutlinecolor'], true, " ") . "\n"; ?>

                        #OPACITY
                        <?php if ($class['overlaystyle_opacity']) echo "OPACITY " . $class['overlaystyle_opacity'] . "\n"; ?>
                        #SIZE
                        <?php
                        if ($class['overlaysize']) {
                            if (is_numeric($class['overlaysize']))
                                echo "SIZE " . $class['overlaysize'];
                            else
                                echo "SIZE [{$class['overlaysize']}]";
                        }
                        echo "\n";
                        ?>
                        #ANGLE
                        <?php
                        if ($class['overlayangle']) {
                            if (is_numeric($class['overlayangle']))
                                echo "ANGLE " . $class['overlayangle'];
                            else
                                echo "ANGLE [{$class['overlayangle']}]";
                        }
                        echo "\n";
                        ?>

                        END # style

                        #TEMPLATE "ttt"

                        #LABEL
                        <?php if ($class['label']) { ?>
                            LABEL
                            <?php if ($class['label_text']) echo "TEXT '" . $class['label_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label_font'] ?: "arial") . ($class['label_fontweight'] ?: "normal") ?>
                            SIZE <?php
                            if ($class['label_size']) {
                                if (is_numeric($class['label_size']))
                                    echo $class['label_size'];
                                else
                                    echo "[{$class['label_size']}]";
                            } else {
                                echo "11";
                            }
                            echo "\n";
                            ?>
                            COLOR <?php echo ($class['label_color']) ? Util::hex2RGB($class['label_color'], true, " ") : "1 1 1";
                            echo "\n"; ?>
                            OUTLINECOLOR <?php echo ($class['label_outlinecolor']) ? Util::hex2RGB($class['label_outlinecolor'], true, " ") : "255 255 255";
                            echo "\n"; ?>
                            SHADOWSIZE 2 2
                            ANTIALIAS true
                            FORCE <?php echo ($class['label_force']) ? "true" : "false";
                            echo "\n"; ?>
                            POSITION <?php echo ($class['label_position']) ?: "auto";
                            echo "\n"; ?>
                            PARTIALS false
                            MINSIZE 1
                            <?php if ($class['label_maxscaledenom']) echo "MAXSCALEDENOM {$class['label_maxscaledenom']}\n"; ?>
                            <?php if ($class['label_minscaledenom']) echo "MINSCALEDENOM {$class['label_minscaledenom']}\n"; ?>
                            <?php if ($class['label_buffer']) echo "BUFFER {$class['label_buffer']}\n"; ?>
                            <?php if ($class['label_repeatdistance']) echo "REPEATDISTANCE {$class['label_repeatdistance']}\n"; ?>

                            <?php if ($class['label_expression']) {
                                echo "EXPRESSION (" . $class['label_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if ($class['label_angle']) {
                                if (is_numeric($class['label_angle']) OR $class['label_angle'] == 'auto' or $class['label_angle'] == 'auto2'
                                    or $class['label_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label_angle'];
                                else
                                    echo "ANGLE [{$class['label_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"
                            OFFSET <?php echo ($class['label_offsetx']) ?: "0"; ?> <?php echo ($class['label_offsety']) ?: "0"; ?>
                            STYLE
                            <?php if ($class['label_backgroundcolor']) {
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
                            END #Label
                        <?php } ?>
                        #LABEL2
                        <?php if ($class['label2']) { ?>
                            LABEL
                            <?php if ($class['label2_text']) echo "TEXT '" . $class['label2_text'] . "'\n"; ?>
                            TYPE truetype
                            FONT <?php echo ($class['label2_font'] ?: "arial") . ($class['label2_fontweight'] ?: "normal") ?>
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
                            COLOR <?php echo ($class['label2_color']) ? Util::hex2RGB($class['label2_color'], true, " ") : "1 1 1";
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
                            MINSIZE 6
                            <?php if ($class['label2_maxscaledenom']) echo "MAXSCALEDENOM {$class['label2_maxscaledenom']}\n"; ?>
                            <?php if ($class['label2_minscaledenom']) echo "MINSCALEDENOM {$class['label2_minscaledenom']}\n"; ?>
                            <?php if ($class['label2_buffer']) echo "BUFFER {$class['label2_buffer']}\n"; ?>
                            <?php if ($class['label2_repeatdistance']) echo "REPEATDISTANCE {$class['label2_repeatdistance']}\n"; ?>

                            <?php if ($class['label2_expression']) {
                                echo "EXPRESSION (" . $class['label2_expression'] . ")\n";
                            }
                            ?>
                            #ANGLE
                            <?php
                            if ($class['label2_angle']) {
                                if (is_numeric($class['label2_angle']) OR $class['label2_angle'] == 'auto' or $class['label2_angle'] == 'auto2'
                                    or $class['label2_angle'] == 'follow'
                                )
                                    echo "ANGLE " . $class['label2_angle'];
                                else
                                    echo "ANGLE [{$class['label2_angle']}]";
                            }
                            echo "\n";
                            ?>
                            WRAP "\n"
                            OFFSET <?php echo ($class['label2_offsetx']) ?: "0"; ?> <?php echo ($class['label2_offsety']) ?: "0"; ?>
                            STYLE
                            <?php if ($class['label2_backgroundcolor']) {
                                $labelBackgroundColor = Util::hex2RGB($class['label2_backgroundcolor'], true, " ");
                                echo
                                    "GEOMTRANSFORM 'labelpoly'\n" .
                                    "COLOR {$labelBackgroundColor}\n";

                                if ($class['label2_backgroundpadding']) {
                                    echo
                                        "OUTLINECOLOR {$labelBackgroundColor}\n" .
                                        "WIDTH {$class['label2_backgroundpadding']}\n";
                                }
                            }
                            ?>
                            END # STYLE
                            END #Label
                        <?php } ?>

                        <?php if ($class['leader']) { ?>
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
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . ".map";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Mapfile written", "ch" => $path . $name);
    }
}
