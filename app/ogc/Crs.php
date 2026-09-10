<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

/**
 * CRS identifiers for OGC API Features Part 2 / Maps: URI ↔ EPSG code, axis order and the
 * conversions into what the WFS engine (BBOX 5th element) and WMS 1.3.0 (CRS/BBOX) expect.
 * Internally every bbox is [minx, miny, maxx, maxy]; only the EPSG:4326 URI is lat/lon.
 */
final class Crs
{
    public const string CRS84 = 'http://www.opengis.net/def/crs/OGC/1.3/CRS84';
    public const string EPSG_PREFIX = 'http://www.opengis.net/def/crs/EPSG/0/';

    public static function uri(int $epsg): string
    {
        return self::EPSG_PREFIX . $epsg;
    }

    /** EPSG code of a CRS URI (CRS84 → 4326); null when not recognised. */
    public static function epsg(string $uri): ?int
    {
        if ($uri === self::CRS84) {
            return 4326;
        }
        if (str_starts_with($uri, self::EPSG_PREFIX)) {
            $code = substr($uri, strlen(self::EPSG_PREFIX));
            if ($code !== '' && ctype_digit($code)) {
                return (int)$code;
            }
        }
        return null;
    }

    /** True for identifiers whose axis order is latitude/longitude (the EPSG:4326 URI). */
    public static function isLatLon(string $uri): bool
    {
        return $uri === self::uri(4326);
    }

    /** @param list<float> $bbox as given, in the axis order of $uri  @return list<float> [minx,miny,maxx,maxy] */
    public static function toXY(array $bbox, string $uri): array
    {
        return self::isLatLon($uri) ? [$bbox[1], $bbox[0], $bbox[3], $bbox[2]] : $bbox;
    }

    /** 5th BBOX element for app\wfs\Request; WfsFilter::getAxisOrder decides the order from it. */
    public static function wfsBboxCrs(string $uri): string
    {
        if ($uri === self::CRS84) {
            return 'EPSG:4326';                   // longitude first
        }
        if (self::isLatLon($uri)) {
            return 'urn:ogc:def:crs:EPSG::4326';  // latitude first
        }
        return 'EPSG:' . self::epsg($uri);
    }

    /** CRS parameter for WMS 1.3.0 (CRS84 is sent as EPSG:4326 with a lat/lon BBOX). */
    public static function wmsCrs(string $uri): string
    {
        return 'EPSG:' . self::epsg($uri);
    }

    /** @param list<float> $xy [minx,miny,maxx,maxy] → WMS 1.3.0 BBOX string (EPSG:4326 is lat/lon in 1.3.0). */
    public static function wmsBbox(array $xy, string $uri): string
    {
        $b = self::epsg($uri) === 4326 ? [$xy[1], $xy[0], $xy[3], $xy[2]] : $xy;
        return implode(',', array_map([self::class, 'num'], $b));
    }

    /** Plain decimal notation without exponent or trailing zeros. */
    public static function num(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }
}
