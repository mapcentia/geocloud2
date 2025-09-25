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
use app\models\Authorization;
use Exception;

error_reporting(E_ERROR | E_PARSE);


final readonly class AuthTask implements Task
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
    public function run(Channel $channel, Cancellation $cancellation): array|false
    {
        echo "[INFO] AuthTask Worker PID: " . getmypid() . "\n";
        $isSuperUser = $this->jwtData["superUser"];
        $subUser = $isSuperUser ? null : $this->jwtData["uid"];
        $userGroup = $isSuperUser ? null : $this->jwtData["userGroup"];
        $auth = new Authorization(connection: $this->connection);
        try {
            $res = $auth->check(relName: $this->rel, transaction: false, isAuth: true, subUser: $subUser, userGroup: $userGroup);
        } catch (GC2Exception) {
            return false;
        }
        $auth->close();
        return $res;
    }
}