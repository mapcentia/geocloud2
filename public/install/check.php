<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


use app\inc\Util;
use app\conf\App;

$trusted = false;
if (isset(App::$param["allowedToInstall"])) {
    foreach (App::$param["allowedToInstall"] as $address) {
        if (Util::ipInRange(Util::clientIp(), $address)) {
            $trusted = true;
            break;
        }
    }

    if (!$trusted) {
        header('HTTP/1.0 404 Not Found');
        echo "<h1>404 Not Found</h1>\n";
        echo "<!--" . Util::clientIp() . "-->";
        exit;
    }
}