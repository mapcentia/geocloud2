<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\inc\Model;
use Exception;
use PDOException;

class Session extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $sValue
     * @param bool $bQuotes
     * @return string
     */
    private function VDFormat(string $sValue, bool $bQuotes = false): string
    {
        $sValue = trim($sValue);
        if ($bQuotes xor get_magic_quotes_gpc()) {
            $sValue = $bQuotes ? addslashes($sValue) : stripslashes($sValue);
        }
        return $sValue;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function check() : array
    {
        $response = [];
        if (isset($_SESSION['auth']) && $_SESSION['auth'] == true) {
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
        } else {
            $response['data']['message'] = "Session not started";
            $response['data']['session'] = false;
        }
        return $response;
    }

    /**
     * @param string $sUserID
     * @param string $pw
     * @param string $schema
     * @param bool $parentdb
     * @param bool $tokenOnly
     * @return array<string, array<string, mixed>|bool|string|int>
     * @throws Exception
     */
    public function start(string $sUserID, string $pw, $schema = "public", $parentdb = false, bool $tokenOnly = false): array
    {
        $response = [];
        $pw = $this->VDFormat($pw, true);

        $isAuthenticated = false;
        $setting = new \app\models\Setting();
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
        if (sizeof($rows) > 1 && $parentdb !== false) {
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
            $properties = json_decode($row['properties']);
            $_SESSION['zone'] = $row['zone'];
            $_SESSION['VDaemonData'] = null;
            $_SESSION['auth'] = true;
            $_SESSION['screen_name'] = $row['screenname'];
            $_SESSION['parentdb'] = $row['parentdb'] ?: $row['screenname'];
            $_SESSION["subuser"] = $row['parentdb'] ? true : false;
            $_SESSION["properties"] = $properties;

            $_SESSION['email'] = $row['email'];
            $_SESSION['usergroup'] = $row['usergroup'] ?: false;
            $_SESSION['created'] = strtotime($row['created']);
            $_SESSION['postgisschema'] = $schema;

            $response['success'] = true;
            $response['message'] = "Session started";
            $response['data'] = [];
            $response['data']['screen_name'] = $_SESSION['screen_name'];
            $response['data']['session_id'] = session_id();
            $response['data']['parentdb'] = $_SESSION['parentdb'];
            $response['data']['subuser'] = $row['parentdb'] ? true : false;;
            $response['data']['email'] = $row['email'];
            $response['data']['properties'] = $properties;

            if (!$tokenOnly) { //NOT OAuth
                // Fetch sub-users
                $_SESSION['subusers'] = [];
                $_SESSION['subuserEmails'] = [];
                $sQuery = "SELECT * FROM users WHERE parentdb = :sUserID";
                $res = $this->prepare($sQuery);
                $res->execute(array(":sUserID" => $_SESSION['screen_name']));
                while ($rowSubUSers = $this->fetchRow($res)) {
                    $_SESSION['subusers'][] = $rowSubUSers["screenname"];
                    $_SESSION['subuserEmails'][$rowSubUSers["screenname"]] = $rowSubUSers["email"];
                };

                // Check if user has secure password (bcrypt hash)
                if (preg_match('/^\$2y\$.{56}$/', $row['pw'])) {
                    $response['data']['passwordExpired'] = false;
                    $_SESSION['passwordExpired'] = false;
                } else {
                    $response['data']['passwordExpired'] = true;
                    $_SESSION['passwordExpired'] = true;
                }
                Database::setDb($response['data']['parentdb']);
                $settings_viewer = new \app\models\Setting();
                $response['data']['api_key'] = $settings_viewer->get()['data']->api_key;
            } else {
                // Get super user key, which are used for JWT secret
                Database::setDb($response['data']['parentdb']);
                $settings_viewer = new \app\models\Setting();
                $superUserApiKey = $settings_viewer->getApiKeyForSuperUser();
                $token = \app\inc\Jwt::createJWT($superUserApiKey, $response['data']['parentdb'], $response['data']['screen_name'], !$response['data']['subuser']);
                return [
                    "access_token" => $token['token'],
                    "token_type" => "bearer",
                    "expires_in" => $token["ttl"],
                    "refresh_token" => "",
                    "scope" => "",
                ];
            }
            // Insert into logins
            $sql = "INSERT INTO logins (db, \"user\") VALUES(:parentDb, :sUserID)";
            $res = $this->prepare($sql);
            try {
                $res->execute([
                    ":sUserID" => $sUserID,
                    ":parentDb" => $parentdb
                ]);
            } catch (PDOException $e) {
                // We do not stop login in case of error
            }
        } else {
            if (!$tokenOnly) { //NOT OAuth
                session_unset();
                $response['success'] = false;
                $response['message'] = "Session not started";
                $response['code'] = "401";
            } else {
                return [
                    "error" => "invalid_grant",
                    "error_description" => "Could not authenticate the user. Check username and password",
                    "code" => 400,
                ];
            }
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
}