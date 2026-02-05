<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


/**
 * Class Input
 * @package app\inc
 */
class Input
{
    /**
     * @var array<string>
     */
    static ?array $params = null;
    static ?string $body = null;
    const string TEXT_PLAIN = "text/plain";
    const string APPLICATION_JSON = "application/json";
    const string APPLICATION_X_WWW_FORM_URLENCODED = "application/x-www-form-urlencoded";
    const string MULTIPART_FORM_DATA = "multipart/form-data";

    /**
     *
     * @param array<string> $arr
     * DEPRECATED
     */
    public static function setParams(?array $arr): void
    {
        self::$params = $arr;
    }

    /**
     * @return GetPart
     */
    public static function getPath(): GetPart
    {
        $request = explode("/", strtok($_SERVER["REQUEST_URI"], '?'));
        return new GetPart($request);
    }

    /**
     * @return string|null
     */
    public static function getMethod(): ?string
    {
        return isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : null;
    }

    public static function getAccessControlRequestMethod(): ?string
    {
        return $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null;
    }

    /**
     * @return string|null
     */
    public static function getAccept(): ?string
    {
        return $_SERVER['HTTP_ACCEPT'];
    }

    /**
     * @return string|null
     */
    public static function getContentType(): ?string
    {
        return $_SERVER["CONTENT_TYPE"] ?? null;
    }

    /**
     * @return string
     */
    public static function getQueryString(): string
    {
        return $_SERVER['QUERY_STRING'];
    }

    /**
     * @return string|null
     */
    public static function getApiKey(): ?string
    {
        return $_SERVER['HTTP_GC2_API_KEY'] ?? null;
    }

    /**
     * @return bool
     */
    public static function getDryRun(): bool
    {
        return $_SERVER['HTTP_X_DRY_RUN'] ?? false;
    }

    /**
     * @return string|null
     */
    public static function getJwtToken(): ?string
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
     * @param bool $decode
     * @return string|null
     */
    public static function getBody(bool $decode = false): ?string
    {
        $content = file_get_contents('php://input');
        if (empty($content)) {
            return null;
        }
        if ($decode) {
            return urldecode($content);
        } else {
            return $content;
        }
    }

    /**
     * @return array<string>
     */
    public static function getCookies(): array
    {
        return $_COOKIE;
    }

    /**
     * @return string|null
     */
    public static function getAuthUser(): ?string
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        // Check for deprecated form: subuser@database
        if (!empty($user) && str_contains($user, '@')) {
            $user = explode('@', $user)[0];
        }
        return $user;
    }

    /**
     * @return string|null
     */
    public static function getAuthPw(): ?string
    {
        return $_SERVER['PHP_AUTH_PW'];
    }

    /**
     * @param string|null $key
     * @param bool $raw
     * @return mixed
     */
    public static function get(?string $key = null, bool $raw = false): mixed
    {

        if (isset(self::$params)) {

            if (isset($key)) {
                return self::$params[$key] ?? null;
            } else {
                return self::$params;
            }
        }

        $query = [];

        switch (static::getMethod()) {
            case "get":
                $query = $_GET;
                break;
            case "put":
            case "delete":
            case "post":
            case "patch":
                $query = static::parseQueryString(file_get_contents('php://input'), $raw);
                break;
        }

        if (!reset($query) && $key == null && sizeof($query) > 0)
            return str_replace("__gc2_plus__", "+", key($query));
        else {
            if ($key != null)
                return $query[$key] ?? null;
            else
                return $query;
        }

    }

    /**
     * @param string $str
     * @param bool $raw
     * @return array
     */
    private static function parseQueryString(string $str, bool $raw = false): array
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

