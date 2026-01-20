<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\auth\types\GrantType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\ClaimAcl;
use app\inc\Util;
use app\models\User as UserModel;
use Firebase\JWT\JWK;
use GuzzleHttp\Exception\GuzzleException;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;

const USER_DATABASE = 'mapcentia';


/**
 * Class Session
 * @package app\models
 */
class Session extends Model
{
    function __construct()
    {
        parent::__construct(new Connection(database: USER_DATABASE));
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
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
     */
    public function start(string $sUserID, string $pw, string|null $schema = "public", string|null $parentDb = null, bool $tokenOnly = false, GrantType $grantType = GrantType::PASSWORD): array
    {
        $pw = Util::format($pw);
        $isAuthenticated = false;
        $conn = new Connection(database: $parentDb);
        $setting = new Setting($conn);
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

        // If there are more than one records found, remove options by specifying the parent database
        if (sizeof($rows) > 1 && $parentDb) {
            $sQuery = "SELECT * FROM users WHERE (screenname = :sUserID OR email = :sEmail) AND parentdb = :parentDb";
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
            // Login is successful
            self::setSessionVars($row, $schema);
            $response = self::createResponse();
            // Check for OAuth2 authentication context
            if (!$tokenOnly) {
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
                $response['data']['api_key'] = (new Setting(new Connection(database: $response['data']['parentdb'])))->get()['data']->api_key;
            } else {
                return $this->createOAuthResponse($response['data']['parentdb'], $response['data']['screen_name'], !$response['data']['subuser'], $grantType == GrantType::AUTHORIZATION_CODE, $response['data']['usergroup']);
            }
            // Insert into logins
            $this->logLogin($sUserID, $parentDb);
            return $response;
        } else {
            throw new GC2Exception("Could not sign the user in. Check username and password", 401, null, 'INVALID_GRANT');
        }
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
        $superUserApiKey = (new Setting(new Connection(database: $db)))->getApiKeyForSuperUser();
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

    /**
     * Starts the authentication process using an ID token.
     *
     * @param string $token The ID token used for authentication.
     * @param string|null $schema The database schema to be set for the session. Defaults to "public".
     * @return array<string, array<string, mixed>|bool|string|int> The response data including user information, API key, and parent database details.
     * @throws GC2Exception
     * @throws GuzzleException|InvalidArgumentException
     */
    public function startWithToken(string $token, string $parentDb, ?string $schema = "public", bool $superuser = false): array
    {
        \app\inc\Session::start();
        $openIdConfig = App::$param['openIdConfig'] ?? null;

        if ($openIdConfig) {

            $clientId = $openIdConfig['clientId'];

            $expectedNonce = $_SESSION['oauth2_nonce'] ?? null;
            if (!$expectedNonce) {
//                throw new GC2Exception('OAuth2 nonce not set in session.');
            }

            $http = new \GuzzleHttp\Client(['timeout' => 5]);

            // 1. Get OIDC metadata
            $metaDataUri = $openIdConfig['metaDataUri'];
            $metaDataResponse = $http->get($metaDataUri);
            $metaData = json_decode($metaDataResponse->getBody(), true);

            // 2. Fetch JWKS (JSON Web Key Set)
            $jwksUrl = $metaData['jwks_uri'];
            $jwksResponse = $http->get($jwksUrl);
            $jwks = json_decode($jwksResponse->getBody(), true);

            // 3. Convert JWKs to key map (Azure uses RS256)
            $publicKeys = JWK::parseKeySet($jwks, 'RS256');

            try {
                // Decode and validate the ID token
                $payload = \Firebase\JWT\JWT::decode($token, $publicKeys);

                // Additional checks: audience and nonce
                if (!isset($payload->nonce) || $payload->nonce !== $expectedNonce) {
//                throw new GC2Exception('Invalid or missing nonce in ID token.');
                }
                if (!isset($payload->aud) || $payload->aud !== $clientId) {
                   // throw new GC2Exception('Invalid audience in ID token.');
                }

                // Optionally: Check exp, iss, etc.

            } catch (\Exception $e) {
                // Handle signature or validation errors
                throw new GC2Exception('Invalid ID token: ' . $e->getMessage());
            }
            $allowedDatabases = explode(',', $payload->database);
            if (!in_array($parentDb, $allowedDatabases) && $payload->database != "*") {
                throw new GC2Exception('Wanted database not allowed: ' . $parentDb . '. Allowed: ' . implode(', ', $allowedDatabases) . '.');
            }
            if ($superuser) {
                $databasesWithSuperuser = explode(',', $payload->superuser ?? "");
                if (!in_array($parentDb, $databasesWithSuperuser) && $payload->superuser != "*") {
                    throw new GC2Exception('Wanted database is not allowed with superuser privileges: ' . $parentDb . '. Allowed: ' . implode(', ', $databasesWithSuperuser));
                }
            }

            $row = null;
            $user = null;
            $userName = $payload->preferred_username;
            $fn = function () use ($payload, &$row, $parentDb, $superuser, $userName, &$user): void {
                if (!$superuser) {
//                $sQuery = "SELECT * FROM users WHERE email = :sEmail AND parentdb = :parentDb";
//                $res = $this->prepare($sQuery);
//                $res->execute([
//                    ":sEmail" => $payload->email,
//                    ":parentDb" => $parentDb
//                ]);
                    $sQuery = "SELECT * FROM users WHERE screenname = :sName AND parentdb = :parentDb";
                    $res = $this->prepare($sQuery);
                    $res->execute([
                        ":sName" => $userName,
                        ":parentDb" => $parentDb
                    ]);
                    $user = new User(userId: $row['screenname'], parentDb: $parentDb);
                } else {
                    $sQuery = "SELECT * FROM users WHERE screenname = :sDb AND parentdb is null";
                    $res = $this->prepare($sQuery);
                    $res->execute([
                        ":sDb" => $parentDb,
                    ]);
                }
                $row = $this->fetchRow($res);
            };
            $fn();

            if (!$row && !$superuser) {
                // Create sub-user
                $user = new UserModel(parentDb: $parentDb);
                $data = [
                    'name' => $userName,
                    'email' => $payload->email,
                    'password' => 'Silke2009!',
                    'parentdb' => $parentDb,
                    'subuser' => true,
                ];
                $user->createUser($data);
                $fn();
            }

            // Set privileges for sub-user
            if ($user) {

                if (!$acl = @file_get_contents(App::$param["path"] . "/app/conf/claim_acl.json")) {
                    error_log("Unable to read claim_acl.json");
                }

                if ($acl) {
                    $acl = json_decode($acl, true);
                    $claimAcl = new ClaimAcl($acl);
                    $grants = $claimAcl->allTablePermissions($payload);
                    $membershipsKeys = $claimAcl->allMembershipKeys($payload);
                    $memberships = [];
                    foreach ($membershipsKeys as $key) {
                        if (!empty($acl[$key]["__membership"])) {
                            $memberships = array_merge($memberships, $acl[$key]["__membership"]);
                        }
                    }

                    // Set grants
                    $conn = new Connection(database: $parentDb);
                    $layer = new Layer(connection: $conn);
                    $table = new Table(table: "settings.geometry_columns_join", connection: $conn);
                    $table->connect();
                    $table->begin();
                    // Reset privileges for sub-user
                    $layer->setPrivilegesOnAll($userName, 'none');
                    foreach ($grants as $rel => $grant) {
                        if (empty($grant)) {
                            continue;
                        }
                        $obj = new StdClass();
                        $obj->_key_ = $rel;
                        $obj->privileges = !empty($grant['write']) ? 'write' : (!empty($grant['read']) ? 'read' : null);
                        $obj->subuser = $userName;
                        try {
                            $layer->updatePrivileges($obj, $table);
                        } catch (GC2Exception $e) {
                            // If a relation is not found, skip
                            error_log($e->getMessage());
                        }
                    }
                    $table->commit();

                    // Reset user group
                    $data = [
                        'user' => $userName,
                        'usergroup' => null,
                        'parentdb' => $parentDb,
                    ];
                    $user->begin();
                    $user->updateUser(data: $data);
                    $row['usergroup'] = null;

                    // Set user group if requested
                    if (count($memberships) > 0) {
                        $data = [
                            'user' => $userName,
                            'usergroup' => $memberships[0],
                            'parentdb' => $parentDb,
                        ];
                        $user->updateUser(data: $data);
                        $row['usergroup'] = $memberships[0];
                    }
                    $user->commit();
                }
            }
        } else {
            $jwt = Jwt::validate($token)['data'];
            $row['screenname'] = $userName = $jwt['uid'];
            $row['parentdb'] = $jwt['database'];
            $row['email'] = $jwt['email'];
            $row['usergroup'] = $jwt['userGroup'];
        }
        // Login successful.
        self::setSessionVars($row, $schema);

        $response = self::createResponse();

        // Fetch sub-users
        $this->setSubUsers();

        $response['data']['api_key'] = (new Setting(new Connection(database: $response['data']['parentdb'])))->get()['data']->api_key;

        // Insert into logins
        $this->logLogin($userName, $parentDb);
        return $response;
    }

    /**
     * Generates a unique nonce for OAuth2 authentication and stores it in the session.
     * The method returns a response array containing the success status and the generated nonce.
     *
     * @return array<string, mixed> An associative array with 'success' indicating the operation status
     *                              and 'nonce' containing the generated nonce.
     */
    public function setOauth2Nonce(): array
    {
        $nonce = uniqid();
        $_SESSION['oauth2_nonce'] = $nonce;
        $response = [];
        $response['success'] = true;
        $response['nonce'] = $nonce;
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