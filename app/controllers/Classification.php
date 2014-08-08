<?php
namespace app\controllers;

use \app\inc\Input;

class Classification extends \app\inc\Controller
{
    private $class;

    function __construct()
    {
        $this->class = new \app\models\Classification(Input::getPath()->part(4));
    }

    public function get_index()
    {
        $id = Input::getPath()->part(5);
        return ($id !== false && $id !== null && $id !== "") ? $this->class->get($id) : $this->class->getAll();
    }

    public function post_index()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response : $this->class->insert();
    }

    public function put_index()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->update(Input::getPath()->part(5), json_decode(urldecode(Input::get()))->data);
    }

    public function delete_index()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->destroy(json_decode(Input::get())->data);
    }
    public function put_unique(){
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->createUnique(Input::getPath()->part(5),json_decode(urldecode(Input::get()))->data);
    }
    public function put_single(){
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->createSingle(json_decode(urldecode(Input::get()))->data);
    }
    public function put_equal(){
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->createEqualIntervals(Input::getPath()->part(5),Input::getPath()->part(6),"#".Input::getPath()->part(7),"#".Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }
    public function put_quantile(){
        $response = $this->auth();
        return (!$response['success']) ? $response :  $this->class->createQuantile(Input::getPath()->part(5),Input::getPath()->part(6),"#".Input::getPath()->part(7),"#".Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }
}