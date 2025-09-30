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
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Statement;
use app\models\Sql;
use Exception;
use Throwable;


error_reporting(E_ERROR | E_PARSE);


final readonly class RunQueryTask implements Task
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
        echo "[INFO] RunQueryTask Worker PID: " . getmypid() . "\n";
        $res = [];
        $connection = new Connection(database: $this->props['db']);
        $statement = new Statement(connection: $connection, convertReturning: true);
        $sqlApi = new Sql(connection: $connection);
        $sqlApi->begin();

            foreach ($this->query as $q) {
                $q['format'] = $q['output_format'] ?? 'json';
                try {
                    $res[] = $statement->run(user: $this->props['user'], api: $sqlApi, query: $q, subuser: !$this->props['superUser'], userGroup: $this->props['userGroup']);
                } catch (Throwable $e) {
                    $sqlApi->rollback();
                    if (isset($q['id'])) {
                        return [[
                            'success' => false,
                            'message' => $e->getMessage(),
                            'id' => $q['id'],
                        ]];
                    } else {
                        return [];
                    }
                }
            }

        $sqlApi->commit();
        return $res;
    }
}