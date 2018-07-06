<?php
namespace app\api\v1;

/**
 * Class Schema
 * @package app\api\v1
 */
class Schema extends \app\inc\Controller
{
    /**
     * Schema constructor.
     */
    function __construct()
    {
        $this->db = new \app\models\Database();
    }

    /**
     * @return mixed
     */
    public function get_index()
    {
        return $this->db->listAllSchemas();
    }
}