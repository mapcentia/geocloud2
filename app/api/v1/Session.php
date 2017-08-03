<?php
namespace app\api\v1;

use \app\inc\Input;

/**
 * Class Session
 * @package app\api\v1
 */
class Session extends \app\inc\Controller
{
    /**
     * @var \app\models\Session
     */
    private $session;

    /**
     * Session constructor.
     */
    function __construct()
    {
        $this->session = new \app\models\Session();
    }

    /**
     * @return array
     */
    public function post_start()
    {
        return $this->session->start(Input::get("u"), Input::get("p"));
    }

    /**
     * @return array
     */
    public function get_stop()
    {
        return $this->session->stop();
    }
}