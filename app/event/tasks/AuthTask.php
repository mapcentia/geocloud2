<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\inc\Connection;
use app\models\Database;
use app\models\Setting;
use app\inc\Controller;
use Exception;

error_reporting(E_ERROR | E_PARSE);

readonly class AuthTask implements Task
{

    public function __construct(
        private array  $jwtData,
        private string $rel
    )
    {
    }

    /**
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): bool
    {
        echo "[INFO] AuthTask Worker PID: " . getmypid() . "\n";
        $conn = new Connection(database: $this->jwtData["database"]);
        $isSuperUser = $this->jwtData["superUser"];
        $uid = $this->jwtData["uid"];
        $settingsData = (new Setting(connection: $conn))->get()["data"];
        $apiKey = $isSuperUser ? $settingsData->api_key : $settingsData->api_key_subuser->$uid;
        $rels = [];
        $res = (new Controller(connection: $conn))->ApiKeyAuthLayer($this->rel, false, $rels, $isSuperUser ? null : $uid, $apiKey);
        return $res['success'];
    }
}