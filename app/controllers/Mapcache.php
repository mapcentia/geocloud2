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

    private function authLayer($layer)
    {
        $_SESSION['http_auth'] = "";
        //die($_SESSION['http_auth']);
        if ($_SESSION['http_auth'] != $this->db) {
            \app\models\Database::setDb($this->db);
            $postgisObject = new \app\inc\Model();
            $auth = $postgisObject->getGeometryColumns($layer, "authentication");
            if ($auth == "Read/write") {
                include('inc/http_basic_authen.php');
            }
            $_SESSION['http_auth'] = $this->db;
        }
    }

    public function fetch()
    {
        $uriParts = array();
        $parts = explode("/", $_SERVER['REQUEST_URI']);
        for ($i = 0; $i < sizeof($parts); $i++) {
            if ($i == 2) {
                $b = explode("@", $parts[$i]);
                if (sizeof($b)>1) {
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
        $uri = implode("/",$uriParts);
        //die($uri);
        $layer = explode("@", $parts[5])[0];

        $this->authLayer($layer);
        $url = $this->host . $uri;

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
        exit();
    }
}
