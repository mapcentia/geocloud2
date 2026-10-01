# Tile seeder v4 — queued seeds with node-independent status and cancel — design

- **Date:** 2026-09-30
- **Status:** approved in chat, spec for implementation
- **Replaces (as the supported API):** `app/api/v3/Tileseeder.php`, which becomes a
  shim over this queue
- **Related:** `2026-09-16-scheduler-locking-design.md` (heartbeat and stale
  reaping), `2026-09-15-snapshot-api-design.md` (the worker pattern this copies),
  `app/api/v4/controllers/MapcacheTileset.php` (already writes `settings.seed_jobs`
  and points at "the existing tileseeder tooling" for status and kill)

## Goal

Seeding a tile cache becomes a queued job that any GC2 node can start, watch and
cancel, so the API works behind a load balancer. The v3 endpoints keep their
shapes but stop shelling out.

Decisions taken during brainstorming:

1. **A dedicated worker runs the seed.** The API only queues a row and answers
   `202`; a cron tick (`app/scripts/seed_worker.php`) claims a row and spawns a
   detached `seed_run.php` that owns it. Status, log and cancel therefore work from
   every node, because everything lives in the row.
2. **Ad hoc only.** No cron on the seed resource. Recurring seeding stays a
   scheduler job that calls the API, and a `seed` flag on scheduler jobs (the way
   `snapshot` works) is a possible follow-up, not part of this.
3. **The snapshot pattern, self-contained** (approach A of three): rows in the
   tenant's `settings.seed_jobs`, claimed with `FOR UPDATE SKIP LOCKED`, its own
   concurrency limit. Not the scheduler's registry in `gc2scheduler`: seeds are
   something a user starts and follows on their own resource, and the scheduler's
   run slots, stickiness and cooldown are built around cron runs. If seeds turn
   out to starve the nightly imports, tightening to shared run slots is a later
   change with no API impact.
4. **Cancel is cooperative.** `DELETE` sets a flag; the owning worker acts on it.
   `pid` and `host` mean nothing on another node, and signalling across nodes needs
   addressing and trust GC2 does not have, while the flag needs only the database
   both sides already share.
5. **The log is the tail in the row.** No shared storage, no streaming the file
   from the owning node. `log` is on the single-job read and omitted from the list,
   exactly as scheduler runs do it.
6. **Progress is deferred.** Parsing `mapcache_seed`'s `\r` output into
   tiles-done/total is the most brittle part and is a follow-up; v1 records status,
   timings and the log tail.

## 1. Why this is a rewrite and not a port

Three defects in `app/api/v3/Tileseeder.php` are load-bearing for the design.

**Command injection.** `post_index()` interpolates `layer`, `grid`, `extent`,
`start`, `end` and `threads` straight into a shell string. A bearer token is
enough to run arbitrary commands as the web-server user. Every value must be
escaped, and the identifiers validated against the database's own mapcache
configuration before use.

**The database password on the command line.** `-d PG:'host=… password=…'` is
readable with `ps` by anyone on the node. It moves into the child's environment
as `PGPASSWORD`.

**Nothing finishes a job.** No row is ever updated: `GET` infers "running" from
`pgrep mapcache_seed` on the local node, so a job on another node looks dead and
`DELETE` either reports it missing or `kill -9`s whatever local process happens to
hold that pid. Rows accumulate forever, and the `host` column is written
literally as `"test"`.

## 2. Resource and routes

```php
#[Controller(route: 'api/v4/tileseeder/jobs/[uuid]', scope: Scope::SUB_USER_ALLOWED)]
```

| Method | Route | Behaviour |
|---|---|---|
| `POST` | `/api/v4/tileseeder/jobs` | One object or an array of objects. Validates, queues, answers **202** with `Location: /api/v4/tileseeder/jobs/<uuid[,uuid…]>` and `_links.self` per job. |
| `GET` | `/api/v4/tileseeder/jobs` | List, newest first. Filters `?status=`, `?tileset=`. No `log`. |
| `GET` | `/api/v4/tileseeder/jobs/{uuid}` | One job (object) or several by comma separated uuids (array), `log` included. |
| `DELETE` | `/api/v4/tileseeder/jobs/{uuid}` | Requests cancellation. **202** when the job is running (the worker has to act), **204** when it was still `pending` (cancelled outright). Comma separated uuids allowed; every uuid is validated before anything is written. |

No `DELETE *`: a client lists and cancels the uuids it wants. The v3 shim keeps
`*` for its own callers.

Request body:

```json
{ "name": "Nightly seed of bygninger", "tileset": "myschema.bygninger",
  "grid": "GoogleMapsCompatible", "zoom_start": 0, "zoom_end": 12,
  "extent_layer": "myschema.kommunegraense", "threads": 2 }
```

`name` is free text for the client's own use. `tileset` keeps v3's meaning (the
mapcache tileset, i.e. the layer key). `extent_layer` and `threads` are optional.

Response (single job read):

```json
{ "uuid": "…", "name": "…", "tileset": "myschema.bygninger", "grid": "GoogleMapsCompatible",
  "zoom_start": 0, "zoom_end": 12, "extent_layer": "myschema.kommunegraense", "threads": 2,
  "status": "running", "stale": false, "username": "viewer1", "host": "ip-10-0-1-7",
  "pid": 20326, "created": "…", "started": "…", "finished": null,
  "heartbeat": "…", "cancel_requested": null, "error": null,
  "log": "…last few KB…", "_links": { "self": "/api/v4/tileseeder/jobs/…" } }
```

Field names are `snake_case`, booleans real booleans, timestamps as Postgres
emits them. Lists are bare arrays.

## 3. Status model

```
pending ──claim──> running ──┬──> succeeded
                             ├──> failed      (non-zero exit, or reaped as stale)
                             └──> cancelled   (cancel_requested honoured)
pending ──cancel──> cancelled                 (no worker involved)
```

`stale` is computed, not stored: `status = 'running' AND heartbeat < now() -
SeedJob::STALE_RUNNING_INTERVAL`. The constant belongs to the seeder's own model,
the way `Snapshot::STALE_RUNNING_INTERVAL` ('2 hours') does, and is much shorter
here — **'10 minutes'** — because a seed heartbeats every 5 seconds, so silence for
ten minutes means the process or its node is gone. The reaper marks such rows
`failed` with `error = 'stale: no heartbeat from <host>'`, which is how a dead node
or a killed worker is cleaned up.

## 4. Cancel across nodes

1. `DELETE` on a `pending` row: status → `cancelled`, `finished = now()`, **204**.
2. `DELETE` on a `running` row: `cancel_requested = now()`, **202**. The response
   says the job is being stopped and links to itself for polling.
3. The owning worker re-reads its row on every heartbeat (5 s). On seeing the flag
   it sends `SIGTERM` to the `mapcache_seed` child, waits `cancelGraceSeconds`
   (default 10), then `SIGKILL`, and writes `cancelled` with the log tail.
4. A `DELETE` on a row that is already finished is a no-op **204** (idempotent).

A client on node B therefore cancels a job running on node A without knowing that
node A exists.

## 5. Log

The worker redirects the child's output to a node-local file under
`app/tmp/<database>/seed/<uuid>.log`, and on every heartbeat copies the last
`logTailBytes` (default 8 KB) into the row's `log` column. The path is recorded in
`log_path` for debugging on the node itself. Retention of the file is
`tileseeder.keepLogHours` (default 72); the worker removes older files when it
starts.

Progress (tiles done/total, rate, ETA) is a follow-up: it needs the `\r` chunks of
`mapcache_seed`'s output parsed, and the tail already tells a client what the
process last said.

## 6. Worker — `app/scripts/seed_worker.php`

Two processes, because a seed runs for hours while a cron tick must not.

**The tick — `seed_worker.php`**, cron under `flock -n` like the other scripts:

```
for each database in Database::listAllDbs():
    reap stale running rows (heartbeat older than STALE_RUNNING_INTERVAL)
    while live children on this node < tileseeder.maxConcurrent:
        claim one pending row  (SELECT … WHERE status='pending'
                                ORDER BY created LIMIT 1 FOR UPDATE SKIP LOCKED)
        status='running', started=now(), host=<node>
        spawn detached:  nohup timeout -s SIGINT -k 60 <maxHours>h \
                         php seed_run.php --database=<db> --uuid=<uuid>
    Model::disconnect($connection)
```

The tick therefore returns in milliseconds and can serve every database, and the
`flock` never covers a running seed. This is the scheduler's pattern
(`Job::runJob` spawns `get.php` the same way), not the snapshot worker's inline
`processPending`, which is right for jobs that take minutes and wrong for one that
can take hours.

**The run — `seed_run.php`** owns one job: it writes `pid`, runs `mapcache_seed`
through `proc_open`, and every 5 seconds writes `heartbeat`, copies the log tail
and re-reads `cancel_requested`. The heartbeat is also how it learns it has *lost*
the row: an update that touches no row means the row is no longer `running`
(reaped as stale, or finalised by a tick whose spawn probe read a false negative),
so the run stops its child and exits without writing a status — whoever took the
row owns the outcome. Without that, a run taken out of `running` kept seeding with
nothing able to observe or cancel it while its freed slot let the next tick start
a second seed on top (recorded 2026-10-01). It finalises its own row (`succeeded` / `failed`
with the exit code / `cancelled`), and a `register_shutdown_function` plus
SIGINT/SIGTERM handlers finalise it even on a crash or on the `timeout` kill —
exactly what `get.php` does for scheduler runs, so a row is never left `running`
by a process that is gone.

`SKIP LOCKED` is what lets several nodes run ticks against the same database
without claiming the same row.

Concurrency is per node (`tileseeder.maxConcurrent`, default 1) because the cost
is CPU and cache writes on that node. A queued backlog is fine; a client sees
`pending`.

## 7. Command building — `app/inc/tileseeder/SeedCommand.php`

A value object, so the command is testable without running anything:

```php
new SeedCommand(database: 'mydb', tileset: '…', grid: '…', zoomStart: 0, zoomEnd: 12,
                extentLayer: '…', threads: 2)
    ->argv(): list<string>      // escaped, no password
    ->env(): array              // ['PGPASSWORD' => …]
```

Rules it enforces, each with a unit test:

- Every value passes through `escapeshellarg()`; nothing is interpolated raw.
- `tileset` must exist as a `<tileset>` in the database's mapcache XML, `grid`
  must be one of the `<grid>` children **that tileset declares**, and
  `extent_layer` must be a relation that exists and that the caller may read.
  Unknown values are refused before any process starts: `400 UNKNOWN_GRID`,
  `404 TILESET_NOT_FOUND`, `404 EXTENT_LAYER_NOT_FOUND`,
  `403 INSUFFICIENT_PRIVILEGES`.
  *(Deviation, recorded 2026-10-01: this said `grid` in `Mapcache::getGrids()`,
  which reads `app/conf/grids/*.xml`. That is the wrong authority and made the
  feature unable to seed anything — GC2's generated config defines and every
  tileset declares a single inline grid `g20`, which `getGrids()` never lists, so
  the only accepted values were ones `mapcache_seed` then refused with "grid not
  configured for tileset". It also broke v3, which passed `grid` straight to the
  binary. `getGrids()` keeps its other callers; the seeder reads the tileset's own
  declarations. `extent_layer` existence is checked by the validator — so the v3
  shim gets it too — and the caller's read privilege by the v4 controller.)*
- `zoom_start <= zoom_end`, both integers within the grid's levels (one level per
  `<resolutions>` entry of that grid's definition; unbounded when the definition
  does not say).
- `-d` (the OGR datasource) and `-l` (the layer inside it) are passed **together
  or not at all**: with no `extent_layer`, neither is passed and the seed covers
  the grid's extent. *(Deviation, recorded 2026-10-01: `-d` used to be
  unconditional. `mapcache_seed` validates the datasource before seeding and
  exits 1 with "ogr datastore contains more than one layer" when `-l` is missing,
  so every seed without an `extent_layer` — the normal case — failed; on a
  datasource with one visible layer it silently clipped the seed to that layer's
  extent instead.)*
- `threads` is an integer in `1..tileseeder.maxThreads` (default 4).
- The PostgreSQL password never appears in `argv()`; it is only in `env()`.

## 8. Authorization

`Scope::SUB_USER_ALLOWED`, and the controller enforces layer privileges itself the
way `MapcacheTileset` does: the caller needs write/owner on the tileset's relation.
The row records `username`. A non-superuser sees, reads and cancels only its own
rows; a superuser sees every row in the database. `tileseeder.maxPending` (default
20) caps queued jobs per database so a token cannot fill the queue.

## 9. v3 shim

`app/api/v3/Tileseeder.php` keeps its four endpoints and response shapes, and
delegates to the same model:

| v3 endpoint | New behaviour |
|---|---|
| `POST /api/v3/tileseeder` | Queues; returns `{uuid, pid: null}` (`pid` is filled once a worker claims it). Its body keeps v3's field names, mapped on the way in: `layer` → `tileset`, `start` → `zoom_start`, `end` → `zoom_end`, `extent` → `extent_layer`, `threads` and `name` unchanged. `cmd` is dropped from the response: it leaked the database password. |
| `GET /api/v3/tileseeder` | `{success: true, pids: [{uuid, pid, name}]}` from the rows with `status='running'`, no `pgrep`. |
| `DELETE /api/v3/tileseeder/{uuid}` | Sets the cancel flag. `*` cancels every running row of the caller. |
| `GET /api/v3/tileseeder/log/{uuid}` | `{data: <last line of the tail>}` from the row. |

No `exec`, no `pgrep`, no `kill` anywhere in v3 after this. Rows written by the
old code (no status) are reported as `status: null` and are not claimed.

## 10. Migration — `app/migration/Sql.php`

`settings.seed_jobs` is extended in place; existing rows survive:

```sql
ALTER TABLE settings.seed_jobs ALTER COLUMN pid DROP NOT NULL;
ALTER TABLE settings.seed_jobs ALTER COLUMN host DROP NOT NULL;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS status VARCHAR(16);
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS username VARCHAR(255);
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS tileset VARCHAR(255);
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS grid VARCHAR(255);
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS zoom_start SMALLINT;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS zoom_end SMALLINT;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS extent_layer VARCHAR(255);
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS threads SMALLINT;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS started TIMESTAMPTZ;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS finished TIMESTAMPTZ;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS heartbeat TIMESTAMPTZ;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS cancel_requested TIMESTAMPTZ;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS error TEXT;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS log TEXT;
ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS log_path TEXT;
CREATE INDEX IF NOT EXISTS seed_jobs_status_created_idx ON settings.seed_jobs (status, created);
```

`name` stays `NOT NULL`; the controller defaults it to the tileset name when a
client omits it.

## 11. Config — `docker/conf/gc2/App.php`

```php
"tileseeder" => [
    "maxConcurrent" => 1,     // seeds running at once, per node
    "maxThreads" => 4,        // upper bound for the request's threads
    "maxPending" => 20,       // queued jobs per database
    "maxHours" => 12,         // timeout for one seed
    "cancelGraceSeconds" => 10,
    "logTailBytes" => 8192,
    "keepLogHours" => 72,
],
```

Read with defaults in code (`App::$param['tileseeder']['maxConcurrent'] ?? 1`), so
an install without the block behaves.

## 12. Worker safety

The controller takes its `Connection` from the constructor and threads it into
every model, per `AGENTS.md` §4; nothing calls `Database::setDb()` and nothing
reads `\app\conf\Connection::$param`. This is also a fix: v3 reads the connection
parameters from those statics. The worker owns its own connection per database and
disconnects between databases.

## 13. Tests

**Unit**
- `SeedCommand`: escaping (a tileset with `;`, `$(…)`, quotes stays one argument),
  the password absent from `argv()` and present in `env()`, zoom and thread bounds,
  unknown grid rejected.
- Status transitions: claim, cancel while pending, cancel while running, stale
  reaping — the pure parts of the model against a real Postgres, as
  `SchedulerLockTest` does.

**API cest** (`TileseederV4ApiCest`, provisioning its own user and a tileset)
- `POST` → 202, `Location`, row is `pending`.
- `GET` list and single; `log` only on the single read.
- `DELETE` pending → 204 and `cancelled`; `DELETE` running → 202 and the flag set;
  `DELETE` twice → idempotent.
- Sub-user isolation: a sub-user cannot see or cancel another user's job.
- Validation: unknown tileset, unknown grid, `zoom_start > zoom_end`, threads out
  of range, shell metacharacters — all `400`, nothing queued.
- `maxPending` enforced.
- The v3 shim: the four endpoints keep their shapes.

**Worker**
- Driven with a stub binary instead of `mapcache_seed` (the recipe the scheduler
  stop-test uses), so a test can assert claim → heartbeat → succeeded, and
  cancel → `SIGTERM` → `cancelled`, without producing tiles.

## 14. Out of scope, and why

- **Progress parsing** — deferred, see decision 6.
- **Seeding from a scheduler job** (`seed: true` next to `snapshot: true`) — the
  natural follow-up once this exists.
- **Fan-out to several nodes** for node-local caches (sqlite/disk): with a
  dedicated worker the seed warms the store the worker can reach. An install whose
  cache is node-local and whose worker runs elsewhere warms nothing useful; that is
  a deployment question, and the honest fix is a shared cache backend (s3/memcache)
  or a worker on the node that owns the store. Worth a line in the docs rather than
  code.
- **Killing across nodes by signal** — replaced by the cancel flag, decision 4.

## 15. Follow-ups left after implementation (recorded 2026-10-01)

Everything below was found during implementation or review, judged out of scope for
this branch, and left for a decision. None of it blocks the queue.

**Outside this feature, found by looking at it**

- **The generated mapcache config is not well-formed XML.** `Mapcachefile::write()`
  emits a bare trailing `&` in every mapserv URL, so libxml rejects the whole
  document; MapCache's own ezxml parser does not care, which is why it has never
  been noticed. `SeedCommand` therefore parses with `LIBXML_RECOVER`. Escaping the
  `&` is the real fix and touches config generation for every layer. Review
  confirmed recovery never makes the validator *accept* a grid a tileset lacks — a
  truncated or malformed file only causes refusals.
- **`Mapcachefile::write()` is a non-atomic `file_put_contents`** on a ~500 KB file,
  so a concurrent regeneration can make a seed request transiently answer 404 or
  `UNKNOWN_GRID`. Pre-existing for the tileset check; this feature extends the
  exposure to `grid` and `zoom_end`. Write-and-rename would close it.
- **`MapcacheTileset::seedDelete()` still runs its own detached `mapcache_seed -m
  delete`** and writes a status-less row, so the v3 tileseeder endpoints neither
  list nor stop it (its doc now says so). Migrating it onto this queue would give
  delete jobs the same cross-node status and cancel; it is new behaviour, not a fix.
- **The api Cests never drop the database they provision**, so every full api run
  leaves one behind. A cleanup helper would stop the dev Postgres accumulating them.

**Inside this feature**

- **`tileseeder.keepLogHours` cannot be honoured in the current image**: the
  pre-existing `clean_tmp_dir.php` cron removes everything under `app/tmp` older
  than 4 hours. The row's log tail — what the API serves — is unaffected.
- **The real-binary test depends on a local `app/wms/mapcache/<db>.xml`**, which is
  git-ignored and generated per install, so on a fresh container it skips and the
  end-to-end guard disappears. The unit and API fixtures still pin both Criticals at
  the validator and at `argv()`.
- **Claim latency is a full traversal.** Pass 1 visits every database (measured
  10.8 ms each, ~30 s over 2755 here) before anything is claimed, because the
  node-wide cap has to be known first. Connection count is unchanged. If a node's
  database count grows, move the count to a central registry in `gc2scheduler`, the
  way the scheduler's `started_jobs` already works, so the cap costs one query.
- **`extent_layer` must be a registered GC2 layer**, not just any relation:
  privileges come from `getGeometryColumns(..., 'privileges')`, which yields `none`
  for a plain table, so naming one answers 403. Conservative, and worth documenting.
- **`EXTENT_LAYER_NOT_FOUND` is decided before the tileset's privilege check**, so a
  sub-user can distinguish "relation exists" from "does not". Same ordering the
  pre-existing `TILESET_NOT_FOUND` already had.
- **`schema.table.extra` passes validation** (only the first two segments are used)
  and then fails inside `mapcache_seed` instead of as a 400.
- **v3 does not enforce `maxPending`** (v3 is super-user-only), and `GET` lists every
  running row while `DELETE *` filters on the caller — §9 mandates the filter.
- **Smaller:** `created` is `timestamp(0)`, so FIFO among same-second rows is
  physical order; `stale` is computed against the web node's clock while the reaper
  uses the database's; `SeedJobTest`'s `SKIP LOCKED` assertion passes for the wrong
  reason, though the locking itself was verified correct by hand with two concurrent
  sessions.
