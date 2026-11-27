<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\PublicIdentity;
use app\inc\Route2;
use app\ows\Legend as LegendService;
use OpenApi\Attributes as OA;
use Override;

/**
 * Legends of layers: title and classes, each class with a PNG icon drawn by MapServer.
 * The v4 successor of /api/v1/legend/json.
 *
 * Same auth model as the v4 OWS endpoint, so the route is PUBLIC and carries the database:
 * a Bearer token (valid for that database) or HTTP Basic identifies the caller, and a request
 * without either is anonymous. Access follows the layer: a layer the caller may not read is
 * left out of the result rather than failing the request, and HTTP Basic is never challenged
 * for it. Geofence (DENY/LIMIT) rules do not affect legends.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "LegendClass",
    description: "One entry of a layer's legend.",
    required: ["name", "expression", "img"],
    properties: [
        new OA\Property(property: "name", description: "Class name. Null for the single picture of a layer whose legend comes from an external source (external WMS, QGIS project or the layer's own legend URL).", type: ["string", "null"], example: "Motorvej"),
        new OA\Property(property: "expression", description: "The class expression (MapServer EXPRESSION), or null when the class has none.", type: ["string", "null"], example: "[type]='motorvej'"),
        new OA\Property(property: "img", description: "The icon as a base64 encoded PNG (no `data:` prefix). Null when it could not be drawn, for example before the layer's mapfile is written.", type: ["string", "null"], example: "iVBORw0KGgoAAAANSUhEUgAAABIAAAAM"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Legend",
    description: "The legend of one layer.",
    required: ["id", "title", "classes"],
    properties: [
        new OA\Property(property: "id", description: "The layer as schema qualified relation name.", type: "string", example: "my_schema.my_table"),
        new OA\Property(property: "title", description: "The layer's title, or its table name when it has none.", type: "string", example: "Roads"),
        new OA\Property(property: "classes", description: "The named classes of the layer in drawing order. Empty when the layer has no named classes.", type: "array", items: new OA\Items(ref: "#/components/schemas/LegendClass")),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'OPTIONS'])]
#[Controller(route: 'api/v4/legends/database/{database}/{layers}', scope: Scope::PUBLIC)]
class Legend extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'legends';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/legends/database/{database}/{layers}', operationId: 'getLegend', description: "Get the legends of one or more layers: for each layer its title and its named classes, each with a small PNG icon. Accepts a Bearer token, HTTP Basic or no credentials. A layer the caller may not read, or that does not exist, is left out of the result; the request itself still succeeds, so the answer is always an array and may be empty. Geofence rules do not affect legends.", summary: 'Get layer legends', security: [['bearerAuth' => []], []], tags: ['Legend'])]
    #[OA\Parameter(name: 'database', description: 'Database name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_database')]
    #[OA\Parameter(name: 'layers', description: 'Comma separated list of layers as `schema.table`. A bare `schema` stands for all layers of that schema.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.roads,my_schema.buildings')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/Legend")))]
    #[OA\Response(response: 400, description: 'Malformed layer list (INVALID_REQUEST)')]
    #[OA\Response(response: 401, description: 'The token is not valid for this database, or the Basic credentials are wrong')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $database = (string)$this->route->getParam('database');
        $names = LegendService::parseNames((string)$this->route->getParam('layers'));
        // The route is PUBLIC, so a presented Bearer token is validated here
        $id = PublicIdentity::resolve($database);
        return $this->getResponse(new LegendService($id)->forLayers($names));
    }

    #[Override]
    public function post_index(): Response
    {
        // Read only
    }

    #[Override]
    public function put_index(): Response
    {
        // Read only
    }

    #[Override]
    public function patch_index(): Response
    {
        // Read only
    }

    #[Override]
    public function delete_index(): Response
    {
        // Read only
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        if (LegendService::parseNames((string)$this->route->getParam('layers')) === null) {
            throw new GC2Exception("Invalid layer list. Use a comma separated list of schema.table or schema", 400, null, 'INVALID_REQUEST');
        }
    }
}
