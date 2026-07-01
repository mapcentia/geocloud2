<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\AcceptedResponse;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\FunctionEventDispatcher;
use app\inc\FunctionRunner;
use app\inc\FunctionScheduler;
use app\inc\FunctionSchema;
use app\inc\FunctionToken;
use app\inc\Input;
use app\inc\Route2;
use app\inc\runners\StubFunctionRunner;
use app\models\Func as FuncModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Class Func
 *
 * Control-plane API for Lambda-like functions. CRUD lives on
 * /api/v4/functions/{name}; invocations on /api/v4/functions/{name}/invocations.
 * Execution is delegated to a FunctionRunner (see conf key "functionRunner").
 *
 * Named Func because "Function" is a reserved word in PHP. The resource is
 * still "functions" on the wire.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Function",
    description: "A Lambda-like function executed in a sandboxed runtime.",
    required: ["name", "runtime", "handler", "code"],
    properties: [
        new OA\Property(property: "name", title: "Name", description: "Unique function name.", type: "string", example: "resizeImage"),
        new OA\Property(property: "runtime", title: "Runtime", description: "Execution runtime.", type: "string", enum: ["nodejs20", "python312"], example: "nodejs20"),
        new OA\Property(property: "handler", title: "Handler", description: "Entry point, e.g. file.exportedFunction.", type: "string", example: "index.handler"),
        new OA\Property(property: "package", title: "Package", description: "How 'code' is packaged: inline source, or a base64-encoded zip (multi-file bundle).", type: "string", enum: ["inline", "zip"], default: "inline"),
        new OA\Property(property: "code", title: "Code", description: "Function source (package=inline), or a base64-encoded zip archive (package=zip).", type: "string", example: "export const handler = async (event) => ({ ok: true });"),
        new OA\Property(property: "env", title: "Environment", description: "Environment variables passed to the runtime.", type: "object", example: ["LOG_LEVEL" => "info"]),
        new OA\Property(property: "memory_mb", title: "Memory", description: "Memory limit in MB.", type: "integer", default: 128, example: 256),
        new OA\Property(property: "timeout_s", title: "Timeout", description: "Wall-clock timeout in seconds.", type: "integer", default: 30, example: 60),
        new OA\Property(property: "triggers", title: "Triggers", description: "Trigger bindings (schedule, event, http).", type: "object", example: ["schedule" => "0 * * * *"]),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/functions/[name]/(action)/[id]', scope: Scope::SUB_USER_ALLOWED)]
class Func extends AbstractApi
{
    private FuncModel $func;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->func = new FuncModel($connection);
        $this->resource = 'functions';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/functions/{name}', operationId: 'getFunctions', description: "Get function definition(s).", tags: ['Functions'])]
    #[OA\Parameter(name: 'name', description: 'Function name', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'resizeImage')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Function"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Function"))])
    )]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $name = $this->route->getParam('name');
        if (!empty($name)) {
            $row = $this->func->getByName($name)['data'];
            return $this->getResponse([self::format($row)], single: true);
        }
        $rows = array_map(fn($r) => self::format($r), $this->func->getAll()['data']);
        return $this->getResponse($rows);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/functions', operationId: 'postFunctions', description: "Create function(s).", tags: ['Functions'])]
    #[OA\RequestBody(description: 'Function definition(s).', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Function"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Function"))])
    )]
    #[OA\Response(response: 201, description: 'Created', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $uid = $this->route->jwt["data"]["uid"];
        $decodedBody = json_decode(Input::getBody(), true);
        $functions = array_is_list($decodedBody) ? $decodedBody : [$decodedBody];
        // Owner identity captured for system-triggered (scheduled) runs.
        $ownerContext = [
            'uid' => $uid,
            'database' => $this->route->jwt["data"]["database"] ?? null,
            'superUser' => $this->route->jwt["data"]["superUser"] ?? false,
            'userGroup' => $this->route->jwt["data"]["userGroup"] ?? null,
        ];
        $list = [];
        $this->func->withTransaction(function () use (&$list, $functions, $uid, $ownerContext) {
            foreach ($functions as $f) {
                FunctionScheduler::assertValidSchedule($f['triggers'] ?? null);
                FunctionEventDispatcher::assertValidEvent($f['triggers'] ?? null);
                $list[] = $this->func->createFunction(
                    name: $f['name'],
                    runtime: $f['runtime'],
                    handler: $f['handler'],
                    code: $f['code'],
                    env: $f['env'] ?? null,
                    memoryMb: $f['memory_mb'] ?? null,
                    timeoutS: $f['timeout_s'] ?? null,
                    triggers: $f['triggers'] ?? null,
                    userName: $uid,
                    ownerContext: $ownerContext,
                    package: $f['package'] ?? 'inline',
                );
                $this->installEventTriggerIfAny($f['triggers'] ?? null);
            }
        });
        return $this->postResponse("/api/v4/functions/", $list);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/functions/{name}', operationId: 'patchFunctions', description: "Update a function. Changing the code bumps its version.", tags: ['Functions'])]
    #[OA\Parameter(name: 'name', description: 'Function name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'resizeImage')]
    #[OA\RequestBody(description: 'Function updates.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Function"))]
    #[OA\Response(response: 303, description: 'Function updated')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $name = $this->route->getParam('name');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $body = json_decode(Input::getBody(), true);
        FunctionScheduler::assertValidSchedule($body['triggers'] ?? null);
        FunctionEventDispatcher::assertValidEvent($body['triggers'] ?? null);
        // If triggers are being changed, note the previous event table so we can
        // drop its trigger if this was its last subscriber.
        $triggersChanging = array_key_exists('triggers', $body);
        $oldTable = $triggersChanging ? $this->currentEventTable($name) : null;

        $row = $this->func->updateFunction(
            name: $name,
            newName: $body['name'] ?? null,
            runtime: $body['runtime'] ?? null,
            handler: $body['handler'] ?? null,
            code: $body['code'] ?? null,
            env: $body['env'] ?? null,
            memoryMb: $body['memory_mb'] ?? null,
            timeoutS: $body['timeout_s'] ?? null,
            triggers: $body['triggers'] ?? null,
            userName: $uid,
            isSuperUser: $isSuperUser,
            package: $body['package'] ?? null,
        );
        $this->installEventTriggerIfAny($body['triggers'] ?? null);
        if ($triggersChanging) {
            $newTable = $this->eventTableOf($body['triggers'] ?? null);
            if ($oldTable !== null && $oldTable !== $newTable) {
                $this->reconcileEventTrigger($oldTable);
            }
        }
        return $this->patchResponse('/api/v4/functions/', [$row['name']]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/functions/{name}', operationId: 'deleteFunctions', description: "Delete a function.", tags: ['Functions'])]
    #[OA\Parameter(name: 'name', description: 'Function name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'resizeImage')]
    #[OA\Response(response: 204, description: "Function deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        $name = $this->route->getParam('name');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $eventTable = $this->currentEventTable($name);
        $this->func->deleteFunction($name, $uid, $isSuperUser);
        $this->reconcileEventTrigger($eventTable);
        return $this->deleteResponse();
    }

    /**
     * Synchronously invoke a function. The result is returned and an invocation
     * record is stored (retrievable via GET .../invocations/{id}).
     *
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/functions/{name}/invocations', operationId: 'invokeFunction', description: "Invoke a function with an event payload. Pass ?async=true (or header X-Invocation-Type: Event) to queue it and return 202 immediately.", tags: ['Functions'])]
    #[OA\Parameter(name: 'name', description: 'Function name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'resizeImage')]
    #[OA\Parameter(name: 'async', description: 'Queue the invocation instead of running it synchronously.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'dry', description: 'Run once to infer + store input/output type schemas (for TypeScript generation). Side effects are NOT rolled back.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\RequestBody(description: 'Event payload passed to the handler.', required: false, content: new OA\JsonContent(type: 'object'))]
    #[OA\Response(response: 200, description: 'Ok (sync)', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 202, description: 'Accepted (async); poll the invocation for status', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 404, description: 'Function not found')]
    #[OA\Response(response: 501, description: 'Runtime not configured')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_invocations(): Response
    {
        $name = $this->route->getParam('name');
        $uid = $this->route->jwt["data"]["uid"];
        $function = $this->func->getByName($name)['data'];
        $body = Input::getBody();
        $event = empty($body) ? [] : (json_decode($body, true) ?? []);

        // Non-secret context shared by sync and async. The token is added per
        // path (minted here for sync; minted by the worker for async, so it
        // never goes stale in the queue).
        $context = [
            'function' => $name,
            'version' => (int)$function['version'],
            'uid' => $uid,
            'database' => $this->route->jwt["data"]["database"] ?? null,
            'superUser' => $this->route->jwt["data"]["superUser"] ?? false,
            'userGroup' => $this->route->jwt["data"]["userGroup"] ?? null,
            // Base URL the sandbox uses to reach GC2. May differ from the public
            // host (e.g. an internal address), hence its own config key.
            'apiBaseUrl' => App::$param['functions']['apiBaseUrl'] ?? App::$param['host'] ?? null,
        ];

        $timeoutS = (int)($function['timeout_s'] ?? 30);

        // Dry-run: run synchronously, infer input/output type schemas from the
        // event + result and store them for TypeScript generation. Returns the
        // schemas instead of recording an invocation. NOTE: unlike SQL methods,
        // a function's side effects are NOT rolled back.
        if ($this->isDry()) {
            $context['token'] = FunctionToken::mint($this->connection, $this->route->jwt["data"], $name, $timeoutS + 15);
            $result = $this->resolveRunner()->invoke($function, $event, $context);
            $inputSchema = FunctionSchema::infer($event);
            $outputSchema = $result->status === 'succeeded' ? FunctionSchema::infer($result->output) : null;
            $this->func->updateSchemas($name, $inputSchema, $outputSchema);
            return new GetResponse(data: [
                'dry_run' => true,
                'status' => $result->status,
                'result' => $result->output,
                'logs' => $result->logs,
                'error' => $result->error,
                'input_schema' => $inputSchema,
                'output_schema' => $outputSchema,
            ]);
        }

        // Async (fire-and-forget): queue and return 202 immediately. A
        // background worker (function_worker.php) runs it and updates the record.
        if ($this->isAsync()) {
            $invocationId = $this->func->createInvocation($name, $event, $uid, 'async', $context);
            return new AcceptedResponse([
                'invocation' => $invocationId,
                'status' => 'pending',
                '_links' => ['self' => "/api/v4/functions/$name/invocations/$invocationId"],
            ]);
        }

        // Sync: mint the scoped token, run inline, return the result.
        $context['token'] = FunctionToken::mint($this->connection, $this->route->jwt["data"], $name, $timeoutS + 15);

        $invocationId = $this->func->createInvocation($name, $event, $uid);
        try {
            $result = $this->resolveRunner()->invoke($function, $event, $context);
            $this->func->finishInvocation($invocationId, $result->status, $result->output, $result->logs, $result->error, $result->durationMs);
        } catch (GC2Exception $e) {
            $this->func->finishInvocation($invocationId, 'failed', null, null, $e->getMessage(), null);
            throw $e;
        }
        return new GetResponse(data: [
            'invocation' => $invocationId,
            'status' => $result->status,
            'result' => $result->output,
            'logs' => $result->logs,
            'duration_ms' => $result->durationMs,
        ]);
    }

    /**
     * If the function declares an event trigger, install the per-table trigger
     * that feeds the function event queue. The trigger is shared by all
     * functions watching the table (idempotent install).
     *
     * @throws GC2Exception
     */
    private function installEventTriggerIfAny(?array $triggers): void
    {
        $table = $this->eventTableOf($triggers);
        if ($table === null) {
            return;
        }
        [$schema, $tableName] = explode('.', $table, 2);
        $this->func->installEventTrigger($schema, $tableName);
    }

    /**
     * The qualified event table declared by a triggers block, or null.
     */
    private function eventTableOf(?array $triggers): ?string
    {
        $table = $triggers['event']['table'] ?? null;
        return (is_string($table) && str_contains($table, '.')) ? $table : null;
    }

    /**
     * The event table a stored function currently subscribes to, or null.
     */
    private function currentEventTable(string $name): ?string
    {
        try {
            $triggers = json_decode($this->func->getByName($name)['data']['triggers'] ?? 'null', true);
        } catch (\Throwable) {
            return null;
        }
        return is_array($triggers) ? $this->eventTableOf($triggers) : null;
    }

    /**
     * Drop the per-table event trigger once no function subscribes to it. The
     * trigger is shared, so we only remove it after the last subscriber leaves
     * (via delete or a triggers change). Best-effort: a leftover trigger is
     * harmless (the dispatcher discards unmatched events).
     */
    private function reconcileEventTrigger(?string $qualifiedTable): void
    {
        if ($qualifiedTable === null) {
            return;
        }
        try {
            if ($this->func->countEventSubscribers($qualifiedTable) === 0) {
                [$schema, $table] = explode('.', $qualifiedTable, 2);
                $this->func->removeEventTrigger($schema, $table);
            }
        } catch (\Throwable) {
            // leave the harmless stale trigger in place
        }
    }

    /**
     * Whether the invocation should be queued for async processing. Triggered by
     * `?async=true` or the header `X-Invocation-Type: Event` (AWS-style).
     */
    private function isAsync(): bool
    {
        if (!empty($_GET['async']) && filter_var($_GET['async'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        return strtolower((string)($_SERVER['HTTP_X_INVOCATION_TYPE'] ?? '')) === 'event';
    }

    /**
     * Whether this is a dry-run (`?dry=true`): run once to infer + store the
     * input/output type schemas used by /api/v4/function-interfaces.
     */
    private function isDry(): bool
    {
        return !empty($_GET['dry']) && filter_var($_GET['dry'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the status/result of one invocation, or list recent invocations for a
     * function when no id is given.
     *
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/functions/{name}/invocations/{id}', operationId: 'getInvocation', description: "Get an invocation record, or list recent invocations.", tags: ['Functions'])]
    #[OA\Parameter(name: 'name', description: 'Function name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'resizeImage')]
    #[OA\Parameter(name: 'id', description: 'Invocation id', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function get_invocations(): Response
    {
        $name = $this->route->getParam('name');
        $id = $this->route->getParam('id');
        // Ensure the function exists (and scope the lookup to it).
        $this->func->getByName($name);
        if (!empty($id)) {
            $row = $this->func->getInvocation($id)['data'];
            if ($row['function_name'] !== $name) {
                throw new GC2Exception("No invocation with that id", 404, null, "NO_INVOCATION_ERROR");
            }
            return $this->getResponse([self::formatInvocation($row)], single: true);
        }
        $rows = array_map(fn($r) => self::formatInvocation($r), $this->func->getInvocations($name)['data']);
        return $this->getResponse($rows);
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $action = $this->route->action;
        $name = $this->route->getParam('name');
        $method = Input::getMethod();

        if ($action === 'invocations') {
            if ($method === 'post') {
                if (empty($name)) {
                    throw new GC2Exception("A function name is required to invoke.", 400, null, "MISSING_FUNCTION_NAME");
                }
                $body = Input::getBody();
                if (!empty($body) && !json_validate($body)) {
                    throw new GC2Exception("Invalid JSON. Check your request", 400, null, "INVALID_DATA");
                }
            }
            return;
        }

        // CRUD on the collection/resource.
        if (empty($name) && in_array($method, ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a Function collection is not allowed.", 400);
        }
        if ($method === 'post' && !empty($name)) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), Input::getBody(), $method);
    }

    public function put_index(): Response
    {
        // Not implemented: use POST to create and PATCH to update.
    }

    public function options_invocations(): void
    {
    }

    public function head_invocations(): void
    {
    }

    /**
     * Resolve the configured runner, defaulting to the stub when none is set.
     */
    private function resolveRunner(): FunctionRunner
    {
        $class = App::$param['functionRunner'] ?? StubFunctionRunner::class;
        return new $class();
    }

    /**
     * Shape a settings.functions row for the API response.
     */
    private static function format(array $row): array
    {
        return [
            'name' => $row['name'],
            'runtime' => $row['runtime'],
            'handler' => $row['handler'],
            'package' => $row['package'] ?? 'inline',
            'code' => $row['code'],
            'env' => isset($row['env']) ? json_decode($row['env']) : null,
            'memory_mb' => isset($row['memory_mb']) ? (int)$row['memory_mb'] : null,
            'timeout_s' => isset($row['timeout_s']) ? (int)$row['timeout_s'] : null,
            'triggers' => isset($row['triggers']) ? json_decode($row['triggers']) : null,
            'input_schema' => isset($row['input_schema']) ? json_decode($row['input_schema']) : null,
            'output_schema' => isset($row['output_schema']) ? json_decode($row['output_schema']) : null,
            'version' => isset($row['version']) ? (int)$row['version'] : null,
            'created' => $row['created'] ?? null,
            'updated' => $row['updated'] ?? null,
        ];
    }

    /**
     * Shape a settings.function_invocations row for the API response.
     */
    private static function formatInvocation(array $row): array
    {
        return [
            'invocation' => $row['uuid'],
            'function' => $row['function_name'],
            'status' => $row['status'],
            'request' => isset($row['request']) ? json_decode($row['request']) : null,
            'response' => isset($row['response']) ? json_decode($row['response']) : null,
            'logs' => $row['logs'] ?? null,
            'error' => $row['error'] ?? null,
            'duration_ms' => isset($row['duration_ms']) ? (int)$row['duration_ms'] : null,
            'created' => $row['created'] ?? null,
            'finished' => $row['finished'] ?? null,
        ];
    }

    static public function getAssert(): Assert\Collection
    {
        $isPatch = Input::getMethod() === 'patch';
        $nameRule = [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Regex('/^[a-zA-Z_][a-zA-Z0-9_]*$/')];
        $runtimeRule = [new Assert\Type('string'), new Assert\Choice(choices: ['nodejs20', 'python312'])];
        $stringRule = [new Assert\Type('string'), new Assert\NotBlank()];

        return new Assert\Collection([
            'name' => $isPatch ? new Assert\Optional($nameRule) : new Assert\Required($nameRule),
            'runtime' => $isPatch ? new Assert\Optional($runtimeRule) : new Assert\Required($runtimeRule),
            'handler' => $isPatch ? new Assert\Optional($stringRule) : new Assert\Required($stringRule),
            'code' => $isPatch ? new Assert\Optional($stringRule) : new Assert\Required($stringRule),
            'package' => new Assert\Optional([new Assert\Type('string'), new Assert\Choice(choices: ['inline', 'zip'])]),
            'env' => new Assert\Optional([new Assert\Type('array')]),
            'memory_mb' => new Assert\Optional([new Assert\Type('integer'), new Assert\Range(min: 64, max: 4096)]),
            'timeout_s' => new Assert\Optional([new Assert\Type('integer'), new Assert\Range(min: 1, max: 900)]),
            'triggers' => new Assert\Optional([new Assert\Type('array')]),
        ]);
    }
}
