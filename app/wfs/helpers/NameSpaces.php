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

    /**
     * Strips namespace prefixes from element tags and removes most attributes
     * from an XML body, preserving a whitelist of WFS-protocol attributes
     * (service, version, outputFormat, maxFeatures, resultType, typeName,
     * srsName, fid, id) and the gml namespace. Direct port of the legacy
     * server.php:dropNameSpace() so XML_Unserializer downstream behavior
     * is unchanged.
     */
    public static function dropNameSpace(string $xml): string
    {
        $xml = preg_replace('/ \w*(?:\:\w*?)?(?<!gml)(?<!service)(?<!version)(?<!outputFormat)(?<!maxFeatures)(?<!resultType)(?<!typeName)(?<!srsName)(?<!fid)(?<!id)=(\".*?\"|\'.*?\')/s', '', $xml);
        $xml = preg_replace('/\<[a-z|0-9]*(?<!gml):(?:.*?)/', '<', $xml);
        $xml = preg_replace('/\<\/[a-z|0-9]*(?<!gml):(?:.*?)/', '</', $xml);
        return $xml;
    }

    /**
     * Strips all "prefix:" segments from a name. Also trims surrounding
     * double quotes, which OpenLayers adds to ogc:PropertyName in WFS
     * requests. Direct port of legacy server.php:dropAllNameSpaces().
     */
    public static function dropAllNameSpaces(string $tag): string
    {
        $tag = preg_replace('/[\w-]*:/', '', $tag);
        return trim($tag, '"');
    }
}
