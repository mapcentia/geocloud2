# AGENTS.md — GC2 (geocloud2) developer rules

Rules for agents and humans changing **this repository** (the GC2 / Centia
backend: PHP 8.4, PostgreSQL/PostGIS, MapServer, Codeception). Rules for
*consuming* the Centia API from an app live in the SDK/app repositories, not
here.

## 1. Repository map

| Path | What |
|---|---|
| `public/index.php` | Front controller. v1–v3 routes are listed by hand; v4 controllers are discovered by their `#[Controller]` attribute. Global CORS headers live here. |
| `app/api/v4/` | The current API: `AbstractApi` (base class), `Controller` + `Scope` (route attribute), `Acceptable*` attributes, `Responses/`, `controllers/`. |
| `app/api/v3/`, `app/api/v2/`, `app/api/v1/`, `app/controllers/` | Legacy APIs and the session-based GUI controllers. Keep them working; do not add new features there unless asked. |
| `app/inc/` | Shared services (`Route2`, `Connection`, `Model`, `Session`, `Jwt`, `SchedulerLock`, `snapshot/`, `WfsPaging`, …). |
| `app/models/` | Database models, one class per resource (`Table`, `Layer`, `Job`, `Snapshot`, …). All extend `Model`. |
| `app/migration/Sql.php` | Idempotent DDL applied at startup/upgrade. Every schema change goes here (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, partial unique indexes). |
| `app/scripts/` | CLI entry points run by cron/supervisord (`scheduler.php`, `get.php`, `snapshot_worker.php`, …). |
| `app/tests/unit`, `app/tests/api` | Codeception suites. Unit tests may hit Postgres; api tests hit the running web server. |
| `docker/` | Image, Apache vhost, supervisord, cron, and the **config template** `docker/conf/gc2/App.php`. |
| `app/conf/App.php` | The **real** local config. Git-ignored. Never commit, never print secrets from it. |
| `docs/superpowers/specs`, `docs/superpowers/plans` | Design specs and implementation plans. Specs are the binding authority for a feature; plans argue from them. |
| `docs/pages` | Sphinx user documentation. |

## 2. Hard rules

- **Never commit `app/conf/App.php`** (holds S3 keys and passwords) and never stage `docker/docker-compose.yml` if it has local edits you did not make. Add new settings to the template `docker/conf/gc2/App.php` with a comment, and read them in code with a default: `App::$param['gc2scheduler']['minInterval'] ?? 0`.
- **Commit only when asked. Never push.** Do not merge to master on your own; present the options.
- **Destructive database operations** (DROP/TRUNCATE/DELETE of user data, dropping columns) need an explicit go-ahead from the user, including in test fixtures that touch shared databases.
- **No secrets in code, tests or docs.** Test users are created on the fly by the Cest itself.
- Branch per feature. The dev container serves the main checkout, so work on a branch in the main checkout, not in a separate worktree.

## 3. API design principles (v4)

All new HTTP functionality goes into a v4 controller. The principles below are what the existing controllers do; a new endpoint that breaks them is a defect.

**Resources and routes**
- Nouns, plural, nested under their owner: `/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}`, `/api/v4/scheduler/jobs/{id}`, `/api/v4/scheduler/runs/{uuid}`.
- One controller per resource, declared with `#[Controller(route: 'api/v4/scheduler/jobs/[id]', scope: Scope::SUPER_USER_ONLY)]`. `[x]` marks an optional segment; `(action)` dispatches to `method_<action>`; the bare route dispatches to `method_index` (`get_index`, `post_index`, `patch_index`, `delete_index`).
- Declare the allowed verbs with `#[AcceptableMethods([...])]`, content types with `#[AcceptableContentTypes]`, accepts with `#[AcceptableAccepts]`. Unsupported verbs throw `405 METHOD_NOT_ALLOWED`.
- `HEAD` is only dispatched when the controller declares a **non-void** `head_<action>()`. `OPTIONS` (CORS preflight) needs an `options_<action>()` stub that returns an empty 2xx; add one for every sub-route that browsers will call.

**Scopes**
- `Scope::SUPER_USER_ONLY` — database owner (management: schema, scheduler, snapshots).
- `Scope::SUB_USER_ALLOWED` — owner and sub-users; the controller must then enforce layer/relation privileges itself (`Authorization`, `Model::getGeometryColumns(..., 'privileges')`, geofence `Rule`/`Geofence`).
- `Scope::PUBLIC` — no token needed; the controller does its own auth (Basic viewer password, API key) and threads its own `Connection`.
- Scope violations are `403 SUPER_USER_ONLY`, not 500.

**Ids and batching ("one of" design)**
- Path ids may be a **comma separated list**: `GET /jobs/5497,5498`, `DELETE /sequences/a,b`. A single id returns one object; a list returns an array (`getResponse($rows, single: count($rows) === 1)`). On DELETE validate every id (existence, running state) **before** deleting any, so a bad list deletes nothing.
- `POST` accepts one object or an **array of objects** (`array_is_list($body)`); respond `201` with `Location: <base>/<id1,id2>`.
- Document both shapes in OpenAPI as `oneOf: [ref Resource, array of ref Resource]` on the GET response and the POST request body.
- Validate the id segment with a positive regex before it reaches SQL or the shell (`^\d+(,\d+)*$`, `^[A-Za-z0-9_\-]+$`). Never interpolate request input into SQL; use bound parameters and quoted identifiers.

**Responses**
- Use the helpers in `AbstractApi`: `getResponse($rows, single: bool)`, `postResponse($baseUri, $ids)` (201), `patchResponse(...)` (303 + Location), `deleteResponse()` (204), `AcceptedResponse` (202 for async work), `StreamedResponse` (bytes, with explicit headers), `RedirectResponse` (presigned URLs).
- Lists are **bare JSON arrays** (`[]`), never wrapped in `{"snapshots": [...]}`. Single resources are bare objects. Field names are `snake_case`. Timestamps are ISO 8601 as Postgres emits them; booleans are real booleans (cast `(bool)` in the presenter, use `FILTER_VALIDATE_BOOLEAN` on input).
- Errors are `GC2Exception($message, $httpCode, null, 'ERROR_CODE')`; the body is `{"success": false, "message", "code", "errorCode"}`. Use stable, SCREAMING_SNAKE codes (`JOB_NOT_FOUND`, `JOB_RUNNING`, `INVALID_REQUEST`, `RUN_NOT_FOUND`) and the matching HTTP status: 400 bad input, 401/403 auth, 404 missing, 409 state conflict, 501 feature not configured, 502 upstream storage/backend failure.
- Async operations: `POST` returns `202` with a status link; a `GET` on the resource exposes `status` (`pending/running/succeeded/failed/...`). Long downloads support `HEAD`, `Range`/`206` and expose `Content-Length`, `Accept-Ranges`, `ETag`.
- Add CORS `Access-Control-Allow-Headers`/`Expose-Headers` entries in `public/index.php` when a new endpoint needs new headers (e.g. `Range`, `If-Range`).

**Validation and OpenAPI**
- Validate bodies with a Symfony `Assert\Collection` returned by `static getAssert(string $method)` and `validateRequest(...)` in `validate()`; required on POST, optional on PATCH; `postWithResource()` when POST hits a resource id.
- Every action carries `OA\*` attributes (path, operationId, tags, parameters, requestBody, responses) and every resource has an `#[OA\Schema(schema: '...')]`. `swagger.php` builds the document from these; unannotated endpoints are invisible to the SDK and MCP generators.

**Compatibility**
- v1–v3 and the session controllers stay backward compatible: add fields, do not rename or remove; keep `|`-style legacy URL notations working when adding smarter defaults (see `WfsPaging::detect`).
- When a v4 endpoint changes, tell the downstream projects (SDK, MCP server, website docs, GUI app) what changed.

## 4. Worker-safe v4 controllers

GC2 must be able to run under a resident PHP SAPI (FrankenPHP/worker mode) where **process globals survive between requests**. php-fpm hides this because it resets state per request; do not rely on that.

- **Thread an explicit `Connection`.** Every v4 controller is constructed as `new $c($Route2, $connection)` by `index.php` (public routes get a default `Connection`, token routes get one built from the JWT's `uid`/`database`). Keep it: `parent::__construct($connection)` and pass `connection:` / `$this->connection` on to every `Model`, `Setting`, `Layer`, `Rule`, `Geofence`, `BasicAuth`, `Tilecache::bust()`, worker class, etc. Never call `Database::setDb()` in a v4 controller, and never read `\app\conf\Connection::$param['postgisdb']`. Symptom of the leak: `FATAL: database "schema" does not exist` (a URL segment became the database name).
- **No request state in statics.** Static caches keyed on nothing (`self::$cache = ...`) leak across users. Key caches on database + user, or keep them on the instance. `Route2`, JWT and session data are per request; do not stash them in class statics.
- **Own the lifecycle of extra PDO sessions.** Things like `SchedulerLock` open their own PDO to `gc2scheduler` (advisory locks are per session); acquire in the controller, `release()` in a `finally`. Long-lived CLI loops over many databases must call `Model::disconnect()` per database to avoid connection exhaustion.
- **Stateless response objects.** Set headers on the `Response` object (`StreamedResponse` takes a headers array), never with a bare `header()` call in a controller or model; `Route2` emits them.
- **Time and randomness** come from the request (`time()`, `Uuid`), never from a static initialised at class load.
- **Filesystem scratch** goes under `app/tmp/<database>/…` with unique names and is removed in a `finally`; nothing in the working directory.
- The same rules apply to models and `inc` classes called from v4: prefer constructor injection of `Connection` over the implicit global default (the legacy callers that still use the default are the exception, not the pattern).

## 5. Background work and CLI scripts

- Cron jobs run under `flock -n` in the Dockerfile; scripts that loop over databases call `Model::disconnect()` between databases.
- Scheduler runs (`get.php`) coordinate through Postgres **session advisory locks** (job lock class 42001, run slots 42002) and the `started_jobs` registry; never reintroduce lock files. Spawned runs are wrapped in `timeout -s SIGINT -k 60 20h`; handlers for SIGINT/SIGTERM and a shutdown function finish the registry row.
- Long operations heartbeat (`SchedulerLock::heartbeat`, snapshot `running` rows) so stale rows can be reaped (`STALE_RUNNING_INTERVAL`, 5-minute `stale` flag).
- Snapshot jobs (`POST /api/v4/snapshots`) are picked up by `snapshot_worker.php`: export with ogr2ogr to (Geo)Parquet, upload via the `SnapshotStorage` abstraction (`local` or `s3`), publish in one transaction, supersede older snapshots.

## 6. Testing

Run tests inside the dev container (host PHP lacks `curl`); repo is mounted at `/var/www/geocloud2`:

```bash
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SchedulerLockTest
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SchedulerJobV4ApiCest
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api   # full suite, foreground, ~10 min
```

- Every new endpoint gets an api Cest that provisions its own user (`POST /api/v2/user`, `POST /api/v4/oauth` password grant, `client_id: gc2-cli`) and cleans up. Pure logic gets a unit test. Cests are ordered and stateful: setup tests first, cleanup last.
- Run one suite per command; run the full api suite in the foreground. `SearchV4ApiCest` needs OpenSearch; `DatabaseManagementCest` is order-sensitive.
- Postgres: `docker exec postgres psql -U mydb -d <db>`; the scheduler registry is in database `gc2scheduler`.
- `error_log()` output lands in `docker logs docker-dev-1`.
- Manual checks against `http://localhost:8080` (dev container). Apache needs `SetEnv ap_trust_cgilike_cl 1` (in the vhost) for backend `Content-Length` to survive on HEAD/206; changing the vhost needs an image rebuild.

## 7. Process

- Non-trivial features: brainstorm → spec in `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` → plan in `docs/superpowers/plans/` → implement task by task with review. The spec is the authority; record deviations in it.
- Commit messages: conventional prefix (`feat(scheduler): …`, `fix(ogc): …`, `docs: …`), body explains the why, and end with the attribution lines the session provides.
- Keep changes scoped: touch legacy APIs only to keep them compatible with a v4 change.
