<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Rules extends Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function getRules(): array
    {

        $sql = "SELECT * FROM settings.geofence order by priority";
        $res = $this->prepare($sql);
        $res->execute();
        $arr = [];
        while ($row = $this->fetchRow($res)) {
            $arr[] = $row;
        }
        return $arr;
    }
}