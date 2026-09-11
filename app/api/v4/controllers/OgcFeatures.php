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
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\inc\Connection;
use app\inc\Model;
use app\inc\PublicIdentity;
use app\inc\Route2;
use app\inc\Util;
use app\ogc\Collections;
use app\ogc\Crs;
use app\ogc\ItemsParams;
use app\ogc\Links;
use app\ogc\Problem;
use app\ows\LayerGate;
use app\wfs\handlers\GetFeature;
use app\wfs\output\GeoJsonWriter;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * OGC API Features Part 1 (Core) + Part 2 (CRS), read-only. Items are produced by the in-process
 * WFS GetFeature handler with a GeoJSON writer, so geofence rules (service "wfst"), versioning
 * (current version or the `datetime` time slice) and workflow filtering apply exactly as for WFS.
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ogc/database/{database}/collections/{collection}/items/[feature]', scope: Scope::PUBLIC)]
final class OgcFeatures extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'ogc';
    }

    public function validate(): void
    {
        // Identity, collection lookup and parameter checks happen in get_index().
    }

    #[OA\Get(path: '/api/v4/ogc/database/{database}/collections/{collectionId}/items', operationId: 'getOgcItems', description: 'Features of a collection as a GeoJSON FeatureCollection. Geofence rules, versioning and workflow are applied by the WFS engine.', tags: ['Ogc'],
        parameters: [
            new OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database'),
            new OA\Parameter(name: 'collectionId', description: 'Collection id (schema.table)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table'),
            new OA\Parameter(name: 'limit', description: 'Page size (default 10, max 10000)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'offset', description: 'Features to skip', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'bbox', description: 'minx,miny,maxx,maxy in bbox-crs', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '9,55,10,56'),
            new OA\Parameter(name: 'bbox-crs', description: 'CRS URI of bbox (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'crs', description: 'Output CRS URI from the collection crs list (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'datetime', description: 'ISO 8601 instant: the version valid at that time (versioned layers only)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'GeoJSON FeatureCollection (application/geo+json) with Content-Crs header'),
            new OA\Response(response: 400, description: 'Invalid or unknown parameter'),
            new OA\Response(response: 401, description: 'Credentials required for a Read/write layer'),
            new OA\Response(response: 403, description: 'Insufficient privileges, or denied by a geofence rule'),
            new OA\Response(response: 404, description: 'Unknown collection'),
        ],
    )]
    #[OA\Get(path: '/api/v4/ogc/database/{database}/collections/{collectionId}/items/{featureId}', operationId: 'getOgcItem', description: 'One feature by primary key as a GeoJSON Feature.', tags: ['Ogc'],
        parameters: [
            new OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database'),
            new OA\Parameter(name: 'collectionId', description: 'Collection id (schema.table)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table'),
            new OA\Parameter(name: 'featureId', description: 'Primary key value', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1'),
            new OA\Parameter(name: 'crs', description: 'Output CRS URI (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'datetime', description: 'ISO 8601 instant (versioned layers only)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'GeoJSON Feature (application/geo+json)'),
            new OA\Response(response: 404, description: 'Unknown feature or collection'),
        ],
    )]
    public function get_index(): Response
    {
        $database = (string)$this->route->getParam('database');
        $collectionId = (string)$this->route->getParam('collection');
        $fid = $this->route->getParam('feature');
        $id = PublicIdentity::resolve($database);
        $base = Links::base($database);
        $collections = new Collections($id, $base);
        $row = $collections->get($collectionId);
        if (($row['type'] ?? null) === 'RASTER') {
            throw new GC2Exception("Collection $collectionId has no items", 400, null, 'NO_ITEMS');
        }
        $schema = $row['f_table_schema'];
        $table = $row['f_table_name'];
        $allowedCrs = Collections::crsList((int)$row['srid']);
        new LayerGate($id)->authorizeRead(["$schema.$table"]);
        $versioned = !empty(new Model(connection: $id->connection)->doesColumnExist("$schema.$table", 'gc2_version_gid')['exists']);
        $collHref = Links::collection($base, $collectionId);
        return $fid === null || $fid === ''
            ? $this->items($id, $collHref, $schema, $table, $allowedCrs, $versioned)
            : $this->item($id, $collHref, $schema, $table, $allowedCrs, $versioned, $fid);
    }

    private function items(PublicIdentity $id, string $collHref, string $schema, string $table, array $allowedCrs, bool $versioned): StreamedResponse
    {
        $p = ItemsParams::fromQuery($_GET, $allowedCrs);
        $ctx = $id->wfsContext($schema, Crs::epsg($p->crs));
        $req = self::wfsRequest($table, $p, $versioned, null);
        try {
            Server::assertLayersEnabled($ctx, $req);   // 404 through Problem::toGc2 if not enabled
        } catch (OwsException $e) {
            throw Problem::toGc2($e);
        }

        $query = $_GET;
        unset($query['offset']);
        $pageHref = "$collHref/items" . ($query ? '?' . http_build_query($query) : '');
        $self = "$collHref/items" . ($_GET ? '?' . http_build_query($_GET) : '');
        $writer = new GeoJsonWriter(
            crsUri: $p->crs,
            links: [
                ['rel' => 'self', 'type' => 'application/geo+json', 'title' => 'This document', 'href' => $self],
                ['rel' => 'collection', 'type' => 'application/json', 'title' => 'The collection', 'href' => $collHref],
            ],
            pageHref: $pageHref,
            offset: $p->offset,
            limit: $p->limit,
        );
        $crs = $p->crs;
        return new StreamedResponse(
            contentType: 'application/geo+json',
            callback: function () use ($ctx, $req, $writer, $crs) {
                header('Content-Crs: <' . $crs . '>');
                // A large collection can stream past the global 30s cap (public/index.php).
                set_time_limit(0);
                Util::disableOb();
                try {
                    new GetFeature($ctx)->handle($req, $writer);
                } catch (Throwable $e) {
                    Problem::render($e);
                }
            },
        );
    }

    /**
     * The single item is captured (not streamed) so a miss can still answer 404: the writer
     * counts the features it wrote, and the engine's output is buffered like Feature::captureDispatch.
     */
    private function item(PublicIdentity $id, string $collHref, string $schema, string $table, array $allowedCrs, bool $versioned, string $fid): StreamedResponse
    {
        if (str_contains($fid, "'")) {
            throw new GC2Exception('Invalid feature id', 400, null, 'INVALID_FEATURE_ID');
        }
        $p = ItemsParams::fromQuery($_GET, $allowedCrs, single: true);
        $ctx = $id->wfsContext($schema, Crs::epsg($p->crs));
        $req = self::wfsRequest($table, $p, $versioned, $fid);
        try {
            Server::assertLayersEnabled($ctx, $req);
        } catch (OwsException $e) {
            throw Problem::toGc2($e);
        }
        $self = "$collHref/items/" . rawurlencode($fid) . ($_GET ? '?' . http_build_query($_GET) : '');
        $writer = new GeoJsonWriter(
            crsUri: $p->crs,
            links: [
                ['rel' => 'self', 'type' => 'application/geo+json', 'title' => 'This document', 'href' => $self],
                ['rel' => 'collection', 'type' => 'application/json', 'title' => 'The collection', 'href' => $collHref],
            ],
            single: true,
            suppressFlush: true,
        );
        $captured = '';
        ob_start(function (string $chunk) use (&$captured): string {
            $captured .= $chunk;
            return '';
        }, 0);
        try {
            new GetFeature($ctx)->handle($req, $writer);
        } catch (Throwable $e) {
            throw Problem::toGc2($e);
        } finally {
            ob_end_flush();
        }
        if ($writer->numberReturned() === 0) {
            throw new GC2Exception("Feature $fid not found", 404, null, 'FEATURE_NOT_FOUND');
        }
        $crs = $p->crs;
        return new StreamedResponse(
            contentType: 'application/geo+json',
            callback: function () use ($captured, $crs) {
                header('Content-Crs: <' . $crs . '>');
                echo $captured;
            },
        );
    }

    private static function wfsRequest(string $table, ItemsParams $p, bool $versioned, ?string $fid): WfsRequest
    {
        $bbox = null;
        if ($p->bbox !== null) {
            // Values stay in the axis order of bbox-crs; the 5th element tells WfsFilter which order that is.
            $bbox = array_map(fn(float $v) => Crs::num($v), $p->bbox);
            $bbox[] = Crs::wfsBboxCrs($p->bboxCrs);
        }
        return new WfsRequest(
            operation: 'GETFEATURE',
            version: '1.1.0',
            service: 'WFS',
            outputFormat: 'GEOJSON',
            typeNames: [$table],
            properties: null,
            featureIds: $fid !== null ? ["$table.$fid"] : null,
            bbox: $bbox,
            resultType: null,
            srsName: $p->crs,
            srs: Crs::epsg($p->crs),
            maxFeatures: $p->limit,
            timeSlice: $versioned ? $p->datetime : null,
            filter: null,
            transactionBody: null,
            rawPostBody: null,
            startIndex: $fid !== null ? null : $p->offset,
        );
    }

    // Read-only; rejected upstream by AcceptableMethods.
    public function post_index(): Response   { return new NoContentResponse(); }
    public function put_index(): Response    { return new NoContentResponse(); }
    public function patch_index(): Response  { return new NoContentResponse(); }
    public function delete_index(): Response { return new NoContentResponse(); }
}
