<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use app\inc\Controller;
use app\inc\Input;

class User extends Controller
{
    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     */
    function get_index()
    {
        $user = new \app\models\User(Input::getPath()->part(4));
        return $user->getData();
    }
}