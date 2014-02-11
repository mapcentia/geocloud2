<?php
namespace app\api\v1;

use \app\inc\Response;
use \app\inc\Input;
use app\inc\Session;

class Schema extends \app\inc\Controller
{
    function __construct()
    {
        $this->db = new \app\models\Database();
    }

    public function get_index()
    {
        return Response::json($this->db->listAllSchemas());
    }
}