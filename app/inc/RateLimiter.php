<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;
use DateTime;

class RateLimiter
{
    /**
     * Applies rate limiting based on a provided JWT (JSON Web Token).
     *
     * Depending on configuration, the rate limiting can be implemented
     * using a fixed or sliding window approach. If no token is provided,
     * the method does not perform any rate limiting.
     *
     * @param string|null $jwtToken The JWT to be used for identifying the consumer.
     *                              If null, no rate-limit actions will be applied.
     * @param int|null $maxPerMinute The maximum number of allowed requests per minute.
     *                               Defaults to a configured value if null. If set to a non-positive
     *                               value, rate limiting is disabled.
     *
     * @return void
     * @throws GC2Exception
     */
    public static function consumeForJwt(?string $jwtToken, ?int $maxPerMinute = null): void
    {
        if (!$jwtToken) {
            // No token -> nothing to limit here (v4 endpoints that require token will fail elsewhere if missing)
            return;
        }

        $limit = $maxPerMinute ?? (App::$param['apiV4']['rateLimitPerMinute'] ?? 120);
        if ($limit <= 0) {
            // Non-positive -> treat as disabled
            return;
        }

        $mode = strtolower(App::$param['apiV4']['rateLimitMode'] ?? 'fixed');
        if ($mode === 'sliding') {
            self::consumeSlidingWindow($jwtToken, $limit, (int)(App::$param['apiV4']['rateLimitWindowSeconds'] ?? 60));
            return;
        }

        // Default: fixed window per minute
        self::consumeFixedWindow($jwtToken, $limit);
    }

    /**
     * Fixed-window limiting per calendar minute
     * @throws GC2Exception
     */
    private static function consumeFixedWindow(string $jwtToken, int $limit): void
    {
        $hash = substr(hash('sha256', $jwtToken), 0, 32);
        $bucket = (new DateTime('now'))->format('YmdHi'); // fixed window per minute
        $key = "ratelimit_v4_fw_{$hash}{$bucket}";

        $item = Cache::getItem($key);
        $count = 0;
        if ($item->isHit()) {
            $count = (int)($item->get() ?? 0);
        }
        $count++;
        $item->set($count)->expiresAfter(70); // expire slightly after a minute window
        Cache::save($item);

        if ($count > $limit) {
            throw new GC2Exception("Too Many Requests", 429, null, "RATE_LIMIT_EXCEEDED");
        }
    }

    /**
     * Sliding-window limiting over the last N seconds (default 60), per JWT.
     * Stores a small map of second=>count for the recent window.
     * @throws GC2Exception
     */
    private static function consumeSlidingWindow(string $jwtToken, int $limit, int $windowSeconds = 60): void
    {
        if ($windowSeconds <= 0) {
            $windowSeconds = 60;
        }
        $now = time();
        $currentSec = $now; // unix second resolution
        $cutoff = $now - ($windowSeconds - 1);

        $hash = substr(hash('sha256', $jwtToken), 0, 32);
        $key = "ratelimit_v4_sw_$hash";

        $item = Cache::getItem($key);
        $bucket = [];
        if ($item->isHit()) {
            $data = $item->get();
            if (is_array($data)) {
                $bucket = $data;
            }
        }

        // Prune old entries and sum
        $sum = 0;
        foreach ($bucket as $sec => $cnt) {
            // Ensure integer keys and values
            $secInt = (int)$sec;
            $cntInt = (int)$cnt;
            if ($secInt >= $cutoff) {
                $sum += $cntInt;
            } else {
                unset($bucket[$sec]);
            }
        }

        // Increment current second
        if (!isset($bucket[$currentSec])) {
            $bucket[$currentSec] = 0;
        }
        $bucket[$currentSec]++;
        $sum++;

        // Save updated bucket with a short TTL beyond the window
        $ttl = $windowSeconds + 10;
        $item->set($bucket)->expiresAfter($ttl);
        Cache::save($item);

        if ($sum > $limit) {
            throw new GC2Exception("Too Many Requests", 429, null, "RATE_LIMIT_EXCEEDED");
        }
    }
}
