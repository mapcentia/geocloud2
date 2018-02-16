<?php

namespace app\inc;

class Input
{
    /**
     * @var
     */
    static $params;

    /**
     *
     * @param array $arr
     */
    public static function setParams(array $arr)
    {
        self::$params = $arr;
    }

    /**
     * @return GetPart
     */
    public static function getPath(): GetPart
    {
        $request = explode("/", strtok($_SERVER["REQUEST_URI"], '?'));
        $obj = new GetPart($request);
        return $obj;
    }

    /**
     * @return string
     */
    public static function getMethod(): string
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return string
     */
    public static function getQueryString(): string
    {
        return $_SERVER['QUERY_STRING'];
    }

    /**
     * @return string
     */
    public static function getApiKey()
    {
        return $_SERVER['HTTP_GC2_API_KEY'];
    }

    /**
     * @return string
     */
    public static function getBody(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * @param string|null $key
     * @param bool $raw
     * @return array|mixed|string
     */
    public static function get(string $key = null, bool $raw = false)
    {

        if (isset(self::$params)) {

            if (isset($key)) {
                return self::$params[$key];
            } else {
                return self::$params;
            }

        }

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

    /**
     * @param string $str
     * @param bool $raw
     * @return array
     */
    static function parseQueryString(string $str, bool $raw = false): array
    {
        $op = [];
        $str = str_replace("+", "__gc2_plus__", $str);
        if ($raw) {
            return array($str => false);
        }
        $pairs = explode("&", $str);
        foreach ($pairs as $pair) {
            list($k, $v) = array_map("urldecode", explode("=", $pair));
            $op[$k] = $v;
        }
        return $op;
    }
}

/**
 * Class GetPart
 * @package app\inc
 */
class GetPart
{
    /**
     * @var array
     */
    private $parts;

    /**
     * GetPart constructor.
     * @param array $request
     */
    function __construct(array $request)
    {
        $this->parts = $request;
    }

    /**
     * @param string $e
     * @return string|null
     */
    function part(string $e)
    {
        return $this->parts[$e];
    }

    /**
     * @return array
     */
    function parts():array
    {
        return $this->parts;
    }
}