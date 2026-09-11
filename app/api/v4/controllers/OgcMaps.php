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
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\PublicIdentity;
use app\inc\Route2;
use app\ogc\Collections;
use app\ogc\Links;
use app\ogc\MapParams;
use app\ogc\MapRenderer;
use OpenApi\Attributes as OA;

/**
 * OGC API Maps Part 1 (Core) for one collection, rendered through the WMS backend (see MapRenderer).
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ogc/database/{database}/collections/{collection}/map', scope: Scope::PUBLIC)]
final class OgcMaps extends AbstractApi
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

    #[OA\Get(path: '/api/v4/ogc/database/{database}/collections/{collectionId}/map', operationId: 'getOgcCollectionMap', description: 'Map of one collection rendered by the WMS backend; geofence rules and versioning (datetime) are applied.', tags: ['Ogc'],
        parameters: [
            new OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database'),
            new OA\Parameter(name: 'collectionId', description: 'Collection id (schema.table)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table'),
            new OA\Parameter(name: 'bbox', description: 'minx,miny,maxx,maxy in bbox-crs (default: the collection extent)', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '9,55,10,56'),
            new OA\Parameter(name: 'bbox-crs', description: 'CRS URI of bbox (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'crs', description: 'Output CRS URI from the collection crs list (default CRS84)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'width', description: 'Image width in pixels (max 16384; default 1024 or from the aspect ratio)', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'height', description: 'Image height in pixels (max 16384; default from the aspect ratio)', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'f', description: 'png (default) or jpeg', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['png', 'jpeg'])),
            new OA\Parameter(name: 'transparent', description: 'Transparent background (default true for png, false for jpeg)', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'bgcolor', description: 'Background colour 0xRRGGBB', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'datetime', description: 'ISO 8601 instant: render the version valid at that time (versioned layers only)', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'image/png or image/jpeg'),
            new OA\Response(response: 400, description: 'Invalid or unknown parameter'),
            new OA\Response(response: 401, description: 'Credentials required for a Read/write layer'),
            new OA\Response(response: 403, description: 'Insufficient privileges, or denied by a geofence rule'),
            new OA\Response(response: 404, description: 'Unknown collection'),
        ],
    )]
    public function get_index(): Response
    {
        $database = (string)$this->route->getParam('database');
        $collectionId = (string)$this->route->getParam('collection');
        $id = PublicIdentity::resolve($database);
        $collections = new Collections($id, Links::base($database));
        $row = $collections->get($collectionId);
        $p = MapParams::fromQuery($_GET, Collections::crsList(isset($row['srid']) ? (int)$row['srid'] : null), false);
        return new MapRenderer($id)->render($row['f_table_schema'], [$row['f_table_name']], $p, $collections->extentBbox($row));
    }

    // Read-only; rejected upstream by AcceptableMethods.
    public function post_index(): Response   { return new NoContentResponse(); }
    public function put_index(): Response    { return new NoContentResponse(); }
    public function patch_index(): Response  { return new NoContentResponse(); }
    public function delete_index(): Response { return new NoContentResponse(); }
}
