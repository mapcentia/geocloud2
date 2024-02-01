<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use Exception;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


#[AcceptableMethods(['POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Key extends AbstractApi {


    /**
     * @throws Exception
     */
    public function __construct()
    {
    }

    public function get_index(): array
    {
        return [];
    }

    /**
     * @return array
     * @throws PDOException
     * @OA\Get(
     *   path="/api/v4/key/{table})",
     *   tags={"Key"},
     *   summary="Add primary key(s) to table
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     example="public.my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", description="Success message"),
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="columns", type="object",
     *         @OA\Property(property="num", type="integer", example=1),
     *         @OA\Property(property="full_type", type="string", example="character varying(255)"),
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $columns = $data->columns;
        $trimmed = array_map('trim', $columns);
        $this->table->addPrimaryKey($trimmed);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/key");
        $res["code"] = "201";
        return $res;
    }

    public function put_index(): array
    {
        return [];
    }

    /**
     * @throws PDOException
     */
    public function delete_index(): array
    {
        $this->table->dropPrimaryKey();
        return ["code" => "204"];
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $key = Input::getMethod() != 'post' ? Route2::getParam("key"): null;
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, $key, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
