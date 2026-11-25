#!/bin/bash
#
# Entrypoint of the gc2 test container (docker/test/docker-compose.yml).
#
# Puts a writable overlay on top of the read-only checkout, so GC2 and the
# tests can write wherever they do in a normal installation (app/tmp,
# app/wms, fixtures in public/, ...) without a byte reaching the checkout.
# Everything written lives in a tmpfs and disappears with the container.

set -e

mkdir -p /overlay/upper /overlay/work
mount -t overlay overlay \
    -o lowerdir=/src,upperdir=/overlay/upper,workdir=/overlay/work \
    /var/www/geocloud2

# The checkout's own scratch files (mapfiles, tiles, logs of the dev
# environment) are not part of a clean installation
for dir in app/tmp app/wms/mapfiles app/wms/mapcache app/wms/files app/wms/qgsfiles public/logs; do
    mkdir -p "/var/www/geocloud2/$dir"
    mount -t tmpfs tmpfs "/var/www/geocloud2/$dir"
done

# The config template plus the test overrides, instead of the local
# app/conf/App.php with its keys and passwords.
cp /src/docker/test/App.php /var/www/geocloud2/app/conf/App.php
# The connection is written out rather than left to the environment: cron jobs
# and "sudo -u www-data" do not inherit the container's variables.
cat >/var/www/geocloud2/app/conf/Connection.php <<PHP
<?php

namespace app\\conf;

class Connection
{
    static \$param = [
        "postgishost" => "$POSTGRES_HOST",
        "postgisport" => "$POSTGRES_PORT",
        "postgisdb" => "$POSTGRES_DB",
        "postgisuser" => "$POSTGRES_USER",
        "postgispw" => "$POSTGRES_PASSWORD",
        "pgbouncer" => false,
        // MapServer reads layers through the PgBouncer of the postgres
        // container, so the suites exercise a separate MapServer connection.
        "mapserver" => [
            "port" => "$MAPSERVER_POSTGRES_PORT",
            "pgbouncer" => true,
        ],
    ];
}
PHP

# Reports of failed tests are the one thing that leaves the container
mkdir -p /var/www/geocloud2/app/tests/_output
mount --bind /test-output /var/www/geocloud2/app/tests/_output

# supervisord writes its log to the working directory
cd /var/www/geocloud2
exec /entrypoint.sh "$@"
