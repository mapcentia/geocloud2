<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Cost extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Calculates the total cost from the settings.cost table for the past 30 days.
     *
     * @return float The sum of costs from the past 30 days. Returns 0.0 if no costs are found.
     */
    public function getCost(): float
    {
        $sql = "select sum(cost)
                    from settings.cost
                    WHERE cost.timestamp > now() - interval '30 day'";
        $res = $this->prepare($sql);
        $res->execute();
        return $res->fetchColumn() ?? 0.0;
    }

}