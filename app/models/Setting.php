<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\models;

use app\inc\Model;

class Setting extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     * @throws \PDOException;
     */
    public function getArray()
    {
        if (\app\conf\App::$param["encryptSettings"]) {
            $secretKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/secret.key");
            $sql = "SELECT pgp_pub_decrypt(settings.viewer.viewer::BYTEA, dearmor('{$secretKey}')) AS viewer FROM settings.viewer";
        } else {
            $sql = "SELECT viewer FROM settings.viewer";
        }

        try {
            $res = $this->execQuery($sql);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
        }

        // Hack. Fall back to unencrypted if error. Preventing fail if changing from unencrypted to encrypted.
        if ($this->PDOerror[0]) {
            $this->PDOerror = null;
            $sql = "SELECT viewer FROM settings.viewer";
            $res = $this->execQuery($sql);
        }

        $arr = $this->fetchRow($res, "assoc");
        return json_decode($arr['viewer']);
    }

    public function updateApiKey()
    {
        $apiKey = md5(microtime() . rand());
        $arr = $this->getArray();
        if (!$_SESSION["subuser"]) {
            $arr->api_key = $apiKey;
        } else {
            $arr->api_key_subuser->{$_SESSION["screen_name"]} = $apiKey;
        }
        if (\app\conf\App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "API key updated";
            $response['key'] = $apiKey;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    public function updatePw($pw)
    {
        $arr = $this->getArray();

        if (!$_SESSION["subuser"]) {
            $arr->pw = $this->encryptPw($pw);
        } else {
            $arr->pw_subuser->{$_SESSION["screen_name"]} = $this->encryptPw($pw);
        }
        if (\app\conf\App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Password saved";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    public function updateExtent($extent)
    {
        $arr = $this->getArray();

        $obj = (array)$arr->extents;
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->extent;
        $arr->extents = $obj;

        $obj = (array)$arr->center;
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->center;
        $arr->center = $obj;

        $obj = (array)$arr->zoom;
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->zoom;
        $arr->zoom = $obj;

        if (\app\conf\App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Extent saved";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    /**
     * @param $extentrestrict
     * @return array
     */
    public function updateExtentRestrict($extentrestrict) : array
    {
        $response = [];

        try {
            $arr = $this->getArray();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $obj = (array)$arr->extentrestricts;
        $obj[\app\conf\Connection::$param['postgisschema']] = $extentrestrict->extent;
        $arr->extentrestricts = $obj;

        $obj = (array)$arr->zoomrestricts;
        $obj[\app\conf\Connection::$param['postgisschema']] = $extentrestrict->zoom;
        $arr->zoomrestricts = $obj;

        if (\app\conf\App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = ($extentrestrict->extent) ? "Extent locked" : "Extent unlocked";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    /**
     * @param $userGroup
     * @return array
     */
    public function updateUserGroups($userGroup) : array
    {
        $response = [];

        try {
            $arr = $this->getArray();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $obj = $arr->userGroups;
        foreach ((array)$userGroup as $key => $value) {
            $obj->$key = $value;
        }
        $arr->userGroups = $obj;
        if (\app\conf\App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Usergroups updated";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    public function get($unsetPw = false)
    {
        try {
            $arr = $this->getArray();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;

        }

        if (!empty($_SESSION["subuser"])) {
            $arr->pw = isset($arr->pw_subuser) ? $arr->pw_subuser->{$_SESSION["screen_name"]} : null;
            $arr->api_key = isset($arr->api_key_subuser) ? $arr->api_key_subuser->{$_SESSION["screen_name"]} : null;
            if (isset($arr->pw_subuser)) unset($arr->pw_subuser);
        }
        // If user has no key, we generate one.
        if (!$arr->api_key) {
            $res = $this->updateApiKey();
            $arr->api_key = $res['key'];
        }
        if ($unsetPw) {
            unset($arr->pw);
        }
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }

        return $response;
    }

    public function getForPublic()
    {
        $arr = $this->getArray();

        unset($arr->pw);
        unset($arr->pw_subuser);
        unset($arr->api_key);
        unset($arr->api_key_subuser);

        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    /**
     * Password is required to have
     * - at least one capital letter
     * - at least one digit
     * - be longer than 7 characters
     * 
     * @return array
     */
    public static function checkPasswordStrength($password)
    {
        $validationErrors = [];

        if (strlen($password) < 8) {
            $validationErrors[] = "has to be at least 8 characters long";
        }

        if (!preg_match("#[0-9]+#", $password)) {
            $validationErrors[] = "must include at least one number";
        }
    
        if (!preg_match("#[A-Z]+#", $password)) {
            $validationErrors[] = "must include at least one capital letter";
        }

        return $validationErrors;
    }

    /**
     * Encrypts password
     */
    public static function encryptPwSecure($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function encryptPw($pass)
    {
        $pass = strip_tags($pass);
        $pass = str_replace(" ", "", $pass); //remove spaces from password
        $pass = str_replace("%20", "", $pass); //remove escaped spaces from password
        $pass = addslashes($pass); //remove spaces from password
        $pass = md5($pass); //encrypt password
        return $pass;
    }
}
