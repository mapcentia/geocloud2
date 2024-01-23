<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route;
use app\models\Layer;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Meta
 * @package app\api\v4
 */
class Meta extends Controller
{
    /**
     * @var Layer
     */
    private $layers;

    /**
     * Meta constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->layers = new Layer();
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Get(
     *   path="/api/v4/meta/{query}",
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
    public function get_index(): array
    {
        $jwt = Jwt::validate()["data"];
        $out = [];
        $res = $this->layers->getAll($jwt["database"], true, Route::getParam("query"), Input::get("iex"), true, false);
        $rows = $res["data"];
        foreach ($rows as $row) {
            $out[$row["_key_"]] = [
                "uuid" => $row["uuid"],
                "f_table_schema" => $row["f_table_schema"],
                "f_table_name" => $row["f_table_name"],
                "f_geometry_column" => $row["f_geometry_column"],
                "f_table_abstract" => $row["f_table_abstract"],
                "f_table_title" => $row["f_table_title"],
                "pkey" => $row["pkey"],
                "coord_dimension" => $row["coord_dimension"],
                "type" => $row["type"],
                "srid" => $row["srid"],
                "authentication" => $row["authentication"],
                "layergroup" => $row["layergroup"],
                "sort_id" => $row["sort_id"],
                "wmssource" => $row["wmssource"],
                "tags" => $row["tags"],
                "privileges" => $row["privileges"],
                "fields" => $row["fields"],
                "children" => $row["children"],
            ];

        }
        return !$res["success"] ? $res : ["success" => true, "data" => $out];
    }
}
