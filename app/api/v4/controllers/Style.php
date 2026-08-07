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
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "Style",
    description: "Style entry of a class. Property keys follow MapServer STYLE parameters.",
    required: [],
    properties: [
        new OA\Property(property: "id", title: "Id", description: "Fixed server-assigned id.", type: "string", example: "e5f6a7b8"),
        new OA\Property(property: "sortid", title: "Sort id", description: "Render order. Defaults to highest existing + 10.", type: "integer", example: 10),
        new OA\Property(property: "name", title: "Name", description: "Display name (UI only).", type: "string", example: "Fill"),
        new OA\Property(property: "color", title: "Color", description: "Fill color.", type: "string", example: "#008000"),
        new OA\Property(property: "width", title: "Width", description: "Line width.", type: "string", example: "2"),
        new OA\Property(property: "outlinecolor", title: "Outline color", description: "Outline color of the style, e.g. the stroke drawn around a polygon fill or symbol. Maps to MapServer STYLE OUTLINECOLOR.", type: "string", example: "#000000"),
        new OA\Property(property: "symbol", title: "Symbol", description: "Name of the MapServer SYMBOL to draw: a built-in vector symbol (circle, square, triangle, hatch1, dashed1, dot-dot, dashed-line-short, dashed-line-long, dash-dot, dash-dot-dot, arrow, arrow2) or a custom symbol defined in the map's SYMBOLSET. Maps to STYLE SYMBOL. Empty draws a plain fill/line with no symbol.", type: "string", example: "circle"),
        new OA\Property(property: "size", title: "Size", description: "Symbol/marker size in layer SIZEUNITS (usually pixels); may reference a numeric attribute as [column] for data-driven sizing. Maps to STYLE SIZE. Numeric value stored as a string.", type: "string", example: "8"),
        new OA\Property(property: "angle", title: "Angle", description: "Rotation angle of the symbol/pattern in degrees (-360 to 360), the literal 'auto', or a [column] reference for data-driven rotation. Maps to STYLE ANGLE.", type: "string", example: "45"),
        new OA\Property(property: "gap", title: "Gap", description: "Center-to-center distance between symbols for decorated lines and polygon fills, in layer SIZEUNITS. For lines, a negative value aligns the symbol's X axis to the line's tangent instead of the output device's X axis. Maps to STYLE GAP. Numeric value stored as a string.", type: "string", example: "10"),
        new OA\Property(property: "style_opacity", title: "Opacity", description: "Opacity of this style, from 0 (fully transparent) to 100 (fully opaque). Maps to STYLE OPACITY. Numeric value stored as a string.", type: "string", example: "100"),
        new OA\Property(property: "pattern", title: "Pattern", description: "Dash pattern for line work (lines, polygon outlines, hatch lines) as alternating on/off lengths in layer SIZEUNITS, e.g. '10 5' for a 10-unit dash followed by a 5-unit gap. Maps to STYLE PATTERN ... END.", type: "string", example: "10 5"),
        new OA\Property(property: "linecap", title: "Line cap", description: "Line cap style used for line ends. Maps to STYLE LINECAP. Defaults to round.", type: "string", enum: ["round", "butt", "square"], example: "round"),
        new OA\Property(property: "geomtransform", title: "Geomtransform", description: "Geometry transformation applied before rendering this style, e.g. drawing at the feature's centroid or bounding box instead of its native geometry. Maps to STYLE GEOMTRANSFORM.", type: "string", enum: ["bbox", "centroid", "end", "labelpnt", "labelpoly", "start", "vertices"], example: "centroid"),
        new OA\Property(property: "minsize", title: "Min size", description: "Minimum size, in pixels, to draw the symbol regardless of scaling. Maps to STYLE MINSIZE. Defaults to 0. Numeric value stored as a string.", type: "string", example: "1"),
        new OA\Property(property: "maxsize", title: "Max size", description: "Maximum size, in pixels, to draw the symbol regardless of scaling. Maps to STYLE MAXSIZE. Defaults to 500. Numeric value stored as a string.", type: "string", example: "50"),
        new OA\Property(property: "style_offsetx", title: "Offset X", description: "Horizontal geometry offset in layer SIZEUNITS (usually pixels); may reference a [column]. Maps to the X component of STYLE OFFSET. Numeric value stored as a string.", type: "string", example: "0"),
        new OA\Property(property: "style_offsety", title: "Offset Y", description: "Vertical geometry offset in layer SIZEUNITS (usually pixels); may reference a [column]. Maps to the Y component of STYLE OFFSET. Numeric value stored as a string.", type: "string", example: "0"),
        new OA\Property(property: "style_polaroffsetr", title: "Polar offset radius", description: "Radius/distance component of a polar-coordinate offset. Maps to the radius component of STYLE POLAROFFSET. Numeric value stored as a string.", type: "string", example: "0"),
        new OA\Property(property: "style_polaroffsetd", title: "Polar offset angle", description: "Angle (counter-clockwise), in degrees, component of a polar-coordinate offset. Maps to the angle component of STYLE POLAROFFSET. Numeric value stored as a string.", type: "string", example: "0"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/{layer}/classes/{class}/styles/[style]', scope: Scope::SUB_USER_ALLOWED)]
class Style extends AbstractLayerApi
{
    private string $classId;
    private ?array $entryIds = null;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'styles';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'getStyle', description: "Get style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id(s)', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Style"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Style"))]))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $entries = $this->classification->getEntries($this->classId, 'styles');
        if ($this->entryIds) {
            $r = array_values(array_filter($entries, fn($e) => in_array($e['id'], $this->entryIds, true)));
            return $this->getResponse($r, single: count($r) == 1);
        }
        return $this->getResponse($entries);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers/{layer}/classes/{class}/styles', operationId: 'postStyle', description: "Create style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\RequestBody(description: 'Style to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Style"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Style"))]))]
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
        $ids = $this->classification->insertEntries($this->classId, 'styles', $items);
        $this->writeMapFiles();
        return $this->postResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/styles/", $ids);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'patchStyle', description: "Update a style (key-merge).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\RequestBody(description: 'Style', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Style"))]
    #[OA\Response(response: 303, description: 'Style updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $this->classification->patchEntryById($this->classId, 'styles', $this->entryIds[0], json_decode(Input::getBody(), true));
        $this->writeMapFiles();
        return $this->patchResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/styles/", [$this->entryIds[0]]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'deleteStyle', description: "Delete style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id(s)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\Response(response: 204, description: 'Style deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        foreach ($this->entryIds as $id) {
            $this->classification->deleteEntryById($this->classId, 'styles', $id);
        }
        $this->writeMapFiles();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $layer = $this->route->getParam("layer");
        $class = $this->route->getParam("class");
        $entry = $this->route->getParam("style");
        $body = Input::getBody();

        if (empty($entry) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a style collection is not allowed", 400);
        }
        if (!empty($entry) && count(explode(',', $entry)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH with multiple styles is not allowed", 400);
        }
        if (Input::getMethod() == 'post' && $entry) {
            $this->postWithResource();
        }
        $this->rejectEmptyArrayPost($body);
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        $this->initiateLayer($layer);
        $this->classId = $class;
        $existing = array_column($this->classification->getEntries($class, 'styles'), 'id'); // throws CLASS_NOT_FOUND
        $this->entryIds = $entry ? array_values(array_unique(explode(',', $entry))) : null;
        foreach ($this->entryIds ?? [] as $id) {
            if (!in_array($id, $existing, true)) {
                throw new GC2Exception("Style not found", 404, null, "STYLE_NOT_FOUND");
            }
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        $collection->fields['name'] = new Assert\Optional([new Assert\Type('string')]);
        foreach (Classification::STYLE_KEYS as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
