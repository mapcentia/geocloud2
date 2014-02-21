<?php
namespace app\controllers;

class Tms extends \app\inc\Controller
{
    function __construct(){
        $this->get_index();
    }
    public function get_index()
    {
        $db = \app\inc\Input::getPath()->part(2);
        $url = "http://local2.mapcentia.com/cgi/tilecache.fcgi?cfg=" . $db . "&" . $_SERVER["QUERY_STRING"];

        $res = @imagecreatefrompng($url);
        if (!$res){
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
}
