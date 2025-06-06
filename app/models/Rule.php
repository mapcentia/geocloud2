<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Rule extends Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param Sql|null $conn
     * @return array
     */
    public function get(Sql $conn = null): array
    {
        $sql = "SELECT * FROM settings.geofence order by priority";
        if ($conn) {
            $res = $conn->prepare($sql);
        } else {
            $res = $this->prepare($sql);
        }
        $res->execute();
        $arr = [];
        while ($row = $this->fetchRow($res)) {
            $arr[] = $row;
        }
        return $arr;
    }
}