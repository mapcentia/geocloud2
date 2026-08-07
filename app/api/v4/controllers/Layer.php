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
use app\conf\App;
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
        new OA\Property(property: "properties", title: "Properties", description: "Layer properties (the def JSON). All values are stored as strings (empty string when unset), except where noted.", type: "object", properties: [
            new OA\Property(property: "theme_column", title: "Class item", description: "Column (attribute) evaluated against each class's expression. Maps to MapServer LAYER CLASSITEM. Empty means classes are matched without a CLASSITEM (e.g. numeric/logical expressions only).", type: "string", example: "type"),
            new OA\Property(property: "label_column", title: "Label item", description: "Column whose value is exposed to legacy label text/expressions. Maps to MapServer LAYER LABELITEM.", type: "string", example: "name"),
            new OA\Property(property: "opacity", title: "Opacity", description: "Layer opacity percentage (0-100), applied via a MapServer LAYER COMPOSITE OPACITY block. Numeric value stored as a string.", type: "string", example: "80"),
            new OA\Property(property: "label_max_scale", title: "Label max scale denominator", description: "Maximum scale denominator at which labels are drawn; labels stop appearing when zoomed out beyond this scale. Maps to MapServer LAYER LABELMAXSCALEDENOM. Numeric value stored as a string.", type: "string", example: "50000"),
            new OA\Property(property: "label_min_scale", title: "Label min scale denominator", description: "Minimum scale denominator at which labels are drawn; labels stop appearing when zoomed in closer than this scale. Maps to MapServer LAYER LABELMINSCALEDENOM. Numeric value stored as a string.", type: "string", example: "1000"),
            new OA\Property(property: "cluster", title: "Clustering distance", description: "Point clustering MAXDISTANCE (layer SIZEUNITS, usually pixels) for an elliptical MapServer CLUSTER block (REGION \"ellipse\"). Empty disables clustering. Numeric value stored as a string.", type: "string", example: "20"),
            new OA\Property(property: "meta_tiles", title: "Meta tiles", description: "Legacy flag that used to toggle metatiling for the tile cache. Metatiling is now effectively controlled by meta_size (any value greater than 1 enables it); this key is kept for backward compatibility.", type: "string", example: ""),
            new OA\Property(property: "meta_size", title: "Meta tile size", description: "Number of tile columns/rows fetched per WMS request when building the tile cache (mapcache METATILE size x size). Defaults to 3 when empty. Numeric value stored as a string.", type: "string", example: "4"),
            new OA\Property(property: "meta_buffer", title: "Meta buffer size (px)", description: "Buffer, in pixels, drawn around each (meta)tile and cropped off afterwards to prevent edge artifacts (mapcache METABUFFER). Numeric value stored as a string.", type: "string", example: "10"),
            new OA\Property(property: "ttl", title: "Time to live (TTL)", description: "Tile time-to-live in seconds, used as the mapcache EXPIRES value and sent via the HTTP Expires/Cache-Control headers; does not itself purge cached tiles. Values below 30 are floored to 30. Numeric value stored as a string.", type: "string", example: "3600"),
            new OA\Property(property: "auto_expire", title: "Auto expire", description: "Age, in seconds, after which an existing cached tile is re-requested and refreshed the next time it is accessed (mapcache AUTO_EXPIRE); overrides ttl for that purpose when set. Numeric value stored as a string.", type: "string", example: "86400"),
            new OA\Property(property: "maxscaledenom", title: "Max scale denominator", description: "Maximum scale denominator at which this layer is drawn (the more-zoomed-out bound). Maps to MapServer LAYER MAXSCALEDENOM. Numeric value stored as a string.", type: "string", example: "500000"),
            new OA\Property(property: "minscaledenom", title: "Min scale denominator", description: "Minimum scale denominator at which this layer is drawn (the more-zoomed-in bound). Maps to MapServer LAYER MINSCALEDENOM. Numeric value stored as a string.", type: "string", example: "1"),
            new OA\Property(property: "symbolscaledenom", title: "Symbol scale denominator", description: "Scale denominator at which symbols and text are rendered at their true SIZE; MapServer scales them as the map scale diverges from this value. Maps to MapServer LAYER SYMBOLSCALEDENOM. Numeric value stored as a string.", type: "string", example: "50000"),
            new OA\Property(property: "geotype", title: "Geom type", description: "Explicit geometry type override for the layer. 'Default' derives the type from the source geometry column.", type: "string", enum: ["Default", "POINT", "LINE", "POLYGON"], example: "Default"),
            new OA\Property(property: "offsite", title: "Offsite", description: "Pixel value(s) MapServer should treat as background/ignore, given as an RGB triplet obtainable from image-processing software. Maps to MapServer LAYER OFFSITE.", type: "string", example: "255 255 255"),
            new OA\Property(property: "format", title: "Format", description: "Tile image format written to the tile cache. The jpeg_* values select JPEG quality presets (60/75/95 respectively) defined in the mapcache config. Defaults to PNG when empty.", type: "string", enum: ["PNG", "jpeg_low", "jpeg_medium", "jpeg_high"], example: "PNG"),
            new OA\Property(property: "lock", title: "Lock", description: "Locks the tile cache so it cannot be cleared/busted via the cache-clear endpoints.", type: "boolean", example: false),
            new OA\Property(property: "layers", title: "Layers", description: "Comma-separated list of additional schema-qualified layer names merged into the WMS GetMap LAYERS request when building tiles for this layer, so several layers render into one cached tile.", type: "string", example: "public.roads,public.rivers"),
            new OA\Property(property: "bands", title: "Bands", description: "Raster band selection passed as MapServer PROCESSING \"BANDS=...\"; one band is treated as greyscale, three as RGB, four as RGBA. Comma-separated band indices.", type: "string", example: "4,2,1"),
            new OA\Property(property: "cache", title: "Cache", description: "Tile cache backend used by mapcache for this layer's tileset. Defaults to the site-wide mapcache type when empty.", type: "string", enum: ["disk", "sqlite", "s3", "memcache"], example: "disk"),
            new OA\Property(property: "s3_tile_set", title: "S3 tile set name", description: "Overrides the tile-set path segment used when cache is 's3'. Defaults to the layer name when empty.", type: "string", example: "my_tileset"),
            new OA\Property(property: "label_no_clip", title: "No clipping of labels", description: "Skips clipping of shapes when determining label anchor points (MapServer PROCESSING \"LABEL_NO_CLIP=True\"), avoiding label position jitter and duplicate labels across tiled requests.", type: "boolean", example: true),
            new OA\Property(property: "polyline_no_clip", title: "No clipping of polylines", description: "Skips clipping of shapes when rendering styled (dashed/symbolised) lines (MapServer PROCESSING \"POLYLINE_NO_CLIP=True\"), avoiding style discontinuities and edge effects across tiled requests.", type: "boolean", example: false),
        ]),
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
        $publicSchemas = App::$param['publicSchemas'] ?? [];
        $schemas = $superUser ? null : array_values(array_unique(array_merge([$uid, 'public'], $publicSchemas)));
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
        $schemas = [];
        $model = new TableModel(null, connection: $this->connection);
        $model->withTransaction(function () use (&$list, &$schemas, $items) {
            foreach ($items as $item) {
                $this->initiateLayer($item['name']);
                if (isset($item['properties'])) {
                    new TileModel(table: $item['name'], connection: $this->connection)->update((object)$item['properties']);
                }
                if (isset($item['classes'])) {
                    $this->classification->replaceClasses($item['classes']);
                }
                $list[] = $item['name'];
                $schemas[explode('.', $item['name'])[0]] = true;
            }
        });
        foreach (array_keys($schemas) as $schema) {
            $this->writeMapFiles($schema);
        }
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
        $this->rejectEmptyArrayPost($body);
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
                new Assert\All([LayerClass::getAssert()]),
            ]);
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
