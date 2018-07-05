<?php
namespace app\api\v2;

use \app\inc\Route;

/**
 * Class Qgis
 * @package app\api\v1
 */
class Qgis extends \app\inc\Controller
{
    private $qgis;
    /**
     * Qgis constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->qgis = new \app\models\Qgis();
    }

    /**
     * @return array|bool
     */
    public function get_write()
    {
        return $this->qgis->writeAll(Route::getParam("user"));
    }
}