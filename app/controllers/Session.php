<?php
namespace app\controllers;


class Session extends \app\inc\Controller
{

    function __construct()
    {
    }

    public function get_log()
    {
        $response['data'] = \app\inc\Session::getLog();
        $response['success'] = true;
        return $response;
    }
}