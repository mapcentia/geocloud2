<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\models\Job;

/**
 * Class Scheduler
 * @package app\api\v3
 */
class Scheduler extends Controller
{
    /**
     * @var \app\models\Tileseeder
     */
    public $tileSeeder;

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
     *   tags={"Scheduler"},
     *   summary="Start scheduled job by id.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="jobId",
     *     in="path",
     *     required=true,
     *     description="Job identifier",
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