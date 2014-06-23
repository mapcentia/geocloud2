<?php
namespace app\api\v1;

use \app\conf\App;
use \app\inc\Input;

class Getheader extends \app\inc\Controller
{
    public function get_index(){
        return getallheaders();
    }
}