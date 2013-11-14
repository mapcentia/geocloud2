<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Response;

class Twitter extends \app\inc\Controller
{
    private $tweet;

    function __construct()
    {
        $this->tweet = new \app\models\Twitter();
    }
    public function get_index($lifetime = 0)
    {
        return Response::json($this->tweet->search(urldecode(Input::get('search')),Input::get('store'),Input::getPath()->part(5)));
    }
}