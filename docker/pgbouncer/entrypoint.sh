#!/bin/sh
#
# PgBouncer in front of PostgreSQL, for MapServer and QGIS Server (the "pgbouncer"
# service of docker/docker-compose.yml). GC2 points them here with
# MAPSERVER_POSTGRES_HOST/PORT; see docker/conf/gc2/Connection.php.
#
# MapServer opens a connection for every layer of every map request. A real
# PostgreSQL connection costs a process and an authentication each time;
# PgBouncer keeps server connections open and hands them out.
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

; Session pooling: a client keeps one server connection for as long as it is
; connected, so everything a direct connection can do still works. The gain is
; the cheap connect, which is what MapServer needs. Do not point GC2's own
; connection here in transaction mode: the scheduler's advisory locks need
; real sessions.
pool_mode = ${PGBOUNCER_POOL_MODE:-session}
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
