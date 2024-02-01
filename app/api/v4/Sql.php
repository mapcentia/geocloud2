<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\api\v2\Sql as V2Sql;
use app\models\Setting;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
class Sql extends AbstractApi
{
    /**
     * @var V2Sql
     */
    private V2Sql $v2;

    public function __construct()
    {
        $this->v2 = new V2Sql();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
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
    #[Override]
    public function post_index(): array
    {
        $jwtData = Jwt::validate()["data"];
        $isSuperUser = $jwtData["superUser"];
        $uid = $jwtData["uid"];
        $user = [
            "user" => $isSuperUser ? $uid : "$uid@{$jwtData["database"]}"
        ];
        $settingsData = (new Setting())->get()["data"];
        $apiKey = $isSuperUser ? $settingsData->api_key : $settingsData->api_key_subuser->$uid;
        Input::setParams(
            [
                "key" => $apiKey,
                "srs" => "4326",
                "convert_types" => true,
            ]
        );
        return $this->v2->get_index($user);
    }

    #[Override]
    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    #[Override]
    public function get_index(): array
    {
        // TODO: Implement get_index() method.
    }

    #[Override]
    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }
}
