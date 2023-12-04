<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Controller;
use app\models\User as UserModel;
use app\models\Database as DatabaseModel;
use app\inc\Session;
use Exception;


/**
 * Class Database
 * @package app\api\v4
 */
class Schema extends Controller
{

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<mixed>
     * @OA\Get(
     *   path="/api/v4/schema",
     *   tags={"Schema"},
     *   summary="Get available schemas",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="List of schemas with count of PostGIS tables",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    function get_index(): array
    {
        $database = new DatabaseModel();
        return $database->listAllSchemas();
    }
}