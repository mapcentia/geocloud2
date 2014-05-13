<?php

namespace app\models;

use app\inc\Model;

class Setting extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function getArray()
    {
        if (\app\conf\App::$param["encryptSettings"]) {
            $secretKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/secret.key");
            $sql = "SELECT pgp_pub_decrypt(settings.viewer.viewer::bytea, dearmor('{$secretKey}')) as viewer FROM settings.viewer";
        } else {
            $sql = "SELECT viewer FROM settings.viewer";
        }
        $res = $this->execQuery($sql);
        // Hack. Fall back to unencrypted if error. Preventing fail if changing from unencrypted to encrypted.
        if ($this->PDOerror[0]){
            $this->PDOerror = null;
            $sql = "SELECT viewer FROM settings.viewer";
            $res = $this->execQuery($sql);
        }
        $arr = $this->fetchRow($res, "assoc");
        return (array)json_decode($arr['viewer']);
    }

    public function updateApiKey()
    {
        $apiKey = md5(microtime() . rand());
        $arr = $this->getArray();
        $arr['api_key'] = $apiKey;
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
        $arr['pw'] = $this->encryptPw($pw);
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
    public function updateExtent($extent){
        $arr = $this->getArray();

        $obj = (array)$arr['extents'];
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->extent;
        $arr['extents'] = $obj;

        $obj = (array)$arr['center'];
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->center;
        $arr['center'] = $obj;

        $obj = (array)$arr['zoom'];
        $obj[\app\conf\Connection::$param['postgisschema']] = $extent->zoom;
        $arr['zoom'] = $obj;

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

    public function get($unsetPw = false)
    {
        $arr = $this->getArray();
        if ($unsetPw) {
            unset($arr['pw']);
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

        unset($arr['pw']);
        unset($arr['api_key']);

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

    public function encryptPw($pass)
    {
        $pass = strip_tags($pass);
        $pass = str_replace(" ", "", $pass); //remove spaces from password
        $pass = str_replace("%20", "", $pass); //remove escaped spaces from password
        $pass = addslashes($pass); //remove spaces from password
        $pass = md5($pass); //encrypt password
        return $pass;
    }
}
