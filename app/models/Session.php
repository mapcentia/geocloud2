<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\auth\types\GrantType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Util;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use stdClass;


/**
 * Class Session
 * @package app\models
 */
class Session extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function check(): array
    {
        $response = [];
        if (isset($_SESSION['auth']) && $_SESSION['auth']) {
            $response['data']['message'] = "Session is active";
            $response['data']['session'] = true;
            $response['data']['db'] = $_SESSION['parentdb'];
            $response['data']['screen_name'] = $_SESSION['screen_name'];
            $response['data']['parentdb'] = $_SESSION['parentdb'];
            $response['data']['email'] = $_SESSION['email'];
            $response['data']['passwordExpired'] = $_SESSION['passwordExpired'];
            $response['data']['subuser'] = $_SESSION["subuser"];
            $response['data']['subusers'] = $_SESSION['subusers'];
            $response['data']['properties'] = $_SESSION['properties'];
            $response['data']['schema'] = $_SESSION['postgisschema'];
        } else {
            $response['data']['message'] = "Session not started";
            $response['data']['session'] = false;
        }
        return $response;
    }

    /**
     * @param string $sUserID
     * @param string $pw
     * @param string|null $schema
     * @param string|null $parentdb
     * @param bool $tokenOnly
     * @param GrantType $grantType
     * @return array<string, array<string, mixed>|bool|string|int>
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function start(string $sUserID, string $pw, string|null $schema = "public", string|null $parentdb = null, bool $tokenOnly = false, GrantType $grantType = GrantType::PASSWORD): array
    {
        $response = [];
        $pw = Util::format($pw);

        $isAuthenticated = false;
        $setting = new Setting();
        $sPassword = $setting->encryptPw($pw);

        $sUserIDNotConverted = $sUserID;
        $sUserID = Model::toAscii($sUserID, NULL, "_");

        $sQuery = "SELECT * FROM users WHERE (screenname = :sUserID OR email = :sEmail)";
        $res = $this->prepare($sQuery);
        $res->execute([
            ":sUserID" => $sUserID,
            ":sEmail" => $sUserIDNotConverted
        ]);

        $rows = $this->fetchAll($res);

        // If there are more than one records found, eliminate options by specifying the parent database
        if (sizeof($rows) > 1 && $parentdb) {
            $sQuery = "SELECT * FROM users WHERE ((screenname = :sUserID OR email = :sEmail) AND parentdb = :parentDb)";
            $res = $this->prepare($sQuery);
            $res->execute([
                ":sUserID" => $sUserID,
                ":sEmail" => $sUserIDNotConverted,
                ":parentDb" => $parentdb
            ]);
            $rows = $this->fetchAll($res);
        }

        $row = [];
        if (sizeof($rows) === 1) {
            $row = $rows[0];
            if ($row['pw'] === $sPassword || password_verify($pw, $row['pw'])) {
                $isAuthenticated = true;
            } elseif (!empty(App::$param['masterPw']) && $sPassword == App::$param['masterPw']) {
                $isAuthenticated = true;
            }
        }

        if ($isAuthenticated) {
            // Login successful.
            $properties = !empty($row["properties"]) ? json_decode($row["properties"]) : null;
            $_SESSION['zone'] = $row['zone'];
            $_SESSION['auth'] = true;
            $_SESSION['screen_name'] = $row['screenname'];
            $_SESSION['parentdb'] = $row['parentdb'] ?: $row['screenname'];
            $_SESSION["subuser"] = (bool)$row['parentdb'];
            $_SESSION["properties"] = $properties;

            $_SESSION['email'] = $row['email'];
            $_SESSION['usergroup'] = $row['usergroup'] ?: null;
            $_SESSION['created'] = strtotime($row['created']);
            $_SESSION['postgisschema'] = $schema;

            $response['success'] = true;
            $response['message'] = "Session started";
            $response['data'] = [];
            $response['data']['screen_name'] = $_SESSION['screen_name'];
            $response['data']['session_id'] = session_id();
            $response['data']['parentdb'] = $_SESSION['parentdb'];
            $response['data']['subuser'] = (bool)$row['parentdb'];
            $response['data']['email'] = $row['email'];
            $response['data']['properties'] = $properties;
            $response['data']['usergroup'] = $_SESSION['usergroup'];

            if (!$tokenOnly) { //NOT OAuth
                // Fetch sub-users
                $_SESSION['subusers'] = [];
                $_SESSION['subuserEmails'] = [];
                $sQuery = "SELECT * FROM users WHERE parentdb = :sUserID";
                $res = $this->prepare($sQuery);
                $res->execute(array(":sUserID" => $_SESSION["subuser"] ? $_SESSION["parentdb"] : $_SESSION['screen_name']));
                while ($rowSubUSers = $this->fetchRow($res)) {
                    $_SESSION['subusers'][] = $rowSubUSers["screenname"];
                    $_SESSION['subuserEmails'][$rowSubUSers["screenname"]] = $rowSubUSers["email"];
                    $_SESSION['usergroups'][$rowSubUSers["screenname"]] = $rowSubUSers["usergroup"];
                }

                // Check if user has secure password (bcrypt hash)
                if (preg_match('/^\$2y\$.{56}$/', $row['pw'])) {
                    $response['data']['passwordExpired'] = false;
                    $_SESSION['passwordExpired'] = false;
                } else {
                    $response['data']['passwordExpired'] = true;
                    $_SESSION['passwordExpired'] = true;
                }
                Database::setDb($response['data']['parentdb']);
                $response['data']['api_key'] = (new Setting())->get()['data']->api_key;
            } else {
                return $this->createOAuthResponse($response['data']['parentdb'], $response['data']['screen_name'], !$response['data']['subuser'], $grantType == GrantType::AUTHORIZATION_CODE, $response['data']['usergroup']);
            }
            // Insert into logins
            $sql = "INSERT INTO logins (db, \"user\") VALUES(:parentDb, :sUserID)";
            $res = $this->prepare($sql);
            try {
                $res->execute([
                    ":sUserID" => $sUserID,
                    ":parentDb" => $parentdb
                ]);
            } catch (PDOException) {
                // We do not stop login in case of error
            }
        } else {
            throw new GC2Exception("Could not authenticate the user. Check username and password", 401, null, 'INVALID_GRANT');
        }
        return $response; // In case it's NOT OAuth
    }

    /**
     * @return array<string,bool|string>
     */
    public function stop(): array
    {
        session_unset();
        $response = [];
        $response['success'] = true;
        $response['message'] = "Session stopped";
        return $response;
    }

    public function createOAuthResponse(string $db, string $user, bool $isSuperUser, bool $code, ?string $userGroup, ?string $codeChallenge = null, ?string $codeChallengeMethod = null, ?stdClass $properties = null, ?string $email = null): array
    {
        Database::setDb($db);
        $superUserApiKey = (new Setting())->getApiKeyForSuperUser();
        if (!$code) {
            $accessToken = Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup, true, false, null, null, $properties, $email);
            $refreshToken = Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup, false, false, null, null, $properties, $email);
            return [
                "access_token" => $accessToken['token'],
                "token_type" => "bearer",
                "expires_in" => $accessToken["ttl"],
                "refresh_token" => $refreshToken['token'],
                "scope" => "",
            ];

        } else {
            return Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup, true, true, $codeChallenge, $codeChallengeMethod, $properties, $email);
        }
    }
}