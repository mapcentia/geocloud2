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
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Meta
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v3/meta/[query]', scope: Scope::SUB_USER_ALLOWED)]
class Meta extends AbstractApi
{
    /**
     * Meta constructor.
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
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
        $res = $layers->getAll($jwt["database"], true, $this->route->getParam("query"), false, true, false, false);
        $rows = $res["data"];
        $out = self::processRows($rows);
        $r = !$res["success"] ? $res : ["relations" => $out];

        return $this->getResponse($r);
    }

    static function processRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row["_key_"]] = self::processRow($row);
        }
        return $out;
    }

    static function processRow(array $row): array
    {
        return [
            "uuid" => $row["uuid"],
            "f_table_schema" => $row["f_table_schema"],
            "f_table_name" => $row["f_table_name"],
            "f_geometry_column" => $row["f_geometry_column"],
            "f_table_abstract" => $row["f_table_abstract"],
            "f_table_title" => $row["f_table_title"],
            "pkey" => $row["pkey"],
            "rel_type" => $row["rel_type"],
            "coord_dimension" => $row["coord_dimension"],
            "type" => $row["type"],
            "srid" => $row["srid"],
            "authentication" => $row["authentication"],
            "layergroup" => $row["layergroup"],
            "sort_id" => $row["sort_id"],
//            "wmssource" => $row["wmssource"],
            "tags" => $row["tags"],
//            "privileges" => $row["privileges"],
            "fields" => $row["fields"],
            "children" => $row["children"],
            "meta" => $row["meta"],
        ];
    }

    #[Override]
    public function validate(): void
    {
        // TODO: Implement validate() method.
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

    public function patch_index(): Response
    {
        // TODO: Implement patch_index() method.
    }
}
