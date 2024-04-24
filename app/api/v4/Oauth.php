<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-20204 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\auth\GrantType;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\models\Session;
use app\models\Setting;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Oauth
 * @package app\api\v4
 */
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
class Oauth extends AbstractApi
{
    public Session $session;

    public function __construct()
    {
    }

    /**
     * @return array<string, array<string, mixed>|bool|string|int>
     *
     * @OA\Post(
     *   path="/api/v4/oauth",
     *   tags={"OAuth"},
     *   summary="Create token",
     *   @OA\RequestBody(
     *     description="OAuth password grant parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="grant_type",type="string", example="password"),
     *         @OA\Property(property="username",type="string", example="user@example.com"),
     *         @OA\Property(property="password",type="string", example="1234Luggage"),
     *         @OA\Property(property="database",type="string", example="roads"),
     *         @OA\Property(property="client_id",type="string", example="xxxxxxxxxx"),
     *         @OA\Property(property="client_secret",type="string", example="xxxxxxxxxx")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="access_token",type="string", example="MTQ0NjJkZmQ5OTM2NDE1ZTZjNGZmZjI3"),
     *         @OA\Property(property="token_type",type="string", example="bearer"),
     *         @OA\Property(property="expires_in",type="integer",  example=3600),
     *         @OA\Property(property="refresh_token",type="string", example="IwOGYzYTlmM2YxOTQ5MGE3YmNmMDFkNTVk"),
     *         @OA\Property(property="scope",type="string", example="sql")
     *       )
     *     )
     *   )
     * )
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $this->session = new Session();
        $data = json_decode(Input::getBody(), true) ?: [];
        if ($data['grant_type'] == GrantType::PASSWORD->value) {
            if (!empty($data["username"]) && !empty($data["password"])) {
                return $this->session->start($data["username"], $data["password"], "public", $data["database"], true);
            } else {
                throw new GC2Exception("Username or password parameter was not provided", 400, null, 'INVALID_REQUEST');
            }
        }
        // If refresh we parse the refresh token and turn it into an access token
        if ($data['grant_type'] == GrantType::REFRESH_TOKEN->value) {
            if (!empty($data["refresh_token"])) {
                $parsedToken = Jwt::parse($data["refresh_token"])['data'];
                if ($parsedToken['response_type'] != 'refresh') {
                    throw new GC2Exception("Not an refresh token", 400);
                }
                (new \app\models\Client())->get($data['client_id']);
                $superUserApiKey = (new Setting())->getApiKeyForSuperUser();
                $accessToken = Jwt::createJWT($superUserApiKey, $parsedToken['database'], $parsedToken['uid'], $parsedToken['superUser'], $parsedToken['userGroup']);
                return [
                    "access_token" => $accessToken['token'],
                    "token_type" => "bearer",
                    "expires_in" => $accessToken["ttl"],
                    "scope" => "",
                ];
            } else {
                throw new GC2Exception("Refresh token was not provided", 400, null, 'INVALID_REQUEST');
            }
        }
        if ($data['grant_type'] == GrantType::AUTHORIZATION_CODE->value) {
            $token = Jwt::changeCodeForAccessToken($data['code']);
            Jwt::parse($token);
            (new \app\models\Client())->get($data['client_id']);
            return [
                "access_token" => $token,
                "token_type" => "bearer",
                "expires_in" => 666,
                "scope" => "",
            ];
        }
        throw new GC2Exception("grant_type must be either password or refresh_token", 400, null, 'INVALID_REQUEST');
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

    public function validate(): void
    {
        // TODO: Implement validateUser() method.
    }
}