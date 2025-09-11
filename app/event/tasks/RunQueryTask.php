<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\conf\App;
use app\inc\Connection;
use app\inc\Statement;
use app\models\Sql;
use Error;
use Exception;


error_reporting(E_ERROR | E_PARSE);


readonly class RunQueryTask implements Task
{

    public function __construct(
        private string $sql,
        private ?array $props,
    )
    {
    }

    /**
     * @throws GC2Exception
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] RunQueryTask Worker PID: " . getmypid() . "\n";
        new App();
        Cache::setInstance();
        $body['q'] = $this->sql;
        $body['key'] = "dsd";
        $body['convert_types'] = true;
        $body['format'] = 'json';
        $body['srs'] = "4326";
        try {
            $connection = new Connection(database: $this->props['db']);
            $statement = new Statement(connection: $connection, convertReturning: true);
            $sqlApi = new Sql(connection: $connection);
            $res = $statement->run(user: $this->props['user'], api: $sqlApi,json:  $body, subuser: !$this->props['superUser'], userGroup: $this->props['userGroup']);
        } catch (GC2Exception $e) {
            $res = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
        return $res;
    }
}