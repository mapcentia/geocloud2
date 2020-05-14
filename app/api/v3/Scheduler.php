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
use app\models\Job;

class Scheduler extends Controller
{
    /**
     * Tileseeder constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->tileSeeder = new \app\models\Tileseeder();
    }

    /**
     * @return void
     *
     * @OA\Get(
     *   path="/api/v3/scheduler/{jobId}",
     *   tags={"scheduler"},
     *   summary="Coming",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
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
        $db = Jwt::extractPayload(Input::getJwtToken())["data"]["database"];
        $job->runJob($id, $db, true);
        exit();
    }
}