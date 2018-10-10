<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use \app\inc\Input;

class Workflow extends \app\inc\Controller
{
    function __construct()
    {
        parent::__construct();

        $this->workflow = new \app\models\Workflow();
    }

    public function get_index($showAll = false)
    {
        return $this->workflow->getRecords($_SESSION["subuser"], $showAll);
    }

    public function post_index()
    {
        return $this->get_index(true);
    }

    public function put_index()
    {
        return $this->workflow->touch(Input::getPath()->part(3), Input::getPath()->part(4), Input::getPath()->part(5), $_SESSION['subuser']);
    }
}