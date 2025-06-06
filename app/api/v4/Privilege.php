<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Jwt;
use app\inc\Input;
use app\inc\Route2;
use app\models\Client as ClientModel;
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
class Privilege extends AbstractApi
{
    /**
     * @return array
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/privileges', operationId: 'getPrivileges', description: "Get privileges", tags: ['Privileges'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Privilege"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $layer = new Layer();
        $split = explode('.', $this->qualifiedName[0]);
        $res = $layer->getPrivilegesAsArray($split[0], $split[1]);
        return ["privileges" => $res];
    }

    #[Override]
    public function post_index(): array
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
    public function patch_index(): array
    {
        $layer = new Layer();
        $body = Input::getBody();
        $data = json_decode($body, true);

        if (!isset($data['privileges'])) {
            $data['privileges'] = [$data];
        }
        $table = new Table("settings.geometry_columns_join");
        $table->begin();
        foreach ($data['privileges'] as $datum) {

            $obj = new StdClass();
            $obj->_key_ = $this->qualifiedName[0];
            $obj->privileges = $datum['privilege'];
            $obj->subuser = $datum['subuser'];
            $layer->updatePrivileges($obj, $table);
        }
        $table->commit();
        header("Location: /api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/privileges/");
        return ["code" => "303"];
    }

    #[Override] public function delete_index(): array
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
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $body = Input::getBody();

        $this->jwt = Jwt::validate()["data"];
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
                new Assert\Choice(['none', 'read', 'write']),

            ]),
        ]);
        $this->validateRequest($collection, $body, 'privileges', Input::getMethod(), true);

        $this->initiate($schema, $table, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}
