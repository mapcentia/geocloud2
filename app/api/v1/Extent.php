<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Session;

/**
 * Class Extent
 * @package app\api\v1
 */
class Extent extends \app\inc\Controller
{
    /**
     * @var \app\models\Layer
     */
    private $layers;

    /**
     * Extent constructor.
     */
    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    /**
     * @return mixed
     */
    public function get_index()
    {
        $layer = Input::getPath()->part(5);
        $extent = Input::getPath()->part(6) ?: "4326";
        return $this->layers->getEstExtent($layer, $extent);
    }
}