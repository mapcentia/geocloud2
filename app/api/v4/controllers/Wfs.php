<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\Controller;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\OwsException;
use app\inc\Connection;
use app\inc\Route2;
use app\inc\Util;
use app\models\Authorization;
use app\wfs\Context;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/wfs/schema/{schema}/srs/[srs]/[timeSlice]', scope: Scope::SUB_USER_ALLOWED)]
final class Wfs extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @throws OwsException
     */
    #[OA\Get(path: '/api/v4/wfs/schema/{schema}/srs/{srs}/{timeSlice}', operationId: 'getWfs', description: "Token-authenticated WFS endpoint (GetCapabilities, DescribeFeatureType, GetFeature). Anonymous and HTTP-Basic clients use /api/v4/wfs/schema/{schema}/database/{database}.", tags: ['Wfs'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'srs', description: 'Output EPSG code (SRID). Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'integer'), example: 25832)]
    #[OA\Parameter(name: 'timeSlice', description: 'Version time slice (ISO date) for versioned layers. Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '2020-01-01')]
    #[OA\Parameter(name: 'SERVICE', description: 'OGC service.', in: 'query', required: true, schema: new OA\Schema(type: 'string', default: 'WFS'), example: 'WFS')]
    #[OA\Parameter(name: 'REQUEST', description: 'WFS operation.', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['GetCapabilities', 'DescribeFeatureType', 'GetFeature']), example: 'GetFeature')]
    #[OA\Parameter(name: 'VERSION', description: 'WFS protocol version.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['1.0.0', '1.1.0'], default: '1.1.0'), example: '1.1.0')]
    #[OA\Parameter(name: 'TYPENAME', description: 'Feature type (table) name(s), comma-separated.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'OUTPUTFORMAT', description: 'Output format.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'gml3')]
    #[OA\Parameter(name: 'SRSNAME', description: 'Requested output CRS.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'urn:ogc:def:crs:EPSG::25832')]
    #[OA\Parameter(name: 'BBOX', description: 'Bounding-box filter (minx,miny,maxx,maxy).', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '724000,6174000,725000,6175000')]
    #[OA\Parameter(name: 'MAXFEATURES', description: 'Maximum number of features to return.', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 100)]
    #[OA\Parameter(name: 'FILTER', description: 'OGC Filter Encoding XML.', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'WFS response (Capabilities, schema, or GML feature collection) streamed as XML', content: new OA\MediaType('text/xml'))]
    #[OA\Response(response: 401, description: 'Unauthorized — missing or invalid token')]
    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    /**
     * @throws OwsException
     */
    #[OA\Post(path: '/api/v4/wfs/schema/{schema}/srs/{srs}/{timeSlice}', operationId: 'postWfs', description: "WFS POST endpoint for XML-encoded GetFeature and Transaction (WFS-T) requests.", tags: ['Wfs'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'srs', description: 'Output EPSG code (SRID). Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'integer'), example: 25832)]
    #[OA\Parameter(name: 'timeSlice', description: 'Version time slice (ISO date) for versioned layers. Optional path segment.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '2020-01-01')]
    #[OA\RequestBody(description: 'WFS XML document (GetFeature query or Transaction).', required: true, content: new OA\MediaType('text/xml', schema: new OA\Schema(type: 'string')))]
    #[OA\Response(response: 200, description: 'WFS response (feature collection or transaction summary) streamed as XML', content: new OA\MediaType('text/xml'))]
    #[OA\Response(response: 401, description: 'Unauthorized — missing or invalid token')]
    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    // WFS uses GET and POST only. PUT/PATCH/DELETE are required by ApiInterface
    // but rejected upstream by AcceptableMethods. Stub them to satisfy the contract.
    /**
     * @throws OwsException
     */
    public function put_index(): StreamedResponse    { return $this->stream(); }

    /**
     * @throws OwsException
     */
    public function patch_index(): StreamedResponse  { return $this->stream(); }

    /**
     * @throws OwsException
     */
    public function delete_index(): StreamedResponse { return $this->stream(); }

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
                Util::disableOb();
                $req = null;
                try {
                    $req = WfsRequest::fromHttp($ctx);
                    $this->authorizeLayers($ctx, $req);
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
        $user = $this->route->jwt['data']['uid'];
        $database = $this->route->jwt['data']['database'];
        $userGroup = $this->route->jwt['data']['userGroup'];

        $schema = $this->route->getParam('schema');
        $parentUser = $user === $database;

        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }

        $srsParam = $this->route->getParam('srs');
        $srs = $srsParam !== null && $srsParam !== '' ? (int) $srsParam : null;

        return new Context(
            connection: new Connection(user: $user, database: $database, schema: $schema),
            database: $database,
            schema: $schema,
            user: $user,
            userGroup: $userGroup ?? null,
            parentUser: $parentUser,
            trusted: $trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
            srs: $srs,
        );
    }

    private function authorizeLayers(\app\wfs\Context $ctx, WfsRequest $req): void
    {
        if ($ctx->trusted) {
            return;
        }
        $model = $ctx->model();
        $isTransaction = $req->operation === 'TRANSACTION';
        foreach ($req->typeNames as $layer) {
            $rel = "$ctx->schema." . $this->tableOf($layer);
            $auth = $model->getGeometryColumns($rel, 'authentication');
            if ($auth === 'Read/write') {
                new Authorization(connection: $ctx->connection)->check(
                    relName: $rel, transaction: $isTransaction, isAuth: true,
                    subUser: $ctx->user, userGroup: $ctx->userGroup, rels: []
                );
            }
        }
    }
    private function tableOf(string $layer): string
    {
        $bits = explode('.', $layer);
        return $bits[1] ?? $bits[0];
    }
}
