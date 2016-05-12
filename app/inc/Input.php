<?php
namespace app\inc;

class Input
{
    public static function getPath()
    {
        $request = explode("/", strtok($_SERVER["REQUEST_URI"], '?'));
        $obj = new GetPart($request);
        return $obj;
    }

    public static function getMethod()
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public static function get($key = null, $raw = false)
    {
        $query = "";
        switch (static::getMethod()) {
            case "get":
                $query = $_GET;
                break;
            case "post":
                $query = static::parseQueryString(file_get_contents('php://input'), $raw);
                break;
            case "put":
                $query = static::parseQueryString(file_get_contents('php://input'), $raw);
                break;
            case "delete":
                $query = static::parseQueryString(file_get_contents('php://input'), $raw);
                break;
        }
        if (!reset($query) && $key == null)
            return str_replace("__gc2_plus__", "+", key($query));
        else {
            if ($key != null)
                return $query[$key];
            else
                return $query;
        }
    }

    static function parseQueryString($str, $raw)
    {
        $str = str_replace("+", "__gc2_plus__", $str);
        if ($raw) {
            return array($str => false);
        }
        $op = array();
        $pairs = explode("&", $str);
        foreach ($pairs as $pair) {
            list($k, $v) = array_map("urldecode", explode("=", $pair));
            $op[$k] = $v;
        }
        return $op;
    }
}

class GetPart
{
    private $parts;

    function __construct($request)
    {
        $this->parts = $request;
    }

    function part($e)
    {
        return $this->parts[$e];
    }

    function parts()
    {
        return $this->parts;
    }
}