<?php
namespace app\api\v1;

class Schema extends \app\inc\Controller
{
    function __construct()
    {
        $this->db = new \app\models\Database();
    }

    public function get_index()
    {
        return $this->db->listAllSchemas();
    }
}