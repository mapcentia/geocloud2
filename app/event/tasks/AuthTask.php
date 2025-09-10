<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\inc\Connection;
use app\models\Authorization;
use Exception;

error_reporting(E_ERROR | E_PARSE);

readonly class AuthTask implements Task
{

    public function __construct(
        private array      $jwtData,
        private string     $rel,
        private Connection $connection,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): bool
    {
        echo "[INFO] AuthTask Worker PID: " . getmypid() . "\n";
        $isSuperUser = $this->jwtData["superUser"];
        $subUser = $isSuperUser ? null : $this->jwtData["uid"];
        $rels = [];
        $auth = new Authorization(connection: $this->connection);
        try {
            $auth->check(relName: $this->rel, transaction: false, rels: $rels, isAuth: true, subUser: $subUser);
        } catch (Exception $e) {
            return false;
        }
        $auth->close();
        return true;
    }
}