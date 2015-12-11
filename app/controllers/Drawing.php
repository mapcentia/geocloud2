<?php
namespace app\controllers;

use \app\inc\Input;

class Drawing extends \app\inc\Controller
{
    public $drawing;
    public $username;

    function __construct()
    {
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