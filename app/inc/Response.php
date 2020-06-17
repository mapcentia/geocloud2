<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

class Response
{
    const SUPER_USER_ONLY = [
        "code" => 400,
        "success" => false,
        "message" => "Only a super user can use this API",
    ];

    static function json($response)
    {
        return $response;
    }

    static function toJson($response)
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

    static function passthru($response, $type = "application/json")
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
