<?php
namespace app\api\v1;

use \app\inc\Response;
use \app\inc\Input;
use \app\inc\Session;

class Loriot extends \app\inc\Controller
{
    private $settings;

    function __construct()
    {
    }

    public function post_index()
    {
        $data = json_decode(Input::get(), true);
        $loriot = new \app\models\Loriot();
        return $loriot->insert($data,Input::getPath()->part(5));
    }
}