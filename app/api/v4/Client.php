<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Route2;
use app\models\Client as ClientModel;
use OpenApi\Annotations\OpenApi;
use Random\RandomException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Client",
    required: ["name", "redirect_uri"],
    properties: [
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
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PUT', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
class Client extends AbstractApi
{
    public function __construct()
    {
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
    public function get_index(): array
    {
        $r = [];
        $client = new ClientModel();
        if (!empty(Route2::getParam("id"))) {
            $ids = explode(',', Route2::getParam("id"));
            foreach ($ids as $id) {
                $r[] = $client->get($id)[0];
            }
        } else {
            $r = $client->get();
        }
        if (count($r) > 1) {
            return ["clients" => $r];
        } else {
            return $r[0];
        }
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
        ),
        links: [
            new OA\Link(
                link: "getClient",
                operationId: "getClient",
                parameters: [
                    "id" => '$response.body#/id'
                ],
                description: "Link to the new client"
            )
        ]
    )]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json'])]
    public function post_index(): array
    {
        $list = [];
        $model = new ClientModel();
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model->connect();
        $model->begin();
        if (!isset($data['clients'])) {
            $data['clients'] = [$data];
        }
        foreach ($data['clients'] as $datum) {
            $arr = [
                'name' => $datum['name'],
                'redirectUri' => $datum['redirect_uri'] ? json_encode($datum['redirect_uri']) : null,
                'homepage' => $datum['homepage'] ?? null,
                'description' => $datum['description'] ?? null,
            ];
            $list[] = $model->insert(...$arr);
        }
        $model->commit();
        if (count($list) > 1) {
            return ["clients" => $list];
        } else {
            return $list[0];
        }
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Put(path: '/api/v4/clients/{id}', operationId: 'putClient', description: "Update client", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\RequestBody(description: 'Properties to update. Partial update is allowed.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Client"))]
    #[OA\Response(response: 204, description: "Client updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    public function put_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        $ids = explode(',', Route2::getParam("id"));
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model = new ClientModel();
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $arr = [
                'id' => $id,
                'name' => $data['name'] ?? null,
                'redirectUri' => json_encode($data['redirect_uri']) ?? null,
                'homepage' => $data['homepage'] ?? null,
                'description' => $data['description'] ?? null,
            ];
            $model->update(...$arr);
        }
        $model->commit();
        header("Location: /api/v4/clients/" . implode(",", $ids));
        return ["code" => "303"];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/clients/{id}', operationId: 'deleteClient', description: "Delete client", tags: ['Clients'])]
    #[OA\Parameter(name: 'id', description: 'Id of client', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\Response(response: 204, description: "Client deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        $ids = explode(',', $id);
        $model = new ClientModel();
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $model->delete($id);
        }
        $model->commit();
        return ["code" => "204"];
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $id = Route2::getParam("id");
        $body = Input::getBody();

        // Put and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("PUT and DELETE on a client collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'put'])) {
            throw new GC2Exception("POST and PUT without request body is not allowed.", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }

        $collection = new Assert\Collection([
            'name' => new Assert\Length(['min' => 3]),
            'homepage' => new Assert\Optional(
                new Assert\Url(['requireTld' => true]),
            ),
            'description' => new Assert\Optional([
                new Assert\Length(['min' => 3])
            ]),
            'redirect_uri' => new Assert\Required([
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\NotBlank(),
                    new Assert\Url(['requireTld' => true]),
                ]),
            ]),
        ]);
        if (!empty($body)) {
            $this->validateRequest($collection, $body, 'clients');
        }
    }
}
