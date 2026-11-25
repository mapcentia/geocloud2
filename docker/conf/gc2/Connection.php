<?php

namespace app\conf;


/**
 * Connection params should be set through the environment.
 * If set in this file, the environment will be ignored.
 *
 * Environment variables:
 *
 * POSTGIS_HOST=postgis
 * POSTGIS_DB=postgres
 * POSTGIS_USER=gc2
 * POSTGIS_PORT=5432
 * POSTGIS_PW=1234
 * POSTGIS_PGBOUNCER=false
 *
 * MapServer and QGIS Server can read layers through another connection than the rest of GC2:
 * a PgBouncer (MapServer connects once per layer per map request, so cheap
 * connections matter) or a read replica. Every setting is optional and falls
 * back to the one above, so with none of them MapServer uses the normal
 * connection. The database is always the request's own.
 *
 * MAPSERVER_POSTGRES_HOST=pgbouncer
 * MAPSERVER_POSTGRES_PORT=6432
 * MAPSERVER_POSTGRES_USER=
 * MAPSERVER_POSTGRES_PASSWORD=
 * MAPSERVER_POSTGRES_PGBOUNCER=true   (the target is a PgBouncer)
 *
 * Existing mapfiles and QGIS project files keep the connection they were written
 * with until they are regenerated. GC2's own connection must stay a direct one: the scheduler
 * needs real sessions and refuses to run through a pooler.
 *
 * Class Connection
 * @package app\conf
 */
class Connection
{
    /**
     * @var array<string|bool>
     */
    static $param = array(
//        "postgishost" => "POSTGISHOST_CONFIGURATION",
//        "postgisdb" => "POSTGISDB_CONFIGURATION",
//        "postgisuser" => "POSTGISUSER_CONFIGURATION",
//        "postgisport" => "POSTGISPORT_CONFIGURATION",
//        "postgispw" => "POSTGISPW_CONFIGURATION",
//        "pgbouncer" => PGBOUNCER_CONFIGURATION,
//        "mapserver" => [
//            "host" => "pgbouncer",
//            "port" => "6432",
//            "pgbouncer" => true,
//        ],
    );
}