<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Workflow extends \app\inc\Controller
{
    function __construct()
    {
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