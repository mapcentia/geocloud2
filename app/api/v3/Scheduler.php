<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

/**
 * @OA\Info(title="GC2 API", version="0.1")
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      in="header",
 *      name="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 * ),
 */


namespace app\api\v3;


use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\conf\Connection;
use app\models\Job;

class Scheduler extends Controller
{
    private $tileSeeder;

    /**
     * Tileseeder constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->tileSeeder = new \app\models\Tileseeder();
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v3/tileseeder",
     *   tags={"tileseeder"},
     *   summary="Starts a mapcache_seed process",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="mapcache_seed parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string"),
     *         @OA\Property(property="layer",type="string"),
     *         @OA\Property(property="start",type="integer"),
     *         @OA\Property(property="end",type="integer"),
     *         @OA\Property(property="extent",type="string")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_index()
    {

        $id = Route::getParam("id");
        $job = new Job();
        $job->runJob($id, "mydb", true);
        exit();


    }
}