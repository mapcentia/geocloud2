<?php
namespace app\api\v1;

use \app\inc\Input;

/**
 * Class Ckan
 * @package app\api\v1
 */
class Ckan extends \app\inc\Controller
{
    /**
     * Ckan constructor.
     */
    function __construct()
    {
        $this->layers = new \app\models\Layer();
    }

    /**
     * @return array|bool
     */
    public function get_index()
    {
        return ($this->authApiKey(Input::getPath()->part(4), Input::get("key"))) ? $this->layers->updateCkan(Input::get("id"), Input::get("host")) : false;
    }
}