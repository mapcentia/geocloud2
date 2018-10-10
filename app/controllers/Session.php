<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;


class Session extends \app\inc\Controller
{

    public function get_log()
    {
        $response['data'] = \app\inc\Session::getLog();
        $response['success'] = true;
        return $response;
    }
    public function get_user()
    {
        $response['data']['db'] = $_SESSION['screen_name'];
        $response['data']['subuser'] = $_SESSION['subuser'];
        $response['data']['subusers'] = $_SESSION['subusers'];
        $response['success'] = true;
        return $response;
    }
}