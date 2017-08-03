<?php
namespace app\api\v1;

use \app\inc\Response;
use \app\inc\Input;
use \app\inc\Session;

/**
 * Class Senti
 * @package app\api\v1
 */
class Senti extends \app\inc\Controller
{
    /**
     * @var
     */
    private $settings;

    /**
     * Senti constructor.
     */
    function __construct()
    {
    }

    /**
     * @return array
     */
    public function post_index()
    {
        $data = json_decode(Input::get(), true);
        $senti = new \app\models\Senti();
        return $senti->insert($data,Input::getPath()->part(5));
    }
}