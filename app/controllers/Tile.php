<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Tile extends \app\inc\Controller
{
    private $wmslayer;

    function __construct()
    {
        $this->wmslayer = new \app\models\Tile(Input::getPath()->part(4));
    }

    public function get_index()
    {
        return Response::json($this->wmslayer->get());
    }

    public function put_index()
    {
        return Response::json($this->wmslayer->update(Input::get('data')));
    }

    public function get_fields()
    {
        return Response::json($this->wmslayer->getfields());
    }
}
