#!/bin/sh
#
# PgBouncer in front of PostgreSQL, for MapServer and QGIS Server (the "pgbouncer"
# service of docker/docker-compose.yml). GC2 points them here with
# MAPSERVER_POSTGRES_HOST/PORT; see docker/conf/gc2/Connection.php.
#
# Without a pooler MapServer opens a connection for every layer of every map
# request, and a real PostgreSQL connection costs a process and an
# authentication each time. With this one the MapServer processes keep their
# connections open and PgBouncer shares a small number of server connections
# between them.
#
# Restart the web server (the MapServer processes) when this container is
# restarted: each process otherwise fails one request on its dead connection
# before it reconnects.
#
# The config is written from the environment, so no password is kept in a file
# in the repository: POSTGRES_HOST, POSTGRES_PORT, POSTGRES_USER and
# POSTGRES_PASSWORD, the same variables GC2 reads.

set -e

: "${POSTGRES_HOST:?}" "${POSTGRES_USER:?}" "${POSTGRES_PASSWORD:?}"
dir=/tmp/pgbouncer
mkdir -p "$dir"
umask 077

cat > "$dir/pgbouncer.ini" <<INI
[databases]
* = host=${POSTGRES_HOST} port=${POSTGRES_PORT:-5432}

[pgbouncer]
listen_addr = 0.0.0.0
listen_port = 6432
unix_socket_dir =
auth_type = scram-sha-256
auth_file = $dir/userlist.txt

; Transaction pooling: a server connection is only borrowed while a query
; runs. MapServer processes keep their client connection open between requests
; (CLOSE_CONNECTION=DEFER in the mapfiles), one per database they draw from, and
; in session mode each of those would pin a server connection.
; Do not point GC2's own connection here: the scheduler's advisory locks need
; real sessions, and it refuses to run through a transaction pooler.
pool_mode = ${PGBOUNCER_POOL_MODE:-transaction}
server_reset_query = DISCARD ALL

; Server connections per database and user. Keep
; (databases in use) x default_pool_size below PostgreSQL's max_connections.
default_pool_size = ${PGBOUNCER_DEFAULT_POOL_SIZE:-40}
max_client_conn = ${PGBOUNCER_MAX_CLIENT_CONN:-1000}
server_idle_timeout = 60
server_lifetime = 1200
server_connect_timeout = 15
ignore_startup_parameters = extra_float_digits,options
admin_users = ${POSTGRES_USER}
stats_users = ${POSTGRES_USER}
INI

printf '"%s" "%s"\n' "$POSTGRES_USER" "$POSTGRES_PASSWORD" > "$dir/userlist.txt"

exec pgbouncer "$dir/pgbouncer.ini"
