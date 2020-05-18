<?php

namespace app\extensions\vidi_cookie_getter\api;

use app\inc\Util;
use app\inc\Input;
use \GuzzleHttp\Client;


class Request extends \app\inc\Controller
{


    function __construct()
    {
        parent::__construct();
    }

    public function get_index()
    {
        $client = new \GuzzleHttp\Client(['cookies' => true]);
        $input = [
            'user' => Input::get("user"),
            'password' => Input::get("password"),
            'database' => Input::get("database"),
            'schema' => "public",
        ];

        //print_r(\GuzzleHttp\json_encode($input));

        try {
            $res = $client->post('https://webgis.digitaleplaner.dk/api/session/start', [
                'headers' => array('Content-Type' => 'application/json'),
                'json' => $input]);
            $cookieJar = $client->getConfig('cookies');
        } catch (\Exception $error) {
            return [
                "success" => false,
                "code" => "400",
                "message" => $error->getMessage(),
            ];
        }
        return [
            "success" => true,
            "session" => ($cookieJar->toArray()[0]['Value']),
        ];
    }
}