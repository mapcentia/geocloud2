<?php
namespace app\api\v1;

/**
 * Class Getheader
 * @package app\api\v1
 */
class Getheader extends \app\inc\Controller
{
    /**
     * @return array|false
     */
    public function get_index(){
        return getallheaders();
    }
}