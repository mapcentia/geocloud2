<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\runners\StubFunctionRunner;
use app\models\Func as FuncModel;
use Throwable;

/**
 * Processes queued async function invocations for one database.
 *
 * This is the privileged data plane: it runs in a background CLI worker (cron),
 * not under the web-server user, so it can actually exec the runtime + sandbox
 * (the limitation found in Phase 0). It claims pending rows, mints a fresh
 * scoped token per invocation, runs the configured runner, and finalises the
 * record. The token is minted here (not at enqueue) so it never goes stale
 * waiting in the queue and is never stored at rest.
 */
class FunctionWorker
{
    private FuncModel $func;
    private FunctionRunner $runner;

    public function __construct(private readonly Connection $connection, ?FunctionRunner $runner = null)
    {
        $this->func = new FuncModel($connection);
        $this->runner = $runner ?? $this->resolveRunner();
    }

    /**
     * Claim and run up to $limit pending invocations.
     *
     * @return array{processed:int, succeeded:int, failed:int}
     */
    public function processPending(int $limit = 10): array
    {
        $claimed = $this->func->claimPending($limit);
        $summary = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        foreach ($claimed as $row) {
            $status = $this->runOne($row);
            $summary['processed']++;
            $summary[$status === 'succeeded' ? 'succeeded' : 'failed']++;
        }
        return $summary;
    }

    /**
     * Run a single claimed invocation row and finalise its record.
     *
     * @return string The final status ('succeeded' | 'failed').
     */
    private function runOne(array $row): string
    {
        $uuid = $row['uuid'];
        try {
            $function = $this->func->getByName($row['function_name'])['data'];
            $context = is_string($row['context'] ?? null) ? (json_decode($row['context'], true) ?: []) : [];
            $event = is_string($row['request'] ?? null) ? (json_decode($row['request'], true) ?: []) : [];

            $timeoutS = (int)($function['timeout_s'] ?? 30);
            $context['token'] = FunctionToken::mint($this->connection, [
                'uid' => $context['uid'] ?? $row['username'],
                'database' => $context['database'] ?? $this->connection->database,
                'superUser' => $context['superUser'] ?? false,
                'userGroup' => $context['userGroup'] ?? null,
            ], $row['function_name'], $timeoutS + 15);

            $result = $this->runner->invoke($function, $event, $context);
            $this->func->finishInvocation($uuid, $result->status, $result->output, $result->logs, $result->error, $result->durationMs);
            return $result->status;
        } catch (Throwable $e) {
            $this->func->finishInvocation($uuid, 'failed', null, null, $e->getMessage(), null);
            return 'failed';
        }
    }

    private function resolveRunner(): FunctionRunner
    {
        $class = App::$param['functionRunner'] ?? StubFunctionRunner::class;
        return new $class();
    }
}
