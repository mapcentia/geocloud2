<?php
namespace app\api\v1;

use \app\inc\Input;

class Session extends \app\inc\Controller
{
    private $session;

    function __construct()
    {
        $this->session = new \app\models\Session();
    }

    public function post_start()
    {
        return $this->session->start(Input::get("u"), Input::get("p"));
    }
    public function get_stop()
    {
        return $this->session->stop();
    }
}