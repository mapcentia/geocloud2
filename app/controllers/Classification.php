<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use \app\inc\Input;

class Classification extends \app\inc\Controller
{
    private $class;

    function __construct()
    {
        parent::__construct();

        $this->class = new \app\models\Classification(Input::getPath()->part(4));
    }

    public function get_index()
    {
        $id = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(4), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : ($id !== false && $id !== null && $id !== "") ? $this->class->get($id) : $this->class->getAll();
    }

    public function post_index()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->insert();
    }

    public function put_index()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->update(Input::getPath()->part(5), json_decode(Input::get(null, true))->data);
    }

    public function delete_index()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->destroy(json_decode(Input::get())->data);
    }

    public function put_unique()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createUnique(Input::getPath()->part(5), json_decode(urldecode(Input::get()))->data);
    }

    public function put_single()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createSingle(json_decode(urldecode(Input::get()))->data, Input::getPath()->part(5));
    }

    public function put_equal()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createEqualIntervals(Input::getPath()->part(5), Input::getPath()->part(6), "#" . Input::getPath()->part(7), "#" . Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }

    public function put_quantile()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createQuantile(Input::getPath()->part(5), Input::getPath()->part(6), "#" . Input::getPath()->part(7), "#" . Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }
    public function put_cluster()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createCluster(Input::getPath()->part(5), json_decode(urldecode(Input::get()))->data);
    }
    public function put_copy()
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->copyClasses(Input::getPath()->part(4), Input::getPath()->part(5));
    }
}