<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\helpers;

final class NameSpaces
{
    public static function dropLastChrs(string $str, int $n): string
    {
        return substr($str, 0, strlen($str) - $n);
    }

    public static function dropFirstChrs(string $str, int $n): string
    {
        return substr($str, $n);
    }

    /** Strip xmlns:* attribute declarations from an XML body. */
    public static function dropNameSpace(string $xml): string
    {
        return preg_replace('/\s+xmlns:[a-zA-Z0-9]+="[^"]*"/', '', $xml);
    }

    /** Strip namespace prefixes from a comma-separated list of qualified names. */
    public static function dropAllNameSpaces(string $tag): string
    {
        $parts = explode(',', $tag);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            $colon = strpos($p, ':');
            $out[] = $colon === false ? $p : substr($p, $colon + 1);
        }
        return implode(',', $out);
    }
}
