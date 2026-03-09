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
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Meta",
    description: "Metadata for relations (tables/views) used by clients and tools. Stored outside the physical schema.",
    required: [],
    properties: [
        new OA\Property(
            property: 'relations',
            description: 'Relation metadata provides supplementary information about database relations.
            Metadata is stored outside the physical schema and does not change table structure or constraints.
            It is used by clients such as UI applications, query builders, and access layers.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                properties: [
                    new OA\Property(property: 'title', description: 'Human-readable relation title.', type: 'string'),
                    new OA\Property(property: 'abstract', description: 'Description or summary of the relation.', type: 'string'),
                    new OA\Property(property: 'group', description: 'Logical grouping (e.g. for UI categorization).', type: 'string'),
                    new OA\Property(property: 'sort_id', description: 'Sorting weight for presentation.', type: 'integer'),
                    new OA\Property(property: 'tags', description: 'Arbitrary classification tags.', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'properties', description: 'Free-form key/value metadata.', type: 'object'),
                    new OA\Property(
                        property: 'fields',
                        description: 'Per-column metadata.',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(
                            properties: [
                                new OA\Property(property: 'alias', description: 'Display name used in clients/UI.', type: 'string'),
                                new OA\Property(property: 'queryable', description: 'Whether the field can be queried/filtered.', type: 'boolean'),
                                new OA\Property(property: 'sort_id', description: 'Sorting weight within the field list.', type: 'integer'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            ))
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: '/api/v4/meta/[query]', scope: Scope::SUB_USER_ALLOWED)]
class Meta extends AbstractApi
{
    private const array PRIVATE_PROPERTIES = ['character_maximum_length',
        'numeric_precision', 'numeric_scale', 'max_bytes', 'is_unique',
        'default_value', 'type', 'is_nullable'];

    private const array PUBLIC_PROPERTIES = ['alias', 'queryable', 'sort_id'];

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'relations';

    }


    /**
     * @return Response
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[OA\Get(path: '/api/v4/meta/{query}', operationId: 'getMetaData', summary: 'Get relation metadata', security: [['bearerAuth' => []]], tags: ['Metadata'])]
    #[OA\Parameter(name: 'query', description: 'Schema-qualified relation name, schema name, or tag (tag:name). Comma-separated values are supported.', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Meta"))]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $layers = new Layer(connection: $this->connection);
        $jwt = Jwt::validate()["data"];
        $res = $layers->getAll(
            db: $jwt["database"],
            auth: true,
            query: $this->route->getParam("query"),
            parse: true,
            lookupForeignTables: false,
            jwt: $jwt,
        );
        $rows = $res["data"];
        $r = self::processRows($rows);
        return new GetResponse(data: ['relations' => $r]);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/meta', operationId: 'patchMetaData', summary: 'Update relation metadata', security: [['bearerAuth' => []]], tags: ['Metadata'])]
    #[OA\RequestBody(description: 'Metadata updates.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Meta"))]
    #[OA\Response(response: 204, description: "Metadata updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    public function patch_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body, true);
        $geometryJoinTable = new \app\models\Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $geometryJoinTable->begin();
        foreach ($data['relations'] as $key => $datum) {
            $split = explode(".", $key);
            $geomFields = (new Layer(connection: $this->connection))->getGeometryColumnsFromTable($split[0], $split[1]);
            foreach ($geomFields as $geomField) {
                if (count($split) == 3) {
                    $key = $split[0] . '.' . $split[1] . '.' . $split[2];
                } else {
                    $key = $split[0] . '.' . $split[1] . '.' . $geomField;
                }
                $datum['_key_'] = $key;
                $geometryJoinTable->updateRecord(data: self::processRowReverse($datum), keyName: '_key_');
            }
        }
        $geometryJoinTable->commit();
        return new NoContentResponse();
    }

    static function processRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = $row['f_table_schema'] . '.' . $row['f_table_name'];
            $out[$key] = self::processRow($row);
        }
        return $out;
    }

    static function processRow(array $row): array
    {
        $out = [];
        $map = [
            "title" => "f_table_title",
            "abstract" => "f_table_abstract",
            "group" => "layergroup",
            "sort_id" => "sort_id",
            "tags" => "tags",
            "properties" => "meta",
            "_uuid" => "uuid",
            "_schema" => "f_table_schema",
            "_rel" => "f_table_name",
            "_geometry_column" => "f_geometry_column",
            "_pkey" => "pkey",
            "_rel_type" => "rel_type",
            "_coord_dimension" => "coord_dimension",
            "_geom_type" => "type",
            "_srid" => "srid",
            "_authentication" => "authentication",
        ];

        foreach ($map as $outKey => $rowKey) {
            if (isset($row[$rowKey])) {
                $out[$outKey] = $row[$rowKey];
            }
        }

        if (isset($row["fields"])) {
            $out["fields"] = self::setPropertiesToPrivate($row["fields"]);
        }

        return $out;
    }

    static function processRowReverse(array $row): array
    {
        $out = [];
        $map = [
            "_key_" => "_key_",
            "f_table_abstract" => "abstract",
            "f_table_title" => "title",
            "layergroup" => "group",
            "sort_id" => "sort_id",
            "tags" => "tags",
            "meta" => "properties",
            "fieldconf" => "fields",
        ];

        foreach ($map as $rowKey => $inputKey) {
            if (isset($row[$inputKey])) {
                $out[$rowKey] = $row[$inputKey];
            }
        }

        return $out;
    }

    protected static function setPropertiesToPrivate(array $properties): array
    {
        $newArray = [];
        foreach ($properties as $field => $property) {
            $col = [];
            foreach ($property as $key => $value) {
                if (in_array($key, self::PRIVATE_PROPERTIES)) {
                    $col['_' . $key] = $value;
                } elseif (in_array($key, self::PUBLIC_PROPERTIES)) {
                    $col[$key] = $value;
                }
            }
            $newArray[$field] = $col;
        }
        return $newArray;
    }

    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();
        error_log("Received metadata patch request with body: " . $body);

        $this->validateRequest(
            collection: self::getAssert(),
            data: $body,
            method: Input::getMethod(),
            allowPatchOnCollection: true
        );
    }

    private function getAssert(): Assert\Collection
    {
        return new Assert\Collection(
            fields: [
                'relations' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Collection(
                            fields: [
                                'title' => new Assert\Optional(new Assert\Type('string')),
                                'abstract' => new Assert\Optional(new Assert\Type('string')),
                                'group' => new Assert\Optional(new Assert\Type('string')),
                                'sort_id' => new Assert\Optional(new Assert\Type('integer')),
                                'tags' => new Assert\Optional(new Assert\Type('list', 'This value should be of type list.')),
                                'properties' => new Assert\Optional(new Assert\Type('associative_array', 'This value should be of type object.')),
                                'fields' => new Assert\Optional([
                                    new Assert\Type('array'),
                                    new Assert\All([
                                        new Assert\Collection(
                                            fields: [
                                                'alias' => new Assert\Optional(new Assert\Type('string')),
                                                'queryable' => new Assert\Optional(new Assert\Type('boolean')),
                                                'sort_id' => new Assert\Optional(new Assert\Type('integer')),
                                            ],
                                            allowExtraFields: false,
                                            allowMissingFields: true,
                                        )
                                    ])
                                ]),
                            ],
                            allowExtraFields: false,
                            allowMissingFields: true,
                        )
                    ])
                ]),

            ],
            allowExtraFields: false,
            allowMissingFields: true,
        );
    }

    #[Override]
    public function post_index(): Response
    {
        // TODO: Implement post_index() method.
    }

    #[Override]
    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.

    }
}
