<?php
namespace app\controllers;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Util;
use \app\inc\Response;

class Mapfile extends \app\inc\Controller
{
    function get_index()
    {
        $postgisObject = new \app\inc\Model();
        $srs = "4326";
        ob_start();
        ?>
        MAP
        #
        # Start of map file
        #
        NAME "<?php echo Connection::$param['postgisdb']; ?>"
        STATUS on
        EXTENT -180 -90 180 90
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
        "wms_title"    "<?php echo Connection::$param['postgisdb']; ?>'s awesome WMS"
        "wms_srs"    "EPSG:<?php echo $srs; ?> EPSG:4326 EPSG:3857 EPSG:900913"
        "wms_name"    "<?php echo $user; ?>"
        "wms_format"    "image/png"
        "wms_onlineresource"    "http://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/<?php echo Connection::$param['postgisdb']; ?>/<?php echo Connection::$param['postgisschema']; ?>/"
        "ows_enable_request" "*"
        "wms_enable_request" "*"
        END
        END
        #
        # Start of reference map
        #

        PROJECTION
        "init=epsg:<?php echo $srs; ?>"
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
        FONT "arial"
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
        POINTS 1 1 END
        #STYLE 5 5 END
        END

        # --------------------

        SYMBOL
        NAME "dashed-line-long"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 1 1 END
        #STYLE 10 10 END
        END

        # --------------------

        SYMBOL
        NAME "dash-dot"
        TYPE ELLIPSE
        FILLED TRUE
        POINTS 1 1 END
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
        $sql = "SELECT * FROM settings.geometry_columns_view WHERE f_table_schema='" . Connection::$param['postgisschema'] . "'";
        $result = $postgisObject->execQuery($sql);
        if ($postgisObject->PDOerror) {
            makeExceptionReport($postgisObject->PDOerror);
        }
        while ($row = $postgisObject->fetchRow($result)) {
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
            PROCESSING "CLOSE_CONNECTION=DEFER"
            <?php $dataSql = ($row['data']) ? : "SELECT * FROM {$row['f_table_schema']}.{$row['f_table_name']}";
            echo "DATA \"{$row['f_geometry_column']} from ({$dataSql}) as foo  using unique {$primeryKey['attname']} using srid={$row['srid']}\"\n";
            ?>
            <?php if ($row['filter']) { ?>
            FILTER "<?php echo $row['filter']; ?>"
            <?php } ?>
            <?php
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
            }
            if (!$row['wmssource']) {
                ?>
                TYPE <?php echo $type . "\n"; ?>
                CONNECTIONTYPE POSTGIS
                CONNECTION "user=postgres dbname=<?php echo Connection::$param['postgisdb']; ?><?php if (Connection::$param['postgishost']) echo " host=" . Connection::$param['postgishost']; ?><?php if (Connection::$param['postgispw']) echo " password=" . Connection::$param['postgispw']; ?> options='-c client_encoding=UTF8'"
            <?php
            } else {
                ?>
                TYPE RASTER
                CONNECTIONTYPE WMS
                CONNECTION "<?php echo $row['wmssource']; ?>"
            <?php } ?>

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

            #LABELMAXSCALE
            METADATA
            "wms_title"    "<?php if ($row['f_table_title']) echo $row['f_table_title']; else echo $row['f_table_name'] ?>"
            "wms_srs"    "EPSG:<?php echo $row['srid']; ?>"
            "wms_name"    "<?php echo $row['f_table_name']; ?>"
            "wms_abstract"    "<?php echo $row['f_table_abstract']; ?>"
            "wms_format"    "image/png"
            "appformap_group"  "<?php if ($row['layergroup']) echo $row['layergroup']; else echo "Default group" ?>"
            "appformap_queryable"    "true"
            "appformap_loader"    "true"
            "wms_enable_request"    "*"
            "gml_include_items" "all"
            "wms_include_items" "all"
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

                    #TEXT
                    <?php if ($class['label_text']) echo "TEXT '" . $class['label_text'] . "'\n"; ?>

                    #MAXSCALEDENOM
                    <?php if ($class['class_maxscaledenom']) echo "MAXSCALEDENOM {$class['class_maxscaledenom']}\n"; ?>

                    #MINSCALEDENOM
                    <?php if ($class['class_minscaledenom']) echo "MINSCALEDENOM {$class['class_minscaledenom']}\n"; ?>

                    STYLE
                    #SYMBOL
                    <?php if ($class['symbol']) echo "SYMBOL '" . $class['symbol'] . "'\n"; ?>

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
                        TYPE truetype
                        FONT "arialbd"
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
                        POSITION <?php echo ($class['label_position']) ? : "auto";
                        echo "\n"; ?>
                        PARTIALS false
                        MINSIZE 6
                        <?php if ($class['label_maxscaledenom']) echo "MAXSCALEDENOM {$class['label_maxscaledenom']}\n"; ?>
                        <?php if ($class['label_minscaledenom']) echo "MINSCALEDENOM {$class['label_minscaledenom']}\n"; ?>
                        <?php if ($class['label_buffer']) echo "BUFFER {$class['label_buffer']}\n"; ?>
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
        <?php } ?>
        END #MapFile
        <?php
        $data = ob_get_clean();
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . ".map";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Mapfile written");
    }
}
