<?php
namespace app\controllers;


class Session extends \app\inc\Controller
{

    public function get_log()
    {
        $response['data'] = \app\inc\Session::getLog();
        $response['success'] = true;
        return $response;
    }
    public function get_user()
    {
        $response['data']['db'] = $_SESSION['screen_name'];
        $response['data']['subuser'] = $_SESSION['subuser'];
        $response['data']['subusers'] = $_SESSION['subusers'];
        $response['success'] = true;
        return $response;
    }
}