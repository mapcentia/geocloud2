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
use app\auth\ResponseType;


/**
 * Class Jwt
 * @package app\inc
 */
class Jwt
{
    const int ACCESS_TOKEN_TTL = 360000000000;
    const int REFRESH_TOKEN_TTL = (3600 * 24 * 30);
    const int CODE_TTL = 30;

    /**
     * @return array
     * @throws GC2Exception
     */
    public static function validate(): array
    {
        // If OPTIONS we don't check token og return an empty response
        if (Input::getMethod() == 'options') {
            return ["success" => true, "data" => []];
        }
        // Check if there is a JWT token in header
        $jwtToken = Input::getJwtToken();
        if ($jwtToken) {
            $data = self::parse($jwtToken);
            if ($data['data']['response_type'] != ResponseType::TOKEN->value) {
                throw new GC2Exception("Not an access token", 400);
            }
            return $data;
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
        try {
            $decoded = (array)\Firebase\JWT\JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Exception $e) {
            header("WWW-Authenticate: Bearer error=\"invalid_token\" error_description=\"{$e->getMessage()}\"");
            throw new GC2Exception($e->getMessage(), 401, null, "INVALID_TOKEN");
        }
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
            header("WWW-Authenticate: Bearer error=\"invalid_token\" error_description=\"Could not extract payload from token\"");
            throw new GC2Exception("Could not extract payload from token", 400, null, "INVALID_TOKEN");
        }
        $response["success"] = true;
        $response["data"] = $arr;
        return $response;
    }

    /**
     * @param string $secret
     * @param string $db
     * @param string $userId
     * @param bool $isSuperUser
     * @param string|null $userGroup
     * @param bool $access
     * @param bool $returnCode
     * @return array
     */
    public static function createJWT(string $secret, string $db, string $userId, bool $isSuperUser, string|null $userGroup, bool $access = true, bool $returnCode = false): array
    {
        $token = [
            "iss" => App::$param["host"],
            "uid" => $userId,
            "exp" => time() + ($access  ? self::ACCESS_TOKEN_TTL : self::REFRESH_TOKEN_TTL),
            "iat" => time(),
            "database" => $db,
            "superUser" => $isSuperUser,
            "userGroup" => $userGroup,
            "response_type" => $access ? ResponseType::TOKEN->value : ResponseType::REFRESH->value,
        ];
        $encoded =   \Firebase\JWT\JWT::encode($token, $secret, "HS256");
        if (!$returnCode) {
            return [
                "token" => $encoded,
                "ttl" => $access ? self::ACCESS_TOKEN_TTL : self::REFRESH_TOKEN_TTL,
            ];
        } else {
            $code = uniqid();
            $CachedString = Cache::getItem($code);
            $CachedString->set($encoded)->expiresAfter(self::CODE_TTL);
            Cache::save($CachedString);
            return [
              "code" => $code
            ];
        }
    }

    /**
     * @param string $code
     * @return string
     * @throws GC2Exception
     */
    public static function changeCodeForAccessToken(string $code): string
    {
        $CachedString = Cache::getItem($code);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            throw new GC2Exception("No token matches the code", 400, null, 'INVALID_REQUEST');
        }
    }
}