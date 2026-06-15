<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
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
use app\inc\Model;
use app\inc\Route2;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;


#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "ViewRestore",
    description: "Instructions for recreating stored star views into target schema(s).",
    required: ["from"],
    properties: [
        new OA\Property(
            property: "from",
            title: "From",
            description: "Source schema name(s) to read stored view definitions from.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["my_schema"],
        ),
        new OA\Property(
            property: "to",
            title: "To",
            description: "Target schema name(s) the views are (re)created in. Must have the same number of entries as 'from'. Defaults to 'from' when omitted.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["my_other_schema"],
        ),
        new OA\Property(
            property: "include",
            title: "Include",
            description: "Optional allow-list of view names. When supplied, only views with these names are recreated.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["my_view"],
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PATCH', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/views/(action)', scope: Scope::SUPER_USER_ONLY)]
class View extends AbstractApi
{
    private readonly Model $model;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'views';
        $this->model = new Model($connection);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/views/backup', operationId: 'postViewBackup', description: "Store all view definitions from the given schema(s) into the view store.", tags: ['View'])]
    #[OA\RequestBody(description: 'List of schema names to back up.', required: true, content: new OA\JsonContent(type: "array", items: new OA\Items(type: "string"), example: ["my_schema"]))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(properties: [
        new OA\Property(property: "count", description: "Number of views stored.", type: "integer", example: 5)
    ]))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_backup(): Response
    {
        $body = Input::getBody();
        $schemas = json_decode($body, true)['schemas'];
        $count = $this->model->storeViewsFromSchema($schemas);
        return $this->getResponse(['count' => $count]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/views/restore', operationId: 'postViewRestore', description: "Recreate stored star views into the target schema(s).", tags: ['View'])]
    #[OA\RequestBody(description: 'Restore instructions.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/ViewRestore"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(properties: [
        new OA\Property(property: "count", description: "Number of views created.", type: "integer", example: 5)
    ]))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_restore(): Response
    {
        $body = Input::getBody();
        $arr = json_decode($body, true);
        $schemas = $arr['from'];
        $targets = $arr['to'] ?? null;
        $include = $arr['include'] ?? null;
        $count = $this->model->createStarViewsFromStore($schemas, $targets, $include);
        return $this->getResponse(['count' => $count]);
    }

    /**
     * Validates the incoming request before the action is executed.
     *
     * - GET requires a non-empty 'schema' query parameter.
     * - POST /backup expects a non-empty JSON array of schema name strings.
     * - POST /restore expects a ViewRestore object (see {@see self::getAssert()}).
     *
     * @throws GC2Exception If the request body/parameters are missing or invalid.
     */
    public function validate(): void
    {
        $method = Input::getMethod();
        $action = $this->route->action;

        if ($method == 'get') {
            if (empty(Input::get('schema'))) {
                throw new GC2Exception("Missing required 'schema' query parameter.", 400, null, "MISSING_PARAMETER");
            }
            return;
        }

        if ($method == 'post') {
            $body = Input::getBody();
            if (empty($body)) {
                throw new GC2Exception("POST without request body is not allowed.", 400, null, "INVALID_DATA");
            }
            if (!json_validate($body)) {
                throw new GC2Exception("Invalid JSON. Check your request", 400, null, "INVALID_DATA");
            }

            if ($action == 'backup') {
                $this->validateRequest(new Assert\Collection([
                    'schemas' => new Assert\Required([
                        new Assert\Type('array'),
                        new Assert\Count(min: 1),
                        new Assert\All([
                            new Assert\Type('string'),
                            new Assert\NotBlank(),
                        ]),
                    ]),
                ]), $body, Input::getMethod());

            } elseif ($action == 'restore') {
                $this->validateRequest(self::getAssert(), $body, Input::getMethod());
            }
        }
    }

    /**
     * Validation rules for the /restore request body.
     */
    public static function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'from' => new Assert\Required([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
            ]),
            'to' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
            ]),
            'include' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
            ]),
        ]);
    }

    public function get_index(): Response
    {
    }
    public function put_index(): Response
    {
    }

    public function delete_index(): Response
    {
    }

    public function post_index(): Response
    {
    }

    public function patch_index(): Response
    {
    }

    public function options_backup(): void
    {
    }

    public function options_restore(): void
    {
    }

}
