<?php
namespace app\controllers;

use \app\inc\Input;
use \app\inc\Util;
use \app\conf\App;

class Logstash extends \app\inc\Controller
{
    protected $guest;
    protected $host;

    function __construct()
    {
        $this->clientIp = Util::clientIp();
        $this->host = App::$param['logstashHost'] ?: "http://127.0.0.1:1337";
    }

    public function post_index()
    {
        $content = urldecode(Input::get());
        $obj = json_decode($content);
        $query = $obj->body->query->filtered->query->query_string->query;
        $split = explode(" ", $query);
        if ($split[0] != $_SESSION["screen_name"]) {
            die("What");
        }
        $ch = curl_init($this->host . "/data");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }
}