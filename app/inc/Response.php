<?php
namespace app\inc;

class Response
{
    static function json($response)
    {
        $callback = $_GET['jsonp_callback'];
        if ($callback) {
            return $callback . '(' . json_encode($response) . ');';
        } else {
            return json_encode($response);
        }
    }
}
