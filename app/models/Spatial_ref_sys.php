<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use PDOException;


/**
 * Class Spatial_ref_sys
 * @package app\models
 */
class Spatial_ref_sys extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param int $srid
     * @return array<mixed>
     */
    function getRowBySrid(int $srid): array
    {
        $sql = "SELECT * FROM public.spatial_ref_sys WHERE srid =:srid";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("srid" => $srid));
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $row = $this->fetchRow($res);
        $response['success'] = true;
        $response['data'] = $row;
        return $response;
    }
}