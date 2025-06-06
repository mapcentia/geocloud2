<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\conf\App;
use app\inc\Jwt;
use Exception;

error_reporting(E_ERROR | E_PARSE);

readonly class ValidateTokenTask implements Task
{

    public function __construct(private string $token)
    {
    }

    /**
     * @throws GC2Exception
     * @throws Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] ValidateTokenTask Worker PID: " . getmypid() . "\n";
        new App();
        Cache::setInstance();
        return Jwt::validate($this->token);
    }
}