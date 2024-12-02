<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


use app\models\Setting;
use PDOException;

final class BasicAuth
{
    private string $user;
    private bool $isSession;
    private bool $isSubuser = false;

    function __construct()
    {
        $this->isSession = Session::isAuth();
        if ($this->isSession) {
            $this->user = Session::getUser();
            $this->isSubuser = Session::isSubUser();
        }
    }

    /**
     * Authenticates a user based on their credentials and sets session information if successful.
     * The method also evaluates user privileges for accessing certain layers.
     *
     * @param string $layerName The name of the layer to be accessed. It is used to determine the schema and check user privileges.
     * @param bool $isTransaction
     * @return void
     */
    public function authenticate(string $layerName, bool $isTransaction): void
    {
        $setting = new Setting();
        $settings = $setting->get();
        if (!$this->isSession || $_SESSION['parentdb'] != $setting->postgisdb) {
            if (!empty(Input::getAuthUser())) {
                $this->user = Input::getAuthUser();
                $password = Input::getAuthPw();
                $userGroup = !empty($settings["data"]->userGroups->{$this->user}) ? $settings["data"]->userGroups->{$this->user} : null;
            }

            if (!empty($this->user) && !empty($password)) {
                $this->isSubuser = $this->user != $setting->postgisdb;
                $passwordCheck = !$this->isSubuser ? $settings["data"]->pw : $settings["data"]->pw_subuser->{$this->user};
            }
            if (empty($this->user) || (isset($password) && isset($passwordCheck) && Setting::encryptPw($password) != $passwordCheck)) {
                self::setAuthHeader($setting->postgisdb);
            }
        }
        // AUTHENTICATION SUCCESSFUL
        $schema = explode('.', $layerName)[0];
        if ($this->isSubuser && $this->user != $schema) {
            $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_ LIKE :schema";
            $postgisObject = new Model();
            $res = $postgisObject->prepare($sql);
            try {
                //die($schema);
                $res->execute(array("schema" => $layerName . ".%"));
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                makeExceptionReport($response);
            }
            while ($row = $postgisObject->fetchRow($res)) {
                $privileges = json_decode($row["privileges"]);
                $prop = $userGroup ?: $this->user;
                if ((!$privileges->$prop || $privileges->$prop == "none" || ($privileges->$prop == "read" && $isTransaction)) && ($prop != $schema)) {
                    self::makeExceptionReport(array("You don't have privileges to this layer. Please contact the database owner, which can grant you privileges."));
                }
            }
        }
    }

    private static function setAuthHeader(string $realm): never
    {
        header("WWW-Authenticate: Basic realm=\"$realm\"");
        header('HTTP/1.0 401 Unauthorized');
        header("Cache-Control: no-cache, must-revalidate");
        // Date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        // Text to send if user hits Cancel button
        die("Attempt to login using Basic Auth was cancelled");
    }

    public static function makeExceptionReport(string|array $value): never
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
        exit();
    }
}
