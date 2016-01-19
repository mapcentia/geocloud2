<?php
namespace app\controllers;

use \app\conf\App;

class Mapcache extends \app\inc\Controller
{
    private $db;
    private $host;
    private $subUser;

    function __construct()
    {
        $this->db = \app\inc\Input::getPath()->part(2);
        $this->host = \app\conf\App::$param["mapCache"]["host"];

        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
            $this->db = $dbSplit[1];
        } else {
            $this->subUser = null;
        }
    }

    public function fetch()
    {
        $uriParts = array();
        $parts = explode("/", $_SERVER['REQUEST_URI']);
        for ($i = 0; $i < sizeof($parts); $i++) {
            if ($i == 2) {
                $b = explode("@", $parts[$i]);
                if (sizeof($b) > 1) {
                    $parts[$i] = $b[1];
                }
            }
            $uriParts[] = $parts[$i];
        }
        $url = null;
        $uri = implode("/", $uriParts);
        $layer = null;
        switch (explode("?", $parts[3])[0]) {
            case "tms";
                $layer = explode("@", $parts[5])[0];
                break;
            case "wmts";
                if ($_SERVER["QUERY_STRING"]) {
                    $get = array_change_key_case($_GET, CASE_UPPER);
                    $layer = $get["LAYER"];
                } else {
                    $layer = explode("@", $parts[5])[0];
                    die($layer);
                }
                break;
            case "wms";
                $get = array_change_key_case($_GET, CASE_UPPER);
                if (
                    strtolower($get["REQUEST"]) == "getlegendgraphic" ||
                    strtolower($get["REQUEST"]) == "getfeatureinfo" ||
                    strtolower($get["REQUEST"]) == "describefeaturetype" ||
                    isset($get["FORMAT_OPTIONS"]) == true
                ) {
                    $url = "http://127.0.0.1" . "/wms/" . $this->db . "/" . $parts[4] . "?" . explode("?", explode("?",$uri)[1])[1];

                } else {
                    $layer = $get["LAYERS"];
                }
                break;
            case "gmaps";
                die("gmaps");
                break;
            case "kml";
                die("kml");
                break;
            case "ve";
                die("ve");
                break;
            case "mapguide";
                die("mapguide");
                break;
            default:
                die("What");
                break;
        }
        //die(print_r($layer, true));
        $url = $url ?: $this->host . $uri;
        //die($url);
        $headers = get_headers($url, 1);
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true),
        ));
        header("X-Powered-By: GC2 MapCache");
        header("Expires: {$headers["Expires"]}");
        switch ($headers["Content-Type"]) {
            case "text/plain":
                header('Content-type: text/plain');
                echo file_get_contents($url, false, $context);
                exit();
                break;
            case "application/xml":
                header('Content-type: application/xml');
                echo file_get_contents($url, false, $context);
                exit();
                break;
            case "application/vnd.ogc.se_xml":
                header('Content-type: application/xml');
                echo file_get_contents($url, false, $context);
                exit();
                break;
            case "text/xml":
                header('Content-type: text/xml');
                echo file_get_contents($url, false, $context);
                exit();
                break;
            case "image/png":
                $this->basicHttpAuthLayer($layer, $this->db, $this->subUser);
                header('Content-type: image/png');
                readfile($url);
                exit();
                break;
            case "image/jpeg":
                $this->basicHttpAuthLayer($layer, $this->db, $this->subUser);
                header('Content-type: image/jpeg');
                readfile($url);
                exit();
                break;
        }
    }
    public function get_add(){
        echo file_get_contents(App::$param["mapCache"]["api"] ."/add?db=" . \app\inc\Input::getPath()->part(4));
        exit();
    }

    public static function reload(){
        $res = file_get_contents(App::$param["mapCache"]["api"] . "/reload");
        return $res;
    }

    public static function getGrids() {
        $gridNames = array();
        $pathToGrids = App::$param['path'] . "app/conf/grids/";
        $grids = scandir($pathToGrids);
        foreach ($grids as $grid) {
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
}