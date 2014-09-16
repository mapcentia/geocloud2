<?php
namespace app\controllers;

use \app\inc\Input;

class Osm extends \app\inc\Controller
{
    private $osm;

    function __construct()
    {
        $this->osm = new \app\models\Osm();
    }

    public function put_view()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->osm->create(json_decode(Input::get(null, true)));
    }
    public function put_table()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->osm->create(json_decode(Input::get(null, true)),true);
    }
}