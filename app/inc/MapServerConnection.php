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
 */
final class MapServerConnection
{
    private const array ENV = [
        'host' => 'MAPSERVER_POSTGRES_HOST',
        'port' => 'MAPSERVER_POSTGRES_PORT',
        'user' => 'MAPSERVER_POSTGRES_USER',
        'password' => 'MAPSERVER_POSTGRES_PASSWORD',
        'pgbouncer' => 'MAPSERVER_POSTGRES_PGBOUNCER',
    ];

    public function __construct(
        public readonly string  $host,
        public readonly string  $port,
        public readonly string  $user,
        public readonly ?string $password,
        public readonly string  $database,
        /** True when the target is a PgBouncer: it rejects the startup "options" MapServer otherwise sends. */
        public readonly bool    $pgbouncer,
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
        return new self(
            host: $setting('host') ?? \app\conf\Connection::$param['mapserverhost'] ?? (string)$connection->host,
            port: $setting('port') ?? (string)$connection->port,
            user: $setting('user') ?? (string)$connection->user,
            password: $setting('password') ?? $connection->password,
            database: (string)$connection->database,
            pgbouncer: $pgbouncer !== null
                ? filter_var($pgbouncer, FILTER_VALIDATE_BOOLEAN)
                : (bool)$connection->pgbouncer,
        );
    }
}
