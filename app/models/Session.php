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
use app\models\User as UserModel;
use Firebase\JWT\JWK;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


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
     * @param string|null $parentDb
     * @param bool $tokenOnly
     * @param GrantType $grantType
     * @return array<string, array<string, mixed>|bool|string|int>
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function start(string $sUserID, string $pw, string|null $schema = "public", string|null $parentDb = null, bool $tokenOnly = false, GrantType $grantType = GrantType::PASSWORD): array
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
        if (sizeof($rows) > 1 && $parentDb) {
            $sQuery = "SELECT * FROM users WHERE ((screenname = :sUserID OR email = :sEmail) AND parentdb = :parentDb)";
            $res = $this->prepare($sQuery);
            $res->execute([
                ":sUserID" => $sUserID,
                ":sEmail" => $sUserIDNotConverted,
                ":parentDb" => $parentDb
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
            self::setSessionVars($row, $schema);
            $response = self::createResponse();

            if (!$tokenOnly) { //NOT OAuth
                // Fetch sub-users
                $this->setSubUsers();

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
            $this->logLogin($sUserID, $parentDb);;
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

    public function createOAuthResponse(string $db, string $user, bool $isSuperUser, bool $code, ?string $userGroup, ?string $codeChallenge = null, ?string $codeChallengeMethod = null): array
    {
        Database::setDb($db);
        $superUserApiKey = (new Setting())->getApiKeyForSuperUser();
        if (!$code) {
            $accessToken = Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup);
            $refreshToken = Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup, false);
            return [
                "access_token" => $accessToken['token'],
                "token_type" => "bearer",
                "expires_in" => $accessToken["ttl"],
                "refresh_token" => $refreshToken['token'],
                "scope" => "",
            ];

        } else {
            return Jwt::createJWT($superUserApiKey, $db, $user, $isSuperUser, $userGroup, true, true, $codeChallenge, $codeChallengeMethod);
        }
    }


    /**
     * Starts the authentication process using an ID token.
     *
     * @param string $token The ID token used for authentication.
     * @param string|null $schema The database schema to be set for the session. Defaults to "public".
     * @return array<string, array<string, mixed>|bool|string|int> The response data including user information, API key, and parent database details.
     * @throws RuntimeException If the ID token is invalid or verification fails.
     * @throws UnexpectedValueException If the token audience or nonce validation fails.
     */
    public function startWithToken(string $token, string|null $schema = "public")
    {
        $parentDb = 'mydb';

        // Azure AD tenant and client (app) ID
        $tenantId = '9fc91e5b-27ae-4660-b4fb-2c590c2e40fd';
        $clientId = '81ed11e6-4e76-4c22-bb55-739238ddbe5f';

        $expectedNonce = $_SESSION['oauth2_nonce'] ?? null;
        if (!$expectedNonce) {
//            throw new \RuntimeException('OAuth2 nonce not set in session.');
        }

        $http = new \GuzzleHttp\Client(['timeout' => 5]);

        // 1. Get OIDC metadata
        $metaUrl = "https://login.microsoftonline.com/{$tenantId}/v2.0/.well-known/openid-configuration";
        $meta = json_decode($http->get($metaUrl)->getBody(), true);

        // 2. Fetch JWKS (JSON Web Key Set)
        $jwks = json_decode($http->get($meta['jwks_uri'])->getBody(), true);

        // 3. Convert JWKs to key map (Azure uses RS256)
        $publicKeys = JWK::parseKeySet($jwks, 'RS256');

        try {
            // Decode and validate the ID token
            $payload = \Firebase\JWT\JWT::decode($token, $publicKeys);

            // Additional checks: audience and nonce
            if (!isset($payload->nonce) || $payload->nonce !== $expectedNonce) {
//                throw new \UnexpectedValueException('Invalid or missing nonce in ID token.');
            }
            if (!isset($payload->aud) || $payload->aud !== $clientId) {
                throw new \UnexpectedValueException('Invalid audience in ID token.');
            }

            // Optionally: Check exp, iss, etc.

            // Proceed with your logic, e.g., looking up user by $payload->preferred_username, $payload->email, etc.

        } catch (\Exception $e) {
            // Handle signature or validation errors
            throw new \RuntimeException('Invalid ID token: ' . $e->getMessage());
        }

        $row = null;
        $fn = function () use ($payload, &$row, $parentDb): void {
            if ($parentDb) {
                $sQuery = "SELECT * FROM users WHERE email = :sEmail AND parentdb = :parentDb";
                $res = $this->prepare($sQuery);
                $res->execute([
                    ":sEmail" => $payload->email,
                    ":parentDb" => $parentDb
                ]);
            } else {
                $sQuery = "SELECT * FROM users WHERE email = :sEmail AND parentdb is null";
                $res = $this->prepare($sQuery);
                $res->execute([
                    ":sEmail" => $payload->email,
                ]);
            }
            $row = $this->fetchRow($res);
        };
        $fn();

        if (!$row) {
            // Create sub-user
            $user = new UserModel();
            $data = [
                'name' => $payload->email,
                'email' => $payload->email,
                'password' => 'Silke2009!',
                'parentdb' => $parentDb,
            ];
            $user->createUser($data);
            $fn();
        }

        // Login successful.
        self::setSessionVars($row, $schema);
        $response = self::createResponse();

        // Fetch sub-users
        $this->setSubUsers();

        Database::setDb($response['data']['parentdb']);
        $response['data']['api_key'] = (new Setting())->get()['data']->api_key;

        // Insert into logins
        $this->logLogin($payload->aud, $parentDb);
        return $response; // In case it's NOT OAuth
    }

    /**
     * Generates a unique nonce for OAuth2 authentication and stores it in the session.
     * The method returns a response array containing the success status and the generated nonce.
     *
     * @return array<string, mixed> An associative array with 'success' indicating the operation status
     *                              and 'data' containing the generated nonce.
     */
    public function setOauth2Nonce(): array
    {
        $nonce = uniqid();
        $_SESSION['oauth2_nonce'] = $nonce;
        $response = [];
        $response['success'] = true;
        $response['data'] = $nonce;
        return $response;
    }

    /**
     * Sets session variables based on the provided user data and schema.
     *
     * @param array $row An associative array containing user data, such as zone, screenname, email, etc.
     * @param string|null $schema The database schema to assign to the session.
     * @return void
     */
    private static function setSessionVars(array $row, ?string $schema): void
    {
        $_SESSION['zone'] = $row['zone'];
        $_SESSION['auth'] = true;
        $_SESSION['screen_name'] = $row['screenname'];
        $_SESSION['parentdb'] = $row['parentdb'] ?: $row['screenname'];
        $_SESSION["subuser"] = (bool)$row['parentdb'];
        $_SESSION["properties"] = !empty($row["properties"]) ? json_decode($row["properties"]) : null;
        $_SESSION['email'] = $row['email'];
        $_SESSION['usergroup'] = $row['usergroup'] ?: null;
        $_SESSION['created'] = strtotime($row['created']);
        $_SESSION['postgisschema'] = $schema;
    }

    /**
     * Creates a response array containing session details.
     *
     * @return array<string, mixed> An associative array with the session details, including:
     *                              - 'success' (bool): Indicates successful operation.
     *                              - 'message' (string): Operation message.
     *                              - 'data' (array): Contains the session details:
     *                                - 'screen_name' (string): The screen name of the user.
     *                                - 'session_id' (string): The current session ID.
     *                                - 'parentdb' (string|null): The parent database associated with the session.
     *                                - 'subuser' (string|bool): Indicator of subuser status.
     *                                - 'email' (string): The email of the user.
     *                                - 'properties' (mixed): User properties.
     *                                - 'usergroup' (mixed): User group information.
     */
    private static function createResponse(): array
    {
        $response['success'] = true;
        $response['message'] = "Session started";
        $response['data'] = [];
        $response['data']['screen_name'] = $_SESSION['screen_name'];
        $response['data']['session_id'] = session_id();
        $response['data']['parentdb'] = $_SESSION['parentdb'];
        $response['data']['subuser'] = $_SESSION["subuser"];
        $response['data']['email'] = $_SESSION['email'];
        $response['data']['properties'] = $_SESSION["properties"];
        $response['data']['usergroup'] = $_SESSION['usergroup'];
        return $response;
    }

    /**
     * Initializes and populates the session with sub-users' details, including their screen names, emails, and user groups.
     *
     * @return void
     */
    private function setSubUsers(): void
    {
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
    }

    /**
     * Logs a user login event into the database.
     *
     * @param string $user The username of the user attempting to log in.
     * @param mixed $parentDb The parent database identifier.
     * @return void
     */
    private function logLogin(string $user, $parentDb): void
    {
        $sql = "INSERT INTO logins (db, \"user\") VALUES(:parentDb, :sUserID)";
        $res = $this->prepare($sql);
        try {
            $res->execute([
                ":sUserID" => $user,
                ":parentDb" => $parentDb
            ]);
        } catch (PDOException) {
            // We do not stop login in case of error
        }
    }
}