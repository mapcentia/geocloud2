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
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\inc\UserFilter;
use app\inc\Util;
use app\models\Authorization;
use app\models\Geofence;
use app\models\Rule;
use app\ows\Context;
use app\ows\Proxy;
use app\ows\Request as OwsRequest;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * The v4 OWS (WMS/WFS/UTFGRID) endpoint, mirroring the MapCache proxy's auth model: a request
 * carrying a Bearer token is authorized with the token identity (the token must be valid for the
 * database in the path), while a token-less request is served anonymously for anonymously-readable
 * layers and challenged with HTTP Basic auth for 'Read/write' layers. Authorization is enforced
 * per layer against the same GC2 model in both cases.
 */
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ows/schema/{schema}/database/{database}', scope: Scope::PUBLIC)]
final class Ows extends AbstractApi
{
    /** Seconds to cache a per-layer allow decision (OWS/WMS tiles are hit many times per second). */
    private const int AUTH_CACHE_TTL = 60;

    /** Bearer token presented on the request, captured by buildContext(). */
    private ?string $bearer = null;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'ows';
    }

    #[OA\Get(path: '/api/v4/ows/schema/{schema}/database/{database}', operationId: 'getOws', description: "OWS (WMS/WFS/UTFGRID) endpoint. Accepts Bearer token, HTTP Basic and anonymous clients; 'Read/write' layers challenge token-less requests with HTTP Basic auth.", tags: ['Ows'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'OWS response (image, XML, or grid) streamed from the backend')]
    #[OA\Response(response: 401, description: 'HTTP Basic authentication required')]
    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    #[OA\Post(path: '/api/v4/ows/schema/{schema}/database/{database}', operationId: 'postOws', description: "OWS POST (WFS XML) endpoint. Accepts Bearer token, HTTP Basic and anonymous clients.", tags: ['Ows'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'OWS response streamed from the backend')]
    #[OA\Response(response: 401, description: 'HTTP Basic authentication required')]
    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    // OWS uses GET/POST only; the rest are rejected upstream by AcceptableMethods.
    public function put_index(): StreamedResponse    { return $this->stream(); }
    public function patch_index(): StreamedResponse  { return $this->stream(); }
    public function delete_index(): StreamedResponse { return $this->stream(); }

    public function validate(): void
    {
        // Auth and layer checks happen inside stream(); nothing to validate here.
    }

    private function stream(): StreamedResponse
    {
        return new StreamedResponse(
            contentType: 'text/xml; charset=UTF-8', // overridden by upstream headers in Proxy::run
            callback: function () {
                Util::disableOb();
                $tmp = null;
                try {
                    $ctx = $this->buildContext();
                    $req = OwsRequest::fromHttp();
                    $this->authorizeLayers($ctx, $req);
                    $filters = $this->applyRules($ctx, $req);
                    $proxy = new Proxy($ctx);
                    [$url, $tmp] = $proxy->resolve($req, $filters);
                    $proxy->run($url, $req);
                } catch (Throwable $e) {
                    error_log((string) $e);
                    // Pre-stream errors render as an OGC ServiceException report.
                    // Don't leak internals (filesystem paths, TypeErrors, etc.) to clients.
                    $msg = $e->getMessage();
                    if (!headers_sent()) {
                        header('Content-Type: text/xml');
                        echo new ServiceException($msg)->getReport();
                    }
                } finally {
                    if ($tmp) {
                        @unlink($tmp);
                    }
                }
            },
        );
    }

    private function buildContext(): Context
    {
        $database = $this->route->getParam('database');
        $schema = $this->route->getParam('schema');
        // The route is PUBLIC, so the dispatcher has not enforced the token — a presented Bearer
        // token is validated here (and must match the database in the path), like the MapCache proxy.
        $this->bearer = Input::getJwtToken() ?: null;

        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }

        if ($this->bearer) {
            $jwt = Jwt::validate($this->bearer)['data'];
            if (($jwt['database'] ?? null) !== $database) {
                throw new ServiceException('Token is not valid for this database');
            }
            $user = $jwt['uid'];
            $userGroup = $jwt['userGroup'] ?? null;
            $connection = new Connection(user: $user, database: $database, schema: $schema);
        } else {
            // For anonymous-readable layers, use the database name as the implicit user.
            $user = Input::getAuthUser() ?: $database;
            $userGroup = null;
            $connection = new Connection(user: $user, database: $database, schema: $schema);
            // A Basic-auth header sets the request identity ($user, parentUser) above,
            // but per-layer auth only runs for requests that carry a layer. Verify the
            // credentials here so a fabricated header can't be trusted as identity on
            // layer-less requests (GetCapabilities).
            if (!$trusted) {
                new BasicAuth(connection: $connection)->verifyCredentials();
            }
        }

        return new Context(
            connection: $connection,
            database: $database,
            schema: $schema,
            user: $user,
            userGroup: $userGroup,
            parentUser: $user === $database,
            trusted: $trusted,
            host: Util::host(),
        );
    }

    /**
     * Per-layer authorization for the current identity — Bearer token (Authorization::check with
     * the JWT identity), HTTP Basic (BasicAuth::authenticate, which challenges with 401), or
     * anonymous (allowed for layers below 'Read/write'). Skipped when trusted.
     *
     * The allow decision is cached per (identity, layer) for AUTH_CACHE_TTL, mirroring the MapCache
     * proxy: a cache hit skips both the authentication lookup and the per-layer check. The identity
     * is the bearer token or the Basic credentials, so a revoked/rotated token or a changed password
     * stops being trusted within the TTL. A wrong Basic password is never cached as an allow —
     * BasicAuth::authenticate() challenges before we reach the cache. ANONYMOUS requests are
     * deliberately NOT cached: an allow cached for "*" would keep serving a layer that has just been
     * switched to Read/write until the TTL expired, so anonymous access is re-evaluated every time.
     */
    private function authorizeLayers(Context $ctx, OwsRequest $req): void
    {
        if ($ctx->trusted || empty($req->layers)) {
            return;
        }
        $authUser = Input::getAuthUser();
        $identity = $this->bearer
            ? 'j:' . hash('sha256', $this->bearer)
            : ($authUser ? 'b:' . hash('sha256', $authUser . ':' . (Input::getAuthPw() ?? '')) : null);
        $model = $ctx->model();
        foreach ($req->layers as $layer) {
            // The route pins the schema; normalize the (possibly unqualified or
            // differently-qualified) request layer to "schema.table" so the auth
            // check runs against the relation MapServer will actually serve.
            // Passing a raw unqualified name to BasicAuth would silently skip the
            // subuser privilege check (its split on '.' yields an empty table name).
            $rel = "$ctx->schema." . $this->tableOf($layer);
            $item = $identity !== null
                ? Cache::getItem($ctx->database . '_owsauth_' . hash('sha256', $identity . '|' . $rel))
                : null;
            if ($item !== null && $item->isHit() && $item->get() === true) {
                continue; // allow decision cached
            }
            $auth = $model->getGeometryColumns($rel, 'authentication');
            if ($auth === 'Read/write' || !empty($authUser)) {
                if ($this->bearer) {
                    new Authorization(connection: $ctx->connection)->check(
                        relName: $rel, transaction: false, isAuth: true,
                        subUser: $ctx->parentUser ? null : $ctx->user, userGroup: $ctx->userGroup, rels: []
                    );
                } else {
                    // Verifies credentials (challenges 401 when missing/wrong) and checks per-layer privilege.
                    new BasicAuth(connection: $ctx->connection)->authenticate($rel, false);
                }
            }
            if ($item !== null) {
                $item->set(true)->expiresAfter(self::AUTH_CACHE_TTL);
                Cache::save($item);
            }
        }
    }

    /**
     * Geofence rules + versioning filter, merged with client filters. Mirrors
     * Wms::setFilterFromRules but keyed by the request's layer names.
     *
     * @return array<string,array<string>> filters keyed by "schema.table"
     */
    private function applyRules(Context $ctx, OwsRequest $req): array
    {
        $filters = $req->filters;
        $rule = new Rule(connection: $ctx->connection);
        $rules = $rule->get();
        $model = $ctx->model();
        // Geofence identity: a token or Basic-auth user is themselves, an anonymous
        // request is "*". Mirrors legacy Wms::setFilterFromRules. Note $ctx->user
        // falls back to the database name for anonymous requests (used as the
        // connection identity), which must NOT reach the geofence or a parent-user
        // rule would match unauthenticated traffic. This public route never starts
        // a session, so the token or Basic auth are the only signals.
        $geofenceUser = $this->bearer || !empty(Input::getAuthUser()) ? $ctx->user : '*';
        foreach ($req->layers as $layer) {
            $table = $this->tableOf($layer);
            $userFilter = new UserFilter($geofenceUser, 'ows', 'select', '*', $ctx->schema, $table);
            $geofence = new Geofence($userFilter);
            $auth = $geofence->authorize($rules);
            if (isset($auth['access'])) {
                if ($auth['access'] === 'deny') {
                    throw new ServiceException('DENY');
                }
                if ($auth['access'] === 'limit' && !empty($auth['filters']['filter'])) {
                    $filters[$layer][] = "({$auth['filters']['filter']})";
                }
            }
            $versioning = $model->doesColumnExist("$ctx->schema.$table", 'gc2_version_gid');
            if (!empty($versioning['exists'])) {
                $filters[$layer][] = 'gc2_version_end_date IS NULL';
            }
        }
        return $filters;
    }

    private function tableOf(string $layer): string
    {
        $bits = explode('.', $layer);
        return $bits[1] ?? $bits[0];
    }
}
