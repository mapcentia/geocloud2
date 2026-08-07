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
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "LayerClass",
    description: "Class definition with styles and labels.",
    required: [],
    properties: [
        new OA\Property(property: "id", title: "Id", description: "Fixed server-assigned id.", type: "string", example: "a1b2c3d4"),
        new OA\Property(property: "name", title: "Name", description: "Class name.", type: "string", example: "My class"),
        new OA\Property(property: "sortid", title: "Sort id", description: "Render/display order.", type: "integer", example: 10),
        new OA\Property(property: "expression", title: "Expression", description: "MapServer class expression.", type: "string", example: "[type]='road'"),
        new OA\Property(property: "styles", title: "Styles", description: "Style entries.", type: "array", items: new OA\Items(ref: "#/components/schemas/Style")),
        new OA\Property(property: "labels", title: "Labels", description: "Label entries.", type: "array", items: new OA\Items(ref: "#/components/schemas/Label")),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/{layer}/classes/[class]', scope: Scope::SUB_USER_ALLOWED)]
class LayerClass extends AbstractLayerApi
{
    private ?array $classIds = null;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'classes';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'getLayerClass', description: "Get class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id(s)', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/LayerClass"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass"))]))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $classes = $this->classification->getAllWithIds();
        if ($this->classIds) {
            $r = array_values(array_filter($classes, fn($c) => in_array($c['id'], $this->classIds, true)));
            return $this->getResponse($r, single: count($r) == 1);
        }
        return $this->getResponse($classes);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers/{layer}/classes', operationId: 'postLayerClass', description: "Create class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\RequestBody(description: 'Class to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/LayerClass"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass"))]))]
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
        $ids = $this->classification->insertClasses($items);
        $this->writeMapFiles();
        return $this->postResponse("/api/v4/layers/$this->layerKey/classes/", $ids);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'patchLayerClass', description: "Update a class (key-merge). styles/labels are managed via their own routes.", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\RequestBody(description: 'Class', required: true, content: new OA\JsonContent(ref: "#/components/schemas/LayerClass"))]
    #[OA\Response(response: 303, description: 'Class updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $this->classification->patchClassById($this->classIds[0], json_decode(Input::getBody(), true));
        $this->writeMapFiles();
        return $this->patchResponse("/api/v4/layers/$this->layerKey/classes/", [$this->classIds[0]]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'deleteLayerClass', description: "Delete class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id(s)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Response(response: 204, description: 'Class deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        foreach ($this->classIds as $id) {
            $this->classification->deleteClassById($id);
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
        $body = Input::getBody();

        if (empty($class) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a class collection is not allowed", 400);
        }
        if (!empty($class) && count(explode(',', $class)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH with multiple classes is not allowed", 400);
        }
        if (Input::getMethod() == 'post' && $class) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        $this->initiateLayer($layer);
        $this->classIds = $class ? explode(',', $class) : null;
        foreach ($this->classIds ?? [] as $id) {
            $this->classification->getClassById($id); // throws CLASS_NOT_FOUND
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['name'] = Input::getMethod() == 'post'
            ? new Assert\Required([new Assert\Type('string'), new Assert\NotBlank()])
            : new Assert\Optional([new Assert\Type('string'), new Assert\NotBlank()]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        foreach (['expression', 'class_minscaledenom', 'class_maxscaledenom', 'leader', 'leader_gridstep', 'leader_maxdistance', 'leader_color'] as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        if (Input::getMethod() == 'post') {
            $collection->fields['styles'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([Style::getAssert()]),
            ]);
            $collection->fields['labels'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([Label::getAssert()]),
            ]);
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
