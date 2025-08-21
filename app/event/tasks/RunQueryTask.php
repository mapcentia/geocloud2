<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\api\v2\Sql;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\conf\App;
use app\inc\Input;
use app\models\Database;
use Error;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

error_reporting(E_ERROR | E_PARSE);



readonly class RunQueryTask implements Task
{

    public function __construct(
        private string $sql,
        private string $db,
    )
    {}

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
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
            $sql = new \app\models\Sql(database: $this->db);
            $sql->connect();
            $res = (new Sql)->get_index(["user" => $this->db], $sql, $body, $this->db);
            $sql->close();
        } catch (Error $e) {
            $res = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
        return $res;
    }
}