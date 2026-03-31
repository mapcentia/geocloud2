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
use app\exceptions\GraphQLException;
use app\inc\Connection;
use app\inc\GraphQL;
use app\models\Sql;
use Exception;


error_reporting(E_ERROR | E_PARSE);


final readonly class RunGraphQLTask implements Task
{

    public function __construct(
        private array $query,
        private ?array $props,
        private string $schema,
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

        try {
            $res = $graphQl->run(
                user: $this->props['user'],
                api: $sqlApi,
                query: $this->query[0]['payload']['query'],
                schema: $this->schema,
                subuser: !$this->props['superUser'],
                userGroup: $this->props['userGroup'],
                variables: isset($this->query['variables']) && is_array($this->query['variables']) ? $this->query['variables'] : [],
                operationName: isset($this->query['operationName']) && is_string($this->query['operationName']) ? $this->query['operationName'] :  null
            );
        } catch (GraphQLException $e) {
            $sqlApi->rollback();
            return [$e->getResponse()];
        }

        $sqlApi->commit();

        $wrapper = [
            'id' => $this->query[0]['id'],
            'type' => 'next',
            'payload' => $res,
        ];

        return [$wrapper];
    }
}