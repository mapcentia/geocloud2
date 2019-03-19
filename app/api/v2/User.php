<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Input;

/**
 * Class User
 * @package app\api\v2
 */
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
     * @return array
     */
    function post_index(): array
    {
        /*
        passwords - force checks
        cleaning names and additional checks
        creating the database

        POST /api/user - create superuser
            200
            400

        */

        return array();
    }

}