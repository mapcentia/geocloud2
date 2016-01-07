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
        //die($url);
        $type = get_headers($url, 1)["Content-Type"];
        //die($type);
        header('X-Powered-By: GC2 TileCache');

        switch ($type) {
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
