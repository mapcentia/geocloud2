<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\auth\types\ResponseType;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\models\Setting;
use Firebase\JWT\JWT;

/**
 * Mints short-lived, scoped JWTs for function invocations.
 *
 * The token carries the *invoking* user's identity (uid / superUser /
 * userGroup), so when a handler calls back into the GC2 data plane
 * (/api/v4/sql, /api/v4/call, GraphQL) the request runs with exactly the
 * caller's privileges - row rules and geofence are applied automatically by
 * Statement.php. It is signed with the database's super-user API key, the same
 * secret Jwt::parse() validates every token against, and is identifiable by a
 * "function" claim for auditing.
 */
abstract class FunctionToken
{
    public const int MIN_TTL = 30;
    public const int MAX_TTL = 900;

    /**
     * Mint a token for an invocation, resolving the signing secret from the
     * database's super-user API key.
     *
     * @param array $jwtData The invoking request's JWT data block.
     * @throws GC2Exception If no signing secret is available.
     */
    public static function mint(Connection $connection, array $jwtData, string $functionName, int $ttlSeconds): string
    {
        $secret = (new Setting($connection))->getApiKeyForSuperUser();
        if (empty($secret)) {
            throw new GC2Exception("Cannot mint function token: no super-user API key for database", 500, null, "FUNCTION_TOKEN_ERROR");
        }
        return self::mintWithSecret($secret, $jwtData, $functionName, $ttlSeconds);
    }

    /**
     * Mint a token with an explicit signing secret. Pure (no DB) - used by the
     * resolver above and directly in tests.
     *
     * @param array $jwtData Must contain at least uid and database.
     */
    public static function mintWithSecret(string $secret, array $jwtData, string $functionName, int $ttlSeconds): string
    {
        $ttl = max(self::MIN_TTL, min(self::MAX_TTL, $ttlSeconds));
        $now = time();
        $payload = [
            "iss" => App::$param["host"] ?? null,
            "uid" => $jwtData["uid"],
            "exp" => $now + $ttl,
            "iat" => $now,
            "database" => $jwtData["database"],
            "superUser" => $jwtData["superUser"] ?? false,
            "userGroup" => $jwtData["userGroup"] ?? null,
            "response_type" => ResponseType::TOKEN->value,
            "properties" => $jwtData["properties"] ?? null,
            "email" => $jwtData["email"] ?? null,
            // Marker so these tokens are auditable / can later be constrained.
            "function" => $functionName,
        ];
        return JWT::encode($payload, $secret, "HS256");
    }
}
