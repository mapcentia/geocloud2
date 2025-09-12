<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\models\Database;
use Exception;

error_reporting(E_ERROR | E_PARSE);


readonly class DatabaseTask implements Task
{

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] DatabaseTask Worker PID: " . getmypid() . "\n";
        $databases = (new Database())->listAllDbs()['data'];
        return array_filter($databases, function ($db) {
            $skipList = [
                'rdsadmin', 'template1', 'template0', 'postgres',
                'gc2scheduler', 'template_geocloud', 'mapcentia'
            ];
            if (in_array($db, $skipList) || str_contains($db, 'test')) {
                return false;
            }
            return true;
        });
    }
}