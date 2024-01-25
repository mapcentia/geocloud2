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
use app\inc\Route;
use app\inc\Route2;
use app\models\Table as TableModel;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

class Constraint extends AbstractApi
{
    private string|null $constraintType;

    /**
     * @throws PhpfastcacheInvalidArgumentException|Exception
     */
    public function __construct()
    {

    }


    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_unique(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->addUniqueConstraint($column);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_unique(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->dropUniqueConstraint($column);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $constraintType = $data->type;
        switch ($constraintType) {
            case "foreign":
                $this->table->addForeignConstraint($this->column, $data->referenced_table, $data->referenced_column);
                break;
            case "unique":
                $this->table->addUniqueConstraint($this->column);
                break;
            case "notnull":
                $this->table->addNotNullConstraint($this->column);
                break;
            case "check":
                $this->table->addCheckConstraint($this->column, $data->check);
                break;

        }
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/columns/$this->column/constraints/$constraintType");
        $res["code"] = "201";
        return $res;
    }

    public function delete_index(): array
    {
        switch ($this->constraint) {
            case "foreign":
                $this->table->dropForeignConstraint($this->column);
                break;
            case "unique":
                $this->table->dropUniqueConstraint($this->column);
                break;
            case "notnull":
                $this->table->dropNotNullConstraint($this->column);
                break;
            case "check":
                $this->table->dropCheckConstraint($this->column);
                break;

        }
        $res["code"] = "204";
        return $res;
    }

    public function get_index(): array
    {
        // TODO: Implement get_index() method.
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $column = Route2::getParam("column");
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
        $this->initiate($schema, $table, null, $column, null, $constraint, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
