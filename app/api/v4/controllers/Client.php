<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\PostResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Client as ClientModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Client",
    required: ["name", "redirect_uri"],
    properties: [
        new OA\Property(
            property: "id",
            title: "Id of the client.",
            description: "Id of client, which identify the client",
            type: "string",
            example: "my_client_id",
        ),
        new OA\Property(
            property: "name",
            title: "Name of the client.",
            description: "Name of client. Can help identify the client",
            type: "string",
            example: "My Application",
        ),
        new OA\Property(
            property: "homepage",
            title: "Homepage of the client.",
            description: "The homepage (or web-app), which starts the code flow",
            type: "string",
            format: "uri",
            example: "https://mapcentia.com"
        ),
        new OA\Property(
            property: "description",
            title: "Description of the client.",
            description: "A longer description of the client, which can help identify the client",
            type: "string",
            example: null,
            nullable: true
        ),
        new OA\Property(
            property: "redirect_uri",
            title: "The allowed redirect URIs.",
            description: "The URIs the auth server is allowed to redirect back to.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["https://my_site1.com", "https://my_site2.com"]
        ),
        new OA\Property(
            property: "public",
            title: "Public",
            description: "Public clients do not require a secret.",
            type: "boolean",
            example: true
        ),
        new OA\Property(
            property: "confirm",
            title: "Confirm",
            description: "Users must confirm client access.",
            type: "boolean",
            example: true
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PATCH', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/clients/[id]', scope: Scope::SUB_USER_ALLOWED)]
class Client extends AbstractApi
{
    private readonly ClientModel $client;
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->client = new ClientModel($connection);
        $this->resource = 'clients';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/clients/{id}', operationId: 'getClient', description: "Get client", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: false, example: '66f5005bd44c6')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(
        allOf: [
            new OA\Schema(
                properties: [
                    new OA\Property(property: "id", description: "Client ID", type: "string", example: "66f5005bd44c6")
                ]
            ),
            new OA\Schema(ref: "#/components/schemas/Client")
        ]
    ))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $r = [];
        if (!empty($this->route->getParam("id"))) {
            $ids = explode(',', $this->route->getParam("id"));
            foreach ($ids as $id) {
                $r[] = $this->client->get($id)[0];
            }
        } else {
            $r = $this->client->get();
        }
        return $this->getResponse($r);
    }

    /**
     * @throws RandomException
     */
    #[OA\Post(path: '/api/v4/clients', operationId: 'postClient', description: 'Create a new client OAuth client', tags: ['Clients'])]
    #[OA\RequestBody(description: 'New client', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Client"))]
    #[OA\Response(response: 201, description: "Client created",
        content: new OA\JsonContent(
            required: ["id", "secret"],
            properties: [
                new OA\Property(
                    property: "id",
                    type: "string",
                    example: "66fd4ad4aa716"
                ),
                new OA\Property(
                    property: "secret",
                    type: "string",
                    example: "5a55bf524a8c9eed61a896878d1a26d1193b30d05715e9cc29aa4f004c4cd8e6"
                ),
            ],
            type: "object"
        )
    )]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json'])]
    public function post_index(): Response
    {
        $list = [];
        $body = Input::getBody();
        $data = json_decode($body, true);
        $this->client->begin();
        if (!isset($data['clients'])) {
            $data['clients'] = [$data];
        }
        foreach ($data['clients'] as $datum) {
            $arr = [
                'id' => $datum['id'],
                'name' => $datum['name'],
                'redirectUri' => $datum['redirect_uri'] ? json_encode($datum['redirect_uri']) : null,
                'homepage' => $datum['homepage'] ?? null,
                'description' => $datum['description'] ?? null,
                'public' => $datum['public'] ?? false,
                'confirm' => $datum['confirm'] ?? true,
            ];
            $list[] = $this->client->insert(...$arr);
        }
        $this->client->commit();
        $baseUri = "/api/v4/clients/";
        header("Location: $baseUri" . implode(",", array_map(fn($c) => $c['id'], $list)));
        $res[$this->resource] = array_map(fn($l) => ['_links' => ['self' => $baseUri . $l['id']], 'secret' => $l['secret']], $list);
        if (count($res[$this->resource]) == 1) {
            $res = $res[$this->resource][0];
        }
        return new PostResponse(data: $res);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/clients/{id}', operationId: 'patchClient', description: "Update client", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\RequestBody(description: 'Properties to update. Partial update is allowed.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Client"))]
    #[OA\Response(response: 204, description: "Client updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    public function patch_index(): Response
    {
        $list = [];
        $ids = explode(',', $this->route->getParam("id"));
        $body = Input::getBody();
        $data = json_decode($body, true);
        $this->client->connect();
        $this->client->begin();
        foreach ($ids as $id) {
            $arr = [
                'id' => $id,
                'newId' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'redirectUri' => $data['redirect_uri'] ? json_encode($data['redirect_uri']) : null,
                'homepage' => $data['homepage'] ?? null,
                'description' => $data['description'] ?? null,
                'public' => $data['public'] ?? null,
                'confirm' => $data['confirm'] ?? null,
            ];
            $list[] = $this->client->update(...$arr);
        }
        $this->client->commit();
        return $this->patchResponse('/api/v4/clients/', $list);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/clients/{id}', operationId: 'deleteClient', description: "Delete client", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\Response(response: 204, description: "Client deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): Response
    {
        $id = $this->route->getParam("id");
        $ids = explode(',', $id);
        $this->client->connect();
        $this->client->begin();
        foreach ($ids as $id) {
            $this->client->delete($id);
        }
        $this->client->commit();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $id = $this->route->getParam("id");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a client collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST and PATCH without request body is not allowed.", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);

        if (Input::getMethod() == 'post') {
            $collection->fields['id'] = new Assert\Required(
                new Assert\Length(min: 3)
            );
            $collection->fields['name'] = new Assert\Required(
                new Assert\Length(min: 3)
            );
        } else {
            $collection->fields['id'] = new Assert\Optional(
                new Assert\Length(min: 3)
            );
            $collection->fields['name'] = new Assert\Optional(
                new Assert\Length(min: 3)
            );
        }
        $collection->fields['homepage'] = new Assert\Optional(
            new Assert\Url(requireTld: true),
        );
        $collection->fields['description'] = new Assert\Optional(
            new Assert\Length(min: 3)
        );
        $collection->fields['redirect_uri'] = new Assert\Optional([
            new Assert\Type('array'),
            new Assert\Count(min: 1),
            new Assert\NotBlank(),
        ]);
        $collection->fields['public'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        $collection->fields['confirm'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
