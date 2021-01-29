<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Tile extends \app\inc\Controller
{
    private $wmslayer;

    function __construct()
    {
        parent::__construct();

        $this->wmslayer = new \app\models\Tile(Input::getPath()->part(4));
    }

    public function get_index()
    {
        $response = $this->auth(Input::getPath()->part(4), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response :  $this->wmslayer->get();
    }

    public function put_index()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->wmslayer->update(json_decode(Input::get(null, true))->data);
    }

    public function get_fields()
    {
        return Response::json($this->wmslayer->getfields());
    }
}
