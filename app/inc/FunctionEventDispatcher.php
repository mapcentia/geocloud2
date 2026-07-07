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

/**
 * DB-event triggers: drains settings.function_event_queue (filled by the
 * _gc2_function_event trigger) and enqueues an async invocation for every
 * function subscribed to that table+op. Only enqueues - the Phase 2 worker runs.
 *
 * Uses its own durable queue (not settings.outbox, which the realtime listener
 * deletes on sight), so events are never lost to a race with that consumer.
 */
class FunctionEventDispatcher
{
    private const array OPS = ['I' => 'insert', 'U' => 'update', 'D' => 'delete'];

    private FuncModel $func;

    public function __construct(private readonly Connection $connection)
    {
        $this->func = new FuncModel($connection);
    }

    /**
     * Drain queued events and enqueue matching invocations. The claim+enqueue
     * runs in one transaction so a failure restores the queue rows.
     *
     * @return int Number of invocations enqueued.
     */
    public function dispatch(int $limit = 100): int
    {
        $enqueued = 0;
        $this->func->withTransaction(function () use (&$enqueued, $limit) {
            $events = $this->func->claimEventQueue($limit);
            if (!$events) {
                return;
            }
            $functions = $this->func->getEventFunctions();
            foreach ($events as $ev) {
                $qualified = $ev['schema_name'] . '.' . $ev['table_name'];
                foreach ($functions as $fn) {
                    $triggers = is_string($fn['triggers'] ?? null) ? json_decode($fn['triggers'], true) : null;
                    if (!is_array($triggers) || !self::matches($ev['op'], $qualified, $triggers)) {
                        continue;
                    }
                    $this->enqueue($fn, $ev);
                    $enqueued++;
                }
            }
        });
        return $enqueued;
    }

    /**
     * Does an event (op char + qualified table) match a function's event trigger?
     */
    public static function matches(string $opChar, string $qualifiedTable, array $triggers): bool
    {
        $event = $triggers['event'] ?? null;
        if (!is_array($event) || ($event['table'] ?? null) !== $qualifiedTable) {
            return false;
        }
        $on = isset($event['on']) && is_array($event['on'])
            ? array_map('strtolower', $event['on'])
            : ['insert', 'update', 'delete'];
        $opName = self::OPS[$opChar] ?? null;
        return $opName !== null && in_array($opName, $on, true);
    }

    /**
     * Validate a triggers.event block at write time.
     *
     * @throws GC2Exception
     */
    public static function assertValidEvent(?array $triggers): void
    {
        $event = $triggers['event'] ?? null;
        if ($event === null) {
            return;
        }
        if (!is_array($event) || !is_string($event['table'] ?? null) || !str_contains($event['table'], '.')) {
            throw new GC2Exception("triggers.event.table must be a 'schema.table' string", 400, null, "INVALID_EVENT_TRIGGER");
        }
        if (isset($event['on'])) {
            if (!is_array($event['on'])) {
                throw new GC2Exception("triggers.event.on must be an array", 400, null, "INVALID_EVENT_TRIGGER");
            }
            foreach ($event['on'] as $op) {
                if (!in_array(strtolower((string)$op), ['insert', 'update', 'delete'], true)) {
                    throw new GC2Exception("triggers.event.on values must be insert|update|delete", 400, null, "INVALID_EVENT_TRIGGER");
                }
            }
        }
    }

    private function enqueue(array $fn, array $ev): void
    {
        $owner = is_string($fn['owner_context'] ?? null) ? (json_decode($fn['owner_context'], true) ?: []) : [];
        $context = [
            'function' => $fn['name'],
            'version' => (int)($fn['version'] ?? 1),
            'uid' => $owner['uid'] ?? $fn['username'],
            'database' => $owner['database'] ?? $this->connection->database,
            'superUser' => $owner['superUser'] ?? false,
            'userGroup' => $owner['userGroup'] ?? null,
            'apiBaseUrl' => App::$param['functions']['apiBaseUrl'] ?? App::$param['host'] ?? null,
            'source' => 'db',
        ];
        $event = [
            'source' => 'db',
            'op' => self::OPS[$ev['op']] ?? $ev['op'],
            'schema' => $ev['schema_name'],
            'table' => $ev['table_name'],
            'pk_column' => $ev['pk_column'],
            'pk' => $ev['pk_value'],
            'row' => is_string($ev['payload'] ?? null) ? json_decode($ev['payload'], true) : null,
        ];
        $this->func->createInvocation($fn['name'], $event, (string)$context['uid'], 'async', $context);
    }
}
