<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\PatchResponse;
use app\api\v4\Responses\PostResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Sequence",
    description: "A sequence is a database object that generates a sequence of integers, often used for auto-incrementing primary key columns.",
    required: ["name"],
    properties: [
        new OA\Property(property: "name", title: "Name", description: "The name of the sequence", type: "string", example: "my_sequence"),
        new OA\Property(property: "data_type", title: "Data type", description: "The data type of the sequence", type: "string",
            default: "bigint",
            enum: ["smallint", "integer", "bigint"],
            example: "bigint"
        ),
        new OA\Property(property: "increment_by", title: "Increment by", description: "Specifies which value is added to the current sequence value to create a new value",
            type: "integer",
            default: 1,
            example: 1
        ),
        new OA\Property(property: "min_value", title: "Min value", description: "Determines the minimum value a sequence can generate.",
            type: "integer", example: 1
        ),
        new OA\Property(property: "max_value", title: "Max value", description: "Determines the maximum value for the sequence.", type: "integer", example: 9223372036854775807),
        new OA\Property(property: "start_value", title: "Start value", description: "he initial value of the sequence", type: "integer", example: 1),
        new OA\Property(property: "cache_size", title: "Cache size", description: "Specifies how many sequence numbers should be preallocated and stored in memory for faster access.",
            type: "integer",
            default: 1, example: 1
        ),
        new OA\Property(property: "owned_by", title: "Qwned by",
            description: "Associates the sequence with a specific table column, so that if that column (or its whole table) is deleted, the sequence will be automatically deleted as well. The format is schema.table.column.",
            type: "string", example: "public.my_table.id"),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/sequences/[sequence]', scope: Scope::SUB_USER_ALLOWED)]
class Sequence extends AbstractApi
{

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'sequences';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/sequences/{sequence}', operationId: 'getSequence', description: "Get sequence(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'sequence', description: 'Sequence names', in: 'path', required: false, example: 'my_sequences')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Sequence"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function get_index(): GetResponse
    {
        $r = [];
        $res = self::getSequences($this->table[0], $this->schema[0]);
        if (!empty($this->sequence)) {
            foreach ($this->sequence as $sequence) {
                foreach ($res as $c) {
                    if ($c['name'] == $sequence) {
                        $r[] = $c;
                    }
                }
            }
        } else {
            $r = $res;
        }
        return $this->getResponse($r);
    }

    #[OA\Post(path: '/api/v4/schemas/{schema}/sequences/', operationId: 'postSequence', description: "Create sequence(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\RequestBody(description: 'New sequence', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sequence"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function post_index(): PostResponse
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $list = [];
        $this->table[0]->connect();
        $this->table[0]->begin();
        if (!isset($data->sequences)) {
            $sequences = [$data];
        } else {
            $sequences = $data->sequences;
        }
        foreach ($sequences as $datum) {
            $list[] = self::addSequence($this->table[0], $this->schema[0], (array)$datum);
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/sequences/";
        return $this->postResponse($baseUri, $list);
    }

    #[Override]
    public function put_index(): Response
    {
        return $this->emptyResponse();
    }

    #[OA\Patch(path: '/api/v4/schemas/{schema}/sequences/{sequence}', operationId: 'patchSequence', description: "Update sequence(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'sequence', description: 'Sequence names', in: 'path', required: true, example: 'my_sequences')]
    #[OA\RequestBody(description: 'Sequence', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sequence"))]
    #[OA\Response(response: 204, description: "Sequence updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function patch_index(): PatchResponse
    {
        $body = Input::getBody();
        $data = json_decode($body, true);
        $names = explode(',', $this->route->getParam("sequence"));
        $this->table[0]->begin();
        $list = [];

        foreach ($names as $name) {
            $list[] = $this->table[0]->alterSequence($name, $this->schema[0], $data);
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/sequences/";
        return $this->patchResponse($baseUri, $list);

    }

    #[OA\Delete(path: '/api/v4/schemas/{schema}/sequences/{sequence}', operationId: 'deleteSequence', description: "Delete sequence(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'sequence', description: 'Sequence names', in: 'path', required: true, example: 'my_sequences')]
    #[OA\Response(response: 204, description: 'Sequence deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): NoContentResponse
    {
        $names = explode(',', $this->route->getParam("sequence"));
        $this->table[0]->begin();
        foreach ($names as $name) {
            $this->table[0]->deleteSequence($name);
        }
        $this->table[0]->commit();
        return $this->deleteResponse();

    }

    public static function getSequences(\app\models\Table $table, string $schema): array
    {
        return $table->getSequences($schema)['data'];
    }

    public static function addSequence(\app\models\Table $table, string $schema, array $data, bool $withOwner = true): string
    {
        return $table->createSequence($data['name'], $schema, $data, $withOwner);
    }

    public static function alterSequence(\app\models\Table $table, string $schema, array $data, bool $withOwner = true): string
    {
        return $table->alterSequence($data['name'], $schema, $data);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[Override]
    public function validate(): void
    {
        $schema = $this->route->getParam("schema");
        $sequence = $this->route->getParam("sequence");
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($sequence) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("Patch and delete on collection is not allowed", 406);
        }
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $sequence) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate(schema: $schema, key: $sequence, sequence: $sequence);
    }

    public static function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'name' => new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'data_type' => new Assert\Optional(new Assert\Type('string')),
                'increment_by' => new Assert\Optional(new Assert\Type('integer')),
                'min_value' => new Assert\Optional(new Assert\Type('integer')),
                'max_value' => new Assert\Optional(new Assert\Type('integer')),
                'start_value' => new Assert\Optional(new Assert\Type('integer')),
                'cache_size' => new Assert\Optional(new Assert\Type('integer')),
                'owned_by' => new Assert\Optional(new Assert\Type('string')),

            ],
        ]);
    }
}