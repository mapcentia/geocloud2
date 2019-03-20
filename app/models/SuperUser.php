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
use \app\models\Database;
use \app\models\Setting;
use \app\models\interfaces\UserInterface;

define('VDAEMON_PARSE', false);
define('VD_E_POST_SECURITY', false);
require(__DIR__ . '/../../public/user/vdaemon/vdaemon.php');

/**
 * Class User
 * @package app\models
 */
class SuperUser extends Model implements UserInterface
{
    function __construct()
    {
        parent::__construct();
        $this->postgisdb = "mapcentia";
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return array();
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return array();
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
        $zone = (empty($data['zone']) ? null : VDFormat($data['zone'], true));

        // Generate user identifier from the name
        $userId = Model::toAscii($name, NULL, "_");

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
        $db = new Database();
        $db->postgisdb = "mapcentia";
        $dbObj = $db->createdb($userId, App::$param['databaseTemplate'], "UTF8");
        if ($dbObj !== true) {
            die("Unable to create database for user identifier $userId");
        }

        // Save new user
        $res = $this->prepare("INSERT INTO users (screenname, pw, email, zone) VALUES(:userId, :password, :email, :zone) RETURNING created");
        $res->execute(array(":userId" => $userId, ":password" => $encryptedPassword, ":email" => $email, ":zone" => $zone));
        $row = $res->fetch();
        if ($row['created']) {
            return array(
                'success' => true,
                'message' => 'User was created',
                'userId' => $userId
            );
        } else {
            die("Failed to store new user in the database");
        }
    }

    /**
     * @param array $data
     * @return array
     */
    public function updateUser(array $data): array
    {
        return array();
    }

    /**
     * @param string $data
     * @return array
     */
    public function deleteUser(string $data): array
    {
        return array();
    }
}