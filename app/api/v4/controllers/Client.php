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
    description: "OAuth client definition used to request and manage user access.",
    required: [],
    properties: [
        new OA\Property(
            property: "id",
            title: "Client id",
            description: "Client id.",
            type: "string",
            example: "my_client_id",
        ),
        new OA\Property(
            property: "name",
            title: "Name",
            description: "Client display name.",
            type: "string",
            example: "My Application",
        ),
        new OA\Property(
            property: "homepage",
            title: "Homepage",
            description: "Homepage or web app URL that starts the code flow.",
            type: "string",
            format: "uri",
            example: "https://mapcentia.com"
        ),
        new OA\Property(
            property: "description",
            title: "Description",
            description: "Longer description to identify the client.",
            type: "string",
            example: null,
            nullable: true
        ),
        new OA\Property(
            property: "redirect_uri",
            title: "Redirect URIs.",
            description: "Allowed redirect URIs for this client.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["https://my_site1.com", "https://my_site2.com"]
        ),
        new OA\Property(
            property: "public",
            title: "Public",
            description: "Public clients do not require a secret.",
            type: "boolean",
            default: false,
            example: true
        ),
        new OA\Property(
            property: "confirm",
            title: "Confirm",
            description: "Require user confirmation before granting access.",
            type: "boolean",
            default: true,
            example: true
        ),
        new OA\Property(
            property: "two_factor",
            title: "Two factor authentication",
            description: "Require two-factor authentication for login.",
            type: "boolean",
            default: true,
            example: true
        ),
        new OA\Property(
            property: "allow_signup",
            title: "Allow users to sign up",
            description: "Allow users to sign up in the web dialog.",
            type: "boolean",
            default: false,
            example: true
        ),
        new OA\Property(
            property: "social_signup",
            title: "Enable social signup",
            description: "Allow social login during signup.",
            type: "boolean",
            default: false,
            example: true
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PATCH', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/clients/[id]', scope: Scope::SUPER_USER_ONLY)]
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
    #[OA\Get(path: '/api/v4/clients/{id}', operationId: 'getClient', description: "Get OAuth client(s).", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '66f5005bd44c6')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(
        oneOf: [
            new OA\Schema(ref: "#/components/schemas/Client"),
            new OA\Schema(
                type: "array",
                items: new OA\Items(ref: "#/components/schemas/Client")
            )
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
            return $this->getResponse($r, single: count($r) == 1);
        } else {
            $r = $this->client->get();
        }
        return $this->getResponse($r);
    }

    /**
     * @throws RandomException
     */
    #[OA\Post(path: '/api/v4/clients', operationId: 'postClient', description: 'Create new OAuth client(s).', tags: ['Clients'])]
    #[OA\RequestBody(
        description: 'Client to create.',
        required: true,
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema(ref: "#/components/schemas/Client"),
                new OA\Schema(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Client")
                )
            ]
        )
    )]
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
        if (!array_is_list($data)) {
            $data = [$data];
        }
        foreach ($data as $datum) {
            $arr = [
                'id' => $datum['id'] ?? uniqid(),
                'name' => $datum['name'],
                'redirectUri' => $datum['redirect_uri'] ? json_encode($datum['redirect_uri']) : null,
                'homepage' => $datum['homepage'] ?? null,
                'description' => $datum['description'] ?? null,
                'public' => $datum['public'] ?? false,
                'confirm' => $datum['confirm'] ?? true,
                'twoFactor' => $datum['two_factor'] ?? true,
                'allowSignup' => $datum['allow_signup'] ?? false,
                'socialSignup' => $datum['social_signup'] ?? false,
            ];
            $list[] = $this->client->insert(...$arr);
        }
        $this->client->commit();
        $baseUri = "/api/v4/clients/";
        header("Location: $baseUri" . implode(",", array_map(fn($c) => $c['id'], $list)));
        $res = array_map(fn($l) => ['_links' => ['self' => $baseUri . $l['id']], 'secret' => $l['secret']], $list);
        if (count($res) == 1) {
            $res = $res[0];
        }
        return new PostResponse(data: $res);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/clients/{id}', operationId: 'patchClient', description: "Update existing OAuth client(s).", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\RequestBody(description: 'Fields to update. Partial update is allowed.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Client"))]
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
                'redirectUri' => isset($data['redirect_uri']) ? json_encode($data['redirect_uri']) : null,
                'homepage' => $data['homepage'] ?? null,
                'description' => $data['description'] ?? null,
                'public' => $data['public'] ?? null,
                'confirm' => $data['confirm'] ?? null,
                'twoFactor' => $data['two_factor'] ?? null,
                'allowSignup' => $data['allow_signup'] ?? null,
                'socialSignup' => $data['social_signup'] ?? null,
            ];
            $list[] = $this->client->update(...$arr);
        }
        $this->client->commit();
        return $this->patchResponse('/api/v4/clients/', $list);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/clients/{id}', operationId: 'deleteClient', description: "Delete OAuth client(s).", tags: ['Clients'])]
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
            $collection->fields['id'] = new Assert\Optional(
                new Assert\Length(min: 3)
            );
            $collection->fields['name'] = new Assert\Required(
                new Assert\Length(min: 3)
            );
            $collection->fields['redirect_uri'] = new Assert\Required([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\NotBlank(),
            ]);
        } else {
            $collection->fields['id'] = new Assert\Optional(
                new Assert\Length(min: 3)
            );
            $collection->fields['name'] = new Assert\Optional(
                new Assert\Length(min: 3)
            );
            $collection->fields['redirect_uri'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\NotBlank(),
            ]);
        }
        $collection->fields['homepage'] = new Assert\Optional(
            new Assert\Url(requireTld: true),
        );
        $collection->fields['description'] = new Assert\Optional(
            new Assert\Length(min: 3)
        );

        $collection->fields['public'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        $collection->fields['confirm'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        $collection->fields['two_factor'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        $collection->fields['allow_signup'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        $collection->fields['social_signup'] = new Assert\Optional(
            new Assert\Type('boolean')
        );
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
