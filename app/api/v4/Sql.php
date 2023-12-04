<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Jwt;
use app\api\v2\Sql as V2Sql;
use app\models\Setting;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
class Sql extends Controller
{
    /**
     * @var V2Sql
     */
    private V2Sql $v2;

    public function __construct()
    {
        parent::__construct();
        $this->v2 = new V2Sql();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Post(
     *   path="/api/v4/sql",
     *   tags={"Sql"},
     *   summary="Do SQL quyeries",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="Parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="q",type="string", example="SELECT 1 as id,ST_setsrid(ST_MakePoint(10,56),4326) as geom"),
     *         @OA\Property(property="srs",type="integer", example="25832", description=""),
     *         @OA\Property(property="format",type="string", example="csv"),
     *         @OA\Property(property="geoformat",type="string", example="wkt"),
     *         @OA\Property(property="allstr",type="boolean", example=false),
     *         @OA\Property(property="lifetime",type="integer", example=0),
     *         @OA\Property(property="base64",type="boolean", example=false)
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="If select then the result will be data on choosen format. If transaction the number of effected rows is returned.",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     )
     *   )
     * )
     */
    public function post_index(): array
    {
        $jwt = Jwt::validate()["data"];
        $uid = $jwt["uid"];
        $user["user"] = $jwt["superUser"] ? $jwt["uid"] : $jwt["uid"] . "@" . $jwt["database"];

        $settings_viewer = new Setting();
        $response = $settings_viewer->get();
        if (!$jwt["superUser"]) {
            $apiKey = $response['data']->api_key_subuser->$uid;
        } else {
            $apiKey = $response['data']->api_key;
        }
        Input::setParams(
            [
                "key" => $apiKey,
                "srs" => "4326"
            ]
        );
        return $this->v2->get_index($user);
    }

}
