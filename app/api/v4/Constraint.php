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

#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Constraint extends AbstractApi
{

    /**
     * @throws Exception
     */
    public function __construct()
    {

    }

    /**
     * @return array
     * @OA\Post(
     *   path="/api/v4/schemas/{schema}/tables/{table}/constraints",
     *   tags={"Constraint"},
     *   summary="Create a new constraint",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *   )
     * )
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $name = $data->name;
        $type = $data->constraint;
        $columns = $data->columns;
        $check = $data->check;
        $referencedTable = $data->referenced_table;
        $referencedColumns = $data->referenced_columns;
        $name = self::addConstraint($this->table, $type, $columns, $check, $name, $referencedTable, $referencedColumns);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/constraints/$name");
        $res["code"] = "201";
        return $res;
    }

    public static function addConstraint(TableModel $table, string $type, ?array $columns = null, ?string $check = null, ?string $name = null, ?string $referencedTable = null, ?array $referencedColumns = null): string
    {
        $newName = "";
        switch ($type) {
            case "primary":
                $newName = $table->addPrimaryKeyConstraint($columns, $name);
                break;
            case "foreign":
                $newName = $table->addForeignConstraint($columns, $referencedTable, $referencedColumns, $name);
                break;
            case "unique":
                $newName = $table->addUniqueConstraint($columns, $name);
                break;
            case "check":
                $newName = $table->addCheckConstraint($check, $name);
                break;

        }
        return $newName;
    }

    /**
     * @return array
     * @OA\Delete(
     *   path="/api/v4/schemas/{schema}/tables/{table}/constraints/{constraint}",
     *   tags={"Constraint"},
     *   summary="Drop a constraint",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="constraint",
     *     in="path",
     *     required=true,
     *     description="Name of constraint",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=204,
     *     description="No content",
     *   )
     * )
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $this->table->dropConstraint($this->constraint);
        $res["code"] = "204";
        return $res;
    }

    /**
     * @return array
     * @OA\Get(
     *   path="/api/v4/schemas/{schema}/tables/{table}/constraints/{constraint}",
     *   tags={"Constraint"},
     *   summary="Get constraint(s)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="constraint",
     *     in="path",
     *     required=false,
     *     description="Name of constraint",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *   )
     * )
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        $constraints = self::getConstraints($this->table, $this->qualifiedName);
        if (!empty($this->constraint)) {
            foreach ($constraints as $constraint) {
                if ($constraint['name'] == $this->constraint) {
                    return $constraint;
                }
            }
        }
        return ["constraints" => $constraints];
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getConstraints(TableModel $table, $name): array
    {
        $res = [];
        $res2 = [];
        $split = explode('.', $name);
        $constraints = $table->getConstrains($split[0], $split[1])['data'];
        foreach ($constraints as $constraint) {
            $res[$constraint['conname']]['constraint'] = $constraint['con'];
            $res[$constraint['conname']]['columns'][] = $constraint['column_name'];
        }
        foreach ($res as $key => $value) {
            $con = self::getConstraintType($value["constraint"]);

            $def = [
                "name" => $key,
                "constraint" => $con,
            ];

            switch ($con) {
                case 'unique':
                case 'primary':
                    $def['columns'] = $value['columns'];
                    break;
                case 'check':
                    $def['check'] = self::getCheckText($value['constraint']);
                    break;
                case 'foreign':
                    $def['columns'] = $value['columns'];
                    $def['referenced_table'] = self::getReferencedTable($value['constraint']);
                    $def['referenced_columns'] = self::getReferencedColumns($value['constraint']);
//                    $def['_'] = $value["constraint"];
                    break;
            }

            $res2[] = $def;
        }
        return $res2;
    }

    private static function getConstraintType(string $con): string
    {
        return strtolower(strtok($con, ' '));
    }

    private static function getCheckText(string $con): string
    {
        preg_match('#\((.*?)\)#', $con, $match);
        return $match[1];
    }

    private static function getReferencedTable(string $con): string
    {
        $needle = 'REFERENCES';
        return trim(strtok(substr($con, strpos($con, $needle) + strlen($needle)), '('));
    }

    private static function getReferencedColumns(string $con): array
    {
        preg_match_all('#\((.*?)\)#', $con, $match);
        return array_map('trim', explode(',', $match[1][1]));
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
        return [];
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $constraint = Route2::getParam("constraint");
        // Put and delete on collection is not allowed
        if (empty($constraint) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $constraint) {
            $this->postWithResource();
        }
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, null, null, $constraint, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
