<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\models\Database;
use app\models\Setting;
use Exception;
use Firebase\JWT\Key;


/**
 * Class Jwt
 * @package app\inc
 */
class Jwt
{
    const int TOKEN_TTL = 36000000;
    const int CODE_TTL = 60000;

    /**
     * @return array
     * @throws GC2Exception
     */
    public static function validate(): array
    {
        // Check if there is a JWT token in header
        $jwtToken = Input::getJwtToken();
        if ($jwtToken) {
            return self::parse($jwtToken);
        } else {
            throw new GC2Exception("No token in header", 400);
        }
    }

    /**
     * @param string $token
     * @return array
     * @throws GC2Exception
     */
    public static function parse(string $token): array
    {
        $response = [];
        // Try to extract the database from token
        $arr = self::extractPayload($token);
        if (!$arr["success"]) {
            return $arr;
        }
        // Get superuser key, which are used for secret
        Database::setDb($arr["data"]["database"]);
        $settings_viewer = new Setting();
        $secret = $settings_viewer->getApiKeyForSuperUser();
        $decoded = (array)\Firebase\JWT\JWT::decode($token, new Key($secret, 'HS256'));
        $response["success"] = true;
        $response["data"] = $decoded;
        return $response;
    }

    /**
     * @param string $token
     * @return array
     * @throws GC2Exception
     */
    public static function extractPayload(string $token): array
    {
        $response = [];
        $arr = null;
        $exception = false;
        // Try to extract the database from token
        if (!isset(explode(".", $token)[1])) {
            $exception = true;
        }
        if (!$exception) {
            try {
                $arr = json_decode(base64_decode(explode(".", $token)[1]), true);
            } catch (Exception) {
                $exception = true;
            }
        }
        if (!$arr) {
            $exception = true;
        }
        if ($exception) {
            throw new GC2Exception("Could not extract payload from token", 400, null, "JWT_PAYLOAD_ERROR");
        }
        $response["success"] = true;
        $response["data"] = $arr;
        return $response;
    }

    /**
     * @param string $secret
     * @param string $db
     * @param string $userId
     * @param bool $isSubUser
     * @param string|null $userGroup
     * @param string $responseType
     * @return array
     */
    public static function createJWT(string $secret, string $db, string $userId, bool $isSubUser, string|null $userGroup, string $responseType): array
    {
        $token = [
            "iss" => App::$param["host"],
            "uid" => $userId,
            "exp" => time() + ($responseType == "access" ? self::TOKEN_TTL : self::CODE_TTL),
            "iat" => time(),
            "database" => $db,
            "superUser" => $isSubUser,
            "userGroup" => $userGroup,
            "response_type" => $responseType,
        ];
        return [
            "token" => \Firebase\JWT\JWT::encode($token, $secret, "HS256"),
            "ttl" => $responseType == "access" ? self::TOKEN_TTL : self::CODE_TTL,
        ];
    }
}