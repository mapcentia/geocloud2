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
use app\models\Func as FuncModel;
use Cron\CronExpression;
use DateTimeInterface;
use Throwable;

/**
 * Schedule triggers: turns functions that declare triggers.schedule (a cron
 * expression) into queued async invocations when due.
 *
 * It only enqueues - the Phase 2 worker executes. Run once per minute from cron
 * (function_scheduler.php). System-triggered runs use the owner identity
 * captured at function creation (owner_context), so the worker mints a token
 * with the right privileges and callbacks still respect rules/geofence.
 */
class FunctionScheduler
{
    private FuncModel $func;

    public function __construct(private readonly Connection $connection)
    {
        $this->func = new FuncModel($connection);
    }

    /**
     * Is the cron expression due at the given time? Invalid expressions are
     * treated as never-due rather than throwing.
     */
    public static function isDue(string $cron, DateTimeInterface $at): bool
    {
        try {
            return (new CronExpression($cron))->isDue($at);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Validate a triggers block at write time so bad cron is a 400, not a
     * silently-never-firing function.
     *
     * @throws GC2Exception
     */
    public static function assertValidSchedule(?array $triggers): void
    {
        $cron = $triggers['schedule'] ?? null;
        if ($cron === null) {
            return;
        }
        if (!is_string($cron)) {
            throw new GC2Exception("triggers.schedule must be a cron string", 400, null, "INVALID_CRON");
        }
        try {
            new CronExpression($cron);
        } catch (Throwable $e) {
            throw new GC2Exception("Invalid cron expression: " . $e->getMessage(), 400, null, "INVALID_CRON");
        }
    }

    /**
     * Enqueue an async invocation for every scheduled function due at $at.
     * Idempotent within a minute via last_scheduled_at.
     *
     * @return int Number of invocations enqueued.
     */
    public function enqueueDue(DateTimeInterface $at): int
    {
        $enqueued = 0;
        foreach ($this->func->getScheduledFunctions() as $fn) {
            $triggers = is_string($fn['triggers'] ?? null) ? json_decode($fn['triggers'], true) : null;
            $cron = $triggers['schedule'] ?? null;
            if (!is_string($cron) || !self::isDue($cron, $at)) {
                continue;
            }
            if ($this->alreadyFiredThisMinute($fn['last_scheduled_at'] ?? null, $at)) {
                continue;
            }

            $owner = is_string($fn['owner_context'] ?? null) ? (json_decode($fn['owner_context'], true) ?: []) : [];
            $context = [
                'function' => $fn['name'],
                'version' => (int)($fn['version'] ?? 1),
                'uid' => $owner['uid'] ?? $fn['username'],
                'database' => $owner['database'] ?? $this->connection->database,
                'superUser' => $owner['superUser'] ?? false,
                'userGroup' => $owner['userGroup'] ?? null,
                'apiBaseUrl' => App::$param['functions']['apiBaseUrl'] ?? App::$param['host'] ?? null,
                'source' => 'schedule',
            ];
            $event = ['source' => 'schedule', 'time' => $at->format(DATE_ATOM), 'cron' => $cron];

            $this->func->createInvocation($fn['name'], $event, (string)$context['uid'], 'async', $context);
            $this->func->markScheduled($fn['name'], $at->format('Y-m-d H:i:00'));
            $enqueued++;
        }
        return $enqueued;
    }

    private function alreadyFiredThisMinute(?string $lastScheduledAt, DateTimeInterface $at): bool
    {
        if (empty($lastScheduledAt)) {
            return false;
        }
        $last = strtotime($lastScheduledAt);
        return $last !== false && intdiv($last, 60) === intdiv($at->getTimestamp(), 60);
    }
}
