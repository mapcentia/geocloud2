<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use PDOException;


/**
 * Class Preparedstatement
 * @package app\models
 */
class Preparedstatement extends Model
{
    /**
     * @param string $uuid
     * @return array<mixed>
     */
    public function getByUuid(string $uuid): array
    {

        $sql = "SELECT * FROM settings.prepared_statements WHERE uuid=:uuid";

        $res = $this->prepare($sql);
        try {
            $res->execute(["uuid" => $uuid]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $row = $this->fetchRow($res);
        if (sizeof($row) == 1) {
            $response['success'] = false;
            $response['message'] = "No statements with that uuid";
            $response['code'] = 400;
            return $response;
        }

        $response['success'] = true;
        $response['message'] = "Statement fetched";
        $response['data'] = $row;
        return $response;
    }
}