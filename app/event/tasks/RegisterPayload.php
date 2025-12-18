<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Sync\Channel;
use app\inc\Connection;
use app\models\Sql;

final class RegisterPayload implements \Amp\Parallel\Worker\Task
{
    public function __construct(
        private readonly array $batchPayload,
        private readonly string $db
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): true
    {
        echo "[INFO] RegisterPayloadTask Worker PID: " . getmypid() . "\n";

        $api = new Sql(connection: new Connection(database: $this->db));
        foreach ($this->batchPayload as $dbName => $tables) {
            foreach ($tables as $tableName => $tableOps) {
                foreach (['INSERT', 'UPDATE', 'DELETE'] as $op) {
                    if (!isset($tableOps[$op]) || !is_array($tableOps[$op])) {
                        continue;
                    }
                    unset($tableOps['full_data']);
                    $sql = "INSERT INTO settings.events (rel, op, batch) VALUES (:rel, :op, :batch)";
                    $api->prepare($sql)->execute(['rel' => $tableName, 'op' => $op, 'batch' => json_encode($tableOps[$op])]);
                }
            }
        }
        return true;
    }
}