<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\inc\Controller;

class Mapcache extends Controller
{

    /**
     * @return array<string>
     */
    public static function getGrids(): array
    {
        $gridNames = array();
        $pathToGrids = App::$param['path'] . "app/conf/grids/";
        $grids = @scandir($pathToGrids);
        if (!empty($grids)) foreach ($grids as $grid) {
            $bits = explode(".", $grid);
            if ($bits[1] == "xml") {
                $str = file_get_contents($pathToGrids . $grid);
                $xml = simplexml_load_string($str);
                if ($xml) {
                    $gridNames[(string)$xml->attributes()->name] = $str;
                }
            }
        }
        return $gridNames;
    }

    /**
     * @return array<string>
     */
    public static function getSources(): array
    {
        $arr = array();
        $pathToSources = App::$param['path'] . "app/conf/mapcache/sources/";
        return self::extracted($pathToSources, $arr);
    }

    /**
     * @return array<string>
     */
    public static function getTileSets(): array
    {
        $arr = array();
        $pathToTilesets = App::$param['path'] . "app/conf/mapcache/tilesets/";
        return self::extracted($pathToTilesets, $arr);
    }

    /**
     * @param string $pathToSources
     * @param array $arr
     * @return array
     */
    public static function extracted(string $pathToSources, array $arr): array
    {
        $sources = @scandir($pathToSources);
        if (!empty($sources)) foreach ($sources as $source) {
            $bits = explode(".", $source);
            if ($bits[1] == "xml") {
                $str = file_get_contents($pathToSources . $source);
                $xml = simplexml_load_string($str);
                if ($xml) {
                    $arr[] = $str;
                }
            }
        }
        return $arr;
    }
}