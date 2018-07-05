<?php
namespace app\api\v2;

use \app\inc\Route;
use \app\conf\Connection;

/**
 * Class Files
 * @package app\api\v1
 */
class Mapcachefile extends \app\inc\Controller
{
    private $mapCacheFile;
    /**
     * Mapcachefile constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->mapCacheFile = new \app\controllers\Mapcachefile();
    }

    /**
     * @return array|bool
     */
    public function get_write()
    {
        return $this->mapCacheFile->get_index();

    }
}