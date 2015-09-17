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
    <cache name="disk" type="disk">
        <base><?php echo App::$param['path'] . "app/tmp/" . Connection::$param['postgisdb']; ?></base>
        <symlink_blank/>
    </cache>
    <?php

    $sql = "SELECT * FROM settings.geometry_columns_view ORDER BY sort_id";
    $result = $postgisObject->execQuery($sql);
    if ($postgisObject->PDOerror) {
        makeExceptionReport($postgisObject->PDOerror);
    }
    while ($row = $postgisObject->fetchRow($result)) {
        if ($row['f_table_schema'] != "sqlapi") {
            ?>
        <source name="<?php echo $row["f_table_schema"] . "." . $row["f_table_name"] ?>" type="wms">
              <getmap>
                     <params>
                            <FORMAT>image/png</FORMAT>
                            <LAYERS><?php echo $row["f_table_schema"] . "." . $row["f_table_name"] ?></LAYERS>
                     </params>
              </getmap>
              <http>
                    <url><?php echo App::$param["mapCache"]["MapCacheWmsHost"] ?>/ows/<?php echo $_SESSION["screen_name"] ?>/<?php echo $row["f_table_schema"] ?>/</url>
              </http>
        </source>
        <tileset name="<?php echo $row["f_table_schema"] . "." . $row["f_table_name"] ?>">
            <source><?php echo $row["f_table_schema"] . "." . $row["f_table_name"] ?></source>
            <cache>disk</cache>
            <grid>g</grid>
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
        return array("success" => true, "message" => "MapCache file written");
    }
}
