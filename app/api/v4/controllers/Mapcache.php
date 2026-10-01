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
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\conf\App;
use app\controllers\Mapcache as MapcacheController;
use app\exceptions\GC2Exception;
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Route2;
use app\inc\Util;
use app\models\Authorization;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Authorizing proxy in front of MapCache, mirroring the OWS proxy: it authorizes every tile
 * request against the requested tileset's layer (anonymous, HTTP Basic, or Bearer token) and
 * only then streams the response from the configured MapCache backend (App::mapCache.host).
 *
 * MapCache tilesets are named "schema.table" (vector variants "schema.table.mvt"/".json"), so the
 * requested tileset maps directly to a GC2 layer and reuses the same read authorization as OWS.
 * The tileset is extracted per service (WMS/WMTS KVP + WMTS RESTful, TMS, Google Maps). Because a
 * tilecache is hit many times per second, the allow decision is cached in Redis for 60s keyed by a
 * hash of the credentials, the database and the tileset.
 */
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/mapcache/database/{database}/[p1]/[p2]/[p3]/[p4]/[p5]/[p6]/[p7]/[p8]/[p9]', scope: Scope::PUBLIC)]
final class Mapcache extends AbstractApi
{
    private const int AUTH_CACHE_TTL = 60;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'mapcache';
    }

    #[OA\Get(path: '/api/v4/mapcache/database/{database}/{path}', operationId: 'getMapcache', description: "Authorizing MapCache proxy (WMTS, TMS, WMS, Google Maps). Accepts anonymous, HTTP Basic and Bearer token. Tile requests are authorized against the tileset's layer.", tags: ['Mapcache'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'path', description: 'MapCache service path, e.g. "wmts/1.0.0/my_schema.my_table/default/g20/8/136/78.png" or "wms".', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Tile or MapCache response streamed from the backend')]
    #[OA\Response(response: 401, description: 'HTTP Basic authentication required')]
    #[OA\Response(response: 403, description: 'Not authorized for the requested tileset')]
    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function put_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function patch_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function delete_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function validate(): void
    {
        // Auth and layer checks happen inside stream(); nothing to validate here.
    }

    private function stream(): StreamedResponse
    {
        return new StreamedResponse(
            contentType: 'application/octet-stream', // overridden by upstream headers below
            callback: function () {
                Util::disableOb();
                try {
                    $database = $this->route->getParam('database');
                    $path = explode('?', $_SERVER['REQUEST_URI'] ?? '', 2)[0];
                    $tail = self::tail($path, $database);
                    $query = [];
                    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
                    $query = array_change_key_case($query, CASE_UPPER);
                    $segments = $tail === '' ? [] : explode('/', $tail);
                    $service = strtolower($query['SERVICE'] ?? ($segments[0] ?? ''));

                    $layers = self::extractLayers($service, $segments, $query);
                    // A merged per-schema tileset (a name with no dot) stands for every
                    // layer in that schema, so expand it and authorize each: the merged
                    // image shows them all, and the bare name on its own authorizes
                    // nothing, because no layer is called that.
                    // Resolve every name to something real before authorizing it. A
                    // name that resolves to nothing must fail closed: treating an
                    // unknown name as readable is what made the gmaps grid-suffix
                    // bug serve a protected layer anonymously, and it would reopen
                    // the same hole the moment another parser gap appeared.
                    $expanded = [];
                    $resolved = false;
                    $connection = new Connection(database: $database);
                    $configured = self::configuredTilesetNames();
                    foreach ($layers as $l) {
                        $ofSchema = self::schemaLayers(explode('.', $l)[0], $connection);
                        if (!str_contains($l, '.')) {
                            // The merged per-schema tileset stands for every layer in
                            // the schema: the image shows them all, so the caller has
                            // to be allowed to read them all.
                            if (!empty($ofSchema)) {
                                array_push($expanded, ...$ofSchema);
                                $resolved = true;
                            }
                        } elseif (in_array($l, $ofSchema, true)) {
                            $expanded[] = $l;
                            $resolved = true;
                        } elseif (in_array($l, $configured, true)) {
                            // Declared by the operator outside the layer model; it has
                            // no privileges to check. Resolved, nothing to authorize.
                            $resolved = true;
                        }
                    }
                    $layers = array_values(array_unique($expanded));
                    if (!$resolved && self::looksLikeTileFetch($segments, $query)) {
                        // A tile fetch whose tileset we could not resolve: fail closed.
                        throw new GC2Exception('Could not resolve tileset for authorization', 403, null, 'FORBIDDEN');
                    }
                    foreach ($layers as $layer) {
                        $this->authorize($database, $layer);
                    }

                    $host = rtrim(App::$param['mapCache']['host'] ?? '', '/');
                    $url = $host . '/mapcache/' . rawurlencode($database) . ($tail !== '' ? '/' . $tail : '');
                    if (!empty($_SERVER['QUERY_STRING'])) {
                        $url .= '?' . $_SERVER['QUERY_STRING'];
                    }
                    $this->proxy($url);
                } catch (GC2Exception $e) {
                    // Set the status via the status-line header (the dispatcher already queued a 200,
                    // and http_response_code() does not reliably override it after disableOb()).
                    $code = $e->getCode() >= 400 ? $e->getCode() : 403;
                    if (!headers_sent()) {
                        header('HTTP/1.1 ' . $code . ' ' . Util::httpCodeText($code), true, $code);
                        header('Content-Type: text/plain; charset=UTF-8');
                        echo $e->getMessage();
                    }
                } catch (Throwable $e) {
                    error_log((string)$e);
                    if (!headers_sent()) {
                        header('HTTP/1.1 500 ' . Util::httpCodeText(500), true, 500);
                        header('Content-Type: text/plain; charset=UTF-8');
                        echo $e->getMessage();
                    }
                }
            },
        );
    }

    /**
     * The MapCache service path after "/api/v4/mapcache/database/{database}", e.g.
     * "wmts/1.0.0/my_schema.roads/default/g20/8/136/78.png" or "" for a bare KVP request.
     */
    public static function tail(string $path, string $database): string
    {
        $prefix = '/api/v4/mapcache/database/' . $database;
        $tail = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : '';
        return trim($tail, '/');
    }

    /**
     * Resolves the GC2 layer(s) ("schema.table") a request targets, per MapCache service. Returns
     * an empty array for capabilities/metadata requests (which carry no specific tileset).
     *
     * @param array<int,string> $segments the service path segments (segments[0] is the service)
     * @param array<string,mixed> $query the query string with UPPERCASED keys
     * @return array<int,string> layer names, vector suffixes (.mvt/.json) stripped
     */
    public static function extractLayers(string $service, array $segments, array $query): array
    {
        $tilesets = [];
        switch ($service) {
            case 'wms':
                if (!empty($query['LAYERS'])) {
                    $tilesets = explode(',', (string)$query['LAYERS']);
                }
                break;
            case 'wmts':
                if (!empty($query['LAYER'])) {
                    $tilesets = [(string)$query['LAYER']];
                } elseif (($segments[1] ?? null) === '1.0.0'
                    && !empty($segments[2])
                    && !str_contains($segments[2], 'Capabilities')) {
                    // RESTful: wmts/1.0.0/{tileset}/{style}/{grid}/{z}/{y}/{x}.ext
                    $tilesets = [$segments[2]];
                }
                break;
            case 'tms':
                // tms/1.0.0/{tileset}@{grid}/{z}/{x}/{y}.ext ; the root/version are metadata.
                if (!empty($segments[2]) && str_contains($segments[2], '@')) {
                    $tilesets = [explode('@', $segments[2])[0]];
                }
                break;
            case 'gmaps':
                // gmaps/{tileset}@{grid}/{z}/{x}/{y}.ext — the same "@" form as TMS.
                // Taking the segment raw made the layer name "<layer>@<grid>", which
                // matches no layer, and authorize()'s anonymous branch then treated
                // it as readable: a Read/write layer's tiles were served to an
                // unauthenticated caller (200 image/png, measured). The grid must be
                // split off here, exactly as TMS does.
                if (!empty($segments[1])) {
                    $tilesets = [explode('@', $segments[1])[0]];
                }
                break;
        }
        $layers = array_map(
            fn($t) => preg_replace('/\.(mvt|json)$/i', '', trim($t)),
            $tilesets
        );
        // A name with no dot is kept: it is the merged per-schema tileset (<schema>,
        // <schema>.mvt), which MapCache serves and GetCapabilities advertises.
        // Dropping it used to make this return nothing, and the caller's
        // fail-closed branch then answered every tile fetch with "Could not resolve
        // tileset for authorization" — so a schema tileset could be listed and
        // seeded but never fetched through the authorizing proxy.
        return array_values(array_filter($layers, fn($l) => $l !== ''));
    }

    /**
     * The tileset names an operator declared outside GC2's layer model, in
     * app/conf/mapcache/tilesets/. They have no geometry_columns_join row, so they
     * cannot be authorized per layer — declaring one is the operator's decision to
     * serve it on the proxy's own terms, and they are allowed through for exactly
     * that reason. Everything else that resolves to no layer fails closed.
     *
     * @return list<string>
     */
    public static function configuredTilesetNames(): array
    {
        $names = [];
        foreach (MapcacheController::getTileSets() as $xml) {
            if (preg_match('/<tileset\s+name="([^"]+)"/i', $xml, $m)) {
                $names[] = $m[1];
            }
        }
        return $names;
    }

    /**
     * The OWS-enabled layers of a schema, as "schema.table".
     *
     * A merged per-schema tileset is drawn from every layer in the schema, so the
     * caller has to be allowed to read every one of them — the same rule the WMS
     * path already applies when one request names several layers. Without this
     * expansion, authorizing the bare schema name checks nothing: the anonymous
     * branch looks the name up with getGeometryColumns(), gets null because no
     * layer is called that, and falls through to "readable anonymously". Measured:
     * dagi.dagi_politikreds2000 is Read/write and answers 401 anonymously, while
     * the dagi tileset that contains it answered 200.
     *
     * The same filter the config generator applies, so authorization and content
     * cannot disagree about what the merged tileset contains: enableows, and never
     * the sqlapi schema.
     *
     * @return list<string>
     */
    public static function schemaLayers(string $schema, Connection $connection): array
    {
        if ($schema === 'sqlapi' || !preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $schema)) {
            return [];
        }
        // settings.getColumns() and not settings.geometry_columns_join: dropping a
        // table leaves its row behind in the join table (81 of 285 on the install
        // this was written against), and the config generator does not see those
        // because getColumns() joins the real catalog. Reading the join table
        // directly would authorize layers the merged tileset does not contain, and
        // would let a stale name pass the existence check that makes an
        // unresolvable tileset fail closed — measured: a stale name reached the
        // upstream MapCache where a name with no row at all was refused.
        //
        // The schema is checked against a positive class above before it goes into
        // the WHERE fragment, which getColumns() takes as SQL text.
        // getColumns() costs 18-90 ms on a database of this size — far too much for a
        // tile path — so the list is cached for the same window the endpoint already
        // accepts for its authorization decisions, and busted by
        // Table::clearCacheOnSchemaChanges() so a layer added or removed normally
        // takes effect at once rather than after the TTL.
        $cacheKey = $connection->database . '_mapcacheSchemaLayers_' . md5($schema);
        $item = Cache::getItem($cacheKey);
        if ($item !== null && $item->isHit() && is_array($item->get())) {
            return $item->get();
        }

        $model = new Model(connection: $connection);
        $filter = "f_table_schema = '" . $schema . "' AND f_table_name NOTNULL AND f_geometry_column NOTNULL";
        $rasterFilter = "r_table_schema = '" . $schema . "' AND r_table_name NOTNULL AND r_raster_column NOTNULL";
        $res = $model->execQuery("SELECT f_table_schema, f_table_name, enableows FROM settings.getColumns("
            . "'" . str_replace("'", "''", $filter) . "', '" . str_replace("'", "''", $rasterFilter) . "')");
        $layers = [];
        foreach ($model->fetchAll($res, 'assoc') as $row) {
            if (!($row['enableows'] ?? true)) {
                continue;
            }
            $layers[$row['f_table_schema'] . '.' . $row['f_table_name']] = true;
        }
        $names = array_keys($layers);
        sort($names);
        if ($item !== null) {
            $item->set($names)->expiresAfter(self::AUTH_CACHE_TTL);
            Cache::save($item);
        }
        return $names;
    }

    /**
     * Heuristic: does the request fetch an actual tile (vs. capabilities/metadata)? A RESTful tile
     * ends in ".../{y}.{ext}" (a number-dot-extension segment); a KVP tile is GetTile/GetMap.
     *
     * @param array<int,string> $segments
     * @param array<string,mixed> $query
     */
    public static function looksLikeTileFetch(array $segments, array $query): bool
    {
        $request = strtolower((string)($query['REQUEST'] ?? ''));
        if ($request === 'gettile' || $request === 'getmap') {
            return true;
        }
        $last = end($segments);
        return $last !== false && (bool)preg_match('/^\d+\.[a-z0-9]+$/i', (string)$last);
    }

    /**
     * Authorizes read access to a single layer for the current identity (Bearer token, HTTP Basic,
     * or anonymous), reusing the OWS/GC2 authorization model. Throws (or challenges with 401) on
     * denial. A successful decision is cached in Redis for AUTH_CACHE_TTL seconds.
     *
     * @throws GC2Exception|Throwable
     */
    private function authorize(string $database, string $layer): void
    {
        $schema = explode('.', $layer)[0];
        $bearer = Input::getJwtToken();
        $basicUser = Input::getAuthUser();

        $identity = $bearer
            ? 'j:' . hash('sha256', $bearer)
            : ($basicUser ? 'b:' . hash('sha256', $basicUser . ':' . (Input::getAuthPw() ?? '')) : 'a');
        $cacheKey = $database . '_mapcacheauth_' . hash('sha256', $identity . '|' . $layer);
        $item = Cache::getItem($cacheKey);
        if ($item !== null && $item->isHit() && $item->get() === true) {
            return; // allow decision cached
        }

        if ($bearer) {
            $jwt = Jwt::validate($bearer)['data'];
            if (($jwt['database'] ?? null) !== $database) {
                throw new GC2Exception('Token is not valid for this database', 403, null, 'FORBIDDEN');
            }
            $connection = new Connection(user: $jwt['uid'], database: $database, schema: $schema);
            $subUser = !empty($jwt['superUser']) ? null : $jwt['uid'];
            new Authorization(connection: $connection)->check(
                relName: $layer, transaction: false, isAuth: true,
                subUser: $subUser, userGroup: $jwt['userGroup'] ?? null, rels: []
            );
        } else {
            // Anonymous or HTTP Basic — mirror Ows::authorizeLayers.
            $user = $basicUser ?: $database;
            $connection = new Connection(user: $user, database: $database, schema: $schema);
            $auth = new Model(connection: $connection)->getGeometryColumns($layer, 'authentication');
            if ($auth === 'Read/write' || !empty($basicUser)) {
                // Verifies credentials (challenges 401 when missing/wrong) and checks per-layer privilege.
                new BasicAuth(connection: $connection)->authenticate($layer, false);
            }
            // else: None/Read/Write layers are readable anonymously.
        }

        if ($item !== null) {
            $item->set(true)->expiresAfter(self::AUTH_CACHE_TTL);
            Cache::save($item);
        }
    }

    /**
     * Streams the MapCache backend response to php://output, forwarding status and headers.
     */
    private function proxy(string $url): void
    {
        header('X-Powered-By: GC2 MapCache');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $line) {
            $trimmed = trim($line);
            // Forward the upstream status line and the headers that matter for tiles/caching.
            if (preg_match('#^HTTP/#', $trimmed)) {
                $parts = explode(' ', $trimmed);
                if (isset($parts[1]) && is_numeric($parts[1]) && !headers_sent()) {
                    header('HTTP/1.1 ' . (int)$parts[1] . ' ' . Util::httpCodeText((int)$parts[1]), true, (int)$parts[1]);
                }
            } elseif (preg_match('/^(Content-Type|Content-Length|Cache-Control|Expires|ETag|Last-Modified|Content-Encoding):/i', $trimmed)) {
                header($trimmed);
            }
            return strlen($line);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
            echo $data;
            return strlen($data);
        });
        curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('MapCache proxy curl error: ' . curl_error($ch));
        }
        curl_close($ch);
    }
}
