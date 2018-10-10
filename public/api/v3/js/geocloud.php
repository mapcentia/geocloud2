<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

use \app\conf\App;
use \app\inc\Util;

header('Content-type: application/javascript');
include_once("../../../../app/conf/App.php");
new \app\conf\App();

// Set the host names if they are not set in App.php
App::$param['protocol'] = App::$param['protocol'] ?: Util::protocol();
App::$param['host'] = App::$param['host'] ?: App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];

echo "window.geocloud_host = window.geocloud_host || \"" . \app\conf\App::$param['host'] . "\";\n";
echo "window.geocloud_maplib = \"" . ((isset($_GET["maplib"])) ? $_GET["maplib"] : "leaflet") . "\";\n";
echo "window.geocloud_i18n = " . (isset($_GET["i18n"]) && $_GET["i18n"] == "false" ? "false"  : "true") .";\n";
echo "window.geocloud_loadcss = " . (isset($_GET["loadcss"]) && $_GET["loadcss"] == "false" ? "false"  : "true") .";\n";
if (isset($_GET["callback"])) {
    echo "window.geocloud_callback = \"" . $_GET["callback"] . "\";\n";
    require_once('async_loader.js');
} else {
    require_once('sync_loader.js');
}
