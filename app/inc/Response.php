<?php
namespace app\inc;

class Response
{
    static function json($response)
    {
        return $response;
    }

    static function toJson($response)
    {
        $callback = Input::get('jsonp_callback');
        if ($callback) {
            return $callback . '(' . json_encode($response) . ');';
        } else {
            return json_encode($response);
        }
    }

    static function passthru($response)
    {
        $callback = Input::get('jsonp_callback');
        if ($callback) {
            return $callback . '(' . ($response) . ');';
        } else {
            return ($response);
        }
    }
}
