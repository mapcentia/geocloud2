<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;
use \app\conf\App;

class Tilecache extends \app\inc\Controller
{
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
            $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/" .$layer;
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
    static function bust ($layer){
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/" .$layer;
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
