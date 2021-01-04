<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\inc\Controller;
use app\inc\Input;

class Mapcache extends Controller
{
    /**
     * @var string|null
     */
    private $db;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string|null
     */
    private $subUser;

    function __construct()
    {
        parent::__construct();

        $this->db = Input::getPath()->part(2);
        $this->host = App::$param["mapCache"]["host"];

        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
            $this->db = $dbSplit[1];
        } else {
            $this->subUser = null;
        }
    }

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

    /**
     * @return array<string>
     */
    public static function getTileSets(): array
    {
        $arr = array();
        $pathToTilesets = App::$param['path'] . "app/conf/mapcache/tilesets/";
        $tilesets = @scandir($pathToTilesets);
        if (!empty($tilesets)) foreach ($tilesets as $tileset) {
            $bits = explode(".", $tileset);
            if ($bits[1] == "xml") {
                $str = file_get_contents($pathToTilesets . $tileset);
                $xml = simplexml_load_string($str);
                if ($xml) {
                    $arr[] = $str;
                }
            }
        }
        return $arr;
    }
}