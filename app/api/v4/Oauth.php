<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-20204 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\auth\types\GrantType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\models\Database;
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
    required: [],
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
            title: "The database the user belongs to",
            type: "string",
            example: "mydb"
        ),
        new OA\Property(
            property: "client_id",
            title: "The OAuth client id",
            type: "string",
            example: "djskjskdj"
        ),
        new OA\Property(
            property: "client_secret",
            title: "The OAuth client secret",
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

    public function __construct()
    {
    }

    /**
     * @return array<string, array<string, mixed>|bool|string|int>
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/oauth', operationId: 'postOauth', tags: ['OAuth'])]
    #[OA\RequestBody(description: 'Create token', required: true, content: new OA\JsonContent(ref: "#/components/schemas/OAuth"))]
    #[OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: "#/components/schemas/OAuthGrant"))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    public function post_index(): array
    {
        $this->session = new Session();
        $data = json_decode(Input::getBody(), true) ?: [];

        // Password grant. We don't check clint_id or client_secret
        if ($data['grant_type'] == GrantType::PASSWORD->value) {
            if (!empty($data["username"]) && !empty($data["password"])) {
                try {
                    return $this->session->start($data["username"], $data["password"], "public", $data["database"], true);
                } catch (GC2Exception) {
                    return self::error("invalid_grant", "Could not authenticate the user. Check username and password", 400);
                }
            } else {
                return self::error("invalid_grant", "Username or password parameter was not provided", 400);
            }
        }

        // Refresh grant
        // If refresh we parse the refresh token and turn it into an access token
        if ($data['grant_type'] == GrantType::REFRESH_TOKEN->value) {
            if (!empty($data["refresh_token"])) {
                try {
                    $parsedToken = Jwt::parse($data["refresh_token"])['data'];
                } catch (GC2Exception $e) {
                    return self::error("invalid_request", "Token could not be parsed: {$e->getMessage()}", 400);
                }
                if ($parsedToken['response_type'] != 'refresh') {
                    return self::error("invalid_grant", "Not an refresh token", 400);
                }
                try {
                    (new \app\models\Client())->get($data['client_id']);
                } catch (GC2Exception) {
                    return self::error("invalid_client", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
                }
                if (!empty($data['client_secret']))
                    try {
                        (new \app\models\Client())->verifySecret($data['client_id'], $data['client_secret']);
                    } catch (GC2Exception) {
                        return self::error("invalid_client", "Client secret is wrong", 401);
                    }
                $superUserApiKey = (new Setting())->getApiKeyForSuperUser();
                $accessToken = Jwt::createJWT($superUserApiKey, $parsedToken['database'], $parsedToken['uid'], $parsedToken['superUser'], $parsedToken['userGroup']);
                return [
                    "access_token" => $accessToken['token'],
                    "token_type" => "bearer",
                    "expires_in" => $accessToken["ttl"],
                    "scope" => "",
                ];
            } else {
                return self::error("invalid_request", "Refresh token was not provided", 400);
            }
        }

        // Code grant
        if ($data['grant_type'] == GrantType::AUTHORIZATION_CODE->value) {
            try {
                $token = Jwt::changeCodeForAccessToken($data['code']);
                $tokenData = Jwt::parse($token)['data'];
                // Create a refresh token from the access token
                $superUserApiKey = (new Setting())->getApiKeyForSuperUser();
                $refreshToken = Jwt::createJWT($superUserApiKey, $tokenData['database'], $tokenData['uid'], $tokenData['superUser'], $tokenData['userGroup'], false);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Code doesn't exists or is expired", 400);
            }
            try {
                (new \app\models\Client())->get($data['client_id']);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
            }
            if (!empty($data['client_secret'])) {
                try {
                    (new \app\models\Client())->verifySecret($data['client_id'], $data['client_secret']);
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
            Database::setDb($user['parentdb']);
            try {
                (new \app\models\Client())->get($data['client_id']);
            } catch (GC2Exception) {
                return self::error("invalid_grant", "Client with identifier '{$data['client_id']}' was not found in the directory", 401);
            }
            $token = (new Session())->createOAuthResponse($user['parentdb'], $user['screen_name'], !$user['subuser'], $user['usergroup'], false);
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
    public function put_index(): array
    {
        return [];
    }

    /**
     */
    public function delete_index(): array
    {
        return [];
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $body = Input::getBody();
        $collection = new Assert\Collection([
            'grant_type' => new Assert\Required([
                new Assert\NotBlank()
            ]),
            'username' => new Assert\Required([
                new Assert\NotBlank()
            ]),
            'password' => new Assert\Required([
                new Assert\NotBlank()
            ]),
            'database' => new Assert\Required([
                new Assert\NotBlank()
            ]),
            'client_id' => new Assert\Optional([
                new Assert\NotBlank()
            ]),
            'client_secret' => new Assert\Optional([
                new Assert\NotBlank()
            ]),
        ]);
        if (!empty($body)) {
            $this->validateRequest($collection, $body, '');
        }
    }
}