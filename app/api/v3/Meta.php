<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\api\v4\AbstractApi;
use app\exceptions\GC2Exception;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Meta
 * @package app\api\v4
 */
class Meta extends AbstractApi
{

    /**
     * Meta constructor.
     */
    function __construct()
    {
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
    #[Override]
    public function get_index(): array
    {
        $layers = new Layer();
        $jwt = Jwt::validate()["data"];
        $auth = $jwt['superUser'];
        $res = $layers->getAll($jwt["database"], $auth, Route2::getParam("query"), false, true, false, false);
        $rows = $res["data"];
        $out = self::processRows($rows);
        return !$res["success"] ? $res : ["relations" => $out];
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
    public function post_index(): array
    {
        // TODO: Implement post_index() method.
        return [];
    }

    #[Override]
    public function put_index(): array
    {
        // TODO: Implement put_index() method.
        return [];
    }

    #[Override]
    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
        return [];
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }
}
