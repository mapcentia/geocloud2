<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;


use app\inc\Controller;

class Session extends Controller
{

    /**
     * @return array
     */
    public function get_log(): array
    {
        $response['data'] = \app\inc\Session::getLog();
        $response['success'] = true;
        return $response;
    }

    /**
     * @return array
     */
    public function get_user(): array
    {
        $response['data']['db'] = $_SESSION['screen_name'];
        $response['data']['subuser'] = $_SESSION["subuser"];
        $response['data']['subusers'] = $_SESSION['subusers'];
        $response['success'] = true;
        return $response;
    }
}