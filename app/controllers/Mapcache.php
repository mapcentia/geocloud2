<?php
namespace app\controllers;

class Mapcache extends \app\inc\Controller
{
    private $db;
    private $host;
    private $subUser;

    function __construct()
    {
        $this->db = \app\inc\Input::getPath()->part(2);
        $this->host = \app\conf\App::$param["mapCache"]["mapcacheHost"];

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
            if ($i == 3) {
                if ($parts[$i] == "xyz") {
                    $parts[$i] = "gmaps";
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
                if (strtolower($get["REQUEST"]) == "getcapabilities" ||
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
                break;
            case "xyz";
                die("xyz");
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
        $type = get_headers($url, 1)["Content-Type"];
        //$type = "image/png";
        //die($type);

        header('X-Powered-By: GC2 MapCache');

        switch ($type) {
            case "text/plain":
                header('Content-type: text/plain');
                $response['success'] = false;
                $response['message'] = "Could create tile";
                $response['code'] = 406;
                header("HTTP/1.0 {$response['code']} " . \app\inc\Util::httpCodeText($response['code']));
                echo \app\inc\Response::toJson($response);
                exit();
                break;
            case "application/xml":
                header('Content-type: application/xml');
                echo file_get_contents($url);
                exit();
                break;
            case "text/xml":
                header('Content-type: text/xml');
                echo file_get_contents($url);
                exit();
                break;
            case "image/png":
                $this->basicHttpAuthLayer($layer, $this->db, $this->subUser);
                header('Content-type: image/png');
                readfile($url);
                exit();
                break;
        }
    }
    public function get_add(){
        echo file_get_contents("http://127.0.0.1:1337/add?db=" . \app\inc\Input::getPath()->part(4));
        exit();
    }

    public function get_reload(){
        echo file_get_contents("http://127.0.0.1:1337/reload");
        exit();
    }
}