<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractLayerApi;
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
use app\models\Classification;
use app\models\Layer as LayerModel;
use app\models\Table as TableModel;
use app\models\Tile as TileModel;
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "Layer",
    description: "Layer definition: properties (the def JSON) and classes with styles and labels.",
    required: ["name"],
    properties: [
        new OA\Property(property: "name", title: "Name", description: "Layer key: schema.table.geometry_column.", type: "string", example: "my_schema.my_table.the_geom"),
        new OA\Property(property: "properties", title: "Properties", description: "Layer properties (the def JSON).", type: "object"),
        new OA\Property(property: "classes", title: "Classes", description: "Classes with styles and labels.", type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass")),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/[layer]', scope: Scope::SUB_USER_ALLOWED)]
class Layer extends AbstractLayerApi
{
    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'layers';
    }

    /**
     * Builds the full layer resource: name, properties (all def keys) and classes.
     */
    private function getLayerResource(string $key): array
    {
        $tile = new TileModel(table: $key, connection: $this->connection);
        $props = $tile->get()['data'][0];
        $properties = [];
        foreach (TileModel::DEF_KEYS as $k) {
            $properties[$k] = $props[$k] ?? "";
        }
        $classification = new Classification(table: $key, connection: $this->connection);
        return [
            'name' => $key,
            'properties' => $properties,
            'classes' => $classification->getAllWithIds(),
        ];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}', operationId: 'getLayer', description: "Get layer(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key: schema.table.geometry_column', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'namesOnly', description: 'Return only layer keys.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Layer"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Layer"))]))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        if ($this->layerKey) {
            return $this->getResponse([$this->getLayerResource($this->layerKey)], single: true);
        }
        $superUser = $this->route->jwt['data']['superUser'];
        $uid = $this->route->jwt['data']['uid'];
        $schemas = $superUser ? null : array_values(array_unique([$uid, 'public']));
        $keys = new LayerModel(connection: $this->connection)->getLayerKeys($schemas);
        if (in_array(Input::get('namesOnly'), ['', 'true', '1', 't'], true)) {
            return $this->getResponse(array_map(fn($k) => ['name' => $k], $keys));
        }
        return $this->getResponse(array_map(fn($k) => $this->getLayerResource($k), $keys));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers', operationId: 'postLayer', description: "Configure existing layer(s): set properties and replace classes.", tags: ['Layer'])]
    #[OA\RequestBody(description: 'Layer configuration.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Layer"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Layer"))]))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        $items = array_is_list($data) ? $data : [$data];
        $list = [];
        $model = new TableModel(null, connection: $this->connection);
        $model->withTransaction(function () use (&$list, $items) {
            foreach ($items as $item) {
                $this->initiateLayer($item['name']);
                if (isset($item['properties'])) {
                    new TileModel(table: $item['name'], connection: $this->connection)->update((object)$item['properties']);
                }
                if (isset($item['classes'])) {
                    $this->classification->replaceClasses($item['classes']);
                }
                $list[] = $item['name'];
            }
        });
        $this->writeMapFiles();
        return $this->postResponse('/api/v4/layers/', $list);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}', operationId: 'patchLayer', description: "Update layer properties (key-merge on the def JSON).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key: schema.table.geometry_column', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\RequestBody(description: 'Layer properties', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Layer"))]
    #[OA\Response(response: 303, description: 'Layer updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        if (isset($data['properties'])) {
            new TileModel(table: $this->layerKey, connection: $this->connection)->update((object)$data['properties']);
        }
        $this->writeMapFiles();
        return $this->patchResponse('/api/v4/layers/', [$this->layerKey]);
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $layer = $this->route->getParam("layer");
        $body = Input::getBody();

        if (empty($layer) && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH on the layer collection is not allowed", 400);
        }
        if (!empty($layer) && count(explode(',', $layer)) > 1) {
            throw new GC2Exception("Only one layer per request is allowed", 400);
        }
        if (Input::getMethod() == 'post' && $layer) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        if (!empty($layer)) {
            $this->initiateLayer($layer);
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        if (Input::getMethod() == 'post') {
            $collection->fields['name'] = new Assert\Required([
                new Assert\Type('string'),
                new Assert\Regex(pattern: '/^[^.,]+\.[^.,]+\.[^.,]+$/', message: 'Layer name must be schema.table.geometry_column'),
            ]);
            $collection->fields['classes'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
            // Tightened to LayerClass::getAssert() in the classes controller task
        }
        $collection->fields['properties'] = new Assert\Optional([
            new Assert\Collection(array_map(fn($k) => new Assert\Optional(), array_flip(TileModel::DEF_KEYS))),
        ]);
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }
}
