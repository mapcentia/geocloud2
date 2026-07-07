# Functions (Lambda-like serverless) in GC2

Run user-supplied code (Node.js / Python) on demand or in response to events, in
a sandbox, as a first-class citizen of the GC2 data plane. This document is the
architecture/implementation overview for maintainers. For the user-facing API
guide see the Centia.io docs page *Functions*.

## Design in one paragraph

GC2 already has a "named, stored, parameterised call" concept in **prepared
statements / methods** (`POST /api/v4/call`). A **function** is the code analog:
instead of SQL, `code` is Node/Python executed in a **gVisor sandbox**. The
**control plane** (PHP) owns CRUD, auth, validation, triggers and the invocation
lifecycle; a separate **data plane** (the Go `function-runner` service) executes
the code out-of-process so arbitrary user code never runs inside PHP/Apache. A
short-lived **scoped JWT** is handed to each invocation so handlers can call back
into GC2 (`/api/v4/sql`, `/call`, GraphQL) with the caller's own privileges —
row rules and geofence apply automatically because the callback flows through
`Statement.php`.

```
Client
  │  POST /api/v4/functions/{name}/invocations
  ▼
┌───────────────────────────────────────────────┐
│ Control plane (PHP, app/api/v4)                │
│  Func.php controller · Func model · scoped JWT │
│  triggers · sync/async dispatch · dry-run/DX   │
└───────────────┬───────────────────────────────┘
   sync: HttpFunctionRunner        async: enqueue → settings.function_invocations
                │                                     │
                ▼                                     ▼
┌───────────────────────────────┐        function_worker.php (cron, privileged)
│ gc2-function-runner (Go)       │◄───────────────────┘  claims queue → invokes runner
│  warm pool · sandbox (runsc)   │
│  runtime agent + user handler  │
│  callback → GC2 via scoped JWT │
└───────────────────────────────┘
```

## Data model

All tables live in each user database's `settings` schema (migrations in
`app/migration/Sql.php`).

**`settings.functions`**

| column | notes |
|---|---|
| `uuid`, `name` | name is unique + a valid TS/JS identifier |
| `runtime` | `nodejs20`, `python312` |
| `handler` | `file.export`, e.g. `index.handler` |
| `code` | inline source, or a base64 zip (see `package`) |
| `code_sha` | sha256 of code |
| `package` | `inline` (default) or `zip` |
| `env` | jsonb env vars for the runtime |
| `memory_mb`, `timeout_s` | resource limits |
| `triggers` | jsonb: `{schedule: "cron"}` and/or `{event: {table, on[]}}` |
| `input_schema`, `output_schema` | inferred by dry-run (for TypeScript) |
| `owner_context` | uid/database/superUser/userGroup captured at create; used to mint tokens for system-triggered runs |
| `last_scheduled_at` | minute-granular schedule dedupe |
| `version` | bumped on code change |

**`settings.function_invocations`** — invocation records + the async queue
(`status` pending→running→succeeded/failed, `invocation_type` sync|async,
`request`, `response`, `logs`, `error`, `duration_ms`, `context`).

**`settings.function_event_queue`** — durable DB-event queue, filled by the
`_gc2_function_event()` trigger, drained by the dispatcher.

## Runners

Execution is behind the `app\inc\FunctionRunner` interface (returns an
`InvocationResult`). Select with the `functionRunner` config key.

| Runner | Class | Use |
|---|---|---|
| Stub | `runners\StubFunctionRunner` | default; every invoke returns **501** |
| Local | `runners\LocalFunctionRunner` | dev; runs handlers in-process (limited by the web-server user's privileges) |
| Http | `runners\HttpFunctionRunner` | **production**; delegates to the Go service |

### The Go data-plane service — `function-runner/`

Standalone Go module (stdlib only). `POST /invoke` + `GET /healthz`, bearer-auth
(`GC2_RUNNER_TOKEN`). It materialises the code + a per-runtime agent into a work
dir and runs it under `timeout` wrapped by a configurable **sandbox** command.

- **Agents** (`function-runner/agents/`, embedded via `go:embed`; mirror the PHP
  `app/inc/runners/agents/`): one-shot (`*-bootstrap.*`) and long-lived pool
  (`*-pool-agent.*`) variants. The handler receives `(event, context)`.
- **Sandbox** `GC2_RUNNER_SANDBOX` (JSON array, placeholders
  `{workdir} {memory_mb} {timeout_s} {runtime}`): empty = none (dev),
  `["unshare","-rn","--"]` = drop network egress unprivileged, or a gVisor
  launcher for production, e.g.
  `["docker","run","--runtime=runsc","--rm","--network=none","--memory={memory_mb}m","-v","{workdir}:{workdir}","-w","{workdir}","gc2/fn-{runtime}"]`
  — or run the whole service under runsc.
- **Warm pools** (`GC2_RUNNER_POOL=1`, `pool.go`): keeps runtime processes alive
  keyed by `sha256(runtime+handler+code+env)`; a repeat invocation reuses an idle
  instance over a persistent stdin/stdout channel (Node ~110 ms cold → ~1 ms
  warm). Up to `GC2_RUNNER_POOL_MAX` concurrent instances; per-call timeout kills
  the instance; idle reaper trims after the TTL. The scoped token/context is sent
  per invocation, never baked into the instance. Under gVisor each warm instance
  *is* a reused sandbox (the Lambda execution-environment model).

Build/run: `docker/docker-compose.yml` has a `function-runner` service; see
`function-runner/README.md`.

## Invocation

`POST /api/v4/functions/{name}/invocations` with the event as the JSON body.

- **Sync** (default): the controller mints a scoped token, runs the runner
  inline, records the invocation, returns the result.
- **Async** (`?async=true` or header `X-Invocation-Type: Event`): inserts a
  `pending` row and returns **202** immediately with the invocation id. A
  privileged background worker (`app/scripts/function_worker.php`) claims pending
  rows (`FOR UPDATE SKIP LOCKED`), mints a fresh token, runs the runner and
  finalises the record. Poll `GET .../invocations/{id}`.

The worker being a separate privileged process is what makes async work: it can
exec the runtime/sandbox (or, with `HttpFunctionRunner`, just calls the Go
service), unlike the web-server user.

## Scoped tokens & data-plane callbacks

`app\inc\FunctionToken` mints a short-lived JWT per invocation, signed with the
database's super-user API key (the same secret `Jwt::parse()` validates against)
and carrying the **invoking user's** identity (`uid`/`superUser`/`userGroup`) —
no privilege escalation. The handler gets `context.token` + `context.apiBaseUrl`
and can call back into GC2; because the callback goes through the normal auth +
`Statement.php` pipeline, row rules and geofence are enforced automatically.

## Triggers

Functions can run automatically. Both trigger types only **enqueue** an async
invocation — the worker executes it.

- **Schedule** (`triggers.schedule`, a cron expression):
  `app/scripts/function_scheduler.php` (cron, once/min) enqueues due functions
  via `app\inc\FunctionScheduler` (`Cron\CronExpression`); `last_scheduled_at`
  dedupes within a minute.
- **DB events** (`triggers.event = {table, on:[insert,update,delete]}`): a
  dedicated durable queue + trigger, **separate from `settings.outbox`** (which
  the realtime listener drains on sight). On create/update the controller
  installs the shared per-table `_gc2_function_event_trigger`;
  `app/scripts/function_event_dispatcher.php` drains `function_event_queue`,
  matches events to functions (`app\inc\FunctionEventDispatcher`) and enqueues
  invocations with `{op, schema, table, pk, row}`. The trigger is
  **reference-counted**: it is dropped once the last subscriber leaves (delete or
  a triggers change).

Wire the three CLIs into cron (already added to `docker/Dockerfile`):

```cron
* * * * * sudo -u www-data php -f .../function_scheduler.php
* * * * * sudo -u www-data php -f .../function_event_dispatcher.php
* * * * * sudo -u www-data php -f .../function_worker.php
```

With `HttpFunctionRunner` all three are DB/HTTP only, so `www-data` is safe.

## Packaging: inline or zip

`package: "inline"` (default) stores source in `code`. `package: "zip"` stores a
base64 zip; both runners extract it (zip-slip guarded) and resolve the handler
entry file (`resolveEntry`: Node tries `.mjs`/`.js`/`.cjs` respecting
`package.json` type, Python `.py`). Node resolves relative/`node_modules`
imports natively; the Python agents add the bundle dir to `sys.path`.

## Developer experience: dry-run + TypeScript

- `POST .../invocations?dry=true` runs once, infers the input/output type tree
  from the real event + result (`app\inc\FunctionSchema::infer`) and stores it.
  Note: side effects are **not** rolled back (opaque code).
- `GET /api/v4/function-interfaces` returns a generated TypeScript `Functions`
  interface from the stored schemas, e.g.
  `resize(event: { url: string }): Promise<{ ok: boolean }>;`.

## Security model

1. **gVisor** syscall sandbox (runsc) — the production isolation boundary.
2. Out-of-process execution — user code never runs in PHP/Apache.
3. Sandbox hardening: read-only rootfs, `--network=none`, memory cgroups,
   non-root, per-call wall-clock timeout.
4. Scoped, short-lived tokens carrying only the caller's privileges.
5. Runner service behind a shared-secret bearer token.
6. Zip-slip guards on bundle extraction.
7. Plan/quota limits via the existing pre-processors.

## Configuration reference (`app/conf/App.php`)

```php
"functionRunner" => \app\inc\runners\HttpFunctionRunner::class,
"functions" => [
    "runnerUrl"   => "http://function-runner:8090",
    "runnerToken" => "…shared secret…",  // == service GC2_RUNNER_TOKEN
    "apiBaseUrl"  => "http://gc2core",    // internal URL for callbacks (defaults to host)
    // LocalFunctionRunner only:
    "sandbox"     => ["unshare", "-rn", "--"],
],
```

Go service env: `GC2_RUNNER_ADDR`, `GC2_RUNNER_TOKEN`, `GC2_RUNNER_SANDBOX`,
`GC2_RUNNER_WORKDIR`, `GC2_RUNNER_POOL`, `GC2_RUNNER_POOL_MAX`,
`GC2_RUNNER_POOL_IDLE_PER_KEY`, `GC2_RUNNER_POOL_IDLE_TTL_SEC`.

## File map

| Area | Path |
|---|---|
| Controller | `app/api/v4/controllers/Func.php`, `FunctionInterface.php` |
| Model | `app/models/Func.php` |
| Runners | `app/inc/runners/{Stub,Local,Http}FunctionRunner.php`, `agents/` |
| Token / schema / triggers | `app/inc/FunctionToken.php`, `FunctionSchema.php`, `FunctionScheduler.php`, `FunctionEventDispatcher.php`, `FunctionWorker.php`, `FunctionRunner.php`, `InvocationResult.php` |
| CLIs | `app/scripts/function_{worker,scheduler,event_dispatcher}.php` |
| Go service | `function-runner/` |
| Migrations | `app/migration/Sql.php` |
| Tests | `app/tests/unit/Function*Test.php`, `app/tests/api/FunctionManagementCest.php`, `function-runner/main_test.go` |

## Testing

```sh
# PHP (inside the app container)
./vendor/bin/codecept run unit
./vendor/bin/codecept run api FunctionManagementCest
# Go service
cd function-runner && go test ./...
```

Integration tests (`HttpFunctionRunnerTest`, `FunctionCallbackIntegrationTest`)
skip gracefully when their dependency (the Go service / a reachable API) isn't
running.

## Status

Built: control-plane API, scoped-token callbacks, sync + async invocation,
schedule triggers, DB-event triggers (with reference-counted auto-removal), the
gVisor/runsc Go runner, warm pools/concurrency, cron wiring, zip bundles, and
dry-run + TypeScript generation. The only external dependency not exercisable in
the current dev environment is a host with `runsc` installed — the sandbox is a
one-line config swap once available.
