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
use app\exceptions\RPCException;
use app\inc\Connection;
use app\inc\Rpc;
use app\models\Sql;
use Exception;


error_reporting(E_ERROR | E_PARSE);


readonly final class RunRpcTask implements Task
{

    public function __construct(
        private array  $query,
        private ?array $props,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] RunRpcTask Worker PID: " . getmypid() . "\n";
        $res = [];
        $connection = new Connection(database: $this->props['db']);
        $rpc = new Rpc(connection: $connection);
        $sqlApi = new Sql(connection: $connection);
        $sqlApi->begin();
        try {
            foreach ($this->query as $q) {
                $res[] = $rpc->run(user: $this->props['user'], api: $sqlApi, query: $q, subuser: !$this->props['superUser'], userGroup: $this->props['userGroup']);
            }
        } catch (RPCException $e) {
            $sqlApi->rollback();
            return $e->getResponse();
        }
        $sqlApi->commit();
        return $res;
    }
}