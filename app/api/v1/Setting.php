<?php
namespace app\api\v1;

use \app\inc\Response;
use \app\inc\Input;
use \app\inc\Session;

class Setting extends \app\inc\Controller
{
    private $settings;

    function __construct()
    {
        $this->settings = new \app\models\Setting();
    }
    public function get_index()
    {
        return $this->settings->getForPublic();
    }
}