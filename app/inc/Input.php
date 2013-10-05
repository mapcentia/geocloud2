<?php
namespace app\inc;

class Input
{
    public static function getPath()
    {
        $request = explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
        $obj = new GetPart($request);
        return $obj;
    }

    public static function getMethod()
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public static function get($key = null)
    {
        $query = "";
        switch (static::getMethod()) {
            case "get":
                $query = $_GET;
                break;
            case "post":
                $query = $_POST;
                break;
            case "put":
                $query = static::parseQueryString(file_get_contents('php://input'));
                break;
            case "delete":
                $query = static::parseQueryString(file_get_contents('php://input'));
                break;
        }
        if (!reset($query))
            //$obj = new GetValue(key($query));
            return key($query);
        else {
            //$obj = new GetValue($query);
            return $query[$key];

        }

    }

    static function parseQueryString($str)
    {
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
}

class GetValue
{
    private $values;

    function __construct($query)
    {
        $this->values = $query;
    }

    function values($e = false)
    {
        return ($e) ? $this->values[$e] : $this->values;
    }

}