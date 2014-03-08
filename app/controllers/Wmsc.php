<?php
namespace app\controllers;

class Wmsc extends \app\inc\Controller
{
    private $db;

    function __construct()
    {
        $this->db = \app\conf\Connection::$param['postgisdb'];
        if ($_SESSION['http_auth'] != \app\inc\Input::getPath()->part(2)) {
            \app\models\Database::setDb($this->db);
            $postgisObject = new \app\inc\Model();
            $auth = $postgisObject->getGeometryColumns(\app\inc\Input::get("LAYERS"), "authentication");
            if ($auth == "Read/write") {
                include('inc/http_basic_authen.php');
            }
        }
        $this->get_wms();
    }

    public function get_wms()
    {
        $url = \app\conf\App::$param['host'] . "/cgi/tilecache.py?cfg=" . $this->db . "&" . $_SERVER["QUERY_STRING"];
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
    }

    public function get_worldwind()
    {
    }
}
