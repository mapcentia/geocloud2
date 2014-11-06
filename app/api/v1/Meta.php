<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Session;

class Meta extends \app\inc\Controller
{
    private $layers;

    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    public function get_index()
    {
        return $this->layers->getAll(Input::getPath()->part(5), Session::isAuth());
    }
}