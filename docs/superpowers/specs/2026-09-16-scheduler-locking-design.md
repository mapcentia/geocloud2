# Scheduler locking with Postgres advisory locks

Date: 2026-09-16
Scope: `app/scripts/get.php`, `app/models/Job.php`, `app/scripts/scheduler.php`,
`app/scripts/scheduler_run_job.php`, `app/scripts/purge_locks.php`, `app/api/v3/Scheduler.php`,
`app/migration/Sql.php` (`gc2scheduler()`).

## Goal

Replace the file-based scheduler locks (`app/tmp/scheduler_locks/<jobId>.lock`,
`poll()` counting files, `purge_locks.php`) with locks held in the
`gc2scheduler` database, so that:

- a job can never run twice at the same time, regardless of who started it
  (cron, the API's "run now", a manual CLI call);
- the global concurrency cap counts only jobs that are really running;
- a crashed, killed or timed-out job releases its lock immediately, without a
  purge script and without a one-hour window of miscounting;
- several GC2 containers sharing one `gc2scheduler` database coordinate
  correctly;
- the running-jobs list the API shows is truthful and does not depend on
  `pgrep` on the web server.

## Non-goals

- No queue/worker-pool rewrite of scheduler.php (that is option 3).
- No change to how jobs are scheduled (cron expressions, `onlyOne()` in the
  cron library) or to the import pipeline in get.php beyond the lock calls.
- No change to `timeout -s SIGINT 20h` as the hard upper bound.

## Mechanism

Two kinds of Postgres session-level advisory locks on the `gc2scheduler`
database, both taken on a dedicated connection that get.php holds open for
its whole lifetime (the *lock session*):

1. **Job lock**, one per job: `pg_try_advisory_lock(GC2_JOB_LOCK_CLASS, <job id>)`
   using the two-argument form (`classid`, `objid`), with
   `GC2_JOB_LOCK_CLASS = 42001`. Exclusive: a second get.php for the same job
   gets `false` and exits with a clear message.
2. **Run slot**, one of `N` (default 20): `pg_try_advisory_lock(GC2_SLOT_LOCK_CLASS, <slot>)`
   for `slot = 1..N`, `GC2_SLOT_LOCK_CLASS = 42002`. The job takes the first
   free slot; if none is free it waits (sleep 10 s, loop, not recursion) and
   tries again. `N` comes from `App::$param['gc2scheduler']['maxJobs']`
   (default 20) so one deployment can raise or lower it without code.

Session-level advisory locks are released by Postgres the moment the lock
session ends, i.e. on normal exit, `exit(1)`, fatal error, SIGINT from
`timeout`, OOM kill, or the container going away. There is nothing to purge.

Why advisory locks and not a row in `started_jobs`: a row needs someone to
mark it finished, and that someone is exactly the process that may have died.
The lock's lifetime *is* the process's lifetime.

## The lock session

- get.php opens it right after option parsing, before anything else touches
  the databases: `$lockConn = new Connection(database: 'gc2scheduler')` and
  `$lockModel = new Model($lockConn)`; the PDO handle must not be shared with
  the `Job`/`Model` instances that get.php later uses for the target database
  (`Database::setDb($db)` swaps the global connection; the lock session must
  survive that).
- PgBouncer: advisory locks are session state. The lock session must connect
  with `pgbouncer: false` semantics, i.e. straight to Postgres, or to a
  PgBouncer pool in session mode. `Connection` already carries a `pgbouncer`
  flag; the lock session passes `pgbouncer: false` explicitly and the design
  documents that the gc2scheduler pool must not be transaction-pooled.
- The session runs `SET statement_timeout = 0` and `SET idle_in_transaction_session_timeout = 0`
  for itself; it never opens a transaction.
- A heartbeat keeps the socket alive through firewalls and lets the UI show
  liveness: every 60 s (from the existing places where get.php already loops
  and sleeps, plus one `SELECT 1` before each page/cell fetch) it runs
  `UPDATE started_jobs SET heartbeat = now() WHERE uuid = :run`.

## Catalog: `started_jobs` becomes the run registry

Migration (`Sql::gc2scheduler()`):

```sql
ALTER TABLE started_jobs ADD COLUMN started_at  TIMESTAMPTZ NOT NULL DEFAULT now();
ALTER TABLE started_jobs ADD COLUMN heartbeat   TIMESTAMPTZ;
ALTER TABLE started_jobs ADD COLUMN finished_at TIMESTAMPTZ;
ALTER TABLE started_jobs ADD COLUMN status      VARCHAR(16) NOT NULL DEFAULT 'running';
ALTER TABLE started_jobs ADD COLUMN host        VARCHAR(255);
ALTER TABLE started_jobs ADD COLUMN slot        INTEGER;
ALTER TABLE started_jobs ADD COLUMN exit_reason TEXT;
ALTER TABLE started_jobs ADD CONSTRAINT started_jobs_status_check
  CHECK (status IN ('running', 'succeeded', 'failed', 'skipped', 'lost'));
CREATE INDEX started_jobs_running_idx ON started_jobs (id) WHERE status = 'running';
```

Lifecycle of a row:

| Event | Who | Change |
|---|---|---|
| get.php acquired job lock + slot | get.php | INSERT row: id, db, name, pid, host (`gethostname()`), slot, status `running` (replaces the INSERT `Job::runJob` does today) |
| job lock busy | get.php | INSERT row with status `skipped`, `exit_reason = 'already running (run <uuid>)'`, `finished_at = now()`, then exit 0 |
| `cleanUp(1)` / `cleanUp(0)` | get.php | UPDATE status `succeeded` / `failed`, `finished_at = now()`, `exit_reason` (the last error line when failed) |
| shutdown without cleanUp (fatal, SIGINT) | get.php `register_shutdown_function` | UPDATE status `failed`, `exit_reason = 'terminated: ' . error_get_last()['message'] ?? 'signal'` — best effort, the lock is released by Postgres regardless |
| row still `running`, lock not held | reaper (see below) | UPDATE status `lost`, `finished_at = now()` |

Job identity for the API: `uuid` (already the primary key).

## Reaper

A row can stay `running` if the process died before its shutdown function
ran (SIGKILL, OOM, power). Instead of a purge-by-age script, a reaper checks
the truth in `pg_locks` (reading the catalog never takes a lock, unlike a
`pg_try_advisory_lock` probe would):

```sql
UPDATE started_jobs s SET status = 'lost', finished_at = now(),
       exit_reason = 'lock not held; process gone'
WHERE s.status = 'running'
  AND NOT EXISTS (
    SELECT 1 FROM pg_locks l
    WHERE l.locktype = 'advisory' AND l.classid = 42001 AND l.objid = s.id AND l.granted
  );
```

It runs (a) at the start of every get.php (cheap, keeps the list honest
without a cron), and (b) in `scheduler.php` each minute before dispatching.
`purge_locks.php` is deleted and its cron line removed from the Dockerfile.

## get.php changes

1. After option parsing: open the lock session; run the reaper.
2. `acquireJobLock($jobId)`: `SELECT pg_try_advisory_lock(42001, :id)`. On
   `false`: look up the running row for `id` for the message, INSERT a
   `skipped` row, print `Info: Job <id> is already running (run <uuid>, started <ts>, host <h>). Exiting.`, exit 0.
   (Exit 0 on purpose: an overlapping cron tick is not an error, and the
   scheduler log must not turn red.)
3. `acquireSlot()`: loop `for ($slot = 1; $slot <= $max; $slot++)`
   `SELECT pg_try_advisory_lock(42002, :slot)`; first `true` wins. None →
   print the waiting line (as `poll()` does today), `$report[SLEEP] += 10`,
   `sleep(10)`, repeat. A plain `while` loop replaces the recursive `poll()`.
4. INSERT the `running` row (moved here from `Job::runJob`, which no longer
   writes `started_jobs`; it keeps spawning and, when `$async` is false,
   waiting).
5. Remove: `$lockDir`/`$lockFile`, `touch`, the `unlink($lockFile)` in
   `cleanUp()`, `FilesystemIterator` counting.
6. `cleanUp($success)` updates the row's status; `register_shutdown_function`
   covers the paths that never reach `cleanUp()`. Also `pcntl_signal(SIGINT, …)`
   when `pcntl` is available, so `timeout`'s SIGINT runs `cleanUp(0)` with
   `exit_reason = 'timeout'` instead of dying mid-transaction.
7. Locks are never released explicitly; closing the lock session at the very
   end (`Model::disconnect($lockConn)`) releases them.

## Job.php / API changes

- `Job::runJob`: stop inserting into `started_jobs`; keep the `nohup timeout`
  spawn and the synchronous wait. Replace the `pgrep timeout` wait with a
  wait on the registry: poll `SELECT status FROM started_jobs WHERE pid = :pid AND host = :host ORDER BY started_at DESC LIMIT 1`
  every second until it is not `running` (or, for the first seconds, until the
  row exists), with the existing `kill()` fallback untouched.
- `Job::getAllStartedJobs($db)` → returns rows with status `running` (after
  running the reaper) plus the last 50 finished ones, newest first.
- `GET /api/v3/scheduler` drops the `pgrep timeout` check and returns the
  registry rows: `uuid, id, name, pid, host, status, started_at, heartbeat, finished_at, exit_reason`.
  A `running` row whose `heartbeat` is older than 5 minutes is flagged
  `stale: true` in the response (the lock is still held, so the process is
  alive but stuck; that is information, not an action).
- New `DELETE /api/v3/scheduler/{uuid}` → "stop this run": if the run's host
  equals `gethostname()`, `kill -INT` its pid (the existing `kill()` uses -9;
  use SIGINT first so the shutdown path records `terminated`, escalate to -9
  after 30 s); otherwise 409 with the host name (cross-host stop is out of
  scope).

## scheduler.php

Unchanged in behaviour: it still relies on the cron library's `onlyOne()` to
avoid dispatching the same cron entry twice within a minute, and get.php's
job lock now makes that a belt-and-braces rather than the only guard. It
additionally runs the reaper once per tick.

## Configuration

```php
"gc2scheduler" => [
    "*" => true,            // existing per-db enable map
    "maxJobs" => 20,        // NEW: run slots (advisory lock keys 42002/1..N)
],
```

`maxJobs` is read at get.php start; changing it does not affect running jobs.
Lowering it below the number of currently held slots is safe: slots above
the new N are simply no longer handed out once released.

## Failure and edge cases

| Situation | Behaviour |
|---|---|
| Process dies (any signal, OOM) | Locks released by Postgres at once; row fixed to `lost` by the next reaper run; slot immediately reusable |
| Postgres restarts during a job | Lock session drops; the job's data connection also drops, so the job fails as today; on restart nothing is stale |
| Same job started twice | Second run records `skipped` and exits 0 within milliseconds |
| More than N jobs due | Extra jobs wait in the slot loop (as today), but the count is exact |
| PgBouncer transaction pooling in front of gc2scheduler | Advisory locks would be lost between statements. The lock session bypasses the pooler; documented as a requirement |
| Two GC2 containers, same gc2scheduler DB | Correct: locks are database-global; `host` tells which container runs what |
| Reaper marks a live job `lost` | Impossible while its session holds the lock (`pg_locks.granted`); if the session is gone the process cannot be doing useful work against the database anyway |

## Migration and rollout

- `run.php` applies the `started_jobs` columns to `gc2scheduler`; old rows get
  status `running` by default — the first reaper run marks them `lost`
  because no lock is held. Expected and harmless.
- Deploy order does not matter: an old get.php ignores the new columns, a new
  get.php on an old table fails at the first UPDATE, so apply the migration
  before the image with the new script.
- Dockerfile: remove the `purge_locks.php` cron line.
- `app/tmp/scheduler_locks/` becomes unused; delete it in a later cleanup.

## Tests

Unit (`app/tests/unit/SchedulerLockTest.php`), against the real `gc2scheduler`
database:

- job lock: session A acquires (true), session B for the same id gets false,
  a different id gets true; closing A's connection lets B acquire.
- slots: with `maxJobs = 2`, three sessions: third gets no slot; after the
  first closes, the third gets slot 1.
- reaper: insert a `running` row for an id with no lock → becomes `lost`; a
  row whose id is locked by an open session stays `running`.
- shutdown bookkeeping: `Job::finishRun(uuid, 'failed', reason)` sets the
  fields.

API (`app/tests/api/SchedulerApiCest.php`): create a job, `GET /api/v3/scheduler`
shows nothing running; start it via the API against a tiny source, the
listing shows a `running` row with `host` and `heartbeat`, and after it ends
`succeeded` with `finished_at`; starting the same job twice while it runs
yields one `running` and one `skipped` row.

Manual: `kill -9` a running get.php and confirm the job can be started again
within seconds and the old row reads `lost`.

## v4 scheduler API

The v2 job controller (session-authenticated, `app/controllers/Job.php`) and
the v3 run listing stay for the existing UI. A v4 API exposes the same
capabilities under JWT with the usual v4 conventions (super-user only,
`Scope::SUPER_USER_ONLY`; job ownership is the JWT's `database`).

### Jobs: `api/v4/scheduler/jobs/[id]` (controller `SchedulerJob`)

Resource (what GET returns):

```json
{
  "id": 42, "name": "bygninger", "schema": "geodanmark", "url": "https://…/wfs?…",
  "schedule": "0 3 * * *",            // "min hour dayofmonth month dayofweek", the five jobs columns joined
  "epsg": 25832, "type": "AUTO", "encoding": "UTF8", "extra": null,
  "delete_append": false, "download_schema": true, "presql": null, "postsql": null,
  "active": true, "snapshot": false,
  "lastcheck": true, "lasttimestamp": "2026-09-16T03:00:12+00:00", "lastrun": null, "report": {…}
}
```

| Method | Path | Behaviour |
|---|---|---|
| GET | `/jobs` | all jobs of the caller's database, ordered by id |
| GET | `/jobs/{id}` | one job (object), or several with comma separated ids `/jobs/5497,5498` (array); 404 `JOB_NOT_FOUND` when any id is not in the caller's database |
| POST | `/jobs` | create one job (object) or several (array of objects); required `name, schema, url, schedule`; defaults `epsg 4326`, `type "AUTO"`, `encoding "UTF8"`, `delete_append false`, `download_schema true`, `active true`, `snapshot false`; 201 + `Location` |
| PATCH | `/jobs/{id}` | single id only; partial update of any writable field; 303 + `Location` |
| DELETE | `/jobs/{id}` | one id or comma separated ids; all ids are checked before anything is deleted; 204; 404 `JOB_NOT_FOUND`; 409 `JOB_RUNNING` while a run of any listed job is `running` |

Validation: `schedule` must be a valid five-field cron expression
(`Cron\CronExpression`, the library `Job::validateCronExpression` already
uses); `name` is normalised with `Model::toAscii(…, '_')` like v2; `epsg`
positive int; booleans typed; `url` non-empty string. Errors 400
`INVALID_REQUEST`. The `cron` column is written with the same string as
`schedule` for the legacy readers.

### Runs: `api/v4/scheduler/runs/[uuid]` (controller `SchedulerRun`)

Run resource = one `started_jobs` row:

```json
{ "uuid": "…", "job": 42, "name": "Started by Scheduler", "pid": 12345, "host": "gc2core-1", "slot": 3,
  "status": "running", "stale": false, "started_at": "…", "heartbeat": "…", "finished_at": null, "exit_reason": null }
```

| Method | Path | Behaviour |
|---|---|---|
| GET | `/runs` | running runs first, then the newest 50 finished, for the caller's database; filters `?job={id}`, `?status={running\|succeeded\|failed\|skipped\|lost}` |
| GET | `/runs/{uuid}` | one run; 404 `RUN_NOT_FOUND` |
| POST | `/runs` | body `{"job": 42, "force": false}`; starts the job asynchronously (the run row is created by get.php itself moments later); 202 `{"job": 42, "status": "starting", "_links": {"runs": "/api/v4/scheduler/runs?job=42"}}`; 404 `JOB_NOT_FOUND`; 409 `JOB_RUNNING` when a run is already `running` (checked before spawning, so a double click does not even spawn a process) |
| DELETE | `/runs/{uuid}` | stop: SIGINT, then SIGKILL after 30 s; 200 `{"uuid", "signal"}`; 404 `RUN_NOT_FOUND`; 409 `RUN_ON_OTHER_HOST` |

`stale` is `status = running AND heartbeat older than 5 minutes`. The reaper
runs before every listing, as in v3.

### Model changes for v4

`app\models\Job` gains db-scoped, id-based methods next to the v2 ones:
`getById(int $id, string $db): ?array`, `createJob(array $fields, string $db): int`,
`patchJob(int $id, string $db, array $fields): void`, `deleteJobById(int $id, string $db): void`.
`getAll`/`newJob`/`updateJob`/`deleteJob` stay for v2.

## Cooldown between runs

Users forget the cron fields and end up with a job that runs every minute.
A global minimum interval guards against that:

```php
"gc2scheduler" => [
    "*" => true,
    "maxJobs" => 20,
    "minInterval" => 3600,   // NEW: seconds that must pass since the last run of a job; 0 = off
],
```

get.php, right after the job lock and before a slot is taken: look up the
job's latest run (`SchedulerLock::latestRun($jobId)`: newest `started_at`
among `running`, `succeeded`, `failed`, `lost` — `skipped` rows do not
count). If `now - started_at < minInterval`, record a `skipped` row with
`exit_reason = "cooldown: last run started <ts>, <n> s ago, minimum <m> s"`,
print `Info: Job <id> skipped: cooldown …`, and exit 0. The check happens
after the lock so two overlapping starts cannot both pass it.

Manual starts bypass the cooldown: "run now" from the v2 UI
(`app/controllers/Job.php::get_run`), `POST /api/v3/scheduler` and
`POST /api/v4/scheduler/runs` call `Job::runJob(..., manual: true)`, which
appends `--manual 1` to the get.php command line; `scheduler_run_job.php`
(cron) does not. get.php reads `--manual` (default 0) and skips the cooldown
check when it is set; the run row's `name` keeps the trigger label the caller
passes (`Started by Scheduler`, `Started from web-ui`, `Started via API v4 by …`).
