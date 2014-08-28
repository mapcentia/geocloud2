<?php
namespace app\controllers;

class Wmsc extends \app\inc\Controller
{
    private $db;

    function __construct()
    {
        $this->db = \app\inc\Input::getPath()->part(2);
        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->db = $dbSplit[1];
        }
        if ($_SESSION['http_auth'] != $this->db) {
            //error_log("WMS-C auth");
            \app\models\Database::setDb($this->db);
            $postgisObject = new \app\inc\Model();
            if ($_SERVER["QUERY_STRING"]) {
                $auth = $postgisObject->getGeometryColumns(\app\inc\Input::get("LAYERS"), "authentication");
            }
            else {
                $parts = explode("/", $_SERVER['REQUEST_URI']);
                $auth = $postgisObject->getGeometryColumns($parts[4], "authentication");
            }
            if ($auth == "Read/write" || $auth == "Write") {
                include('inc/http_basic_authen.php');
            }
            $_SESSION['http_auth'] = $this->db;
        }
        if ($_SERVER["QUERY_STRING"]) {
            $this->get_wms();
        } else {
            $this->get_tms();
        }
    }

    public function get_wms()
    {
        $url = "http://127.0.0.1/cgi/tilecache.py?cfg=" . $this->db . "&" . $_SERVER["QUERY_STRING"];
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
        imageAlphaBlending($res, true);
        imageSaveAlpha($res, true);
        imagepng($res);
        exit();
    }

    public function get_tms()
    {

        $parts = explode("/", $_SERVER['REQUEST_URI']);
        $url = "http://127.0.0.1/cgi/tilecache.py/{$parts[3]}/{$parts[4]}/{$parts[5]}/{$parts[6]}/{$parts[7]}?cfg={$this->db}";
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
        imageAlphaBlending($res, true);
        imageSaveAlpha($res, true);
        imagepng($res);
        exit();
    }

    public function get_worldwind()
    {
    }
}
