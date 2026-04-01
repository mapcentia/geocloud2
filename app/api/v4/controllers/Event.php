<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\ApiInterface;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\models\Layer;
use app\models\Table as TableModel;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Events",
    description: "Events for a table.",
    required: ["enabled"],
    properties: [
        new OA\Property(
            property: "enabled",
            title: "Enabled",
            description: "Enable or disable events for this table.",
            type: "boolean",
            example: true,
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/[table]/events', scope: Scope::SUB_USER_ALLOWED)]
class Event extends AbstractApi
{
    private Layer $layer;

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'events';
        $this->layer = new Layer(connection: $this->connection);

    }

    /**
     * @return Response
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/events', operationId: 'getEvents', description: "Check if event trigger is enabled.", tags: ['Events'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Events"))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        return new GetResponse(["enabled"=> false]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/events', operationId: 'postEvents', description: "Install event trigger.", tags: ['Events'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'Privileges.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Events"))]
    #[OA\Response(response: 201, description: "Event trigger installed.")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body);
        if ($data->enabled === true) {
            $this->layer->installNotifyTrigger($this->qualifiedName[0]);
        } elseif ($data->enabled === false) {
            $this->layer->removeNotifyTrigger($this->qualifiedName[0]);
        }
        return new NoContentResponse();
    }

    public function delete_index(): Response
    {

    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = $this->route->getParam("table");
        $schema = $this->route->getParam("schema");
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['post', 'delete'])) {
            throw new GC2Exception("Patch and delete on a table collection is not allowed", 400);
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate(schema: $schema, relation: $table);
    }

    /**
     * @return Assert\Collection
     */
    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['enabled'] = new Assert\Required(
            new Assert\Type('boolean'),
        );
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function post_index(): Response
    {
        // TODO: Implement patch_index() method.
    }
}
