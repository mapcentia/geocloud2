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
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\PublicIdentity;
use app\inc\Route2;
use app\inc\Util;
use app\ogc\Collections;
use app\ogc\Conformance;
use app\ogc\Links;
use app\ogc\MapParams;
use app\ogc\MapRenderer;
use app\ogc\Params;
use OpenApi\Attributes as OA;

/**
 * OGC API landing page, conformance and collections for one database, plus the dataset-level
 * multi-collection map. Items live in OgcFeatures, the single-collection map in OgcMaps.
 * PUBLIC route: identity resolved per request by PublicIdentity (Bearer/Basic/anonymous).
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ogc/database/{database}/[p1]/[p2]', scope: Scope::PUBLIC)]
final class Ogc extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'ogc';
    }

    public function validate(): void
    {
        // Identity and parameter checks happen per sub-resource in get_index().
    }

    #[OA\Get(path: '/api/v4/ogc/database/{database}', operationId: 'getOgcLandingPage', description: 'OGC API landing page for a database (Bearer, HTTP Basic or anonymous).', tags: ['Ogc'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'Landing page with links to conformance, collections and the OpenAPI document')]
    #[OA\Get(path: '/api/v4/ogc/database/{database}/conformance', operationId: 'getOgcConformance', description: 'Conformance classes implemented by this OGC API.', tags: ['Ogc'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Response(response: 200, description: 'conformsTo list')]
    #[OA\Get(path: '/api/v4/ogc/database/{database}/collections', operationId: 'getOgcCollections', description: 'OWS-enabled layers as OGC API collections (id = schema.table), filtered by the caller\'s access.', tags: ['Ogc'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'limit', description: 'Page size (default 100, max 1000)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 100))]
    #[OA\Parameter(name: 'offset', description: 'Collections to skip', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(response: 200, description: 'Collections with extent, crs list and links')]
    #[OA\Get(path: '/api/v4/ogc/database/{database}/collections/{collectionId}', operationId: 'getOgcCollection', description: 'One collection.', tags: ['Ogc'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'collectionId', description: 'Collection id (schema.table)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table')]
    #[OA\Response(response: 200, description: 'Collection')]
    #[OA\Response(response: 404, description: 'Unknown or not visible')]
    #[OA\Get(path: '/api/v4/ogc/database/{database}/map', operationId: 'getOgcDatasetMap', description: 'Map of several collections (same schema) rendered through the WMS backend with geofence rules and versioning applied.', tags: ['Ogc'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'collections', description: 'Comma-separated collection ids', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.roads,my_schema.buildings')]
    #[OA\Parameter(name: 'bbox', description: 'minx,miny,maxx,maxy in bbox-crs', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'bbox-crs', description: 'CRS URI of bbox (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'crs', description: 'Output CRS URI (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'width', description: 'Image width in pixels (max 16384)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'height', description: 'Image height in pixels (max 16384)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'f', description: 'png (default) or jpeg', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['png', 'jpeg']))]
    #[OA\Parameter(name: 'transparent', description: 'Transparent background (default true for png)', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'bgcolor', description: 'Background colour 0xRRGGBB', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'datetime', description: 'ISO 8601 instant: render the version valid at that time (versioned layers)', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'image/png or image/jpeg')]
    public function get_index(): Response
    {
        $database = (string)$this->route->getParam('database');
        $p1 = $this->route->getParam('p1');
        $p2 = $this->route->getParam('p2');
        $id = PublicIdentity::resolve($database);
        $base = Links::base($database);
        return match (true) {
            $p1 === null || $p1 === ''            => $this->landing($database, $base),
            $p1 === 'conformance' && $p2 === null => $this->conformance(),
            $p1 === 'collections' && $p2 === null => $this->collections($id, $base),
            $p1 === 'collections'                 => $this->collection($id, $base, $p2),
            $p1 === 'map' && $p2 === null         => $this->map($id, $base),
            default => throw new GC2Exception('Not found', 404, null, 'NOT_FOUND'),
        };
    }

    private function landing(string $database, string $base): Response
    {
        Params::assertKnown($_GET, ['f']);
        Params::assertJson($_GET);
        return new GetResponse(data: [
            'title' => "$database OGC API",
            'description' => "OGC API Features and Maps for the database $database",
            'links' => [
                ['rel' => 'self', 'type' => 'application/json', 'title' => 'This document', 'href' => $base],
                ['rel' => 'service-desc', 'type' => 'application/vnd.oai.openapi+json;version=3.0', 'title' => 'OpenAPI definition', 'href' => Util::host() . '/swagger/api.php?v=4'],
                ['rel' => 'service-doc', 'type' => 'text/html', 'title' => 'API documentation', 'href' => Util::host() . '/swagger-ui/'],
                ['rel' => 'conformance', 'type' => 'application/json', 'title' => 'Conformance classes', 'href' => "$base/conformance"],
                ['rel' => 'data', 'type' => 'application/json', 'title' => 'Collections', 'href' => "$base/collections"],
            ],
        ]);
    }

    private function conformance(): Response
    {
        Params::assertKnown($_GET, ['f']);
        Params::assertJson($_GET);
        return new GetResponse(data: ['conformsTo' => Conformance::CLASSES]);
    }

    private function collections(PublicIdentity $id, string $base): Response
    {
        Params::assertKnown($_GET, ['limit', 'offset', 'f']);
        Params::assertJson($_GET);
        $limit = Params::int($_GET, 'limit', Collections::DEFAULT_LIMIT, 1, Collections::MAX_LIMIT);
        $offset = Params::int($_GET, 'offset', 0, 0, PHP_INT_MAX);
        $page = new Collections($id, $base)->list($limit, $offset);
        $self = "$base/collections" . ($_GET ? '?' . http_build_query($_GET) : '');
        $links = [['rel' => 'self', 'type' => 'application/json', 'title' => 'This document', 'href' => $self]];
        if ($offset + count($page['collections']) < $page['numberMatched']) {
            $links[] = ['rel' => 'next', 'type' => 'application/json', 'title' => 'Next page',
                'href' => "$base/collections?" . http_build_query(['limit' => $limit, 'offset' => $offset + $limit])];
        }
        if ($offset > 0) {
            $links[] = ['rel' => 'prev', 'type' => 'application/json', 'title' => 'Previous page',
                'href' => "$base/collections?" . http_build_query(['limit' => $limit, 'offset' => max(0, $offset - $limit)])];
        }
        return new GetResponse(data: [
            'links' => $links,
            'numberMatched' => $page['numberMatched'],
            'numberReturned' => count($page['collections']),
            'collections' => $page['collections'],
        ]);
    }

    private function collection(PublicIdentity $id, string $base, string $collectionId): Response
    {
        Params::assertKnown($_GET, ['f']);
        Params::assertJson($_GET);
        $collections = new Collections($id, $base);
        $row = $collections->find($collectionId)
            ?? throw new GC2Exception("Collection $collectionId not found", 404, null, 'COLLECTION_NOT_FOUND');
        return new GetResponse(data: $collections->toCollection($row));
    }

    /** Dataset-level map: every collection must be visible and in the same schema (one mapfile per schema). */
    private function map(PublicIdentity $id, string $base): Response
    {
        $collections = new Collections($id, $base);
        $raw = isset($_GET['collections']) ? (string)$_GET['collections'] : '';
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($c) => $c !== ''));
        if ($ids === []) {
            throw new GC2Exception("Parameter 'collections' is required", 400, null, 'INVALID_PARAMETER');
        }
        $rows = [];
        $first = null;
        foreach ($ids as $cid) {
            $row = $collections->find($cid) ?? throw new GC2Exception("Collection $cid not found", 404, null, 'COLLECTION_NOT_FOUND');
            $first ??= $row;
            if ($row['f_table_schema'] !== $first['f_table_schema']) {
                throw new GC2Exception('All collections of one map must belong to the same schema', 400, null, 'INVALID_PARAMETER');
            }
            $rows[] = $row;
        }
        $p = MapParams::fromQuery($_GET, Collections::crsList(isset($first['srid']) ? (int)$first['srid'] : null), true);
        return new MapRenderer($id)->render(
            $first['f_table_schema'],
            array_map(fn(array $r) => $r['f_table_name'], $rows),
            $p,
            $collections->extentBbox($first)
        );
    }

    // OGC API is read-only; the rest is rejected upstream by AcceptableMethods.
    public function post_index(): Response   { return new NoContentResponse(); }
    public function put_index(): Response    { return new NoContentResponse(); }
    public function patch_index(): Response  { return new NoContentResponse(); }
    public function delete_index(): Response { return new NoContentResponse(); }
}
