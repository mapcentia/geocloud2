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
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Constraint",
    required: ["constraint", "columns"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name of the constraint",
            description: "Name of the constraint",
            type: "string",
            example: "my-constraint",
        ),
        new OA\Property(
            property: "constraint",
            title: "Type of the constraint",
            description: "Type of the constraint",
            type: "string",
            example: "foreign",
        ),
        new OA\Property(
            property: "columns",
            title: "Columns",
            description: "Columns in constraint",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["c1", "c2", "c3"],
        ),
        new OA\Property(
            property: "referenced_table",
            title: "Referenced table",
            description: "Referenced table in a foreign key constraint",
            type: "string",
            example: "my_schema.my.table",
        ),
        new OA\Property(
            property: "referenced_columns",
            title: "Referenced columns",
            description: "Referenced columns in a foreign key constraint. This will default to the primary key if omitted.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["c1", "c2", "c3"],
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/constraints/{constraint}', operationId: 'getConstraint', description: "Get constraints", tags: ['Constraint'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'constraint', description: 'Constraint name(s)', in: 'path', required: false, example: 'my_constraint')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Constraint"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $r = [];
        $res = self::getConstraints($this->table[0], $this->qualifiedName[0]);
        if (!empty($this->constraint)) {
            foreach ($this->constraint as $constraint) {
                foreach ($res as $c) {
                    if ($c['name'] == $constraint) {
                        $r[] = $c;
                    }
                }
            }
        } else {
            $r = $res;
        }
        if (count($r) > 1) {
            return ["constraints" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/constraints/{constraint}', operationId: 'postConstraint', description: "Get constraints", tags: ['Constraint'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'New constraint', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Constraint"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $list = [];

        $this->table[0]->begin();

        if (isset($data->constraints)) {
            foreach ($data->constraints as $datum) {
                $name = $datum->name;
                $type = $datum->constraint;
                $columns = $datum->columns;
                $check = $datum->check;
                $referencedTable = $datum->referenced_table;
                $referencedColumns = $datum->referenced_columns;
                $list[] = self::addConstraint($this->table[0], $type, $columns, $check, $name, $referencedTable, $referencedColumns);
            }
        } else {
            $name = $data->name;
            $type = $data->constraint;
            $columns = $data->columns;
            $check = $data->check;
            $referencedTable = $data->referenced_table;
            $referencedColumns = $data->referenced_columns;
            $list[] = self::addConstraint($this->table[0], $type, $columns, $check, $name, $referencedTable, $referencedColumns);

        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/$this->schema/tables/{$this->unQualifiedName[0]}/constraints/" . implode(',', $list));
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
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/constraints/{constraint}', operationId: 'deleteConstraint', description: "Get constraint", tags: ['Constraint'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'constraint', description: 'Constraint name(s)', in: 'path', required: true, example: 'my_constraint')]
    #[OA\Response(response: 204, description: 'Column deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): array
    {
        $this->table[0]->begin();
        foreach ($this->constraint as $constraint) {
            $this->table[0]->dropConstraint($constraint);
        }
        $this->table[0]->commit();
        $res["code"] = "204";
        return $res;
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
