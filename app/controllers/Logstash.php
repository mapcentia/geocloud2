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