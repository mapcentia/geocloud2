<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\GraphQL;
use app\inc\GraphQL as _GraphQl;
use app\models\Sql;
use Exception;
use Throwable;


error_reporting(E_ERROR | E_PARSE);


final readonly class RunGraphQLTask implements Task
{

    public function __construct(
        private array $query,
        private string $schema,
        private ?array $props,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] RunGraphQLTask Worker PID: " . getmypid() . "\n";
        $connection = new Connection(database: $this->props['db']);
        $graphQl = new GraphQl(connection: $connection);

        $sqlApi = new Sql(connection: $connection);
        $sqlApi->begin();

        $res = $graphQl->run(
            user: $this->props['user'],
            api: $sqlApi,
            query: $this->query['query'],
            schema: $this->schema,
            subuser: !$this->props['superUser'],
            userGroup: $this->props['userGroup'],
            variables: $this->query['variables'],
            operationName: $this->query['operationName']
        );

        $sqlApi->commit();
        return $res;
    }
}