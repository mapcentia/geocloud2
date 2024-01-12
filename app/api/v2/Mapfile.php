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

use app\inc\Controller;
use app\inc\Route;
use app\conf\Connection;

/**
 * Class Files
 * @package app\api\v1
 */
class Mapfile extends Controller
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