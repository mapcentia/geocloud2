<?php
namespace app\api\v1;

use \app\inc\Input;

class Collector extends \app\inc\Controller
{
    private $collector;
    function __construct()
    {
        $this->collector = new \app\models\Collector();
    }

    public function post_index()
    {
        //die(Input::get());
        $content = json_decode(Input::get(), true);
        return $this->collector->store($content);

    }
}