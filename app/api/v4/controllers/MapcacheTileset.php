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

    #[OA\Delete(path: '/api/v4/mapcache/database/{database}/tileset/{tileset}', operationId: 'deleteMapcacheTileset', description: "Delete a MapCache tileset's cached tiles (optionally scoped by extent and zoom). Runs mapcache_seed -m delete as a background job. Requires write/owner authorization.", tags: ['Mapcache'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'tileset', description: 'Tileset name, i.e. the layer "schema.table" (vector variants "schema.table.mvt"/".json").', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.roads')]
    #[OA\Parameter(name: 'bbox', description: 'Optional extent to delete: minx,miny,maxx,maxy in the grid SRS.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '890000,7260000,1730000,7870000')]
    #[OA\Parameter(name: 'zoom', description: 'Optional zoom range to delete: minzoom,maxzoom (or a single zoom).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '0,12')]
    #[OA\Parameter(name: 'grid', description: 'Grid name (default g20).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'g20')]
    #[OA\Response(response: 202, description: 'Deletion job started')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 401, description: 'Authentication required')]
    #[OA\Response(response: 403, description: 'Not authorized')]
    #[OA\Response(response: 404, description: 'Database cache config or tileset not found')]
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

        $config = App::$param['path'] . 'app/wms/mapcache/' . $database . '.xml';
        if (!is_file($config)) {
            throw new GC2Exception('No tile cache configuration for this database', 404, null, 'NOT_FOUND');
        }
        // The tileset must exist in the generated config, else mapcache_seed would error.
        if (!str_contains((string)file_get_contents($config), '<tileset name="' . $tileset . '"')) {
            throw new GC2Exception('Tileset not found', 404, null, 'NOT_FOUND');
        }

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

        // Launch mapcache_seed detached. escapeshellarg on every interpolated value; the seed
        // process writes to its own log and its pid is captured for tracking/killing.
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
            'message' => 'Tile cache deletion started',
            'uuid' => $uuid,
            'pid' => $pid,
            'tileset' => $tileset,
            'grid' => $grid,
            'scope' => ['bbox' => $bbox, 'zoom' => $zoom],
            '_links' => ['self' => '/api/v4/mapcache/database/' . $database . '/tileset/' . $tileset],
        ]);
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
