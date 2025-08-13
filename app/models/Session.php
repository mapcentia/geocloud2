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
        $clientId = 'geofa';

        $expectedNonce = $_SESSION['oauth2_nonce'] ?? null;
        if (!$expectedNonce) {
//            throw new \RuntimeException('OAuth2 nonce not set in session.');
        }

        $http = new \GuzzleHttp\Client(['timeout' => 5]);

        // 1. Get OIDC metadata
//        $metaUrl = "https://login.microsoftonline.com/{$tenantId}/v2.0/.well-known/openid-configuration";
//        $metaUrl = "http://localhost:8089/realms/master/.well-known/openid-configuration";
        $meta = json_decode('{"issuer":"http://localhost:8089/realms/master","authorization_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/auth","token_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/token","introspection_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/token/introspect","userinfo_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/userinfo","end_session_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/logout","frontchannel_logout_session_supported":true,"frontchannel_logout_supported":true,"jwks_uri":"http://localhost:8089/realms/master/protocol/openid-connect/certs","check_session_iframe":"http://localhost:8089/realms/master/protocol/openid-connect/login-status-iframe.html","grant_types_supported":["authorization_code","client_credentials","implicit","password","refresh_token","urn:ietf:params:oauth:grant-type:device_code","urn:ietf:params:oauth:grant-type:token-exchange","urn:ietf:params:oauth:grant-type:uma-ticket","urn:openid:params:grant-type:ciba"],"acr_values_supported":["0","1"],"response_types_supported":["code","none","id_token","token","id_token token","code id_token","code token","code id_token token"],"subject_types_supported":["public","pairwise"],"prompt_values_supported":["none","login","consent"],"id_token_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512"],"id_token_encryption_alg_values_supported":["ECDH-ES+A256KW","ECDH-ES+A192KW","ECDH-ES+A128KW","RSA-OAEP","RSA-OAEP-256","RSA1_5","ECDH-ES"],"id_token_encryption_enc_values_supported":["A256GCM","A192GCM","A128GCM","A128CBC-HS256","A192CBC-HS384","A256CBC-HS512"],"userinfo_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512","none"],"userinfo_encryption_alg_values_supported":["ECDH-ES+A256KW","ECDH-ES+A192KW","ECDH-ES+A128KW","RSA-OAEP","RSA-OAEP-256","RSA1_5","ECDH-ES"],"userinfo_encryption_enc_values_supported":["A256GCM","A192GCM","A128GCM","A128CBC-HS256","A192CBC-HS384","A256CBC-HS512"],"request_object_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512","none"],"request_object_encryption_alg_values_supported":["ECDH-ES+A256KW","ECDH-ES+A192KW","ECDH-ES+A128KW","RSA-OAEP","RSA-OAEP-256","RSA1_5","ECDH-ES"],"request_object_encryption_enc_values_supported":["A256GCM","A192GCM","A128GCM","A128CBC-HS256","A192CBC-HS384","A256CBC-HS512"],"response_modes_supported":["query","fragment","form_post","query.jwt","fragment.jwt","form_post.jwt","jwt"],"registration_endpoint":"http://localhost:8089/realms/master/clients-registrations/openid-connect","token_endpoint_auth_methods_supported":["private_key_jwt","client_secret_basic","client_secret_post","tls_client_auth","client_secret_jwt"],"token_endpoint_auth_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512"],"introspection_endpoint_auth_methods_supported":["private_key_jwt","client_secret_basic","client_secret_post","tls_client_auth","client_secret_jwt"],"introspection_endpoint_auth_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512"],"authorization_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512"],"authorization_encryption_alg_values_supported":["ECDH-ES+A256KW","ECDH-ES+A192KW","ECDH-ES+A128KW","RSA-OAEP","RSA-OAEP-256","RSA1_5","ECDH-ES"],"authorization_encryption_enc_values_supported":["A256GCM","A192GCM","A128GCM","A128CBC-HS256","A192CBC-HS384","A256CBC-HS512"],"claims_supported":["aud","sub","iss","auth_time","name","given_name","family_name","preferred_username","email","acr"],"claim_types_supported":["normal"],"claims_parameter_supported":true,"scopes_supported":["openid","service_account","email","address","basic","offline_access","profile","organization","roles","phone","microprofile-jwt","acr","web-origins"],"request_parameter_supported":true,"request_uri_parameter_supported":true,"require_request_uri_registration":true,"code_challenge_methods_supported":["plain","S256"],"tls_client_certificate_bound_access_tokens":true,"revocation_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/revoke","revocation_endpoint_auth_methods_supported":["private_key_jwt","client_secret_basic","client_secret_post","tls_client_auth","client_secret_jwt"],"revocation_endpoint_auth_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","HS256","HS512","ES256","RS256","HS384","ES512","PS256","PS512","RS512"],"backchannel_logout_supported":true,"backchannel_logout_session_supported":true,"device_authorization_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/auth/device","backchannel_token_delivery_modes_supported":["poll","ping"],"backchannel_authentication_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/ext/ciba/auth","backchannel_authentication_request_signing_alg_values_supported":["PS384","RS384","EdDSA","ES384","ES256","RS256","ES512","PS256","PS512","RS512"],"require_pushed_authorization_requests":false,"pushed_authorization_request_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/ext/par/request","mtls_endpoint_aliases":{"token_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/token","revocation_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/revoke","introspection_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/token/introspect","device_authorization_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/auth/device","registration_endpoint":"http://localhost:8089/realms/master/clients-registrations/openid-connect","userinfo_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/userinfo","pushed_authorization_request_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/ext/par/request","backchannel_authentication_endpoint":"http://localhost:8089/realms/master/protocol/openid-connect/ext/ciba/auth"},"authorization_response_iss_parameter_supported":true}', true);

        // 2. Fetch JWKS (JSON Web Key Set)
        $jwks = json_decode('{"keys":[{"kid":"knVHiD_fBwdst2UohvIPXPKrYuYleNH7efhWsR9HHdw","kty":"RSA","alg":"RS256","use":"sig","x5c":["MIICmzCCAYMCBgGXNK8vszANBgkqhkiG9w0BAQsFADARMQ8wDQYDVQQDDAZtYXN0ZXIwHhcNMjUwNjAzMDcyNDQ1WhcNMzUwNjAzMDcyNjI1WjARMQ8wDQYDVQQDDAZtYXN0ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDQY+eDujPrOy28elPH8iq7AJfRZjibbMNQBKAWMLkZmYZtFM8bSHZo8pLmTVLZdcsz6xS/LK0YM2bak5yYxWfLrIjMiiG1jecUgbPV/epeok4l89bg+4TwvX9uBqXPqK1XED80Y6tPmuHVVND1/g2VPq/h3nHgkN8a8YEpZo0Rus2Kwh4paReKFzr5PGJbR+8bXBhtvvoxLqrrjqyl3T2C7B2fIKX9I00+i/upVkEx5FoeGcBxigYTXDiLZZr1GWskpMm29bSa3s1KlpNjW4P6v567JnR/2OThhz6Qm1+skcth/nf2M8PFoEMX31nOB2AfC8nWAbudrRQ0KXQn50mpAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAIwGq7cpQ/G/s4hIOotSQ1kxQ42TC8+7RJ9tvPKW69YEoNfozc15wMtCZqNLxknlWAngLVZIg/dZcsxHbrR5IawpLlj9iw3+MK/+dL5agXUwRkQQUF06/alnAy1/Xnhy38YfcPVYWa3E4SKvrGBDBS+ZwzQPxQMrLfxSyKC/vIM4xorixRfYR45wF3s4yB3x7T70NMSp9Su2BwsSk2Ri3Eg5sa262YJUemhPLkDNL8EQ0A2gPxXjZ7ui8UrDt1/iGR2/KYh5LWKg8tYGodR6vkgRQZnIXMY/0/g6Sv5C746KB8Omvljbav3Av6b7aWAfr+M07bUFafF7chaxYYr26E4="],"x5t":"tQFrIRxHmM4bSYZCDH58YThxI1U","x5t#S256":"h3RzGqSq9wwRs4YDoY6smvuH7-7Kyxii7bP6R-zWYII","n":"0GPng7oz6zstvHpTx_IquwCX0WY4m2zDUASgFjC5GZmGbRTPG0h2aPKS5k1S2XXLM-sUvyytGDNm2pOcmMVny6yIzIohtY3nFIGz1f3qXqJOJfPW4PuE8L1_bgalz6itVxA_NGOrT5rh1VTQ9f4NlT6v4d5x4JDfGvGBKWaNEbrNisIeKWkXihc6-TxiW0fvG1wYbb76MS6q646spd09guwdnyCl_SNNPov7qVZBMeRaHhnAcYoGE1w4i2Wa9RlrJKTJtvW0mt7NSpaTY1uD-r-euyZ0f9jk4Yc-kJtfrJHLYf539jPDxaBDF99ZzgdgHwvJ1gG7na0UNCl0J-dJqQ","e":"AQAB"},{"kid":"i1iGDa6V8FvqMA8Gc_Jibo9rGHdxVvo_dhax4paQuwI","kty":"RSA","alg":"RSA-OAEP","use":"enc","x5c":["MIICmzCCAYMCBgGXNK8xWjANBgkqhkiG9w0BAQsFADARMQ8wDQYDVQQDDAZtYXN0ZXIwHhcNMjUwNjAzMDcyNDQ2WhcNMzUwNjAzMDcyNjI2WjARMQ8wDQYDVQQDDAZtYXN0ZXIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCuXCMGH1yW9ZCRInqZ1T27Cp0VRoXNQ5hJwRg9EGBBBVKWj3eLwVQ9CWSloboeNtTBsAUkZENqO5MwyjLFhkcP6FwTkKe/o9yNiNyx2XUJyCyKlzs2pnCC8KT+dh0XDtzKx8acviiOGrTiIPei4QuFoVRJM33auDet2TCbByzAVQ8emSzH8Mc7kbbvONeZD/+s2v1A7JniVh3gNLtnwcC56pi0mYyM6t1RaierwXOaWVEwQtWmPKq9sCogabZr4UAdiag1QiqkXd4ES0ImdqZ5s3fTTPThahWtI6MkNsBmgnBrNSx4NRSxPjV6WPd5eYXJNLvW5S2oN/BCaSu0eKDRAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAGt/K3q+2Dt6mTpuurS8XxdbT1uEu8UFfoz1EYLiD8bktsYRHgQ6oBJLDgoA+J66fn2/j5AsinSTonNZB0ziuFp0Jjo8bTY3Y7N6DU/i2NHFx81nnSAfouIDz/vNlTIDtugr2q19da0JSmuQvO8f904Zj9RIzxxFhy1qTaTACt3pGKUuqscXh0pY5dcgNo38aYDj9iEiG4AQKSqBMgkUiJP3WFafLiFMLBecSVYxnYavH2ZxjqGO+lC/6ub0vIQt65Vh04SObrm/+U7XsdTrfmg/uTvwWp+sIF4OcXV5/RZvxH/LdFQ1mt1kayfRo2RsLiSYJwcRc+tuq7ZCsMpdQNA="],"x5t":"qQlW47Mwr2gc0_j5KMCH1r_JJ9A","x5t#S256":"eX2f7lnCEq7kDgbKpAvwLjXQ7ZWdUiTN_sAltK_PjB0","n":"rlwjBh9clvWQkSJ6mdU9uwqdFUaFzUOYScEYPRBgQQVSlo93i8FUPQlkpaG6HjbUwbAFJGRDajuTMMoyxYZHD-hcE5Cnv6PcjYjcsdl1Ccgsipc7NqZwgvCk_nYdFw7cysfGnL4ojhq04iD3ouELhaFUSTN92rg3rdkwmwcswFUPHpksx_DHO5G27zjXmQ__rNr9QOyZ4lYd4DS7Z8HAueqYtJmMjOrdUWonq8FzmllRMELVpjyqvbAqIGm2a-FAHYmoNUIqpF3eBEtCJnamebN300z04WoVrSOjJDbAZoJwazUseDUUsT41elj3eXmFyTS71uUtqDfwQmkrtHig0Q","e":"AQAB"}]}', true);

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
                'password' => 'xxx',
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
