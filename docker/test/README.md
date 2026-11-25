# Clean test environment

`run.sh` starts a throw-away GC2 (PostgreSQL/PostGIS, Redis, OpenSearch, web
server), runs
the Codeception suites in it and removes the containers again.

```bash
docker/test/run.sh                         # unit, then api
docker/test/run.sh unit                    # one suite
docker/test/run.sh api SnapshotV4ApiCest   # one test class
docker/test/run.sh --keep api              # leave the containers for inspection
```

Exit status: `0` every suite passed, `1` a suite failed, `2` the environment
could not be started. Reports of failed tests are in `app/tests/_output`.

## What "clean" means

- Own compose project (`gc2test`) and network, no published ports and no named
  volumes. The dev containers and their databases are not touched, and both
  can run at the same time.
- PostgreSQL starts from an empty cluster. The image creates
  `template_geocloud`, `mapcentia` and `gc2scheduler`; `run.sh` then applies
  `app/migration/run.php` and creates the empty databases `mydb` and `aau`,
  which unit tests address by name.
- The checkout is mounted **read-only**, and `entrypoint.sh` lays a tmpfs
  overlay over it. GC2 and the tests can write anywhere (fixtures in
  `public/`, mapfiles, logs), but nothing reaches the checkout, and the scratch
  files of the dev environment (`app/tmp`, `app/wms/*`, `public/logs`) are
  hidden. Only `app/tests/_output` is written through.
- The configuration is the template `docker/conf/gc2/App.php` plus the
  overrides in `docker/test/App.php` — not the local `app/conf/App.php`. A
  setting the tests need goes into `docker/test/App.php`; no secrets there.
- MapServer reads layers through the PgBouncer of the postgres container
  (port 6432, switched to transaction pooling by `run.sh`) with persistent
  connections, while GC2 itself connects to PostgreSQL directly. The suites
  therefore cover a separate MapServer connection.
- The PostgreSQL password is generated for each run. `entrypoint.sh` writes it
  to the container's `Connection.php`, because cron jobs and
  `sudo -u www-data` do not inherit the container's environment.
- Containers, network and volumes are removed when the script ends, also after
  a failure or Ctrl-C. A run that was killed outright is cleaned up by the
  next one.

## Images

| Variable | Default |
|---|---|
| `GC2_TEST_IMAGE` | `dev` (build it with `docker compose -f docker/docker-compose.yml build dev`) |
| `GC2_TEST_POSTGIS_IMAGE` | `mapcentia/postgis:pg17-postgis3-php8.4-trixie-1` |
| `GC2_TEST_OPENSEARCH_IMAGE` | `opensearchproject/opensearch:3` |
| `GC2_TEST_PROJECT` | `gc2test`; set another name to run two environments side by side |

The code under test is the checkout, not the source inside the image; the
image supplies PHP, GDAL, MapServer and cron. `app/vendor` must be installed
in the checkout. The gc2 container runs privileged (overlay mount, and the
function runner's `unshare` sandbox), as the dev service does.

## Tests that are left out

Tests in the group `dev-data` read layers that exist only in a developer's own
`mydb`. `run.sh` skips that group in the unit suite; they still run in the dev
container.
