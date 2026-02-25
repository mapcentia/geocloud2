<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\PatchResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Class Meta
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v3/meta/[query]', scope: Scope::SUB_USER_ALLOWED)]
class Meta extends AbstractApi
{
    private const array PRIVATE_PROPERTIES = ['num', 'typname', 'full_type', 'character_maximum_length',
        'numeric_precision', 'numeric_scale', 'max_bytes', 'reference', 'restriction', 'is_primary', 'is_unique',
        'index_method', 'checks', 'geom_type', 'srid', 'is_array', 'udt_name', 'identity_generation',
        'default_value', 'type', 'comment', 'is_nullable'];

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'relations';

    }


    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @OA\Get(
     *   path="/api/v3/meta/{query}",
     *   tags={"Meta"},
     *   summary="Get layer meta data",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="query",
     *     in="path",
     *     required=false,
     *     description="Can be a schema qualified relation name, a schame name, a tag in the form tag:name or combination of the three separated by comma.",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="If select then the result will be data in the requested format. If transaction the number of effected rows is returned.",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     )
     *   )
     * )
     */
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

    public function patch_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body, true);
        $geometryJoinTable = new \app\models\Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $geometryJoinTable->begin();
        foreach ($data['relations'] as $key => $datum) {
            $geometryJoinTable->updateRecord(data: self::processRowReverse($datum), keyName: '_key_');

        }
        $geometryJoinTable->commit();

        return new PatchResponse(data: []);
    }

    static function processRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::processRow($row);
        }
        return $out;
    }

    static function processRow(array $row): array
    {
        return [
            "title" => $row["f_table_title"],
            "abstract" => $row["f_table_abstract"],
            "group" => $row["layergroup"],
            "sort_id" => $row["sort_id"],
            "tags" => $row["tags"],
            "meta" => $row["meta"],
            "fields" => self::setPropertiesToPrivate($row["fields"]),

            "_uuid" => $row["uuid"],
            "_key" => $row["_key_"],
            "_schema" => $row["f_table_schema"],
            "_rel" => $row["f_table_name"],
            "_geometry_column" => $row["f_geometry_column"],
            "_pkey" => $row["pkey"],
            "_rel_type" => $row["rel_type"],
            "_coord_dimension" => $row["coord_dimension"],
            "_geom_type" => $row["type"],
            "_srid" => $row["srid"],
            "_authentication" => $row["authentication"],
//            "wmssource" => $row["wmssource"],
//            "privileges" => $row["privileges"],
            "_children" => $row["children"],
        ];

    }

    static function processRowReverse(array $row): array
    {
        return [
            "_key_" => $row["_key"],
            "f_table_abstract" => $row["abstract"],
            "f_table_title" => $row["title"],
            "layergroup" => $row["group"],
            "sort_id" => $row["sort_id"],
            "tags" => $row["tags"],
            "meta" => $row["meta"],
            "fieldconf" => ($row["fields"]),
        ];

    }

    protected static function setPropertiesToPrivate(array $properties): array
    {
        $newArray = [];
        foreach ($properties as $field => $property) {
            $col = [];
            foreach ($property as $key => $value) {
                if (in_array($key, self::PRIVATE_PROPERTIES)) {
                    //$col['_' . $key] = $value;
                } else {
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
                'title' => new Assert\Optional(new Assert\Type('string')),
                'abstract' => new Assert\Optional(new Assert\Type('string')),
                'group' => new Assert\Optional(new Assert\Type('string')),
                'sort_id' => new Assert\Optional(new Assert\Type('integer')),
                'tags' => new Assert\Optional(new Assert\Type('array')),
                'meta' => new Assert\Optional(new Assert\Type('array', 'This value should be of type object.')),
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
            allowExtraFields: true,
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
