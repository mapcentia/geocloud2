<?php
namespace app\inc;

class Route
{
    public static function getRequest()
    {
        $request = explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
        return $request;
    }
    public static function getMethod(){
        return strtolower($_SERVER['REQUEST_METHOD']);
    }
}