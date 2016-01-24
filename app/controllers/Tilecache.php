<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;
use \app\conf\App;

class Tilecache extends \app\inc\Controller
{
    private $db;
    private $host;
    private $subUser;
    private $type;

    function __construct()
    {
        $this->db = \app\inc\Input::getPath()->part(2);
        $this->host = "http://127.0.0.1";
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
        $uri = implode("/", $uriParts);
        $layer = null;
        $url = null;
        switch (explode("?", $parts[3])[0]) {
            case "tms";
                $layer = explode("@", $parts[5])[0];
                $url = $this->host . "/cgi/tilecache.py" . "/" . $uriParts[4] . "/" . $uriParts[5] . "/" . $uriParts[6] . "/" . $uriParts[7] . "/" . $uriParts[8] . "?cfg=" . $this->db;
                break;
            case "wms";
                $get = array_change_key_case($_GET, CASE_UPPER);
                if (strtolower($get["REQUEST"]) == "getcapabilities" ||
                    strtolower($get["REQUEST"]) == "getlegendgraphic" ||
                    strtolower($get["REQUEST"]) == "getfeatureinfo" ||
                    strtolower($get["REQUEST"]) == "describefeaturetype" ||
                    isset($get["FORMAT_OPTIONS"]) == true
                ) {
                    $url = $this->host . "/ows/" . $this->db . "/" . $parts[4] . "?" . explode("?", $uri)[1];

                } else {
                    $layer = $get["LAYERS"];
                    $url = $this->host . "/cgi/tilecache.py" . "?" . explode("?", $uri)[1] . "&cfg=" . $this->db;
                }
                break;
        }
        //die(print_r($layer, true));
        $url = $url ?: $this->host . $uri;

        header("X-Powered-By: GC2 TileCache");

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

    public function delete_index()
    {
        if (Input::getPath()->part(4) === "schema") {
            $response = $this->auth(null, array());
            if (!$response['success']) {
                return $response;
            }
            $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/" . Input::getPath()->part(5) . ".*";
        } else {
            $parts = explode(".", Input::getPath()->part(4));
            $layer = $parts[0] . "." . $parts[1];
            $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));

            if (!$response['success']) {
                return $response;
            }
            $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/" . $layer;
        }
        $dir = str_replace("..", "", $dir);
        //$dirReal = realpath($dir); // Do not work on *
        if ($dir) {
            exec("rm -R {$dir}");
            if (strpos($dir, ".*") !== false) {
                $dir = str_replace(".*", "", $dir);
                exec("rm -R {$dir}");
            }
            $respons['success'] = true;
            $respons['message'] = "Tile cache deleted";
        } else {
            $respons['success'] = false;
            $respons['message'] = "No tile cache to delete.";
        }
        return Response::json($respons);
    }

    static function bust($layer)
    {
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/" . $layer;
        $dir = str_replace("..", "", $dir);
        if ($dir) {
            exec("rm -R {$dir}");
            if (strpos($dir, ".*") !== false) {
                $dir = str_replace(".*", "", $dir);
                exec("rm -R {$dir}");
            }
            $respons['success'] = true;
            $respons['message'] = "Tile cache deleted";
        } else {
            $respons['success'] = false;
            $respons['message'] = "No tile cache to delete.";
        }
        return $respons;
    }
}
