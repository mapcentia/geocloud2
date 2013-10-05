<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Tilecache extends \app\inc\Controller
{
    public function delete_index(){
        global $basePath;
        if (Input::getPath()->part(3) === "schema") {
            $dir = $basePath . "tmp/" . \Connection::$param["postgisschema"] . "/" . Input::getPath()->part(4) . ".*";
        } else {
            $dir = $basePath . "tmp/" .  \Connection::$param["postgisschema"] . "/" .  Input::getPath()->part(3);
        }
        $dir = str_replace("..", "", $dir);
        //$dirReal = realpath($dir); // Do not work on *
        if ($dir) {
            exec("rm -R {$dir}");
            $respons['success'] = true;
            $respons['message'] = "Tile cache invalidated";
        } else {
            $respons['success'] = false;
            $respons['message'] = "No tile cache to invalidate.";
        }
        return Response::json($respons);
    }
}
