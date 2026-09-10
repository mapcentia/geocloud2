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
use app\exceptions\ServiceException;
use app\inc\Connection;
use app\inc\PublicIdentity;
use app\inc\Route2;
use app\inc\Util;
use app\ows\LayerGate;
use app\ows\Proxy;
use app\ows\Request as OwsRequest;
use app\ows\RuleFilters;
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
                    $database = (string)$this->route->getParam('database');
                    $schema = (string)$this->route->getParam('schema');
                    // The route is PUBLIC, so the dispatcher has not enforced the token — a presented
                    // Bearer token is validated here (and must match the database in the path).
                    $id = PublicIdentity::resolve($database, $schema);
                    $ctx = $id->owsContext($schema);
                    $req = OwsRequest::fromHttp();
                    // The route pins the schema; normalize the (possibly unqualified) request layer
                    // to "schema.table" so auth runs against the relation MapServer will serve.
                    $rels = array_map(fn(string $l) => "$schema." . RuleFilters::tableOf($l), $req->layers);
                    new LayerGate($id)->authorizeRead($rels);
                    $filters = new RuleFilters($id)->forLayers($req->layers, $schema, $req->filters);
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
}
