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
        return Response::json($this->update($_POST));
    }
    public function put_pw()
    {
        return Response::json($this->updatePw($_POST['pw']));
    }
    public function put_apikey()
    {
        return Response::json($this->updateApiKey());
    }
}


/*switch ($request[4]) {
    case "get": // All tables
        break;
    case "update": // All tables
        $response = $settings_viewer->update($_POST);
        break;
    case "updatepw": // All tables
        $response = $settings_viewer->updatePw($_POST['pw']);
        break;
    case "updateapikey": // All tables
        $response = $settings_viewer->updateApiKey();
        break;

}*/
