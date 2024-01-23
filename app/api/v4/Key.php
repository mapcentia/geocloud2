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
use app\models\Table as TableModel;
use Exception;
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
        // TODO: Implement get_index() method.
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
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
        // TODO: Implement put_index() method.
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
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
        $key = Input::getMethod() != 'post';
        $this->jwt = Jwt::validate()["data"];
        $this->check($schema, $table, $key, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
