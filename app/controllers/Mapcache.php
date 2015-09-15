<?php
namespace app\controllers;

class Mapcache extends \app\inc\Controller
{
    private $db;
    private $host;

    function __construct()
    {
        $this->db = \app\inc\Input::getPath()->part(2);
        $this->host = \app\conf\App::$param["mapcacheHost"];

        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->db = $dbSplit[1];
        }
        $this->fetch();
    }

    private function fetch()
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
                }
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
        $url = $this->host . $uri;
        //die($url);
        $type = get_headers($url, 1)["Content-Type"];
        //die($type);
        switch ($type) {
            case "application/xml":
                header('Content-type: text/xml');
                echo file_get_contents($url);
                exit();
                break;
            case "image/png":
                $this->basicHttpAuthLayer($layer, $this->db);
                $res = imagecreatefrompng($url);
                if (!$res) {
                    $response['success'] = false;
                    $response['message'] = "Could create tile";
                    $response['code'] = 406;
                    header("HTTP/1.0 {$response['code']} " . \app\inc\Util::httpCodeText($response['code']));
                    echo \app\inc\Response::toJson($response);
                    exit();
                }
                header('Content-type: image/png');
                header('X-Powered-By: GC2 MapCache');
                imageAlphaBlending($res, true);
                imageSaveAlpha($res, true);
                imagepng($res);
                imagedestroy($res);
                exit();
                break;
        }
    }
}