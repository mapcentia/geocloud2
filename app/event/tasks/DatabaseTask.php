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
use app\conf\App;
use app\models\Database;
use Exception;

error_reporting(E_ERROR | E_PARSE);


final readonly class DatabaseTask implements Task
{

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        $skipList = [
            'rdsadmin', 'template1', 'template0', 'postgres',
            'gc2scheduler', 'template_geocloud', 'mapcentia'
        ];

        $exclude = App::$param['realtimeExclude'] ?? [];
        $include = App::$param['realtimeInclude'] ?? [];
        $exclude = array_merge($exclude, $skipList);
        echo "[INFO] DatabaseTask Worker PID: " . getmypid() . "\n";
        $databases = (new Database())->listAllDbs()['data'];
        return array_filter($databases, function ($db) use ($exclude, $include) {
            if (!empty($include) && in_array($db, $include)) {
                return true;
            } elseif (!empty($include) && !in_array($db, $include)) {
                return false;
            }

            if (!empty($exclude) && in_array($db, $exclude)) {
                return false;
            }
            return true;
        });
    }
}