<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use \app\inc\Input;

class Drawing extends \app\inc\Controller
{
    public $drawing;
    public $username;

    function __construct()
    {
        parent::__construct();

        $this->drawing = new \app\models\Drawing();
        $this->username = (isset($_SESSION['subuser']) && $_SESSION['subuser'] != "") ? $_SESSION['subuser'] : $_SESSION['screen_name'];

    }

    public function get_index()
    {
        return $this->drawing->load($this->username);
    }

    public function post_index()
    {
        return $this->drawing->save(Input::get(), $this->username);
    }
}