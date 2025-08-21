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
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;
use stdClass;


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
     * @throws InvalidArgumentException
     */
    private function clearCacheOnSchemaChanges(): void
    {
        $patterns = [
            $this->postgisdb . '_settings_*',
        ];
        Cache::deleteByPatterns($patterns);
    }

    /**
     * @return stdClass
     * @throws PDOException
     */
    public function getArray(): stdClass
    {
        $cacheType = "settings";
        $cacheId = $this->postgisdb . "_" . $cacheType . "_viewer";
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            if (App::$param["encryptSettings"]) {
                $secretKey = file_get_contents(App::$param["path"] . "app/conf/secret.key");
                $sql = "SELECT pgp_pub_decrypt(settings.viewer.viewer::BYTEA, dearmor('$secretKey')) AS viewer FROM settings.viewer";
            } else {
                $sql = "SELECT viewer FROM settings.viewer";
            }
            try {
                $res = $this->execQuery($sql);
            } catch (PDOException) {
                // Hack. Fall back to unencrypted if error. Preventing fail if changing from unencrypted to encrypted.
                $sql = "SELECT viewer FROM settings.viewer";
                $res = $this->execQuery($sql);
            }
            $arr = $this->fetchRow($res);
            $response = json_decode($arr['viewer']) ?? new stdClass();
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * Updates the API key for a specified user and stores it in the database.
     * Differentiates between superuser and subuser to appropriately assign the API key.
     *
     * @param string $userName The username of the user for whom the API key is being updated.
     * @param bool $isSuperuser Indicates whether the user is a superuser or a subuser.
     *
     * @return string Returns the generated API key.
     *
     * @throws PDOException|InvalidArgumentException If an error occurs during the database update process.
     */
    public function updateApiKeyForUser(string $userName, bool $isSuperuser): string
    {
        $apiKey = md5(microtime() . rand());
        $arr = $this->getArray();
        if ($isSuperuser) {
            $arr->api_key = $apiKey;
        } else {
            if (!isset($arr->api_key_subuser)) {
                $arr->api_key_subuser = new stdClass();
            }
            $arr->api_key_subuser->{$userName} = $apiKey;
        }
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $this->clearCacheOnSchemaChanges();
        return $apiKey;
    }

    /**
     * Updates the API key for the current user session.
     *
     * @return array An associative array containing the status of the update, a success message, and the updated API key.
     * @throws InvalidArgumentException
     */
    public function updateApiKey(): array
    {
        $response['success'] = true;
        $response['message'] = "API key updated";
        $response['key'] = $this->updateApiKeyForUser($_SESSION["screen_name"], !$_SESSION["subuser"]);
        return $response;
    }

    /**
     * @param string $pw
     * @return array
     * @throws InvalidArgumentException
     */
    public function updatePw(string $pw): array
    {
        $arr = $this->getArray();
        if (!$_SESSION["subuser"]) {
            $arr->pw = $this->encryptPw($pw);
        } elseif (!empty($_SESSION["screen_name"])) {
            if (!isset($arr->pw_subuser)) {
                $arr->pw_subuser = new stdClass();
            }
            $arr->pw_subuser->{$_SESSION["screen_name"]} = $this->encryptPw($pw);
        }
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $response['success'] = true;
        $response['message'] = "Password saved";
        $this->clearCacheOnSchemaChanges();
        return $response;
    }

    /**
     * @param object $extent
     * @return array
     * @throws InvalidArgumentException
     */
    public function updateExtent(object $extent): array
    {
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
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $this->clearCacheOnSchemaChanges();
        $response['success'] = true;
        $response['message'] = "Extent saved";
        return $response;
    }

    /**
     * @param object $extentrestrict
     * @return array
     * @throws InvalidArgumentException
     */
    public function updateExtentRestrict(object $extentrestrict): array
    {
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
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $this->clearCacheOnSchemaChanges();
        $response['success'] = true;
        $response['message'] = ($extentrestrict->extent) ? "Extent locked" : "Extent unlocked";
        return $response;
    }

    /**
     * @param object $userGroup
     * @return array
     * @throws InvalidArgumentException
     */
    public function updateUserGroups(object $userGroup): array
    {
        $response = [];
        $arr = $this->getArray();
        $obj = $arr->userGroups;
        foreach ((array)$userGroup as $key => $value) {
            if ($value === "") {
                unset($obj->$key);
            } else {
                $obj->$key = $value;
            }
        }
        $arr->userGroups = $obj;
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $this->clearCacheOnSchemaChanges();
        $response['success'] = true;
        $response['message'] = "Usergroups updated";
        return $response;
    }

    /**
     * @param bool $unsetPw
     * @return array
     * @throws InvalidArgumentException
     */
    public function get(bool $unsetPw = false): array
    {
        $cacheType = "settings";
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . ($_SESSION["screen_name"] ?? ""); // Cache per user because personal API key is stored
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
        } else {
            $arr = $this->getArray();
            if (!empty($_SESSION["subuser"])) {
                $arr->pw = $arr->pw_subuser->{$_SESSION["screen_name"]} ?? null;
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
            $response['success'] = true;
            $response['data'] = $arr;

            // Get userGroups from mapcentia database
            Database::setDb("mapcentia");
            $users = new Model();
            $sQuery = "SELECT * FROM users WHERE parentdb = :parentDb";
            $users->connect();
            $res = $users->prepare($sQuery);
            $this->execute($res, [":parentDb" => $this->postgisdb]);
            $rows = $this->fetchAll($res);
            $userGroups = [];
            foreach ($rows as $row) {
                $userGroups[$row["screenname"]] = $row["usergroup"];
            }
            $response["data"]->userGroups = (object)$userGroups;
            Database::setDb($this->postgisdb);
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            Cache::save($CachedString);
            $response["cache"]["hit"] = false;
        }
        return $response;
    }

    /**
     * @return array
     */
    public function getForPublic(): array
    {
        $arr = $this->getArray();
        unset($arr->pw);
        unset($arr->pw_subuser);
        unset($arr->api_key);
        unset($arr->api_key_subuser);
        $response['success'] = true;
        $response['data'] = $arr;
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
    public static function encryptPwSecure(string $password): false|string|null
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
        //encrypt password
        return md5($pass);
    }
}
