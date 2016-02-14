<?php
namespace app\api\v1;

use \app\inc\Input;

class Ckan extends \app\inc\Controller
{
    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    public function get_index()
    {
        return ($this->authApiKey(Input::getPath()->part(4), Input::get("key"))) ? $this->layers->updateCkan(Input::get("id"), Input::get("host")) : false;
    }
}