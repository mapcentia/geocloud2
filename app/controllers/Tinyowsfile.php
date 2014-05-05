<?php
namespace app\controllers;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;

class Tinyowsfile extends \app\inc\Controller
{
    function get_index()
    {
        $postgisObject = new Model();
        ob_start();?>

    <tinyows online_resource="http://<?php echo $_SERVER['HTTP_HOST']; ?>/cgi/tinyows.cgi"
             schema_dir="/usr/tinyows/schema/">

        <pg user="<?php echo Connection::$param['postgisuser']; ?>"
            dbname="<?php echo Connection::$param['postgisdb']; ?>" <?php if (Connection::$param['postgishost']) echo " host=\"" . Connection::$param['postgishost'] . "\""; ?><?php if (Connection::$param['postgisport']) echo " port=\"" . Connection::$param['postgisport'] . "\""; ?><?php if (Connection::$param['postgispw']) echo " password=\"" . Connection::$param['postgispw'] . "\""; ?>/>

        <metadata name="TinyOWS Server"
                  title="TinyOWS Server - Demo Service"/>

        <?php

        $sql = "SELECT * FROM settings.geometry_columns_view WHERE f_table_schema='" . Connection::$param['postgisschema'] . "'";
        $result = $postgisObject->execQuery($sql);
        if ($postgisObject->PDOerror) {
            makeExceptionReport($postgisObject->PDOerror);
        }
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['f_table_schema'] != "sqlapi") {
                ?>
                <layer retrievable="1"
                       writable="1"
                       ns_prefix="<?php echo $_SESSION["screen_name"] ?>"
                       ns_uri="http://www.twitter.com"
                       name="<?php echo $row["f_table_name"] ?>"
                       schema="<?php echo $row["f_table_schema"] ?>"
                       srid="4326,3857,900913"
                       title="<?php echo ($row['f_table_title']) ? : $row['f_table_name'] ?>"/>
            <?php
            }
        }
        echo "</tinyows>";
        $data = ob_get_clean();
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . ".xml";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $data);
        fclose($fh);
        return array("success" => true, "message" => "Tinyows file written");
    }
}
