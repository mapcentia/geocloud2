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
use app\inc\Model;
use app\models\Database;

/**
 * Class Qgis
 * @package app\api\v1
 */
class Stat extends Controller
{
    /**
     * Qgis constructor.
     */
    function __construct()
    {
        parent::__construct();

    }

    /**
     * @return array|bool
     */
    public function get_index()
    {
        Database::setDb(\app\inc\Session::get()['parentdb']);
        return (new Model())->getStats();
    }
}