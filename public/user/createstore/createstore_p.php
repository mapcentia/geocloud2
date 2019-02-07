<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

use \app\inc\Model;
use \app\conf\App;

include("../header.php");
\app\models\Database::setDb("mapcentia");
if (!$_SESSION['screen_name']) {
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
    die();
} else {
    $name = Model::toAscii($_SESSION['screen_name'], NULL, "_");
    $db = new \app\models\Database;
    $dbObj = $db->createdb($name, App::$param['databaseTemplate'], "UTF8");

    // databaseTemplate is set in conf/main.php
    if ($dbObj) {
        header("location: " . \app\conf\App::$param['userHostName'] . "/user/login");
        echo "<div><a href='" . \app\conf\App::$param['userHostName'] . "/user/login'>Hmmm... Redirect didn't work. Use this link</a></div>";
    } else {
        echo "<h2>Sorry, something went wrong. Try again</h2>";
        echo "<div><a href='" . \app\conf\App::$param['userHostName'] . "/user/signup' class='btn btn-danger'>Go back</a></div>";
    }
}
