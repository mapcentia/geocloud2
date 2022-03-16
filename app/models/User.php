<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\inc\Model;
use app\inc\Session;
use app\inc\Util;
use Exception;
use PDOException;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;


/**
 * Class User
 * @package app\models
 */
class User extends Model
{
    /**
     * @var string|null
     */
    public $userId;

    /**
     * @var string|null
     */
    public $parentdb;

    function __construct(?string $userId = null, ?string $parentdb = null)
    {
        parent::__construct();
        $this->userId = $userId;
        $this->parentdb = $parentdb;
        $this->postgisdb = "mapcentia";
    }

    /**
     * @return array<bool|array<mixed>>
     * @throws Exception
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
     * @param string $userIdentifier
     * @return array<string, array<int, array>>
     * @throws Exception
     */
    public function getDatabasesForUser(string $userIdentifier): array
    {
        if (empty($userIdentifier)) {
            throw new Exception('User name or email should not be empty');
        }
        $data = [];
        if (strrpos($userIdentifier, '@') === false) {
            $userName = Model::toAscii($userIdentifier, NULL, "_");
            $query = "SELECT screenname, email, parentdb FROM users WHERE screenname = :sUserID";
            $res = $this->prepare($query);
            $res->execute(array(":sUserID" => $userName));

        } else {
            $query = "SELECT screenname, email, parentdb FROM users WHERE email = :sUserEmail";
            $res = $this->prepare($query);
            $res->execute(array(":sUserEmail" => $userIdentifier));

        }
        while ($row = $this->fetchRow($res)) {
            $data[] = $row;
        }

        return [
            'databases' => $data,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $query = "SELECT email, parentdb, usergroup, screenname as userid, zone FROM users WHERE (screenname = :sUserID OR email = :sUserID) AND (parentdb = :parentDb OR parentDB IS NULL)";
        $res = $this->prepare($query);
        $res->execute(array(":sUserID" => $this->userId, ":parentDb" => $this->parentdb));
        $row = $this->fetchRow($res);
        if (!$row['userid']) {
            $response['success'] = false;
            $response['message'] = "User identifier $this->userId was not found (parent database: " . ($this->parentdb ?: 'null') . ")";
            $response['code'] = 404;
            return $response;
        }
        if (!empty($row['properties'])) {
            $row['properties'] = json_decode($row['properties']);
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
     * @param array<string|bool> $data
     * @return array<mixed>
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

        $name = Util::format($data['name'], true);
        $email = Util::format($data['email'], true);
        $password = Util::format($data['password'], true);
        $group = (empty($data['usergroup']) ? null : Util::format($data['usergroup'], true));
        $zone = (empty($data['zone']) ? null : Util::format($data['zone'], true));
        $parentDb = (empty($data['parentdb']) ? null : Util::format($data['parentdb'], true));
        $properties = (empty($data['properties']) ? null : $data['properties']);
        if ($parentDb) {
            $sql = "SELECT 1 from pg_database WHERE datname=:sDatabase";
            try {
                $res = $this->prepare($sql);
                $res->execute([":sDatabase" => $parentDb]);
                $row = $this->fetchRow($res);
            } catch (Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
            if (!$row) {
                $response['success'] = false;
                $response['message'] = "Database '{$parentDb}' doesn't exist";
                $response['code'] = 400;
                return $response;
            }
        }
        if ($properties) {
            $properties = json_encode($properties);
        }

        // Generate user identifier from the name
        $userId = Model::toAscii($name, NULL, "_");

        // Check if such user identifier already exists
        $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE screenname = '$userId'");
        $result = $this->fetchRow($res);
        if ($result['count'] > 0) {
            if ($data['subuser']) {
                $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE screenname = '" . $userId . "' AND parentdb = '" . $this->userId . "'");
                $result = $this->fetchRow($res);
                if ($result['count'] > 0) {
                    return array(
                        'code' => 400,
                        'success' => false,
                        'errorCode' => 'SUB_USER_ALREADY_EXISTS',
                        'message' => "User identifier $userId already exists for parent database " . $this->userId
                    );
                }
                $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE screenname = '" . $userId . "' AND parentdb ISNULL");
                $result = $this->fetchRow($res);
                if ($result['count'] > 0) {
                    return array(
                        'code' => 400,
                        'success' => false,
                        'errorCode' => 'PARENT_USER_EXISTS_WITH_NAME',
                        'message' => "User identifier $userId already exists as database"
                    );
                }

            } else {
                return array(
                    'code' => 400,
                    'success' => false,
                    'errorCode' => 'USER_ALREADY_EXISTS',
                    'message' => "User identifier $userId already exists"
                );
            }
        }

        // Check if such email already exists in the database - there can not be two super-user with the same email,
        // but there can be tow sub-users with the same email in different databases
        // TODO use parentDb instead of $this->userID if allowUnauthenticatedClientsToCreateSubUsers and no session is started
        if (empty($this->userId)) {
            $sql = "SELECT COUNT(*) AS count FROM users WHERE email = '$email'";
        } else {
            $sql = "SELECT COUNT(*) AS count FROM users WHERE email = '$email' AND parentdb = '" . $this->userId . "'";
        }

        $res = $this->execQuery($sql);
        $result = $this->fetchRow($res);
        if ($result['count'] > 0) {
            return array(
                'code' => 400,
                'success' => false,
                'errorCode' => 'EMAIL_ALREADY_EXISTS',
                'message' => "Email $email already exists"
            );
        }

        // Check if the password is strong enough
        $passwordCheckResults = Setting::checkPasswordStrength($password);
        if (sizeof($passwordCheckResults) > 0) {
            return array(
                'code' => 400,
                'success' => false,
                'errorCode' => 'WEAK_PASSWORD',
                'message' => 'Password does not meet following requirements: ' . implode(', ', $passwordCheckResults)
            );
        }

        $encryptedPassword = Setting::encryptPwSecure($password);

        // Create new database
        if ($data['subuser'] === false) {
            $db = new Database();
            $db->postgisdb = $this->postgisdb;
            try {
                $db->createdb($userId, App::$param['databaseTemplate']);
            } catch (Exception $e) {
                // Clean up
                try {
                    $db->dropUser($userId);
                    $db->dropDatabase($userId);
                } catch (PDOException $e) {
                    // Pass
                }
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['test'] = Session::getUser();
                $response['code'] = 400;
                return $response;
            }
        }

        $sQuery = "INSERT INTO users (screenname,pw,email,parentdb,usergroup,zone,properties) VALUES(:sUserID, :sPassword, :sEmail, :sParentDb, :sUsergroup, :sZone, :sProperties) RETURNING screenname,parentdb,email,usergroup,zone,properties";

        try {
            $res = $this->prepare($sQuery);
            $res->execute(array(
                ":sUserID" => $userId,
                ":sPassword" => $encryptedPassword,
                ":sEmail" => $email,
                ":sParentDb" => $parentDb ?: $this->userId,
                ":sUsergroup" => $group,
                ":sZone" => $zone,
                ":sProperties" => $properties,
            ));

            $row = $this->fetchRow($res);
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = Session::getUser();
            $response['code'] = 400;
            return $response;
        }
        if (isset($group)) {
            $obj[$userId] = $group;
            Database::setDb($this->parentdb);
            $settings = new Setting();
            if (!$settings->updateUserGroups((object)$obj)['success']) {
                $response['success'] = false;
                $response['message'] = "Could not update settings.";
                $response['code'] = 400;
                return $response;
            }
            Database::setDb("mapcentia");
        }

        // Start email notification
        if (!empty(App::$param["signupNotification"])) {
            $client = new PostmarkClient(App::$param["signupNotification"]["key"]);
            $messages = [];
            // For the new user
            $messages[] = [
                'To' => $email,
                'From' => App::$param["signupNotification"]["user"]["from"],
                'TrackOpens' => false,
                'Subject' => App::$param["signupNotification"]["user"]["subject"],
                'HtmlBody' => App::$param["signupNotification"]["user"]["htmlBody"],
            ];
            // Notification of others
            if (!empty(App::$param["signupNotification"]["others"])) {
                $messages[] = [
                    'Bcc' => implode(",", App::$param["signupNotification"]["others"]["bcc"]),
                    'From' => App::$param["signupNotification"]["others"]["from"],
                    'TrackOpens' => false,
                    'Subject' => App::$param["signupNotification"]["others"]["subject"],
                    'HtmlBody' => "<p>
                                        Navn: {$userId}<br>
                                        E-mail: {$email}
                                   </p>",
                ];
            }

            try {
                $client->sendEmailBatch($messages);
            } catch (PostmarkException $ex) {
//                echo $ex->httpStatusCode . "\n";
//                echo $ex->postmarkApiErrorCode . "\n";

            } catch (Exception $generalException) {
//                 A general exception is thown if the API
            }
        }
        $row["properties"] = json_decode($row["properties"]);
        $response['success'] = true;
        $response['message'] = 'User was created';
        $response['data'] = $row;
        $response['session'] = Session::get();
        $subusers = Session::getByKey("subusers") ?? [];
        $subuserEmails = Session::getByKey("subuserEmails") ?? [];
        $subusers[] = $row["screenname"];
        $subuserEmails[$userId] = $row["email"];
        Session::set("subusers", $subusers);
        Session::set("subuserEmails", $subuserEmails);
        return $response;
    }

    /**
     * @param array<string> $data
     * @return array<bool|string|int|string[]>
     */
    public function updateUser(array $data): array
    {
        $user = isset($data["user"]) ? Model::toAscii($data["user"], NULL, "_") : null;

        // Check if such email already exists
        $email = null;
        if (isset($data["email"])) {
            $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE email = '" . $data["email"] . "'");
            $result = $this->fetchRow($res);
            if ($result['count'] > 0) {
                return array(
                    'code' => 400,
                    'success' => false,
                    'message' => "Email " . $data["email"] . " already exists"
                );
            }

            $email = $data["email"];
        }

        // Check if the password is strong enough
        $password = null;
        if (isset($data["password"])) {
            $passwordCheckResults = Setting::checkPasswordStrength(Util::format($data["password"], true));
            if (sizeof($passwordCheckResults) > 0) {
                return array(
                    'code' => 400,
                    'success' => false,
                    'message' => 'Password does not meet following requirements: ' . implode(', ', $passwordCheckResults)
                );
            }

            $password = Setting::encryptPwSecure(Util::format($data["password"], true));
        }

        $userGroup = $data["usergroup"] ?? null;
        $properties = isset($data["properties"]) ? json_encode($data["properties"]) : null;

        $sQuery = "UPDATE users SET screenname=screenname";
        if ($password) $sQuery .= ", pw=:sPassword";
        if ($email) $sQuery .= ", email=:sEmail";
        if ($properties) $sQuery .= ", properties=:sProperties";
        if (isset($userGroup)) {
            $sQuery .= ", usergroup=:sUsergroup";
            $obj[$user] = $userGroup;
            Database::setDb($this->parentdb);
            $settings = new Setting();
            if (!$settings->updateUserGroups((object)$obj)['success']) {
                $response['success'] = false;
                $response['message'] = "Could not update settings.";
                $response['code'] = 400;
                return $response;
            }
            Database::setDb("mapcentia");
        }

        if (!empty($data["parentdb"])) {
            $sQuery .= " WHERE screenname=:sUserID AND parentdb=:sParentDb RETURNING screenname,email,usergroup,properties";
        } else {
            $sQuery .= " WHERE screenname=:sUserID RETURNING screenname,email,usergroup,properties";
        }

        try {
            $res = $this->prepare($sQuery);
            if ($password) $res->bindParam(":sPassword", $password);
            if ($email) $res->bindParam(":sEmail", $email);
            if (isset($userGroup)) {
                $str = $userGroup !== "" ? $userGroup : null;
                $res->bindParam(":sUsergroup", $str);
            }
            if ($properties) $res->bindParam(":sProperties", $properties);
            $res->bindParam(":sUserID", $user);
            if (!empty($data["parentdb"])) $res->bindParam(":sParentDb", $data["parentdb"]);


            $res->execute();
            $row = $this->fetchRow($res);
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = Session::getUser();
            $response['code'] = 400;
            return $response;
        }
        $row["properties"] = json_decode($row["properties"]);
        $response['success'] = true;
        $response['message'] = "User was updated";
        $response['data'] = $row;
        return $response;
    }

    /**
     * @param string $data
     * @return array<bool|string|int>
     */
    public function deleteUser(string $data): array
    {
        $user = $data ? Model::toAscii($data, NULL, "_") : null;
        $sQuery = "DELETE FROM users WHERE screenname=:sUserID AND parentdb=:parentDb";
        try {
            $res = $this->prepare($sQuery);
            $res->execute([":sUserID" => $user, ":parentDb" => $this->userId]);
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = Session::getUser();
            $response['code'] = 400;
            return $response;
        }
        $subusers = Session::getByKey("subusers");
        $subuserEmails = Session::getByKey("subuserEmails");
        if (!empty($subusers) && !empty($subuserEmails)) {
            $subusers = array_diff($subusers, [$user]);
            $subuserEmails = array_diff($subuserEmails, [$user]);
            Session::set("subusers", $subusers);
            Session::set("subuserEmails", $subuserEmails);
        }
        $response['success'] = true;
        $response['message'] = "User was deleted";
        $response['data'] = $res->rowCount();
        return $response;
    }

    /**
     * @param string $userId
     * @return array<mixed>
     */
    public function getSubusers(string $userId): array
    {
        $sQuery = "SELECT * FROM users WHERE parentdb = :sUserID";
        $res = $this->prepare($sQuery);
        $res->execute([":sUserID" => $userId]);

        $subusers = [];
        while ($row = $this->fetchRow($res)) {
            $row["screenName"] = $row["screenname"];
            unset($row["screenname"]);
            unset($row["pw"]);
            $subusers[] = $row;
        }

        $response = [];
        $response['success'] = true;
        $response['data'] = $subusers;

        return $response;
    }

    /**
     * @param string $userId
     * @param string $checkedPassword
     * @return bool
     */
    public function hasPassword(string $userId, string $checkedPassword): bool
    {
        $sQuery = "SELECT pw FROM users WHERE screenname = :sUserID";
        $res = $this->prepare($sQuery);
        $res->execute([":sUserID" => $userId]);
        $row = $this->fetchRow($res);
        $pwd = Util::format($checkedPassword, true);
        $hasPassword = false;
        if (md5($pwd) === $row['pw']) {
            $hasPassword = true;
        } else if (password_verify($pwd, $row['pw'])) {
            $hasPassword = true;
        }
        return $hasPassword;
    }
}