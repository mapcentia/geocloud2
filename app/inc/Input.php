<?php
namespace app\inc;

class Input
{
    public static function getPath()
    {
        $request = explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
        return $request;
    }

    public static function getMethod()
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public static function getQuery()
    {
        $query = "";
        switch (static::getMethod()){
            case "get":
                $query = $_GET;
                break;
            case "post":
                $query = $_POST;
                break;
            case "put":
                parse_str(file_get_contents('php://input'), $_PUT);
                $query = $_PUT;
                break;
            case "delete":
                parse_str(file_get_contents('php://input'), $_DELETE);
                $query = $_DELETE;
                break;
        }
        return $query;
    }
}