<?php
namespace app\api\v1;

use \app\inc\Input;

/**
 * Class Collector
 * @package app\api\v1
 */
class Collector extends \app\inc\Controller
{
    /**
     * @var \app\models\Collector
     */
    private $collector;

    /**
     * Collector constructor.
     */
    function __construct()
    {
        $this->collector = new \app\models\Collector();
    }

    /**
     * @return array
     */
    public function post_index()
    {
        $content = json_decode(Input::get(), true);
        return $this->collector->store($content);

    }
}