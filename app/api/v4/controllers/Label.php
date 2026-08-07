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
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
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

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        $collection->fields['name'] = new Assert\Optional([new Assert\Type('string')]);
        $collection->fields['on'] = new Assert\Optional([new Assert\Type('boolean')]);
        foreach (Classification::LABEL_KEYS as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
