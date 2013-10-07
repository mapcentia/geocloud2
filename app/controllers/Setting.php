<?php
namespace app\controllers;

use app\inc\Response;

class Setting extends \app\inc\Controller
{
    private $settings;
    private $request;

    function __construct()
    {
        $this->request = \app\inc\Input::getPath();
        $this->settings = new \app\models\Setting();
    }
    public function get_index()
    {
        return Response::json($this->settings->get());
    }
    public function put_index()
    {
        return Response::json($this->settings->update($_POST));
    }
    public function put_pw()
    {
        return Response::json($this->settings->updatePw($_POST['pw']));
    }
    public function put_apikey()
    {
        return Response::json($this->settings->updateApiKey());
    }
}
