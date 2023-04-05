<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\models\Geofence as GeofenceModel;


/**
 * Class Geofence
 * @package app\api\v3
 */
class Geofence extends Controller
{
    public GeofenceModel $geofence;

    public function __construct()
    {
        parent::__construct();
        $this->geofence = new GeofenceModel(null);
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v3/geofence",
     *   tags={"Geofence"},
     *   summary="Get all geofence rules",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="List of geofence rules",
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
    public function get_index(): array
    {
        return $this->geofence->get();
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v3/geofence",
     *   tags={"Geofence"},
     *   summary="Create a new geofence rule",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="Geofence JSON rule",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="priority",type="integer", example=1),
     *         @OA\Property(property="username",type="string", example="joe"),
     *         @OA\Property(property="service",type="string", example="sql"),
     *         @OA\Property(property="request",type="string", example="*"),
     *         @OA\Property(property="layer",type="string", example="*"),
     *         @OA\Property(property="schema",type="string", example="*"),
     *         @OA\Property(property="access",type="string", example="limit"),
     *         @OA\Property(property="read_filter",type="string", example="userid='joe'"),
     *         @OA\Property(property="write_filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="The newly created geofence rule",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="id",type="integer", example=1),
     *         @OA\Property(property="priority",type="integer", example=1),
     *         @OA\Property(property="username",type="string", example="joe"),
     *         @OA\Property(property="service",type="string", example="sql"),
     *         @OA\Property(property="request",type="string", example="*"),
     *         @OA\Property(property="layer",type="string", example="*"),
     *         @OA\Property(property="schema",type="string", example="*"),
     *         @OA\Property(property="access",type="string", example="limit"),
     *         @OA\Property(property="read_filter",type="string", example="userid='joe'"),
     *         @OA\Property(property="write_filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   )
     * )
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $arr = json_decode($body, true);
        return $this->geofence->create($arr);
    }

    /**
     * @return array
     *
     * @OA\Put(
     *   path="/api/v3/geofence",
     *   tags={"Geofence"},
     *   summary="Updates a geofence rule",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="Geofence JSON rule with id property",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="id",type="integer", example=1),
     *         @OA\Property(property="priority",type="integer", example=1),
     *         @OA\Property(property="username",type="string", example="joe"),
     *         @OA\Property(property="service",type="string", example="sql"),
     *         @OA\Property(property="request",type="string", example="*"),
     *         @OA\Property(property="layer",type="string", example="*"),
     *         @OA\Property(property="schema",type="string", example="*"),
     *         @OA\Property(property="access",type="string", example="limit"),
     *         @OA\Property(property="read_filter",type="string", example="userid='joe'"),
     *         @OA\Property(property="write_filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="The changed created geofence rule",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="id",type="integer", example=1),
     *         @OA\Property(property="priority",type="integer", example=1),
     *         @OA\Property(property="username",type="string", example="joe"),
     *         @OA\Property(property="service",type="string", example="sql"),
     *         @OA\Property(property="request",type="string", example="*"),
     *         @OA\Property(property="layer",type="string", example="*"),
     *         @OA\Property(property="schema",type="string", example="*"),
     *         @OA\Property(property="access",type="string", example="limit"),
     *         @OA\Property(property="read_filter",type="string", example="userid='joe'"),
     *         @OA\Property(property="write_filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   )
     * )
     */
    public function put_index(): array
    {
        $body = Input::getBody();
        $arr = json_decode($body, true);
        return $this->geofence->update($arr);
    }

    /**
     * @return array
     *
     * @OA\Delete(
     *   path="/api/v3/geofence/{id}",
     *   tags={"Geofence"},
     *   summary="Deletes a geofence rule",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Id of geofence rule",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="object",  @OA\Property(property="id", type="integer", example=1)),
     *       )
     *     )
     *   )
     * )
     */
    public function delete_index(): array
    {
        $id = Route::getParam("id");
        if (!is_numeric($id)) {
            $response['success'] = false;
            $response['message'] = "id is not a integer";
            $response['code'] = 400;
            return $response;
        }
        return $this->geofence->delete((int)$id);
    }
}


