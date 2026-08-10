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
use app\inc\BasicAuth;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Util;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/wfs/schema/{schema}/database/{database}/srs/[srs]/[timeSlice]', scope: Scope::PUBLIC)]
final class WfsNoToken extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @throws OwsException
     */
    #[OA\Get(path: '/api/v4/wfs/schema/{schema}/database/{database}/srs/{srs}/{timeSlice}', operationId: 'getWfsNoToken', description: "Anonymous and HTTP-Basic WFS endpoint (GetCapabilities, DescribeFeatureType, GetFeature) for clients such as QGIS. Read access is anonymous for anonymously-readable layers; protected layers are challenged with HTTP Basic auth.", tags: ['Wfs'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'srs', description: 'Output EPSG code (SRID). Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'integer'), example: 25832)]
    #[OA\Parameter(name: 'timeSlice', description: 'Version time slice (ISO date) for versioned layers. Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '2020-01-01')]
    #[OA\Parameter(name: 'SERVICE', description: 'OGC service.', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'WFS'), example: 'WFS')]
    #[OA\Parameter(name: 'REQUEST', description: 'WFS operation.', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['GetCapabilities', 'DescribeFeatureType', 'GetFeature']), example: 'GetFeature')]
    #[OA\Parameter(name: 'VERSION', description: 'WFS protocol version.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['1.0.0', '1.1.0'], default: '1.1.0'), example: '1.1.0')]
    #[OA\Parameter(name: 'TYPENAME', description: 'Feature type (table) name(s), comma-separated.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'OUTPUTFORMAT', description: 'Output format.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'gml3')]
    #[OA\Parameter(name: 'SRSNAME', description: 'Requested output CRS.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'urn:ogc:def:crs:EPSG::25832')]
    #[OA\Parameter(name: 'BBOX', description: 'Bounding-box filter (minx,miny,maxx,maxy).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '724000,6174000,725000,6175000')]
    #[OA\Parameter(name: 'MAXFEATURES', description: 'Maximum number of features to return.', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 100)]
    #[OA\Parameter(name: 'FILTER', description: 'OGC Filter Encoding XML.', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'WFS response (Capabilities, schema, or GML feature collection) streamed as XML', content: new OA\MediaType('text/xml'))]
    #[OA\Response(response: 401, description: 'Unauthorized — HTTP Basic auth required/failed for a protected layer')]
    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    /**
     * @throws OwsException
     */
    #[OA\Post(path: '/api/v4/wfs/schema/{schema}/database/{database}/srs/{srs}/{timeSlice}', operationId: 'postWfsNoToken', description: "Anonymous and HTTP-Basic WFS POST endpoint for XML-encoded GetFeature and Transaction (WFS-T) requests. Transactions on a protected layer require HTTP Basic auth.", tags: ['Wfs'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'srs', description: 'Output EPSG code (SRID). Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'integer'), example: 25832)]
    #[OA\Parameter(name: 'timeSlice', description: 'Version time slice (ISO date) for versioned layers. Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '2020-01-01')]
    #[OA\RequestBody(description: 'WFS XML document (GetFeature query or Transaction).', required: true, content: new OA\MediaType('text/xml', schema: new OA\Schema(type: 'string')))]
    #[OA\Response(response: 200, description: 'WFS response (feature collection or transaction summary) streamed as XML', content: new OA\MediaType('text/xml'))]
    #[OA\Response(response: 401, description: 'Unauthorized — HTTP Basic auth required/failed for a protected layer')]
    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    // WFS uses GET and POST only. PUT/PATCH/DELETE are required by ApiInterface
    // but rejected upstream by AcceptableMethods. Stub them to satisfy the contract.
    /**
     * @throws OwsException
     */
    public function put_index(): StreamedResponse
    {
        return $this->stream();
    }

    /**
     * @throws OwsException
     */
    public function patch_index(): StreamedResponse
    {
        return $this->stream();
    }

    /**
     * @throws OwsException
     */
    public function delete_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function validate(): void
    {
        // Auth & layer checks happen inside Server::dispatch; nothing to validate here
    }

    /**
     * @throws OwsException
     */
    private function stream(): StreamedResponse
    {
        $ctx = $this->buildContext();
        $writer = new GmlWriter(
            gmlNameSpace: $ctx->schema,
            gmlNameSpaceUri: str_replace('https://', 'http://', "$ctx->host/$ctx->database/$ctx->schema"),
        );

        return new StreamedResponse(
            contentType: 'text/xml; charset=UTF-8',
            callback: function () use ($ctx, $writer) {
                // Lift the global 30s cap (public/index.php) for the stream, as
                // the legacy WFS bootstrap (app/wfs/server.php) does — a large
                // GetFeature can stream past 30s and must not be killed midway.
                // Per-request; the limit is restored at request shutdown (also
                // under a persistent SAPI such as FrankenPHP worker mode).
                set_time_limit(0);
                Util::disableOb();
                $req = null;
                try {
                    $req = WfsRequest::fromHttp($ctx);
                    $this->basicAuthPerLayer($ctx, $req);
                    new Server($ctx)->dispatch($req, $writer);
                } catch (Throwable $e) {
                    // Catch all so DB/protocol errors render as OWS exception
                    // reports instead of bubbling up as 500. Matches legacy
                    // server.php's broader catch.
                    ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
                }
            },
        );
    }

    /**
     * @throws OwsException
     */
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

        $connection = new Connection(user: $user, database: $database, schema: $schema);
        // A Basic-auth header sets the request identity ($user, parentUser) above,
        // but per-layer auth only runs for requests that carry a typeName. Verify
        // the credentials here so a fabricated header can't be trusted as identity
        // on layer-less requests (GetCapabilities/DescribeFeatureType).
        if (!$trusted) {
            new BasicAuth(connection: $connection)->verifyCredentials();
        }

        $srsParam = $this->route->getParam('srs');
        $srs = $srsParam !== null && $srsParam !== '' ? (int)$srsParam : null;

        return new Context(
            connection: $connection,
            database: $database,
            schema: $schema,
            user: $user,
            userGroup: null,
            parentUser:  $user === $database,
            trusted: $trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
            srs: $srs,
        );
    }

    private function basicAuthPerLayer(Context $ctx, Request $req): void
    {
        if ($ctx->trusted || empty($req->typeNames)) return;
        $model = $ctx->model();
        $isTransaction = $req->operation === 'TRANSACTION';
        foreach ($req->typeNames as $tn) {
            $auth = $model->getGeometryColumns("{$ctx->schema}.$tn", 'authentication');
            $needsAuth = $auth === 'Read/write'
                || ($isTransaction && ($auth === 'Write' || $auth === 'Read/write'))
                || !empty(Input::getAuthUser());
            if ($needsAuth) {
                new BasicAuth(connection: $ctx->connection)->authenticate("{$ctx->schema}.$tn", $isTransaction);
            }
        }
    }
}
