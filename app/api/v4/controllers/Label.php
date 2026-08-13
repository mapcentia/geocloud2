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
    schema: "Label",
    description: "Label entry of a class. Property keys follow MapServer LABEL parameters.",
    required: [],
    properties: [
        new OA\Property(property: "id", title: "Id", description: "Fixed server-assigned id.", type: "string", example: "c9d0e1f2"),
        new OA\Property(property: "sortid", title: "Sort id", description: "Render order. Defaults to highest existing + 10.", type: "integer", example: 10),
        new OA\Property(property: "name", title: "Name", description: "Display name (UI only).", type: "string", example: "Name label"),
        new OA\Property(property: "on", title: "On", description: "Whether the label is enabled.", type: "boolean", example: true),
        new OA\Property(property: "text", title: "Text", description: "Label text expression.", type: "string", example: "[name]"),
        new OA\Property(property: "force", title: "Force", description: "Forces the label to always be drawn even if it would overlap other labels/features. Maps to MapServer LABEL FORCE.", type: "boolean", example: false),
        new OA\Property(property: "minscaledenom", title: "Min scale denominator", description: "Minimum scale denominator at which this label is drawn. Maps to MapServer LABEL MINSCALEDENOM. Numeric value stored as a string.", type: "string", example: "1"),
        new OA\Property(property: "maxscaledenom", title: "Max scale denominator", description: "Maximum scale denominator at which this label is drawn. Maps to MapServer LABEL MAXSCALEDENOM. Numeric value stored as a string.", type: "string", example: "500000"),
        new OA\Property(property: "position", title: "Position", description: "Label placement relative to its anchor point: auto, or a combination of upper/center/lower and left/center/right (e.g. 'ul' = upper-left, 'cc' = centered on the point). Maps to MapServer LABEL POSITION.", type: "string", enum: ["auto", "ul", "uc", "ur", "cl", "cc", "cr", "ll", "lc", "lr"], example: "auto"),
        new OA\Property(property: "size", title: "Size", description: "Label font size in pixels; may reference a numeric attribute as [column]. Maps to MapServer LABEL SIZE. Defaults to 11 when empty. Numeric value stored as a string.", type: "string", example: "11"),
        new OA\Property(property: "color", title: "Color", description: "Label text color. Maps to MapServer LABEL COLOR. Defaults to black when empty.", type: "string", example: "#000000"),
        new OA\Property(property: "outlinecolor", title: "Outline color", description: "Halo/outline color drawn around the label text to improve legibility. Maps to MapServer LABEL OUTLINECOLOR. Defaults to white when empty.", type: "string", example: "#ffffff"),
        new OA\Property(property: "buffer", title: "Buffer", description: "Minimum distance, in pixels, kept clear around the label so it does not crowd other labels. Maps to MapServer LABEL BUFFER. Numeric value stored as a string.", type: "string", example: "2"),
        new OA\Property(property: "repeatdistance", title: "Repeat distance", description: "Distance, in pixels, at which the label is repeated along a line feature. Maps to MapServer LABEL REPEATDISTANCE. Numeric value stored as a string.", type: "string", example: "150"),
        new OA\Property(property: "angle", title: "Angle", description: "Rotation angle of the label text in degrees, or one of the keywords 'auto', 'auto2' or 'follow' for automatic orientation along the feature. May also reference a [column]. Maps to MapServer LABEL ANGLE.", type: "string", example: "0"),
        new OA\Property(property: "backgroundcolor", title: "Background", description: "Fill color of a rectangle drawn behind the label text, rendered as a MapServer STYLE with GEOMTRANSFORM 'labelpoly' nested inside the LABEL. Empty disables the background.", type: "string", example: "#ffffff"),
        new OA\Property(property: "backgroundpadding", title: "Padding", description: "Padding, in pixels, around the label text; used as the WIDTH of the background rectangle style when backgroundcolor is set. Defaults to 1. Numeric value stored as a string.", type: "string", example: "2"),
        new OA\Property(property: "offsetx", title: "Offset X", description: "Horizontal pixel offset applied to the label position. Maps to the X component of MapServer LABEL OFFSET. Numeric value stored as a string.", type: "string", example: "0"),
        new OA\Property(property: "offsety", title: "Offset Y", description: "Vertical pixel offset applied to the label position. Maps to the Y component of MapServer LABEL OFFSET. Numeric value stored as a string.", type: "string", example: "0"),
        new OA\Property(property: "font", title: "Font", description: "Base font family name, combined with fontweight to resolve a FONTSET alias defined in the server's fonts.txt (e.g. 'arial' + 'bold' -> 'arialbold'). Available families depend on server configuration; 'arial' and 'courier' are provided out of the box. Contributes to MapServer LABEL FONT.", type: "string", example: "arial"),
        new OA\Property(property: "fontweight", title: "Font weight", description: "Font weight/style, appended to font to select the concrete FONTSET alias. Contributes to MapServer LABEL FONT.", type: "string", enum: ["normal", "bold", "italic", "bolditalic"], example: "normal"),
        new OA\Property(property: "expression", title: "Expression", description: "MapServer LABEL-level EXPRESSION; when set, this label entry is only rendered for features matching the expression.", type: "string", example: "[type]='primary'"),
        new OA\Property(property: "maxsize", title: "Max size", description: "Maximum font size, in pixels, used when scaling text. Maps to MapServer LABEL MAXSIZE. Defaults to 256. Numeric value stored as a string.", type: "string", example: "256"),
        new OA\Property(property: "minfeaturesize", title: "Min feature size", description: "Minimum size, in pixels, a feature must have to be labeled (line length for lines, smallest bounding-box dimension for polygons); the keyword 'auto' labels only features larger than their own rendered label. Maps to MapServer LABEL MINFEATURESIZE.", type: "string", example: "auto"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/{layer}/classes/{class}/labels/[label]', scope: Scope::SUB_USER_ALLOWED)]
class Label extends AbstractLayerApi
{
    private string $classId;
    private ?array $entryIds = null;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'labels';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}/classes/{class}/labels/{label}', operationId: 'getLabel', description: "Get label(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'label', description: 'Label id(s)', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'c9d0e1f2')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Label"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Label"))]))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $entries = $this->classification->getEntries($this->classId, 'labels');
        if ($this->entryIds) {
            $r = array_values(array_filter($entries, fn($e) => in_array($e['id'], $this->entryIds, true)));
            return $this->getResponse($r, single: count($r) == 1);
        }
        return $this->getResponse($entries);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers/{layer}/classes/{class}/labels', operationId: 'postLabel', description: "Create label(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\RequestBody(description: 'Label to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Label"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Label"))]))]
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
        $ids = $this->classification->insertEntries($this->classId, 'labels', $items);
        $this->writeMapFiles();
        return $this->postResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/labels/", $ids);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}/classes/{class}/labels/{label}', operationId: 'patchLabel', description: "Update a label (key-merge).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'label', description: 'Label id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'c9d0e1f2')]
    #[OA\RequestBody(description: 'Label', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Label"))]
    #[OA\Response(response: 303, description: 'Label updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $this->classification->patchEntryById($this->classId, 'labels', $this->entryIds[0], json_decode(Input::getBody(), true));
        $this->writeMapFiles();
        return $this->patchResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/labels/", [$this->entryIds[0]]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/layers/{layer}/classes/{class}/labels/{label}', operationId: 'deleteLabel', description: "Delete label(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'label', description: 'Label id(s)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'c9d0e1f2')]
    #[OA\Response(response: 204, description: 'Label deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        foreach ($this->entryIds as $id) {
            $this->classification->deleteEntryById($this->classId, 'labels', $id);
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
        $entry = $this->route->getParam("label");
        $body = Input::getBody();

        if (empty($entry) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a label collection is not allowed", 400);
        }
        if (!empty($entry) && count(explode(',', $entry)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH with multiple labels is not allowed", 400);
        }
        if (Input::getMethod() == 'post' && $entry) {
            $this->postWithResource();
        }
        $this->rejectEmptyArrayPost($body);
        $this->validateRequest(self::getAssert(allowId: false), $body, Input::getMethod());
        $this->initiateLayer($layer);
        $this->classId = $class;
        $existing = array_column($this->classification->getEntries($class, 'labels'), 'id'); // throws CLASS_NOT_FOUND
        $this->entryIds = $entry ? array_values(array_unique(explode(',', $entry))) : null;
        foreach ($this->entryIds ?? [] as $id) {
            if (!in_array($id, $existing, true)) {
                throw new GC2Exception("Label not found", 404, null, "LABEL_NOT_FOUND");
            }
        }
    }

    /**
     * @param bool $allowId Whether a client-supplied `id` is accepted. True when nested under a
     *   full-layer replace (ids round-trip); false on the sub-resource create path, where a
     *   client-supplied id is rejected because ids are server-assigned.
     */
    static public function getAssert(bool $allowId = true): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        $collection->fields['name'] = new Assert\Optional([new Assert\Type('string')]);
        $collection->fields['on'] = new Assert\Optional([new Assert\Type('boolean')]);
        foreach (Classification::LABEL_KEYS as $key) {
            if ($key === 'id' && !$allowId) {
                continue;
            }
            $collection->fields[$key] = new Assert\Optional();
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
