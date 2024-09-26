<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Route2;
use app\inc\Input;
use app\models\Geofence as GeofenceModel;
use Override;


/**
 * Class Geofence
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])]
class Geofence extends AbstractApi
{
    public GeofenceModel $geofence;

    public function __construct()
    {


    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v4/geofence",
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
     * @throws GC2Exception
     */
    #[AcceptableAccepts(['application/json'])]
    public function get_index(): array
    {
        $geofence = new GeofenceModel(null);
        if (!empty(Route2::getParam("id"))) {
            $ids = explode(',', Route2::getParam("id"));
            foreach ($ids as $id) {
                $r[] = $geofence->get($id)[0];
            }
        } else {
            $r = $geofence->get(null);
        }
        if (count($r) > 1) {
            return ["rules" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v4/geofence",
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
     *         @OA\Property(property="filter",type="string", example="userid='joe'")
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
     *         @OA\Property(property="filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   )
     * )
     */
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json'])]
    public function post_index(): array
    {
        $list = [];
        $model = new GeofenceModel(null);
        $body = Input::getBody();
        $data = json_decode($body, true);
        if (!isset($data['rules'])) {
            $data['rules'] = [$data];
        }
        $model->connect();
        $model->begin();
        foreach ($data['rules'] as $datum) {
            $list[] = $model->create($datum)['data']['id'];
        }
        $model->commit();
        header("Location: /api/v4/rules/" . implode(",", $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     *
     * @OA\Put(
     *   path="/api/v4/geofence",
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
     *         @OA\Property(property="filter",type="string", example="userid='joe'")
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
     *         @OA\Property(property="filter",type="string", example="userid='joe'")
     *       )
     *     )
     *   )
     * )
     * @throws GC2Exception
     */
    #[AcceptableContentTypes(['application/json'])]
    public function put_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No rule id", 404, null, 'MISSING_ID');
        }
        $ids = explode(',', Route2::getParam("id"));
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model = new GeofenceModel(null);
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
            }
            $data["id"] = $id;
            $model->update($data);
        }
        $model->commit();
        header("Location: /api/v4/rules/" . implode(",", $ids));
        return ["code" => "303"];
    }

    /**
     * @return array
     *
     * @OA\Delete(
     *   path="/api/v4/geofence/{id}",
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
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $ids = explode(',', Route2::getParam("id"));
        $model = new GeofenceModel(null);
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
            }
            $model->delete((int)$id);
        }
        $model->commit();
        return ["code" => "204"];
    }

    #[Override] public function validate(): void
    {
    }
}


