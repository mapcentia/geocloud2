<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
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
use app\exceptions\GC2Exception;


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
     * @return array<bool|array>
     * @throws Exception
     */
    public function getAll(): array
    {
        $query = "SELECT * FROM users WHERE email<>''";
        $res = $this->execQuery($query);
        $rows = $this->fetchAll($res);
        $response['success'] = true;
        $response['data'] = $rows;
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
     * @return array
     * @throws GC2Exception
     */
    public function getData(): array
    {
        $query = "SELECT email, parentdb, usergroup, screenname as userid, properties, zone FROM users WHERE (screenname = :sUserID OR email = :sUserID) AND (parentdb = :parentDb OR parentDB IS NULL)";
        $res = $this->prepare($query);
        $res->execute(array(":sUserID" => $this->userId, ":parentDb" => $this->parentdb));
        $row = $this->fetchRow($res);
        if (!$row['userid']) {
            throw new GC2Exception("User identifier $this->userId was not found (parent database: " . ($this->parentdb ?: 'null') . ")", 404, null, 'USER_NOT_FOUND');
        }
        if (!empty($row['properties'])) {
            $row['properties'] = json_decode($row['properties']);
        }
        $response['success'] = true;
        $response['data'] = $row;
        return $response;
    }

    /**
     * @param array<string|bool> $data
     * @return array
     * @throws GC2Exception
     */
    public function createUser(array $data): array
    {
        $mandatoryParameters = ['name', 'email', 'password'];
        foreach ($mandatoryParameters as $item) {
            if (empty($data[$item])) {
                throw new GC2Exception("$item has to be provided", 400, null, "MISSING_PARAMETER");
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
            $res = $this->prepare($sql);
            $res->execute([":sDatabase" => $parentDb]);
            $row = $this->fetchRow($res);
            if (!$row) {
                throw new GC2Exception("Database '$parentDb' doesn't exist", 400, null, "PARENT_DATABASE_NOT_FOUND");
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
                    throw new GC2Exception("User identifier $userId already exists for parent database " . $this->userId, 400, null, 'SUB_USER_ALREADY_EXISTS');
                }
                $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE screenname = '" . $userId . "' AND parentdb ISNULL");
                $result = $this->fetchRow($res);
                if ($result['count'] > 0) {
                    throw new GC2Exception("User identifier $userId already exists as database", 400, null, 'PARENT_USER_EXISTS_WITH_NAME');
                }

            } else {
                throw new GC2Exception("User identifier $userId already exists", 400, null, 'USER_ALREADY_EXISTS');
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
            throw new GC2Exception("Email $email already exists", 400, null, 'EMAIL_ALREADY_EXISTS');
        }

        // Check if the password is strong enough
        $passwordCheckResults = Setting::checkPasswordStrength($password);
        if (sizeof($passwordCheckResults) > 0) {
            throw new GC2Exception('Password does not meet following requirements: ' . implode(', ', $passwordCheckResults), 400, null, 'WEAK_PASSWORD');
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
                throw new GC2Exception($e->getMessage(), 400);
            }
        }

        $sQuery = "INSERT INTO users (screenname,pw,email,parentdb,usergroup,zone,properties) VALUES(:sUserID, :sPassword, :sEmail, :sParentDb, :sUsergroup, :sZone, :sProperties) RETURNING screenname,parentdb,email,usergroup,zone,properties";
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
                                        Navn: $userId<br>
                                        E-mail: {$email}
                                   </p>",
                ];
            }

            $client->sendEmailBatch($messages);
        }
        $row["properties"] = !empty($row["properties"]) ? json_decode($row["properties"]) : null;
        $response['success'] = true;
        $response['message'] = 'User was created';
        $response['data'] = $row;
        $response['session'] = Session::get();
        $subusers = Session::getByKey("subusers") ?? [];
        $subuserEmails = Session::getByKey("subuserEmails") ?? [];
        $userGroups = Session::getByKey("usergroups") ?? [];
        $userGroups[$userId] = $group ?? null;
        $subusers[] = $row["screenname"];
        $subuserEmails[$userId] = $row["email"];
        Session::set("subusers", $subusers);
        Session::set("subuserEmails", $subuserEmails);
        Session::set("usergroups", $userGroups);
        return $response;
    }

    /**
     * @param array<string> $data
     * @return array<bool|string|int|string[]>
     * @throws Exception
     */
    public function updateUser(array $data): array
    {
        $user = isset($data["user"]) ? Model::toAscii($data["user"], NULL, "_") : null;

        // Check if such email already exists
        $email = null;
        if (isset($data["email"])) {
            $res = $this->execQuery("SELECT COUNT(*) AS count FROM users WHERE email = '" . $data["email"] . "' AND screenname <> '" . $user . "'");
            $result = $this->fetchRow($res);
            if ($result['count'] > 0) {
                throw new Exception("Email " . $data["email"] . " already exists");
            }
            $email = $data["email"];
        }

        // Check if the password is strong enough
        $password = null;
        if (isset($data["password"])) {
            $passwordCheckResults = Setting::checkPasswordStrength(Util::format($data["password"], true));
            if (sizeof($passwordCheckResults) > 0) {
                throw new Exception("Password does not meet following requirements: " . implode(", ", $passwordCheckResults));

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
            $userGroups = Session::getByKey("usergroups") ?? [];
            $userGroups[$user] = !empty($userGroup) ? $userGroup : null;
            Session::set("usergroups", $userGroups);
        }

        if (!empty($data["parentdb"])) {
            $sQuery .= " WHERE screenname=:sUserID AND parentdb=:sParentDb RETURNING screenname,email,usergroup,properties";
        } else {
            $sQuery .= " WHERE screenname=:sUserID RETURNING screenname,email,usergroup,properties";
        }
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
        $row["properties"] = !empty($row["properties"]) ? json_decode($row["properties"]) : null;
        $response['success'] = true;
        $response['message'] = "User was updated";
        $response['data'] = $row;
        return $response;
    }

    /**
     * @param string $data
     * @return array<bool|string|int>
     * @throws GC2Exception
     */
    public function deleteUser(string $data): array
    {
        $user = $data ? Model::toAscii($data, NULL, "_") : null;
        $sQuery = "DELETE FROM users WHERE screenname=:sUserID AND parentdb=:parentDb";
        $res = $this->prepare($sQuery);
        $res->execute([":sUserID" => $user, ":parentDb" => $this->userId]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("User doesn't exists", 404, null, "USER_DOES_NOT_EXISTS");
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
     * @return array
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