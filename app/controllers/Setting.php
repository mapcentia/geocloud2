<?php
namespace app\controllers;

use app\inc\Response;
use app\inc\Input;

class Setting extends \app\inc\Controller
{
    private $settings;

    function __construct()
    {
        $this->settings = new \app\models\Setting();
    }

    public function get_index()
    {
        return Response::json($this->settings->get());
    }

    public function put_index()
    {
        return Response::json($this->settings->update(Input::get()));
    }

    public function put_pw()
    {
        return Response::json($this->settings->updatePw(Input::get('pw')));
    }

    public function put_apikey()
    {
        return Response::json($this->settings->updateApiKey());
    }

    public function put_extent()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response : $this->settings->updateExtent(json_decode(Input::get())->data);
    }
}
