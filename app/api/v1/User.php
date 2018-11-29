<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

class User extends \app\inc\Controller
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
        $user = new \app\models\User(\app\inc\Input::getPath()->part(4));
        return $user->getData();
    }
}