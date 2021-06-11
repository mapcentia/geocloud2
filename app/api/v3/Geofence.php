<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\models\Geofence As GeofenceModel;
use app\inc\UserFilter;


/**
 * Class Geofence
 * @package app\api\v3
 */
class Geofence extends Controller
{
    /**
     * @var GeofenceModel
     */
    public $geofence;

    /**
     * @var UserFilter
     */
    public $userFilter;

    /**
     * Tileseeder constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->userFilter = new UserFilter("mydb", "mydb", "wfs","GetFeature", "0.0.0.0", "test", "city_center");
        $this->geofence = new GeofenceModel($this->userFilter);
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
        $res =$this->geofence->authorize();
        print_r($res);
    }
}
