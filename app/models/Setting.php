<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Cache;
use app\inc\Globals;
use app\inc\Model;
use Error;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;


/**
 * Class Setting
 * @package app\models
 */
class Setting extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     *
     */
    private function clearCacheOnSchemaChanges(): void
    {
        // We clear all cache, because it can take long time to clear by tag
        Cache::clear();
    }

    /**
     * @return object
     */
    public function getArray(): object
    {
        if (App::$param["encryptSettings"]) {
            $secretKey = file_get_contents(App::$param["path"] . "app/conf/secret.key");
            $sql = "SELECT pgp_pub_decrypt(settings.viewer.viewer::BYTEA, dearmor('{$secretKey}')) AS viewer FROM settings.viewer";
        } else {
            $sql = "SELECT viewer FROM settings.viewer";
        }

        try {
            $res = $this->execQuery($sql);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }

        // Hack. Fall back to unencrypted if error. Preventing fail if changing from unencrypted to encrypted.
        if (isset($this->PDOerror[0])) {
            $this->PDOerror = null;
            $sql = "SELECT viewer FROM settings.viewer";
            $res = $this->execQuery($sql);
        }

        $arr = $this->fetchRow($res);
        return json_decode($arr['viewer']);
    }

    /**
     * @return array<mixed>
     */
    public function updateApiKey(): array
    {
        $this->clearCacheOnSchemaChanges();
        $apiKey = md5(microtime() . rand());
        $arr = $this->getArray();
        if (!$_SESSION["subuser"]) {
            $arr->api_key = $apiKey;
        } else {
            $arr->api_key_subuser->{$_SESSION["screen_name"]} = $apiKey;
        }
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('{$pubKey}'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        if (!isset($this->PDOerror[0])) {
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

    /**
     * @param string $pw
     * @return array<mixed>
     */
    public function updatePw(string $pw): array
    {
        $this->clearCacheOnSchemaChanges();
        $arr = $this->getArray();
        if (!$_SESSION["subuser"]) {
            $arr->pw = $this->encryptPw($pw);
        } else {
            $arr->pw_subuser->{$_SESSION["screen_name"]} = $this->encryptPw($pw);
        }
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
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

    /**
     * @param object $extent
     * @return array<mixed>
     */
    public function updateExtent(object $extent): array
    {
        $this->clearCacheOnSchemaChanges();
        $arr = $this->getArray();
        $obj = (array)$arr->extents;
        $obj[Connection::$param['postgisschema']] = $extent->extent;
        $arr->extents = $obj;

        $obj = (array)$arr->center;
        $obj[Connection::$param['postgisschema']] = $extent->center;
        $arr->center = $obj;

        $obj = (array)$arr->zoom;
        $obj[Connection::$param['postgisschema']] = $extent->zoom;
        $arr->zoom = $obj;

        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
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
     * @param object $extentrestrict
     * @return array<mixed>
     */
    public function updateExtentRestrict(object $extentrestrict): array
    {
        $this->clearCacheOnSchemaChanges();
        $response = [];
        try {
            $arr = $this->getArray();
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $obj = (array)$arr->extentrestricts;
        $obj[Connection::$param['postgisschema']] = $extentrestrict->extent;
        $arr->extentrestricts = $obj;

        $obj = (array)$arr->zoomrestricts;
        $obj[Connection::$param['postgisschema']] = $extentrestrict->zoom;
        $arr->zoomrestricts = $obj;

        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
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
     * @param object $userGroup
     * @return array<mixed>
     */
    public function updateUserGroups(object $userGroup): array
    {
        $this->clearCacheOnSchemaChanges();
        $response = [];
        try {
            $arr = $this->getArray();
        } catch (PDOException $e) {
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
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
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

    /**
     * @param bool $unsetPw
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get(bool $unsetPw = false): array
    {
        $cacheType = "settings";
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $_SESSION["screen_name"];

        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            $response = $CachedString->get();
            try {
                $response["cache"]["hit"] = $CachedString->getCreationDate();
                $response["cache"]["tags"] = $CachedString->getTags();
            } catch (PhpfastcacheLogicException $exception) {
                $response["cache"] = $exception->getMessage();
            }
            $response["cache"]["signature"] = md5(serialize($response));
            return $response;
        } else {
            try {
                $arr = $this->getArray();
            } catch (PDOException $e) {
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
            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $this->postgisdb]);

            } catch (Error $exception) {
                die($exception->getMessage());
            }
            Cache::save($CachedString);
            $response["cache"]["hit"] = false;

            return $response;
        }
    }

    /**
     * @return array<mixed>
     */
    public function getForPublic(): array
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
     * @return string|null
     */
    public function getApiKeyForSuperUser(): ?string
    {
        return $this->getArray()->api_key;
    }

    /**
     * Password is required to have
     * - at least one capital letter
     * - at least one digit
     * - be longer than 7 characters
     *
     * @param string $password
     * @return array<string>
     */
    public static function checkPasswordStrength(string $password): array
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
     * @param string $password
     * @return false|string|null
     */
    public static function encryptPwSecure(string $password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @param string $pass
     * @return string
     */
    public static function encryptPw(string $pass): string
    {
        $pass = strip_tags($pass);
        $pass = str_replace(" ", "", $pass); //remove spaces from password
        $pass = str_replace("%20", "", $pass); //remove escaped spaces from password
        $pass = addslashes($pass); //remove spaces from password
        $pass = md5($pass); //encrypt password
        return $pass;
    }
}
