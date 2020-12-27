<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use phpDocumentor\Reflection\Types\Mixed;

class Response
{
    const SUPER_USER_ONLY = [
        "code" => 400,
        "success" => false,
        "message" => "Only a super user can use this API",
    ];

    /**
     * @param string $response
     * @return string
     */
    static function json(string $response): string
    {
        return $response;
    }

    /**
     * @param array<mixed> $response
     * @return string
     */
    static function toJson(array $response): string
    {
        $code = (isset($response["code"])) ? $response["code"] : "200";
        header("HTTP/1.0 {$code} " . Util::httpCodeText($code));
        $callback = Input::get('jsonp_callback') ?: Input::get('callback');
        if ($callback) {
            header('Content-type: application/javascript; charset=utf-8');
            return $callback . '(' . json_encode($response, JSON_UNESCAPED_UNICODE) . ');';
        } else {
            header('Content-type: application/json; charset=utf-8');
            return json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @param string $response
     * @param string $type
     * @return string
     */
    static function passthru(string $response, string $type = "application/json") : string
    {
        $callback = Input::get('jsonp_callback') ?: Input::get('callback');
        if ($callback) {
            header('Content-type: application/javascript; charset=utf-8');
            return $callback . '(' . ($response) . ');';
        } else {
            header('Content-type: ' . $type . '; charset=utf-8');
            return ($response);
        }
    }
}
