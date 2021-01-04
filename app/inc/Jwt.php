<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\models\Database;
use app\models\Setting;
use Exception;
use PDOException;


/**
 * Class Jwt
 * @package app\inc
 */
class Jwt
{
    const TOKEN_TTL = 3600;

    /**
     * @return array<mixed>
     */
    public static function validate(): array
    {
        // Check if there is a JWT token in header
        $jwtToken = Input::getJwtToken();
        if ($jwtToken) {
            return self::parse($jwtToken);
        } else {
            return ["code" => 400, "success" => false, "message" => "No token in header"];
        }
    }

    /**
     * @param string $token
     * @return array<mixed>
     */
    public static function parse(string $token): array
    {
        $response = [];
        // Try to extract the database from token
        $arr = self::extractPayload($token);
        if (!$arr["success"]) {
            return $arr;
        }
        // Get super user key, which are used for secret
        Database::setDb($arr["data"]["database"]);
        try {
            $settings_viewer = new Setting();
            $secret = $settings_viewer->getApiKeyForSuperUser();
        } catch (PDOException $exception) {
            $response["success"] = false;
            $response["message"] = $exception->getMessage();
            return $response;
        }

        try {
            $decoded = (array)\Firebase\JWT\JWT::decode($token, $secret, ['HS256']);
        } catch (Exception $exception) {
            $response["success"] = false;
            $response["message"] = $exception->getMessage();
            return $response;
        }

        $response["success"] = true;
        $response["data"] = $decoded;
        return $response;
    }

    /**
     * @param string $token
     * @return array<mixed>
     */
    public static function extractPayload(string $token): array
    {
        $response = [];
        // Try to extract the database from token
        $arr = json_decode(base64_decode(explode(".", $token)[1]), true);
        if (!$arr) {
            $response["success"] = false;
            $response["message"] = "Payload could not be extracted from token";
            return $response;
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
     * @return array<mixed>
     */
    public static function createJWT(string $secret, string $db, string $userId, bool $isSubUser): array
    {
        $token = [
            "iss" => App::$param["host"],
            "uid" => $userId,
            "exp" => time() + self::TOKEN_TTL,
            "iat" => time(),
            "database" => $db,
            "superUser" => $isSubUser,
        ];
        return [
            "token" => \Firebase\JWT\JWT::encode($token, $secret),
            "ttl" => self::TOKEN_TTL,
        ];
    }
}