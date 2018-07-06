<?php
namespace app\api\v2;

use \app\inc\Route;
use \app\conf\Connection;

/**
 * Class Files
 * @package app\api\v1
 */
class Mapfile extends \app\inc\Controller
{
    private $mapFile;
    /**
     * Mapfile constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->mapFile = new \app\controllers\Mapfile();
    }

    /**
     * @return array|bool
     */
    public function get_write()
    {
        Connection::$param['postgisschema'] = Route::getParam("schema");
        return $this->mapFile->get_index();

    }
}