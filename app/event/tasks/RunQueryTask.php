<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\api\v2\Sql;
use app\inc\Cache;
use app\conf\App;
use app\inc\Input;
use app\models\Database;
use Error;

error_reporting(E_ERROR | E_PARSE);



class RunQueryTask implements Task
{
    private string $sql;
    private string $db;

    public function __construct(string $sql, string $db)
    {
        $this->sql = $sql;
        $this->db = $db;
    }

    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] RunQueryTask Worker PID: " . getmypid() . "\n";
        new App();
        Cache::setInstance();
        Database::setDb($this->db);

        Input::setParams(
            [
                "q" => $this->sql,
                "key" => "dsd",
                "srs" => "4326",
                "convert_types" =>  true,
                "format" => "json",
            ]
        );
        try {
            $res = (new Sql)->get_index(["user" => $this->db]);
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