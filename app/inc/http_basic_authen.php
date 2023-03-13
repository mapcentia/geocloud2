<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Input;
use app\models\Setting;

$db = Input::getPath()->part(2);
$dbSplit = explode("@", $db);

if (!function_exists("makeExceptionReport")) {
    /**
     * @param string|array<string> $value
     */
    function makeExceptionReport($value): void
    {
        ob_get_clean();
        ob_start();

        echo '<ServiceExceptionReport
	   version="1.2.0"
	   xmlns="http://www.opengis.net/ogc"
	   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	   xsi:schemaLocation="http://www.opengis.net/ogc http://wfs.plansystem.dk:80/geoserver/schemas//wfs/1.0.0/OGC-exception.xsd">
	   <ServiceException>';
        if (is_array($value)) {
            if (sizeof($value) == 1) {
                print $value[0];
            } else {
                print_r($value);
            }
        } else {
            print $value;
        }
        echo '</ServiceException>
	</ServiceExceptionReport>';
        $data = ob_get_clean();
        echo $data;
        die();
    }
}

$settings = null;

if (sizeof($dbSplit) == 2 || !empty($_SESSION["subuser"])) { // is Sub-user
    $db = $dbSplit[1];
    if (empty($_SESSION["subuser"])) {
        $_SESSION["subuser"] = true; // Coming from outside session
        $subUser = $_SESSION["screen_name"] = $dbSplit[0];
    } else {
        $subUser = $_SESSION["screen_name"];
    }

    $settingsModel = new Setting();
    $settings = $settingsModel->get();
    $userGroup = !empty($settings["data"]->userGroups->$subUser) ? $settings["data"]->userGroups->$subUser : null;

    if ($dbSplit[0] != $postgisschema) {
        $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_ LIKE :schema";
        $res = $postgisObject->prepare($sql);
        try {
            $res->execute(array("schema" => $postgisschema . "." . $HTTP_FORM_VARS["TYPENAME"] . ".%"));
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            makeExceptionReport($response);
        }
        while ($row = $postgisObject->fetchRow($res, "assoc")) {
            $privileges = json_decode($row["privileges"]);
            $prop = $userGroup ?: $subUser;
            if (($privileges->$prop == false || $privileges->$prop == "none") && ($prop != $postgisschema)) {
                makeExceptionReport(array("You don't have privileges to this layer. Please contact the database owner, which can grant you privileges."));
            }
        }
    }
}

if (empty($_SESSION['auth']) || $_SESSION['parentdb'] != $db) {
    if (!$settings) {
        $settingsModel = new Setting();
        $settings = $settingsModel->get();
    }
    // mod_php
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        // most other servers
    } elseif (isset($_SERVER['HTTP_AUTHENTICATION'])) {
        if (strpos(strtolower($_SERVER['HTTP_AUTHENTICATION']), 'basic') === 0)
            list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
    }
    if (empty($username) || $username != Input::getPath()->part(2) || Setting::encryptPw($password) != $settings["data"]->pw) {
        header('WWW-Authenticate: Basic realm="' . Input::getPath()->part(2) . '"');
        header('HTTP/1.0 401 Unauthorized');
        header("Cache-Control: no-cache, must-revalidate");
        // Date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        // Text to send if user hits Cancel button
        die("Attempt to login using Basic Auth was cancelled");
    } else {
        $_SESSION['http_auth'] = $db;
    }
}

