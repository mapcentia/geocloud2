<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-20204 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Route;
use app\auth\types\GrantType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Session;
use app\models\Setting;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Oauth
 * @package app\api\v4
 */
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "OAuth",
    required: ["grant_type"],
    properties: [
        new OA\Property(
            property: "grant_type",
            title: "OAuth grant type",
            type: "string",
            example: "password",
        ),
        new OA\Property(
            property: "username",
            title: "Username",
            description: "Username - either database user or sub-user",
            type: "string",
            example: "mydb",
        ),
        new OA\Property(
            property: "password",
            title: "Password",
            type: "string",
            example: "abc123!"
        ),
        new OA\Property(
            property: "database",
            title: "Database",
            description: "The database the user belongs to",
            type: "string",
            example: "mydb"
        ),
        new OA\Property(
            property: "client_id",
            title: "Client id",
            description: "The OAuth client id",
            type: "string",
            example: "djskjskdj"
        ),
        new OA\Property(
            property: "client_secret",
            title: "Client secret",
            description: "The OAuth client secret",
            type: "string",
            example: "xxx"
        ),
        new OA\Property(
            property: "code",
            title: "Code",
            description: "The code which is exchanged for an access token",
            type: "string",
            example: "xxx"
        ),
        new OA\Property(
            property: "redirect_uri",
            title: "Redirect uri",
            description: "The code which is exchanged for an access token",
            type: "string",
            example: "xxx"
        ),
        new OA\Property(
            property: "code_verifier",
            title: "Code verifier",
            description: "The code verifier",
            type: "string",
            example: "xxx"
        ),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "OAuthGrant",
    required: [],
    properties: [
        new OA\Property(
            property: "access_token",
            title: "JWT access token",
            type: "string",
            example: "MTQ0NjJkZmQ5OTM2NDE1ZTZjNGZmZjI3",
        ),
        new OA\Property(
            property: "token_type",
            title: "Token type",
            description: "Always 'bearer'",
            type: "string",
            example: "bearer",
        ),
        new OA\Property(
            property: "expires_in",
            title: "Expiration time",
            type: "integer",
            example: 3600,
        ),
        new OA\Property(
            property: "refresh_token",
            title: "JWT refresh token",
            type: "string",
            example: "MTQ0NjJkZmQ5OTM2NDE1ZTZjNGZmZjI3",
        ),
        new OA\Property(
            property: "scope",
            title: "Scope",
            type: "string",
            example: "sql",
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
class Oauth extends AbstractApi
{
    public Session $session;

    public function __construct(private readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'oauth';
    }

    /**
     * @return array<string, array<string, mixed>|bool|string|int>
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/oauth', operationId: 'postOauth', tags: ['OAuth'])]
    #[OA\RequestBody(description: 'Create token', required: true, content: new OA\JsonContent(ref: "#/components/schemas/OAuth"))]
    #[OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: "#/components/schemas/OAuthGrant"))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/x-www-form-urlencoded'])]
    #[Route('api/v4/oauth/(action)')]
    public function post_index(): array
    {
        $this->session = new Session();

        if (Input::getContentType() == 'application/json') {
            $data = json_decode(Input::getBody(), true) ?: [];
        } else {
            // Parse application/x-www-form-urlencoded body into array
            $body = Input::getBody();
            $data = [];
            if (!empty($body)) {
                parse_str($body, $data);
            }
        }
        // Password grant.
        if ($data['grant_type'] == GrantType::PASSWORD->value) {
            $client = new \app\models\Client(new Connection(database: $data['database']));
            $clientData = $client->get($data['client_id']);
            if (!$clientData[0]['public']) {
                if (empty($data['client_secret'])) {
                    return self::error("invalid_client", "Client secret is missing. Client is not public", 401);
                }
                try {
                    $client->verifySecret($data['client_id'], $data['client_secret']);
                } catch (GC2Exception) {
                    return self::error("invalid_client", "Client secret is wrong", 401);
                }
            }

            try {
                return $this->session->start($data["username"], $data["password"], "public", $data["database"], true);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Could not authenticate the user. Check username and password", 400);
            }
        }

        // Refresh grant
        // If refresh we parse the refresh token and turn it into an access token
        if ($data['grant_type'] == GrantType::REFRESH_TOKEN->value) {
            try {
                $parsedToken = Jwt::parse($data["refresh_token"])['data'];
            } catch (GC2Exception $e) {
                return self::error("invalid_request", "Token could not be parsed: {$e->getMessage()}", 400);
            }
            if ($parsedToken['response_type'] != 'refresh') {
                return self::error("invalid_grant", "Not an refresh token", 400);
            }
            try {
                $client = new \app\models\Client(connection: new Connection(database: $parsedToken['database']));
                $clientData = $client->get($data['client_id']);
            } catch (GC2Exception) {
                return self::error("invalid_client", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
            }
            if (!$clientData[0]['public']) {
                try {
                    $client->verifySecret($data['client_id'], $data['client_secret']);
                } catch (GC2Exception) {
                    return self::error("invalid_client", "Client secret is wrong", 401);
                }
            }
            $superUserApiKey = (new Setting(connection: new Connection(database: $parsedToken['database'])))->getApiKeyForSuperUser();
            $accessToken = Jwt::createJWT($superUserApiKey, $parsedToken['database'], $parsedToken['uid'], $parsedToken['superUser'], $parsedToken['userGroup'], true, false, null, null, $parsedToken['properties'], $parsedToken['email']);
            return [
                "access_token" => $accessToken['token'],
                "token_type" => "bearer",
                "expires_in" => $accessToken["ttl"],
                "scope" => "",
            ];
        }

        // Code grant
        if ($data['grant_type'] == GrantType::AUTHORIZATION_CODE->value) {
            try {
                $token = Jwt::changeCodeForAccessToken($data['code'], $data['code_verifier']);
                $tokenData = Jwt::parse($token)['data'];
                // Create a refresh token from the access token
                $superUserApiKey = (new Setting(connection: new Connection(database: $tokenData['database'])))->getApiKeyForSuperUser();
                $refreshToken = Jwt::createJWT($superUserApiKey, $tokenData['database'], $tokenData['uid'], $tokenData['superUser'], $tokenData['userGroup'], false);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Code doesn't exists, is expired or code challenge failed.", 400);
            }
            try {
                $client = new \app\models\Client(connection: new Connection(database: $tokenData['database']));
                $clientData = $client->get($data['client_id']);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
            }
            if (!$clientData[0]['public']) {
                try {
                    $client->verifySecret($data['client_id'], $data['client_secret']);
                } catch (GC2Exception) {
                    return self::error("invalid_client", "Client secret is wrong", 401);
                }
            }
            return [
                "access_token" => $token,
                "refresh_token" => $refreshToken['token'],
                "token_type" => "bearer",
                "expires_in" => Jwt::ACCESS_TOKEN_TTL,
                "scope" => "",
            ];
        }
        // Device code grant
        if ($data['grant_type'] == GrantType::DEVICE_CODE->value) {
            try {
                $user = Jwt::checkDeviceCode($data['device_code']);
            } catch (GC2Exception $e) {
                if ($e->getErrorCode() == 'AUTHORIZATION_PENDING')
                    return self::error("authorization_pending", "Authorization is pending", 400);
                else
                    return self::error("invalid_request", $e->getMessage(), 400);
            }
            try {
                $client = new \app\models\Client(connection: new Connection(database: $user['parentdb']));
                $clientData = $client->get($data['client_id']);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
            }
            if (!$clientData[0]['public']) {
                try {
                    $client->verifySecret($data['client_id'], $data['client_secret']);
                } catch (GC2Exception) {
                    return self::error("invalid_client", "Client secret is wrong", 401);
                }
            }
            $token = (new Session())->createOAuthResponse($user['parentdb'], $user['screen_name'], !$user['subuser'], false, $user['usergroup']);
            Jwt::clearDeviceCode($data['device_code']);
            return $token;
        }
        return self::error("unsupported_grant_type", "grant_type must be either password, refresh_token or authorization_code", 401);
    }

    public function post_device(): array
    {
        $codes = Jwt::createDeviceAndUserCode();
        return [
            "device_code" => $codes['device_code'],
            "user_code" => $codes['user_code'],
            "verification_uri" => App::$param['host'] . '/device',
            "interval" => 5,
            "expires_in" => Jwt::DEVICE_CODE_TTL,
        ];
    }

    private static function error(string $err, string $message, int $code): array
    {
        return [
            "error" => $err,
            "error_description" => $message,
            "code" => $code,
        ];
    }

    /**
     */
    public function get_index(): array
    {
        return [];
    }

    /**
     */
    public function patch_index(): array
    {
        return [];
    }

    /**
     */
    public function delete_index(): array
    {
        return [];
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        // Only validate application/json
        // POST also accepts application/x-www-form-urlencoded
        if (Input::getContentType() == 'application/json') {
            $body = Input::getBody();
            $collection = self::getAssert(json_decode($body)->grant_type);
            $this->validateRequest($collection, $body, Input::getMethod());
        }
    }

    static public function getAssert($type = null): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['grant_type'] = new Assert\Optional([
            new Assert\NotBlank(),
            new Assert\Choice(choices: ['password', 'authorization_code', 'refresh_token', 'device_code']),
        ]);
        $collection->fields['client_id'] = new Assert\Required([
            new Assert\NotBlank()
        ]);
        $collection->fields['client_secret'] = new Assert\Optional([
            new Assert\NotBlank()
        ]);
        if ($type == 'password') {
            $collection->fields['username'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['password'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['database'] = new Assert\Required([
                new Assert\NotBlank()
            ]);

        } elseif ($type == 'authorization_code') {
            $collection->fields['client_id'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['code'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['redirect_uri'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['code_verifier'] = new Assert\Required([
                new Assert\NotBlank()
            ]);

        } elseif ($type == 'refresh_token') {
            $collection->fields['client_id'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['refresh_token'] = new Assert\Required([
                new Assert\NotBlank()
            ]);

        } elseif ($type == 'device_code') {
            $collection->fields['client_id'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
            $collection->fields['device_code'] = new Assert\Required([
                new Assert\NotBlank()
            ]);
        }

        return $collection;
    }
}