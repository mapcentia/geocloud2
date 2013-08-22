<?php
namespace inc;

class Route
{
    public static function getRequest()
    {
        $request = explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
        return $request;
    }
    public function gedtRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));

        switch ($method) {
            case 'PUT':
                rest_put($request);
                break;
            case 'POST':
                rest_post($request);
                break;
            case 'GET':
                rest_get($request);
                break;
            case 'HEAD':
                rest_head($request);
                break;
            case 'DELETE':
                rest_delete($request);
                break;
            case 'OPTIONS':
                rest_options($request);
                break;
            default:
                rest_error($request);
                break;
        }
    }
}