<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use phpDocumentor\Reflection\Types\Integer;

class Input
{
    /**
     * @var
     */
    static $params;
    const TEXT_PLAIN = "text/plain";
    const APPLICATION_JSON = "application/json";
    const APPLICATION_X_WWW_FORM_URLENCODED = "application/x-www-form-urlencoded";
    const MULTIPART_FORM_DATA = "multipart/form-data";

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
    public static function getContentType(): string
    {
        return $_SERVER["CONTENT_TYPE"];
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
     * @return mixed|null
     */
    public static function getJwtToken()
    {
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            list($type, $data) = explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2);
            if (strcasecmp($type, "Bearer") == 0) {
                return $data;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public static function getBody(): string
    {
        return urldecode(file_get_contents('php://input'));
    }

    public static function getCookies(): array
    {
        return $_COOKIE;
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
                return isset(self::$params[$key]) ? self::$params[$key] : null;
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
                return isset($query[$key]) ? $query[$key] : null;
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
            list($k, $v) = array_pad(array_map("urldecode", explode("=", $pair)), 2, null);
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
     * @param int $e
     * @return string|null
     */
    function part(int $e)
    {
        return isset($this->parts[$e]) ? $this->parts[$e] : null;
    }

    /**
     * @return array<string>
     */
    function parts(): array
    {
        return $this->parts;
    }
}