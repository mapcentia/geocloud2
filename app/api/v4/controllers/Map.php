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
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Setting as SettingModel;
use Override;
use Psr\Cache\InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Schema(
    schema: "Map",
    description: "Per-schema map view configuration (initial center, zoom and extent).",
    properties: [
        new OA\Property(
            property: "center",
            title: "Center",
            description: "Map center as [x, y] in EPSG:3857.",
            type: "array",
            items: new OA\Items(type: "number"),
            example: [1386651.0, 7503372.0],
            nullable: true,
        ),
        new OA\Property(
            property: "zoom",
            title: "Zoom",
            description: "Initial zoom level.",
            type: "number",
            example: 12,
            nullable: true,
        ),
        new OA\Property(
            property: "extent",
            title: "Extent",
            description: "Map extent as [minx, miny, maxx, maxy] in EPSG:3857.",
            type: "array",
            items: new OA\Items(type: "number"),
            example: [1354000.0, 7478000.0, 1419000.0, 7528000.0],
            nullable: true,
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/map/schema/{schema}', scope: Scope::SUB_USER_ALLOWED)]
class Map extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'map';
    }

    /**
     * @return Response
     * @throws InvalidArgumentException
     */
    #[OA\Get(path: '/api/v4/map/schema/{schema}', operationId: 'getMap', description: "Get the map view configuration (center, zoom, extent) for a schema.", tags: ['Map'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Map"))]
    #[OA\Response(response: 403, description: 'Not authorized')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $setting = new SettingModel(connection: $this->connection);
        $config = $setting->getMapConfig($this->schema[0]);
        $config['_links'] = ['self' => '/api/v4/map/schema/' . $this->schema[0]];
        return $this->getResponse([$config], single: true);
    }

    /**
     * @return Response
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/map/schema/{schema}', operationId: 'patchMap', description: "Set the map view configuration (center, zoom, extent) for a schema. Only the provided properties are updated.", tags: ['Map'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\RequestBody(description: 'Map view configuration. Any subset of center, zoom and extent.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Map"))]
    #[OA\Response(response: 303, description: 'Updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 403, description: 'Not authorized')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $data = json_decode(Input::getBody(), true) ?? [];
        $setting = new SettingModel(connection: $this->connection);
        $setting->updateMapConfig($this->schema[0], $data);
        return $this->patchResponse('/api/v4/map/schema/', [$this->schema[0]]);
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $schema = $this->route->getParam("schema");
        if (empty($schema)) {
            throw new GC2Exception("Schema is required", 400, null, "SCHEMA_REQUIRED");
        }
        if (Input::getMethod() == 'patch') {
            $this->validateRequest(self::getAssert(), Input::getBody(), Input::getMethod());
        }
        $this->initiate(schema: $schema);
    }

    static public function getAssert(): Assert\Collection
    {
        $nullableNumberArray = fn(int $count) => new Assert\AtLeastOneOf([
            new Assert\IsNull(),
            new Assert\Sequentially([
                new Assert\Type('array'),
                new Assert\Count(exactly: $count),
                new Assert\All([new Assert\Type('numeric')]),
            ]),
        ]);
        return new Assert\Collection([
            'center' => new Assert\Optional($nullableNumberArray(2)),
            'extent' => new Assert\Optional($nullableNumberArray(4)),
            'zoom' => new Assert\Optional([
                new Assert\AtLeastOneOf([
                    new Assert\IsNull(),
                    new Assert\Type('numeric'),
                ]),
            ]),
        ]);
    }

    public function post_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function delete_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
