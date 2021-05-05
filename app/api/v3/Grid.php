<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Input;
use app\models\Grid as ModelGrid;


/**
 * Class Grid
 * @package app\api\v3
 */
class Grid extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<mixed>
     *
     * @OA\Post(
     *   path="/api/v3/grid",
     *   tags={"Grid"},
     *   summary="Create a fishnet grid from an input polygon",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="Fishnet parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="table", type="string", example="new_grid"),
     *         @OA\Property(property="extent", type="string", example="my_extent_polygon"),
     *         @OA\Property(property="size", type="integer", example=10000),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Return true if fishnet grid was created",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success", type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function post_index(): array
    {
        $response = [];
        $model = new ModelGrid();
        $body = Input::getBody();
        $arr = json_decode($body, true);

        $table = $arr["table"];
        $extent = $arr["extent"];
        $size = $arr["size"];

        $res = $model->create($table, $extent, $size);

        if (!$res["success"]) {
            $response["success"] = false;
            $response["message"] = $res["message"];
            $response["code"] = 500;
        } else {
            $response["success"] = true;
        }
        return $response;

    }
}
