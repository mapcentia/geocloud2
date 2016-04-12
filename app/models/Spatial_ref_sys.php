<?php

namespace app\models;

use app\inc\Model;

class Spatial_ref_sys extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    function getRowBySrid($srid)
    {
        $sql = "SELECT * FROM public.spatial_ref_sys WHERE srid =:srid";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("srid" => $srid));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $row = $this->fetchRow($res, "assoc");
        $response['success'] = true;
        $response['data'] = $row;
        return $response;
    }
}