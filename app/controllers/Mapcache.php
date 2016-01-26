<?php
namespace app\controllers;

use \app\conf\App;

class Mapcache extends \app\inc\Controller
{
    private $db;
    private $host;
    private $subUser;
    private $type;

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
                    $url = "http://127.0.0.1" . "/wms/" . $this->db . "/" . $parts[4] . "?" . explode("?", explode("?", $uri)[1])[1];

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

        header("X-Powered-By: GC2 MapCache");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header_line) {
            $bits = explode(":", $header_line);
            if ($bits[0] == "Content-Type") {
                $this->type = trim($bits[1]);
            }
            // Send text/xml instead of application/vnd.ogc.se_xml
            if ($bits[0] == "Content-Type" && trim($bits[1]) == "application/vnd.ogc.se_xml"){
                header("Content-Type: text/xml");
            } elseif ($bits[0] != "Content-Encoding" && trim($bits[1]) != "chunked") {
                header($header_line);
            }
            return strlen($header_line);
        });
        $content = curl_exec($ch);
        curl_close($ch);

        // Check authentication level if image
        if (explode("/", $this->type)[0] == "image") {
            $this->basicHttpAuthLayer($layer, $this->db, $this->subUser);
        }

        // Return content
        echo $content;
        exit();
    }

    public function get_add()
    {
        echo file_get_contents(App::$param["mapCache"]["api"] . "/add?db=" . \app\inc\Input::getPath()->part(4));
        exit();
    }

    public static function reload()
    {
        $res = file_get_contents(App::$param["mapCache"]["api"] . "/reload");
        return $res;
    }

    public static function getGrids()
    {
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