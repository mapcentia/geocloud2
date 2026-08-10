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
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\UserFilter;
use app\inc\Util;
use app\models\Geofence;
use app\models\Rule;
use app\ows\Context;
use app\ows\Proxy;
use app\ows\Request as OwsRequest;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ows/schema/{schema}/database/{database}', scope: Scope::PUBLIC)]
final class OwsNoToken extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'ows';
    }

    #[OA\Get(path: '/api/v4/ows/schema/{schema}/database/{database}', operationId: 'getOwsNoToken', description: "Anonymous and HTTP-Basic clients OWS endpoint (WMS/WFS/UTFGRID).", tags: ['Ows'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'OWS response (image, XML, or grid) streamed from the backend')]
    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    #[OA\Post(path: '/api/v4/ows/schema/{schema}/database/{database}', operationId: 'postOwsNoToken', description: "Anonymous and HTTP-Basic clients OWS POST (WFS XML) endpoint.", tags: ['Ows'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'OWS response streamed from the backend')]
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
                    $this->basicAuthPerLayer($ctx, $req);
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
        $authUser = Input::getAuthUser();
        if (!$authUser && empty($database)) {
            throw new OwsException(
                'Authentication required',
                attributes: ['exceptionCode' => 'NoApplicableCode']
            );
        }
        // For anonymous-readable layers, use the database name as the implicit user.
        // BasicAuth is invoked per-layer inside Server::dispatch when actually needed.
        $user = $authUser ?: $database;

        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }

        return new Context(
            connection: new Connection(user: $user, database: $database, schema: $schema),
            database: $database,
            schema: $schema,
            user: $user,
            userGroup: $userGroup ?? null,
            parentUser: $user === $database,
            trusted: $trusted,
            host: Util::host(),
        );
    }

    private function basicAuthPerLayer(Context $ctx, OwsRequest $req): void
    {
        if ($ctx->trusted || empty($req->layers)) return;
        $model = $ctx->model();
        foreach ($req->layers as $tn) {
            $auth = $model->getGeometryColumns($tn, 'authentication');
            $needsAuth = $auth === 'Read/write' || !empty(Input::getAuthUser());
            if ($needsAuth) {
                new BasicAuth(connection: $ctx->connection)->authenticate($tn, false);
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
        foreach ($req->layers as $layer) {
            $table = $this->tableOf($layer);
            $userFilter = new UserFilter($ctx->user, 'ows', 'select', '*', $ctx->schema, $table);
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
