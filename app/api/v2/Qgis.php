<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *
 */

namespace app\api\v2;

use \app\inc\Route;

/**
 * Class Qgis
 * @package app\api\v1
 */
class Qgis extends \app\inc\Controller
{
    /**
     * @var \app\models\Qgis
     */
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