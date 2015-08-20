<?php
namespace app\api\v1;

class Getheader extends \app\inc\Controller
{
    public function get_index(){
        return getallheaders();
    }
}