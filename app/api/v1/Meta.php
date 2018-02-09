<?php

namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Session;

/**
 * Class Meta
 * @package app\api\v1
 */
class Meta extends \app\inc\Controller
{
    /**
     * @var \app\models\Layer
     */
    private $layers;

    /**
     * Meta constructor.
     */
    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    /**
     * @return array
     */
    public function get_index()
    {
        // Get the URI params from request
        // /meta/{user}/[query]
        $r = func_get_arg(0);
        return $this->layers->getAll($r["query"], Session::isAuth(), Input::get("iex"), Input::get("parse"), Input::get("es"), Input::get("suborder"));
    }
}