<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

/**
 * The database connection MapServer -- and QGIS Server -- read layers through.
 * It is written into the mapfiles and into the datasources of QGIS projects.
 *
 * MapServer is by far the busiest database client of an install that serves
 * tiles: it opens a connection for every layer of every map request. It can
 * therefore be pointed somewhere else than the rest of GC2 -- at a PgBouncer,
 * which makes those connections cheap, or at a read replica, which takes the
 * rendering load off the primary. GC2 itself (API, scheduler, workers) keeps
 * the normal connection; the scheduler in particular needs real sessions for
 * its advisory locks and must not go through a pooler.
 *
 * Every setting is optional and falls back, one by one, to the normal
 * connection, so with nothing configured MapServer connects exactly as before.
 * Set them in app/conf/Connection.php:
 *
 *   "mapserver" => ["host" => "pgbouncer", "port" => "6432", "pgbouncer" => true],
 *
 * or in the environment: MAPSERVER_POSTGRES_HOST, MAPSERVER_POSTGRES_PORT,
 * MAPSERVER_POSTGRES_USER, MAPSERVER_POSTGRES_PASSWORD and
 * MAPSERVER_POSTGRES_PGBOUNCER ("true"/"false"). The file wins over the
 * environment, as for the normal connection. The database is always the
 * request's own.
 *
 * The older "mapserverhost" setting (host only) still works.
 *
 * Persistent connections. With "persistent" => true (MAPSERVER_POSTGRES_PERSISTENT)
 * the mapfiles get PROCESSING "CLOSE_CONNECTION=DEFER": a MapServer process then
 * keeps its connection open for its lifetime instead of connecting for every
 * layer. Measured on a tileset of 50 layers, 12 requests at a time: 23 tiles/s
 * direct, 82 through a PgBouncer, 267 with persistent connections on top.
 *
 * The price is that every MapServer process holds one connection per database
 * it has drawn from, for as long as the process lives. Behind a PgBouncer in
 * transaction mode that is free -- a server connection is only borrowed while a
 * query runs -- which is why "persistent" defaults to on when the MapServer
 * connection is marked "pgbouncer" => true, and off otherwise. A PgBouncer in
 * session mode, or PostgreSQL itself, needs a connection per process and
 * database: (FastCGI processes) x (databases) must then fit in the pool or in
 * max_connections, so set "persistent" => false there unless it does.
 *
 * A kept connection can die under MapServer: when the PgBouncer (or, without
 * one, PostgreSQL) is restarted, the next request of each MapServer process
 * fails once before the process reconnects. Measured: 11 of 120 requests after
 * a PgBouncer restart, none after that. MapCache stores such an answer as a
 * tile for the raw formats (MVT, JSON), so restart Apache along with the
 * pooler. A restart of PostgreSQL behind a PgBouncer is not noticed.
 */
final class MapServerConnection
{
    private const array ENV = [
        'host' => 'MAPSERVER_POSTGRES_HOST',
        'port' => 'MAPSERVER_POSTGRES_PORT',
        'user' => 'MAPSERVER_POSTGRES_USER',
        'password' => 'MAPSERVER_POSTGRES_PASSWORD',
        'pgbouncer' => 'MAPSERVER_POSTGRES_PGBOUNCER',
        'persistent' => 'MAPSERVER_POSTGRES_PERSISTENT',
    ];

    public function __construct(
        public readonly string  $host,
        public readonly string  $port,
        public readonly string  $user,
        public readonly ?string $password,
        public readonly string  $database,
        /** True when the target is a PgBouncer: it rejects the startup "options" MapServer otherwise sends. */
        public readonly bool    $pgbouncer,
        /**
         * True when a MapServer process keeps its connection open between
         * requests (CLOSE_CONNECTION=DEFER) instead of connecting once per
         * layer. See the class comment for what that costs.
         */
        public readonly bool    $persistent = false,
    )
    {
    }

    public static function resolve(Connection $connection): self
    {
        $file = \app\conf\Connection::$param['mapserver'] ?? [];
        $file = is_array($file) ? $file : [];
        $setting = function (string $key) use ($file): ?string {
            $value = $file[$key] ?? null;
            if ($value === null || $value === '') {
                $env = getenv(self::ENV[$key]);
                $value = $env === false || $env === '' ? null : $env;
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            return $value === null ? null : (string)$value;
        };

        $pgbouncer = $setting('pgbouncer');
        $pgbouncer = $pgbouncer !== null ? filter_var($pgbouncer, FILTER_VALIDATE_BOOLEAN) : (bool)$connection->pgbouncer;
        // Only the MapServer connection's own settings switch this on: an
        // install whose normal connection is a PgBouncer keeps its mapfiles.
        $persistent = $setting('persistent');
        $persistent = $persistent !== null
            ? filter_var($persistent, FILTER_VALIDATE_BOOLEAN)
            : $setting('pgbouncer') !== null && $pgbouncer;
        return new self(
            host: $setting('host') ?? \app\conf\Connection::$param['mapserverhost'] ?? (string)$connection->host,
            port: $setting('port') ?? (string)$connection->port,
            user: $setting('user') ?? (string)$connection->user,
            password: $setting('password') ?? $connection->password,
            database: (string)$connection->database,
            pgbouncer: $pgbouncer,
            persistent: $persistent,
        );
    }
}
