<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use \app\conf\App;
use app\inc\Model;

define('VDAEMON_PARSE', false);
define('VD_E_POST_SECURITY', false);
require(__DIR__ . '/../../public/user/vdaemon/vdaemon.php');

/**
 * Class User
 * @package app\models
 */
class User extends Model
{
    public $userId;

    function __construct($userId = null)
    {
        parent::__construct();
        $this->userId = $userId;
        $this->postgisdb = "mapcentia";
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAll(): array
    {
        $query = "SELECT * FROM users WHERE email<>''";
        $res = $this->execQuery($query);
        $rows = $this->fetchAll($res);
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $rows;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $domain = \app\conf\App::$param['domain'];
        $query = "SELECT screenname as userid, zone, '{$domain}' as host FROM users WHERE screenname = :sUserID";
        $res = $this->prepare($query);
        $res->execute(array(":sUserID" => $this->userId));
        $row = $this->fetchRow($res);
        if (!$row['userid']) {
            $response['success'] = false;
            $response['message'] = "Userid not found";
            //$response['code'] = 406;
            return $response;
        }
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $row;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    /**
     * @param array $data
     * @return array
     */
    public function createUser(array $data): array
    {
        $mandatoryParameters = ['name', 'email', 'password'];   
        foreach ($mandatoryParameters as $item) {
            if (empty($data[$item])) {
                return array(
                    'code' => 400,
                    'success' => false,
                    'message' => "$item has to be provided"
                );
            }
        }

        $name = VDFormat($data['name'], true);
        $email = VDFormat($data['email'], true);
        $password = VDFormat($data['password'], true);
        $group = (empty($data['usergroup']) ? null : VDFormat($data['zone'], true));
        $zone = (empty($data['zone']) ? null : VDFormat($data['zone'], true));

        // Generate user identifier from the name
        $userId = Model::toAscii($name, NULL, "_");

        // @todo In case of subuser, there can be two subusers with same name but belonging to different super users

        // Check if such user identifier already exists
        $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE screenname = '$userId'");
        $result = $this->fetchRow($res);
        if ($result['count'] > 0) {
            return array(
                'code' => 400,
                'success' => false,
                'message' => "User identifier $userId already exists"
            );
        }

        // Check if such email already exists
        $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE email = '$email'");
        $result = $this->fetchRow($res);
        if ($result['count'] > 0) {
            return array(
                'code' => 400,
                'success' => false,
                'message' => "Email $email already exists"
            );
        }

        $passwordCheckResults = Setting::checkPasswordStrength($password);
        if (sizeof($passwordCheckResults) > 0) {
            return array(
                'code' => 400,
                'success' => false,
                'message' => 'Password does not meet following requirements: ' . implode(', ', $passwordCheckResults)
            );
        }

        $encryptedPassword = Setting::encryptPwSecure($password);

        // Create new database
        if ($data['subuser'] === false) {
            $db = new Database();
            $db->postgisdb = $this->postgisdb;
            $dbObj = $db->createdb($userId, App::$param['databaseTemplate'], "UTF8");
            if ($dbObj !== true) {
                die("Unable to create database for user identifier $userId");
            }
        }

        $sQuery = "INSERT INTO users (screenname,pw,email,parentdb,usergroup,zone) VALUES(:sUserID, :sPassword, :sEmail, :sParentDb, :sUsergroup, :zone) RETURNING screenname,parentdb,email,usergroup,zone";

        try {
            $res = $this->prepare($sQuery);
            $res->execute(array(
                ":sUserID" => $userId,
                ":sPassword" => $encryptedPassword,
                ":sEmail" => $email,
                ":sParentDb" => $this->userId,
                ":sUsergroup" => $group,
                ":zone" => $zone
            ));

            $row = $this->fetchRow($res, "assoc");
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = \app\inc\Session::getUser();
            $response['code'] = 400;
            return $response;
        }

        $response['success'] = true;
        $response['message'] = 'User was created';
        $response['data'] = $row;
        return $response;
    }

    /**
     * @param array $data
     * @return array
     */
    public function updateUser(array $data): array
    {
        $user = isset($data["user"]) ? Model::toAscii($data["user"], NULL, "_") : null;

        $password = isset($data["password"]) ? Setting::encryptPw($data["password"]) : null;
        $email = isset($data["email"]) ? $data["email"] : null;
        $userGroup = isset($data["usergroup"]) ? $data["usergroup"] : null;

        $sQuery = "UPDATE users SET screenname=screenname";
        if ($password) $sQuery .= ", pw=:sPassword";
        if ($email) $sQuery .= ", email=:sEmail";
        if ($userGroup) {
            $sQuery .= ", usergroup=:sUsergroup";
            $obj[$user] = $userGroup;

            Database::setDb($this->getData()["data"]["userid"]);
            $settings = new \app\models\Setting();
            if (!$settings->updateUserGroups((object)$obj)['success']) {
                $response['success'] = false;
                $response['message'] = "Could not update settings.";
                $response['code'] = 400;
                return $response;
            };
            Database::setDb("mapcentia");

        }
        $sQuery .= " WHERE screenname=:sUserID RETURNING screenname,email,usergroup";

        try {
            $res = $this->prepare($sQuery);
            if ($password) $res->bindParam(":sPassword", $password);
            if ($email) $res->bindParam(":sEmail", $email);
            if ($userGroup) $res->bindParam(":sUsergroup", $userGroup);
            $res->bindParam(":sUserID", $user);

            $res->execute();
            $row = $this->fetchRow($res, "assoc");
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = \app\inc\Session::getUser();
            $response['code'] = 400;
            return $response;
        }

        $response['success'] = true;
        $response['message'] = "User update";
        $response['data'] = $row;
        return $response;
    }

    /**
     * @param string $data
     * @return array
     */
    public function deleteUser(string $data): array
    {
        $user = $data ? Model::toAscii($data, NULL, "_") : null;
        $sQuery = "DELETE FROM users WHERE screenname=:sUserID";
        try {
            $res = $this->prepare($sQuery);
            $res->execute([":sUserID" => $user]);
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = \app\inc\Session::getUser();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "User deleted";
        $response['data'] = $res->rowCount();
        return $response;
    }
}