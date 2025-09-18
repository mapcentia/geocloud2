<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Connection;
use app\inc\Model;

class Cost extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct($connection);
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
        $this->execute($res);
        return $res->fetchColumn() ?? 0.0;
    }
}