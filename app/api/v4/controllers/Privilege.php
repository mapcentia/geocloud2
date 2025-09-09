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
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Layer;
use app\models\Table;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use StdClass;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Privilege",
    required: ["subuser", "privileges"],
    properties: [
        new OA\Property(
            property: "subuser",
            title: "Sub-user",
            description: "Name of the sub-user",
            type: "string",
            example: "joe",
        ),
        new OA\Property(
            property: "privileges",
            title: "Privileges",
            description: "Either none, read, write",
            type: "string",
            example: "all",
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/privileges', scope: Scope::SUB_USER_ALLOWED)]
class Privilege extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'privileges';
    }

    /**
     * @return Response
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/privileges', operationId: 'getPrivileges', description: "Get privileges", tags: ['Privileges'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Privilege"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $layer = new Layer(connection: $this->connection);
        $split = explode('.', $this->qualifiedName[0]);
        $res = $layer->getPrivilegesAsArray($split[0], $split[1]);
        return [$this->resource => $res];
    }

    #[Override]
    public function post_index(): Response
    {
        // TODO: Implement post_index() method.
        return [];
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException|GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/privileges', operationId: 'patchPrivileges', description: "Update privileges", tags: ['Privileges'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'Privileges', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Privilege"))]
    #[OA\Response(response: 204, description: "Privileges updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $layer = new Layer(connection: $this->connection);
        $body = Input::getBody();
        $data = json_decode($body, true);

        if (!isset($data['privileges'])) {
            $data['privileges'] = [$data];
        }
        $table = new Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $table->connect();
        $table->begin();
        foreach ($data['privileges'] as $datum) {

            $obj = new StdClass();
            $obj->_key_ = $this->qualifiedName[0];
            $obj->privileges = $datum['privilege'];
            $obj->subuser = $datum['subuser'];
            $layer->updatePrivileges($obj, $table);
        }
        $table->commit();
        $baseUri = "Location: /api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/privileges/";
        return $this->patchResponse($baseUri);
    }

    #[Override] public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
        return [];
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[Override] public function validate(): void
    {
        $table = $this->route->getParam("table");
        $schema = $this->route->getParam("schema");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $table) {
            $this->postWithResource();
        }

        $collection = new Assert\Collection([
            'subuser' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'privilege' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
                new Assert\Choice(choices: ['none', 'read', 'write']),

            ]),
        ]);
        $this->validateRequest($collection, $body, Input::getMethod(), true);
        $this->initiate(schema: $schema, relation: $table);
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
