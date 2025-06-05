<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\auth\types\ResponseType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\models\Database;
use app\models\Setting;
use Exception;
use Firebase\JWT\Key;
use Psr\Cache\InvalidArgumentException;
use stdClass;


/**
 * Class Jwt
 * @package app\inc
 */
abstract class Jwt
{
    const int ACCESS_TOKEN_TTL = 3600;
    const int REFRESH_TOKEN_TTL = (3600 * 24);
    const int CODE_TTL = 120;
    const int DEVICE_CODE_TTL = 1800;


    /**
     * @return array
     * @throws GC2Exception
     */
    public static function validate(?string $token = null): array
    {
        // If OPTIONS we don't check token og return an empty response
        if (Input::getMethod() == 'options') {
            return ["success" => true];
        }

        // Check if there is a JWT token in the header, or it is passed as a parameter
        $jwtToken = $token ?? Input::getJwtToken();
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
        $secret = (new Setting())->getApiKeyForSuperUser();
        try {
            $decoded = (array)\Firebase\JWT\JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Exception $e) {
//            header("WWW-Authenticate: Bearer error=\"invalid_token\" error_description=\"{$e->getMessage()}\"");
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
//            header("WWW-Authenticate: Bearer error=\"invalid_token\" error_description=\"Could not extract payload from token\"");
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
    public static function createJWT(string $secret, string $db, string $userId, bool $isSuperUser, ?string $userGroup, bool $access = true, bool $returnCode = false, ?string $codeChallenge = null, ?string $codeChallengeMethod = null, ?stdClass $properties = null, ?string $email = null): array
    {
        $token = [
            "iss" => App::$param["host"],
            "uid" => $userId,
            "exp" => time() + ($access ? self::ACCESS_TOKEN_TTL : self::REFRESH_TOKEN_TTL),
            "iat" => time(),
            "database" => $db,
            "superUser" => $isSuperUser,
            "userGroup" => $userGroup,
            "response_type" => $access ? ResponseType::TOKEN->value : ResponseType::REFRESH->value,
            "properties" => $properties,
            "email" => $email,
        ];
        $encoded = \Firebase\JWT\JWT::encode($token, $secret, "HS256");
        if (!$returnCode) {
            return [
                "token" => $encoded,
                "ttl" => $access ? self::ACCESS_TOKEN_TTL : self::REFRESH_TOKEN_TTL,
            ];
        } else {
            $code = uniqid();
            $CachedString = Cache::getItem($code);
            $CachedString->set([$encoded, $codeChallenge, $codeChallengeMethod])->expiresAfter(self::CODE_TTL);
            Cache::save($CachedString);
            return [
                "code" => $code
            ];
        }
    }

    /**
     * @param string $code
     * @param string|null $verifier
     * @return string
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function changeCodeForAccessToken(string $code, ?string $verifier = null): string
    {
        $CachedString = Cache::getItem($code);
        if ($CachedString != null && $CachedString->isHit()) {
            $challenge = $CachedString->get()[1];
            $method = $CachedString->get()[2] ?? 'plain';
            if (
                ($method == 'S256' && Util::base64urlEncode(pack('H*', hash('sha256', $verifier))) != $challenge) ||
                ($method == 'plain' && Util::base64urlEncode($verifier) != $challenge)
            ) {
                throw new GC2Exception("Invalid code", 400);
            }
            $token = $CachedString->get()[0];
            Cache::deleteItem($code);
            return $token;
        } else {
            throw new GC2Exception("No token matches the code", 400, null, 'INVALID_REQUEST');
        }
    }

    public static function createDeviceAndUserCode(): array
    {
        $userCode = self::generateUserCode();
        $deviceCode = uniqid();
        $CachedString = Cache::getItem($deviceCode);
        $CachedString->set($userCode)->expiresAfter(self::DEVICE_CODE_TTL);
        Cache::save($CachedString);
        $CachedString = Cache::getItem($userCode);
        $CachedString->set(1)->expiresAfter(self::DEVICE_CODE_TTL);
        Cache::save($CachedString);
        return [
            "device_code" => $deviceCode,
            "user_code" => $userCode,
        ];
    }

    /**
     * @throws GC2Exception
     */
    public static function checkDeviceCode(string $deviceCode): array
    {
        $CachedString = Cache::getItem($deviceCode);

        if ($CachedString != null && $CachedString->isHit()) {
            $userCode = $CachedString->get();
            $CachedString = Cache::getItem($userCode);
            if ($CachedString != null && $CachedString->isHit()) {
                $val = $CachedString->get();
                if (!empty($val) && $val == 1) {
                    throw new GC2Exception("authorization_pending", 400, null, 'AUTHORIZATION_PENDING');
                }
                if (!empty($val) && is_array($val)) {
                    return $val;
                }
            }
        }
        throw new GC2Exception("No device matches the code", 400, null, 'INVALID_REQUEST');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function clearDeviceCode(string $deviceCode): void
    {
        Cache::deleteItem($deviceCode);
    }

    private static function generateUserCode(): string
    {
        $characters = 'BCDFGHJKLMNPQRSTVWXZ';
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return substr($randomString, 0, 4) . '-' . substr($randomString, 4, 4);
    }
}