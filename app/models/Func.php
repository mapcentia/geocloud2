<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;
use PDO;


/**
 * Class Func
 *
 * Manages Lambda-like functions stored in settings.functions and their
 * invocation records in settings.function_invocations. This is the control
 * plane only - actual execution is delegated to a FunctionRunner.
 *
 * Named Func because "Function" is a reserved word in PHP.
 */
class Func extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * Retrieves all functions.
     *
     * @return array{success: bool, message: string, data: array}
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM settings.functions ORDER BY name";
        $res = $this->prepare($sql);
        $this->execute($res);
        return [
            'success' => true,
            'message' => "Functions fetched",
            'data' => $this->fetchAll($res, 'assoc'),
        ];
    }

    /**
     * Fetches a single function by name.
     *
     * @param string $name
     * @return array{success: bool, message: string, data: array}
     * @throws GC2Exception If no function with the given name exists.
     */
    public function getByName(string $name): array
    {
        $sql = "SELECT * FROM settings.functions WHERE name = :name";
        $res = $this->prepare($sql);
        $this->execute($res, ["name" => $name]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No function with that name", 404, null, "NO_FUNCTION_ERROR");
        }
        return [
            'success' => true,
            'message' => "Function fetched",
            'data' => $this->fetchRow($res),
        ];
    }

    /**
     * Creates a function.
     *
     * @return string The name of the created function.
     */
    public function createFunction(
        string  $name,
        string  $runtime,
        string  $handler,
        string  $code,
        ?array  $env,
        ?int    $memoryMb,
        ?int    $timeoutS,
        ?array  $triggers,
        string  $userName,
        ?array  $ownerContext = null,
        string  $package = 'inline'
    ): string
    {
        $sql = "INSERT INTO settings.functions
                    (name, runtime, handler, code, code_sha, env, memory_mb, timeout_s, triggers, username, owner_context, package)
                VALUES
                    (:name, :runtime, :handler, :code, :code_sha, :env, :memory_mb, :timeout_s, :triggers, :username, :owner_context, :package)
                RETURNING name";
        $res = $this->prepare($sql);
        $this->execute($res, [
            'name' => $name,
            'runtime' => $runtime,
            'handler' => $handler,
            'code' => $code,
            'code_sha' => hash('sha256', $code),
            'env' => $env !== null ? json_encode($env) : null,
            'memory_mb' => $memoryMb ?? 128,
            'timeout_s' => $timeoutS ?? 30,
            'triggers' => $triggers !== null ? json_encode($triggers) : null,
            'username' => $userName,
            'owner_context' => $ownerContext !== null ? json_encode($ownerContext) : null,
            'package' => $package,
        ]);
        return $res->fetchColumn();
    }

    /**
     * Install the function-event trigger on a table so row changes land in
     * settings.function_event_queue. Idempotent. The trigger is per-table and
     * shared by every function watching it; the dispatcher fans out to them.
     *
     * @throws GC2Exception If the table lacks a single-column primary key.
     */
    public function installEventTrigger(string $schema, string $table): void
    {
        $pkSql = "SELECT a.attname
                  FROM pg_index i
                  JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
                  WHERE i.indrelid = :rel ::regclass AND i.indisprimary";
        $res = $this->prepare($pkSql);
        $this->execute($res, ['rel' => "\"$schema\".\"$table\""]);
        $pks = $res->fetchAll(PDO::FETCH_COLUMN);
        if (count($pks) !== 1) {
            throw new GC2Exception("Event-trigger table must have a single-column primary key", 400, null, "EVENT_TRIGGER_PK");
        }
        $pk = $pks[0];
        $this->execute($this->prepare("DROP TRIGGER IF EXISTS _gc2_function_event_trigger ON \"$schema\".\"$table\""));
        $this->execute($this->prepare(
            "CREATE TRIGGER _gc2_function_event_trigger AFTER INSERT OR UPDATE OR DELETE ON \"$schema\".\"$table\" " .
            "FOR EACH ROW EXECUTE PROCEDURE _gc2_function_event('$pk', '$schema', '$table')"
        ));
    }

    /**
     * Drop the shared function-event trigger from a table (idempotent).
     */
    public function removeEventTrigger(string $schema, string $table): void
    {
        $this->execute($this->prepare("DROP TRIGGER IF EXISTS _gc2_function_event_trigger ON \"$schema\".\"$table\""));
    }

    /**
     * How many functions currently subscribe to a table's events.
     */
    public function countEventSubscribers(string $qualifiedTable): int
    {
        $sql = "SELECT count(*) FROM settings.functions WHERE triggers->'event'->>'table' = :t";
        $res = $this->prepare($sql);
        $this->execute($res, ['t' => $qualifiedTable]);
        return (int)$res->fetchColumn();
    }

    /**
     * Functions that declare an event trigger (triggers.event.table).
     */
    public function getEventFunctions(): array
    {
        $sql = "SELECT * FROM settings.functions WHERE triggers->'event'->>'table' IS NOT NULL";
        $res = $this->prepare($sql);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * Atomically claim (delete) up to $limit queued events, oldest first.
     * Caller must run inside a transaction so a rollback restores the rows.
     *
     * @return array Claimed event rows.
     */
    public function claimEventQueue(int $limit = 100): array
    {
        $sql = "DELETE FROM settings.function_event_queue
                WHERE id IN (
                    SELECT id FROM settings.function_event_queue
                    ORDER BY id LIMIT :limit FOR UPDATE SKIP LOCKED
                )
                RETURNING *";
        $res = $this->prepare($sql);
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $res->execute();
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * Functions that declare a schedule trigger (triggers.schedule).
     */
    public function getScheduledFunctions(): array
    {
        $sql = "SELECT * FROM settings.functions WHERE triggers->>'schedule' IS NOT NULL";
        $res = $this->prepare($sql);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * Record that a function's schedule was fired at $atIso (minute granularity),
     * so a scheduler that runs more than once a minute can't double-fire it.
     */
    public function markScheduled(string $name, string $atIso): void
    {
        $sql = "UPDATE settings.functions SET last_scheduled_at = :at WHERE name = :name";
        $res = $this->prepare($sql);
        $this->execute($res, ['name' => $name, 'at' => $atIso]);
    }

    /**
     * Updates a function. Null fields keep their current value. The version is
     * bumped whenever the code changes.
     *
     * @return array The updated row's uuid and name.
     * @throws GC2Exception If the function is missing or the user is not allowed.
     */
    public function updateFunction(
        string  $name,
        ?string $newName,
        ?string $runtime,
        ?string $handler,
        ?string $code,
        ?array  $env,
        ?int    $memoryMb,
        ?int    $timeoutS,
        ?array  $triggers,
        string  $userName,
        bool    $isSuperUser = false,
        ?string $package = null
    ): array
    {
        $old = $this->getByName($name)['data'];
        $codeChanged = $code !== null && $code !== $old['code'];
        $params = [
            'name' => $name,
            'newName' => $newName ?? $old['name'],
            'runtime' => $runtime ?? $old['runtime'],
            'handler' => $handler ?? $old['handler'],
            'code' => $code ?? $old['code'],
            'code_sha' => $codeChanged ? hash('sha256', $code) : $old['code_sha'],
            'env' => $env !== null ? json_encode($env) : $old['env'],
            'memory_mb' => $memoryMb ?? $old['memory_mb'],
            'timeout_s' => $timeoutS ?? $old['timeout_s'],
            'triggers' => $triggers !== null ? json_encode($triggers) : $old['triggers'],
            'version' => $codeChanged ? ((int)$old['version'] + 1) : (int)$old['version'],
            'package' => $package ?? $old['package'] ?? 'inline',
        ];
        $where = "WHERE name = :name";
        if (!$isSuperUser) {
            $where .= " AND username = :username";
            $params['username'] = $userName;
        }
        $sql = "UPDATE settings.functions SET
                    name = :newName, runtime = :runtime, handler = :handler, code = :code,
                    code_sha = :code_sha, env = :env, memory_mb = :memory_mb, timeout_s = :timeout_s,
                    triggers = :triggers, version = :version, package = :package, updated = now()
                $where
                RETURNING uuid, name";
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new GC2Exception("Not allowed to patch function", 404, null, "NOT_ALLOWED_ERROR");
        }
        return $row;
    }

    /**
     * Deletes a function by name.
     *
     * @throws GC2Exception If the function is missing or the user is not allowed.
     */
    public function deleteFunction(string $name, string $userName, bool $isSuperUser = false): void
    {
        $this->getByName($name);
        $params = ['name' => $name];
        $where = "WHERE name = :name";
        if (!$isSuperUser) {
            $where .= " AND username = :username";
            $params['username'] = $userName;
        }
        $sql = "DELETE FROM settings.functions $where RETURNING uuid, name";
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        if (!$res->fetch(PDO::FETCH_ASSOC)) {
            throw new GC2Exception("Not allowed to delete function", 404, null, "NOT_ALLOWED_ERROR");
        }
    }

    /**
     * Store the input/output type schemas inferred by a dry-run.
     */
    public function updateSchemas(string $name, ?array $inputSchema, ?array $outputSchema): void
    {
        $sql = "UPDATE settings.functions SET input_schema = :i, output_schema = :o WHERE name = :name";
        $res = $this->prepare($sql);
        $this->execute($res, [
            'name' => $name,
            'i' => $inputSchema !== null ? json_encode($inputSchema) : null,
            'o' => $outputSchema !== null ? json_encode($outputSchema) : null,
        ]);
    }

    /**
     * Generate a TypeScript `Functions` interface from stored dry-run schemas.
     * Function names are validated to be valid TS identifiers on create.
     */
    public function getFunctionsTypeScript(): string
    {
        $out = "export interface Functions {\n";
        foreach ($this->getAll()['data'] as $f) {
            $in = !empty($f['input_schema'])
                ? \app\inc\FunctionSchema::toTypeScript(json_decode($f['input_schema'], true))
                : 'Record<string, unknown>';
            $ret = !empty($f['output_schema'])
                ? \app\inc\FunctionSchema::toTypeScript(json_decode($f['output_schema'], true))
                : 'unknown';
            $out .= "    " . $f['name'] . "(event: " . $in . "): Promise<" . $ret . ">;\n";
        }
        $out .= "}\n";
        return $out;
    }

    /**
     * Records the start of an invocation and returns its uuid.
     */
    public function createInvocation(string $functionName, ?array $request, string $userName, string $type = 'sync', ?array $context = null): string
    {
        $sql = "INSERT INTO settings.function_invocations (function_name, status, request, username, invocation_type, context)
                VALUES (:function_name, 'pending', :request, :username, :type, :context)
                RETURNING uuid";
        $res = $this->prepare($sql);
        $this->execute($res, [
            'function_name' => $functionName,
            'request' => $request !== null ? json_encode($request) : null,
            'username' => $userName,
            'type' => $type,
            'context' => $context !== null ? json_encode($context) : null,
        ]);
        return $res->fetchColumn();
    }

    /**
     * Atomically claim up to $limit pending async invocations for this database,
     * flipping them to 'running'. Uses FOR UPDATE SKIP LOCKED so concurrent
     * workers never grab the same row (DB-as-queue).
     *
     * @return array Claimed invocation rows.
     */
    public function claimPending(int $limit = 10): array
    {
        $sql = "UPDATE settings.function_invocations u
                SET status = 'running'
                FROM (
                    SELECT uuid FROM settings.function_invocations
                    WHERE status = 'pending' AND invocation_type = 'async'
                    ORDER BY created
                    LIMIT :limit
                    FOR UPDATE SKIP LOCKED
                ) sub
                WHERE u.uuid = sub.uuid
                RETURNING u.*";
        $res = $this->prepare($sql);
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $res->execute();
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * Finalises an invocation record with the result of the run.
     */
    public function finishInvocation(
        string  $uuid,
        string  $status,
        mixed   $response,
        ?string $logs,
        ?string $error,
        ?int    $durationMs
    ): void
    {
        $sql = "UPDATE settings.function_invocations SET
                    status = :status, response = :response, logs = :logs,
                    error = :error, duration_ms = :duration_ms, finished = now()
                WHERE uuid = :uuid";
        $res = $this->prepare($sql);
        $this->execute($res, [
            'uuid' => $uuid,
            'status' => $status,
            'response' => $response !== null ? json_encode($response) : null,
            'logs' => $logs,
            'error' => $error,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Fetches a single invocation record by uuid.
     *
     * @throws GC2Exception If no invocation with the given uuid exists.
     */
    public function getInvocation(string $uuid): array
    {
        $sql = "SELECT * FROM settings.function_invocations WHERE uuid = :uuid";
        $res = $this->prepare($sql);
        $this->execute($res, ["uuid" => $uuid]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No invocation with that id", 404, null, "NO_INVOCATION_ERROR");
        }
        return [
            'success' => true,
            'message' => "Invocation fetched",
            'data' => $this->fetchRow($res),
        ];
    }

    /**
     * Lists invocation records for a function, newest first.
     */
    public function getInvocations(string $functionName, int $limit = 50): array
    {
        $sql = "SELECT * FROM settings.function_invocations
                WHERE function_name = :function_name
                ORDER BY created DESC
                LIMIT :limit";
        $res = $this->prepare($sql);
        $res->bindValue(':function_name', $functionName);
        $res->bindValue(':limit', $limit, PDO::PARAM_INT);
        $this->execute($res);
        return [
            'success' => true,
            'message' => "Invocations fetched",
            'data' => $this->fetchAll($res, 'assoc'),
        ];
    }
}
