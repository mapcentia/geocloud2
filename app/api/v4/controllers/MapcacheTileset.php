<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\AcceptedResponse;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Route2;
use app\inc\Util;
use app\models\Authorization;
use app\models\Tileseeder;
use app\models\User;
use OpenApi\Attributes as OA;
use PDO;
use PDOException;
use Override;

/**
 * Deletes a MapCache tileset's cached tiles. Deletion is delegated to `mapcache_seed -m delete`,
 * which understands every configured cache backend (disk, sqlite, bdb, s3, memcache) and supports
 * scoped invalidation by extent and zoom range. Because a full-tileset delete over a large cache
 * can take a long time, the seed process is launched detached in the background and tracked in
 * settings.seed_jobs (the same table the tile seeder uses); the request returns 202 Accepted with
 * a job uuid/pid that can be polled or killed through the existing tileseeder tooling.
 *
 * Deletion is a write operation: it requires an authenticated owner/superuser or a sub-user with
 * `read/write` on the layer (Bearer token or HTTP Basic), independent of the layer's read-auth level.
 */
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['DELETE', 'OPTIONS'])]
#[Controller(route: 'api/v4/mapcache/database/{database}/tileset/[tileset]', scope: Scope::PUBLIC)]
final class MapcacheTileset extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'mapcache';
    }

    #[OA\Delete(path: '/api/v4/mapcache/database/{database}/tileset/{tileset}', operationId: 'deleteMapcacheTileset', description: "Delete a MapCache tileset's cached tiles. A FULL delete (no bbox/zoom) wipes the backend store directly — synchronous for sqlite/bdb (200), background for disk (202); s3/memcache are not supported for a full delete (400 — use a scoped delete or TTL/lifecycle). A SCOPED delete (bbox and/or zoom) runs mapcache_seed -m delete as a background job (202). Requires write/owner authorization.", tags: ['Mapcache'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'tileset', description: 'Tileset name, i.e. the layer "schema.table" (vector variants "schema.table.mvt"/".json").', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.roads')]
    #[OA\Parameter(name: 'bbox', description: 'Optional extent to delete (triggers a scoped mapcache_seed delete): minx,miny,maxx,maxy in the grid SRS.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '890000,7260000,1730000,7870000')]
    #[OA\Parameter(name: 'zoom', description: 'Optional zoom range to delete (triggers a scoped mapcache_seed delete): minzoom,maxzoom (or a single zoom).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '0,12')]
    #[OA\Parameter(name: 'grid', description: 'Grid name for a scoped delete (default g20).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'g20')]
    #[OA\Response(response: 200, description: 'Tile cache deleted (synchronous backend wipe)')]
    #[OA\Response(response: 202, description: 'Deletion job started (scoped seed, or background disk wipe)')]
    #[OA\Response(response: 400, description: 'Bad request, or full delete unsupported for the backend (s3/memcache)')]
    #[OA\Response(response: 401, description: 'Authentication required')]
    #[OA\Response(response: 403, description: 'Not authorized')]
    #[OA\Response(response: 404, description: 'Database cache config or tileset not found (scoped delete)')]
    #[Override]
    public function delete_index(): Response
    {
        $database = $this->route->getParam('database');
        $tileset = $this->route->getParam('tileset');
        if (empty($tileset) || !str_contains($tileset, '.')) {
            throw new GC2Exception('A qualified tileset name ("schema.table") is required', 400, null, 'BAD_REQUEST');
        }
        // The tileset's underlying layer (vector .mvt/.json variants share the layer's privileges).
        $layer = preg_replace('/\.(mvt|json)$/i', '', $tileset);

        // Authorize before revealing whether the config/tileset exists.
        $this->requireWrite($database, $layer);

        // Read the URL query string directly: Input::get() parses the request BODY for DELETE, so
        // ?bbox=&zoom=&grid= would otherwise be silently ignored (a full-tileset delete).
        $query = [];
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $grid = !empty($query['grid']) ? $query['grid'] : 'g20';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $grid)) {
            throw new GC2Exception('Invalid grid', 400, null, 'BAD_REQUEST');
        }
        $bbox = self::parseBbox($query['bbox'] ?? null);
        $zoom = self::parseZoom($query['zoom'] ?? null);

        // Hybrid strategy. mapcache_seed -m delete iterates the whole grid coordinate space (it does
        // not enumerate existing tiles), so a full-tileset delete over a deep grid never terminates.
        // Therefore: a SCOPED delete (bbox/zoom) — where the coordinate space is bounded — goes to
        // mapcache_seed; a FULL delete wipes the backend store directly (O(existing tiles)).
        if ($bbox !== null || $zoom !== null) {
            return $this->seedDelete($database, $tileset, $grid, $bbox, $zoom);
        }
        return $this->wipeBackend($database, $tileset, $layer);
    }

    /**
     * Scoped delete via a detached mapcache_seed -m delete background job (works for every backend
     * because the bounded extent/zoom keeps the coordinate iteration small). Tracked in
     * settings.seed_jobs; returns 202 with the job uuid/pid.
     *
     * @throws GC2Exception|\Throwable
     */
    private function seedDelete(string $database, string $tileset, string $grid, ?string $bbox, ?string $zoom): Response
    {
        $config = App::$param['path'] . 'app/wms/mapcache/' . $database . '.xml';
        if (!is_file($config)) {
            throw new GC2Exception('No tile cache configuration for this database', 404, null, 'NOT_FOUND');
        }
        if (!str_contains((string)file_get_contents($config), '<tileset name="' . $tileset . '"')) {
            throw new GC2Exception('Tileset not found', 404, null, 'NOT_FOUND');
        }

        $uuid = Util::guid();
        $log = App::$param['path'] . 'public/logs/seeder_' . $uuid . '.log';
        $cmd = '/usr/bin/nohup /usr/local/bin/mapcache_seed'
            . ' -c ' . escapeshellarg($config)
            . ' -t ' . escapeshellarg($tileset)
            . ' -g ' . escapeshellarg($grid)
            . ' -m delete';
        if ($bbox !== null) {
            $cmd .= ' -e ' . escapeshellarg($bbox);
        }
        if ($zoom !== null) {
            $cmd .= ' -z ' . escapeshellarg($zoom);
        }
        $cmd .= ' -v';
        $pid = (int)exec($cmd . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!');
        if ($pid <= 0) {
            throw new GC2Exception('Failed to start the tile cache deletion job', 500, null, 'JOB_START_FAILED');
        }
        new Tileseeder(connection: new Connection(database: $database))->insert([
            'uuid' => $uuid,
            'name' => 'delete ' . $tileset,
            'pid' => $pid,
            'host' => $_SERVER['SERVER_ADDR'] ?? '',
        ]);

        return new AcceptedResponse([
            'success' => true,
            'mode' => 'seed',
            'message' => 'Scoped tile cache deletion started',
            'backend' => $this->cacheType($database, preg_replace('/\.(mvt|json)$/i', '', $tileset)),
            'uuid' => $uuid,
            'pid' => $pid,
            'tileset' => $tileset,
            'grid' => $grid,
            'scope' => ['bbox' => $bbox, 'zoom' => $zoom],
            '_links' => ['self' => '/api/v4/mapcache/database/' . $database . '/tileset/' . $tileset],
        ]);
    }

    /**
     * Full-tileset delete by wiping the backend store directly, dispatched on the tileset's cache
     * type. File-based backends are removed by their known layout (see Mapcachefile). s3/memcache
     * can't be wiped cheaply here, so they are steered to a scoped delete or TTL/lifecycle expiry.
     *
     * @throws GC2Exception
     */
    private function wipeBackend(string $database, string $tileset, string $layer): Response
    {
        $cache = $this->cacheType($database, $layer);
        $base = App::$param['path'] . 'app/wms/mapcache/';
        return match ($cache) {
            'sqlite' => $this->wipeSqlite($base . 'sqlite/' . $database . '/' . $tileset . '.sqlite3', $tileset),
            'disk' => $this->wipeDiskDir($base . 'disk/' . $database . '/' . $tileset, $tileset),
            'bdb' => $this->wipeDir($base . 'bdb/' . $database . '/' . $tileset, $tileset, 'bdb'),
            's3', 'memcache' => throw new GC2Exception(
                "Full delete is not supported for the '$cache' cache backend. Use a scoped delete "
                . "(?bbox=…&zoom=…)" . ($cache === 's3' ? ' or an S3 lifecycle rule.' : '; memcache entries expire via TTL.'),
                400, null, 'UNSUPPORTED_BACKEND'
            ),
            default => throw new GC2Exception("Unknown cache backend '$cache'", 400, null, 'UNKNOWN_BACKEND'),
        };
    }

    /**
     * Empties the tileset's SQLite tile store (DELETE FROM tiles) rather than unlinking the file.
     * MapCache may run on a separate instance that this process cannot reload, and it caches the open
     * sqlite handle — an unlinked file would not be recreated (and no new tiles cached) until a
     * reload, whereas emptying it keeps the same file so MapCache re-seeds on demand. DELETE without a
     * WHERE uses SQLite's truncate optimization, so it stays fast even for millions of tiles (the
     * freed pages are reused as the cache refills; the file is intentionally not VACUUMed/shrunk).
     */
    private function wipeSqlite(string $dbFile, string $tileset): Response
    {
        if (!is_file($dbFile)) {
            return $this->completed('sqlite', $tileset, 0); // never seeded — nothing to empty
        }
        $removed = 0;
        try {
            $pdo = new PDO('sqlite:' . $dbFile);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // wait briefly if MapCache is mid-write
            $removed = (int)$pdo->exec('DELETE FROM tiles');
            $pdo = null;
        } catch (PDOException $e) {
            // A file with no "tiles" table has never cached anything — nothing to empty.
            if (!str_contains($e->getMessage(), 'no such table')) {
                throw new GC2Exception('Could not empty the SQLite tile cache: ' . $e->getMessage(), 500, null, 'CACHE_WIPE_FAILED');
            }
        }
        return $this->completed('sqlite', $tileset, $removed);
    }

    /**
     * Removes a disk tileset directory. To avoid blocking on a directory that may hold millions of
     * tile files, it is renamed to a tombstone (instant) and deleted in a detached background rm.
     */
    private function wipeDiskDir(string $dir, string $tileset): Response
    {
        if (!is_dir($dir)) {
            return $this->completed('disk', $tileset, 0);
        }
        $uuid = Util::guid();
        $tombstone = $dir . '.deleting-' . $uuid;
        if (!@rename($dir, $tombstone)) {
            exec('rm -rf ' . escapeshellarg($dir)); // fallback: synchronous
            return $this->completed('disk', $tileset, 1);
        }
        exec('/usr/bin/nohup rm -rf ' . escapeshellarg($tombstone) . ' > /dev/null 2>&1 &');
        return new AcceptedResponse([
            'success' => true,
            'mode' => 'wipe',
            'message' => 'Tile cache deletion started (disk directory removed in background)',
            'backend' => 'disk',
            'uuid' => $uuid,
            'tileset' => $tileset,
        ]);
    }

    /** Removes a cache directory (bdb) synchronously. */
    private function wipeDir(string $dir, string $tileset, string $backend): Response
    {
        $existed = is_dir($dir);
        if ($existed) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
        return $this->completed($backend, $tileset, $existed ? 1 : 0);
    }

    private function completed(string $backend, string $tileset, int $removed): Response
    {
        return new GetResponse([
            'success' => true,
            'mode' => 'wipe',
            'message' => 'Tile cache deleted',
            'backend' => $backend,
            'tileset' => $tileset,
            'removed' => $removed,
        ]);
    }

    /**
     * The tileset's cache backend: the layer's def.cache, else the configured default. Read directly
     * from settings.geometry_columns_join (not the cached getColumns) so a just-changed cache type is
     * never served stale — deleting the wrong backend would silently leave the real cache in place.
     */
    private function cacheType(string $database, string $layer): string
    {
        [$schema, $table] = array_pad(explode('.', $layer, 2), 2, '');
        $model = new Model(connection: new Connection(database: $database));
        // geometry_columns_join has an extra "<schema>.<table>.gc2_non_postgis" row with a NULL def
        // alongside the real geometry-column row, so require a non-null def.
        $res = $model->prepare(
            "SELECT def FROM settings.geometry_columns_join "
            . "WHERE split_part(_key_, '.', 1) = :s AND split_part(_key_, '.', 2) = :t AND def IS NOT NULL LIMIT 1"
        );
        $model->execute($res, [':s' => $schema, ':t' => $table]);
        $row = $model->fetchRow($res);
        $cache = null;
        if (!empty($row['def'])) {
            $def = json_decode((string)$row['def']);
            $cache = $def->cache ?? null;
        }
        return $cache ?: (App::$param['mapCache']['type'] ?? 'sqlite');
    }

    /**
     * Requires write/owner authorization for the layer, independent of the layer's read-auth level.
     * Superusers and schema owners always pass; sub-users need `read/write`. Bearer token and HTTP
     * Basic are both accepted; anonymous requests are challenged (401) or rejected.
     *
     * @throws GC2Exception|\Throwable
     */
    private function requireWrite(string $database, string $layer): void
    {
        $schema = explode('.', $layer)[0];
        $bearer = Input::getJwtToken();

        if ($bearer) {
            $jwt = Jwt::validate($bearer)['data'];
            if (($jwt['database'] ?? null) !== $database) {
                throw new GC2Exception('Token is not valid for this database', 403, null, 'FORBIDDEN');
            }
            if (!empty($jwt['superUser'])) {
                return; // database owner
            }
            $connection = new Connection(user: $jwt['uid'], database: $database, schema: $schema);
            $auth = new Authorization(connection: $connection);
            $chain = new User(connection: $connection)->getFullInheritance($jwt['userGroup'] ?? [], $database);
            if ($auth->isOwner($jwt['uid'], $chain, $schema)) {
                return;
            }
            $privileges = json_decode((string)new Model(connection: $connection)->getGeometryColumns($layer, 'privileges'), true) ?: [];
            if ($auth->extractHighestPrivilege($privileges, $jwt['uid'], $chain) === 'read/write') {
                return;
            }
            throw new GC2Exception('Insufficient privileges to delete the tile cache', 403, null, 'INSUFFICIENT_PRIVILEGES');
        }

        // HTTP Basic (or anonymous). authenticate() with isTransaction=true verifies the credentials
        // (challenging 401 when missing/wrong) and enforces write privilege for sub-users.
        $user = Input::getAuthUser() ?: $database;
        $connection = new Connection(user: $user, database: $database, schema: $schema);
        try {
            new BasicAuth(connection: $connection)->authenticate($layer, true);
        } catch (ServiceException $e) {
            throw new GC2Exception($e->getMessage(), 403, null, 'INSUFFICIENT_PRIVILEGES');
        }
    }

    /** @return string|null normalized "minx,miny,maxx,maxy", or null when absent */
    public static function parseBbox(?string $bbox): ?string
    {
        if (empty($bbox)) {
            return null;
        }
        $parts = explode(',', $bbox);
        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            throw new GC2Exception('bbox must be minx,miny,maxx,maxy', 400, null, 'BAD_REQUEST');
        }
        return implode(',', array_map(fn($p) => (string)(float)$p, $parts));
    }

    /** @return string|null normalized "minzoom,maxzoom" (or "z"), or null when absent */
    public static function parseZoom(?string $zoom): ?string
    {
        if (empty($zoom)) {
            return null;
        }
        $parts = explode(',', $zoom);
        if (count($parts) < 1 || count($parts) > 2 || count(array_filter($parts, 'ctype_digit')) !== count($parts)) {
            throw new GC2Exception('zoom must be minzoom,maxzoom (integers)', 400, null, 'BAD_REQUEST');
        }
        return implode(',', $parts);
    }

    public function validate(): void
    {
        // Input and authorization are handled in delete_index().
    }

    public function get_index(): Response    { throw new GC2Exception('Method not allowed', 405, null, 'METHOD_NOT_ALLOWED'); }
    public function post_index(): Response    { throw new GC2Exception('Method not allowed', 405, null, 'METHOD_NOT_ALLOWED'); }
    public function put_index(): Response     { throw new GC2Exception('Method not allowed', 405, null, 'METHOD_NOT_ALLOWED'); }
    public function patch_index(): Response   { throw new GC2Exception('Method not allowed', 405, null, 'METHOD_NOT_ALLOWED'); }
}
