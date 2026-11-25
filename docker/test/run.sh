#!/usr/bin/env bash
#
# Runs the Codeception suites in clean containers and removes the containers.
#
#   docker/test/run.sh                       unit, then api
#   docker/test/run.sh unit                  one suite
#   docker/test/run.sh api SnapshotV4ApiCest one test class
#   docker/test/run.sh --keep api            leave the containers running
#
# Exit status: 0 when every suite passed, 1 when a suite failed, 2 when the
# environment could not be started. See docker/test/README.md.

set -uo pipefail

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    sed -n '3,12p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    exit 0
fi

# One function, so bash has read the whole script before a run starts and
# editing this file during a run cannot derail it.
main() {

here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
project=${GC2_TEST_PROJECT:-gc2test}
keep=0

if [ "${1:-}" = "--keep" ]; then
    keep=1
    shift
fi

# A password that exists for this run only
GC2_TEST_PG_PASSWORD=$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')
export GC2_TEST_PG_PASSWORD

compose() { docker compose -p "$project" -f "$here/docker-compose.yml" "$@"; }
in_gc2() { compose exec -T -w /var/www/geocloud2/app gc2 "$@"; }
psql_pg() { compose exec -T postgres psql -U postgres -v ON_ERROR_STOP=1 "$@"; }

cleanup() {
    if [ "$keep" = 1 ]; then
        echo
        echo "Containers left running (project $project). Remove them with:"
        echo "  GC2_TEST_PG_PASSWORD=x docker compose -p $project -f $here/docker-compose.yml down -v"
        return
    fi
    echo
    echo "== Removing the containers"
    compose down -v --remove-orphans >/dev/null 2>&1 || echo "WARNING: could not remove project $project"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

fail_env() {
    echo "ERROR: $1" >&2
    compose logs --tail 40 >&2 || true
    exit 2
}

echo "== Starting clean containers (project $project)"
# Leftovers of an interrupted run
compose down -v --remove-orphans >/dev/null 2>&1
compose up -d --wait postgres redis opensearch || fail_env "PostgreSQL, Redis or OpenSearch did not start"

# MapServer goes through the postgres image's PgBouncer and keeps its
# connections open (see docker/conf/gc2/Connection.php). That wants transaction
# pooling; the image is configured for session pooling.
compose exec -T postgres sh -c "sed -i 's/^pool_mode = .*/pool_mode = transaction/' /etc/pgbouncer/pgbouncer.ini && pkill -HUP pgbouncer" \
    || fail_env "could not switch PgBouncer to transaction pooling"

compose up -d gc2 || fail_env "the GC2 container did not start"

echo "== Waiting for the web server"
for i in $(seq 1 60); do
    if in_gc2 curl -s -o /dev/null http://localhost/ 2>/dev/null; then break; fi
    [ "$i" = 60 ] && fail_env "the web server did not answer"
    sleep 1
done

echo "== Applying migrations"
in_gc2 php migration/run.php || fail_env "migrations failed"

# Unit tests address these two databases by name
echo "== Creating the databases mydb and aau"
for db in mydb aau; do
    psql_pg -q -d postgres -c "CREATE DATABASE $db TEMPLATE template_geocloud" || fail_env "could not create $db"
done

if [ $# -gt 0 ]; then
    runs=("$*")
else
    runs=(unit api)
fi

status=0
failed=()
for run in "${runs[@]}"; do
    echo
    echo "== codecept run $run"
    # Tests in the group dev-data read layers of a developer's own mydb
    # shellcheck disable=SC2086
    if ! in_gc2 php vendor/bin/codecept run --skip-group dev-data $run; then
        status=1
        failed+=("$run")
    fi
done

echo
if [ $status = 0 ]; then
    echo "== All suites passed"
else
    echo "== FAILED: ${failed[*]}"
fi
exit $status
}

main "$@"
