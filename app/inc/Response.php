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
            header('Content-type: application/javascript');
            return $callback . '(' . json_encode($response) . ');';
        } else {
            header('Content-type: application/json');
            return json_encode($response);
        }
    }

    static function passthru($response)
    {
        $callback = Input::get('jsonp_callback');
        if ($callback) {
            header('Content-type: application/javascript');
            return $callback . '(' . ($response) . ');';
        } else {
            header('Content-type: application/json');
            return ($response);
        }
    }
}
