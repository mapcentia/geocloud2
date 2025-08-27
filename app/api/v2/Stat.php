<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\inc\Connection;
use app\inc\Controller;
use app\inc\Model;

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
     * @return array
     */
    public function get_index(): array
    {
        return (new Model(connection: new Connection(database: \app\inc\Session::get()['parentdb'])))->getStats();
    }
}