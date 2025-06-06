<?php

namespace app\event\tasks;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\conf\App;
use app\models\Database;
use app\models\Sql;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

readonly class PreparePayloadTask implements Task
{
    public function __construct(
        private array  $batchPayload,
        private string $db
    )
    {
    }

    /**
     * @throws GC2Exception
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \Exception
     */
    public function run(Channel $channel, Cancellation $cancellation): array
    {
        echo "[INFO] PreparePayloadTask Worker PID: " . getmypid() . "\n";

        new App();
        Cache::setInstance();

        Database::setDb($this->db);
        $api = new Sql();
        $api->connect();

        $results = [];
        $grouped = [];
        // Group notifications by schema.table + key
        foreach ($this->batchPayload as $p) {
            $bits = explode(',', $p);
            $op = $bits[0];
            $schema = $bits[1];
            $table = $bits[2];
            $key = $bits[3];
            $value = $bits[4];

            $schemaTable = "{$schema}.{$table}";
            $results[$this->db][$schemaTable][$op][] = array_slice($bits, 3);

            if ($op === 'INSERT' || $op === 'UPDATE') {
                $groupKey = $schemaTable . ':' . $key;
                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'schemaTable' => $schemaTable,
                        'key' => $key,
                        'values' => []
                    ];
                }
                $grouped[$groupKey]['values'][] = $value;
            }
        }
        // Run blocking queries in batch
        foreach ($grouped as $grp) {
            $schemaTable = $grp['schemaTable'];
            $key = $grp['key'];
            $uniqueVals = array_unique($grp['values']);
            // Build IN list safely (better: prepared statements)
            $inList = implode(',', $uniqueVals);
            $sql = "SELECT * FROM {$schemaTable} WHERE \"{$key}\" IN ($inList)";
            echo $sql . "\n";
            $response = $api->sql($sql);
            if (!isset($results[$this->db][$schemaTable]['full_data'])) {
                $results[$this->db][$schemaTable]['full_data'] = [];
            }
            $results[$this->db][$schemaTable]['full_data'] = array_merge(
                $results[$this->db][$schemaTable]['full_data'],
                $response
            );
        }
        $api->close();
        return $results;
    }
}