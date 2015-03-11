<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Session;

class Extent extends \app\inc\Controller
{
    private $layers;

    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    public function get_index()
    {
        $layer = Input::getPath()->part(5);
        $extent = Input::getPath()->part(6) ?: "4326";
        return $this->layers->getExtent($layer, $extent);
    }
}