<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\exceptions\GC2Exception;
use app\ows\RuleFilters;

/** Query-parameter validation shared by the OGC controllers. Every failure is a 400. */
final class Params
{
    /** OGC API Common: unknown query parameters are rejected. */
    public static function assertKnown(array $query, array $known): void
    {
        foreach (array_keys($query) as $k) {
            if (!in_array((string)$k, $known, true)) {
                throw new GC2Exception("Unknown query parameter '$k'", 400, null, 'UNKNOWN_PARAMETER');
            }
        }
    }

    /** JSON endpoints accept f=json only. */
    public static function assertJson(array $query): void
    {
        if (isset($query['f']) && $query['f'] !== 'json') {
            throw new GC2Exception("Unsupported format '{$query['f']}'", 400, null, 'UNSUPPORTED_FORMAT');
        }
    }

    public static function int(array $query, string $name, int $default, int $min, int $max): int
    {
        if (!isset($query[$name]) || $query[$name] === '') {
            return $default;
        }
        $raw = (string)$query[$name];
        if (!preg_match('/^-?\d+$/', $raw)) {
            throw new GC2Exception("Parameter '$name' must be an integer", 400, null, 'INVALID_PARAMETER');
        }
        $v = (int)$raw;
        if ($v < $min) {
            throw new GC2Exception("Parameter '$name' must be at least $min", 400, null, 'INVALID_PARAMETER');
        }
        return min($v, $max);
    }

    /** @return list<float>|null [a,b,c,d] exactly as given (4 numbers, or 6 with the vertical pair dropped) */
    public static function bbox(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 4 && count($parts) !== 6) {
            throw new GC2Exception('Parameter bbox must have 4 (or 6) numbers', 400, null, 'INVALID_PARAMETER');
        }
        foreach ($parts as $p) {
            if (!is_numeric($p)) {
                throw new GC2Exception('Parameter bbox must be numeric', 400, null, 'INVALID_PARAMETER');
            }
        }
        $n = array_map('floatval', $parts);
        $b = count($n) === 6 ? [$n[0], $n[1], $n[3], $n[4]] : $n;
        if ($b[0] > $b[2] || $b[1] > $b[3]) {
            throw new GC2Exception('Parameter bbox lower corner must not exceed upper corner', 400, null, 'INVALID_PARAMETER');
        }
        return $b;
    }

    /** @param list<string> $allowed */
    public static function crs(?string $raw, array $allowed, string $name): string
    {
        if ($raw === null || $raw === '') {
            return Crs::CRS84;
        }
        if (!in_array($raw, $allowed, true)) {
            throw new GC2Exception("Parameter '$name': unsupported CRS '$raw'", 400, null, 'INVALID_CRS');
        }
        return $raw;
    }

    /** An ISO 8601 instant, validated before it can reach SQL. Intervals are not supported (yet). */
    public static function datetime(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (str_contains($raw, '/')) {
            throw new GC2Exception('Parameter datetime: intervals are not supported, pass an instant', 400, null, 'DATETIME_INTERVAL_UNSUPPORTED');
        }
        if (!RuleFilters::isInstant($raw)) {
            throw new GC2Exception('Parameter datetime must be an ISO 8601 instant', 400, null, 'INVALID_PARAMETER');
        }
        return $raw;
    }

    public static function bool(array $query, string $name, bool $default): bool
    {
        if (!isset($query[$name]) || $query[$name] === '') {
            return $default;
        }
        $v = strtolower((string)$query[$name]);
        if (in_array($v, ['true', '1', 'yes'], true)) return true;
        if (in_array($v, ['false', '0', 'no'], true)) return false;
        throw new GC2Exception("Parameter '$name' must be true or false", 400, null, 'INVALID_PARAMETER');
    }

    /** RRGGBB or 0xRRGGBB → RRGGBB */
    public static function bgcolor(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $hex = preg_replace('/^0x/i', '', $raw);
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new GC2Exception('Parameter bgcolor must be 0xRRGGBB', 400, null, 'INVALID_PARAMETER');
        }
        return $hex;
    }
}
