<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\models\Database;
use app\models\Table as TableModel;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use ReflectionClass;

/**
 *
 */
abstract class AbstractApi
{
    public TableModel $table;
    public ?string $qualifiedName;
    public ?string $unQualifiedName;
    public ?string $schema;
    public ?string $column;
    public ?bool $key;
    public ?string $index;
    public ?string $constraint;
    public array $jwt;

    abstract public function validate(): void;

    abstract public function get_index(): array;

    abstract public function post_index(): array;

    abstract public function put_index(): array;

    abstract public function delete_index(): array;


    /**
     * @param string|null $schema
     * @param string|null $relation
     * @param string|null $key
     * @param string|null $column
     * @param string|null $index
     * @param string|null $constraint
     * @param string $userName
     * @param bool $superUser
     * @return void
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function initiate(?string $schema, ?string $relation, ?string $key, ?string $column, ?string $index, ?string $constraint, string $userName, bool $superUser): void
    {
        $this->unQualifiedName = $relation;
        $this->schema = $schema;
        $this->key = $key;
        $this->column = $column;
        $this->index = $index;
        $this->constraint = $constraint;

        if (!$superUser && !($userName == $this->schema || $this->schema == "public")) {
            throw new GC2Exception("Not authorized", 403, null, "UNAUTHORIZED");
        }
        $this->qualifiedName = $relation ? $schema . "." . $relation : null;
        if (!empty($this->schema)) {
            $this->doesSchemaExist();
        }
        if ($this->qualifiedName) {
            $this->doesTableExist();
            $this->table = new TableModel($this->qualifiedName);
        }
        if ($this->key) {
            $this->doesKeyExist();
        }
        if (!empty($this->column)) {
            $this->doesColumnExist();
        }
        if (!empty($this->index)) {
            $this->doesIndexExist();
        }
        if (!empty($this->constraint)) {
            $this->doesConstraintExist();
        }
    }

    /**
     * @throws GC2Exception
     */
    public function doesSchemaExist(): void
    {
        $db = new Database();
        if (!$db->doesSchemaExist($this->schema)) {
            throw new GC2Exception("Schema not found", 404, null, "SCHEMA_NOT_FOUND");
        }
    }

    /**
     * @throws GC2Exception
     */
    public function doesTableExist(): void
    {
        $db = new Database();
        if (!$db->doesRelationExist($this->qualifiedName)) {
            throw new GC2Exception("Table not found", 404, null, "TABLE_NOT_FOUND");
        }
    }

    /**
     * @throws GC2Exception
     */
    public function doesColumnExist(): void
    {
        if (!isset($this->table->metaData[$this->column])) {
            throw new GC2Exception("Column not found", 404, null, "COLUMN_NOT_FOUND");
        }
    }

    /**
     * @return void
     * @throws GC2Exception
     */
    public function doesIndexExist(): void
    {
        $indices = $this->table->getIndexes($this->schema, $this->unQualifiedName)["index_method"][$this->column];
        if (!$indices || !in_array($this->index, $indices)) {
            throw new GC2Exception("Index not found", 404, null, "INDEX_NOT_FOUND");
        }
    }

    /**
     * @throws GC2Exception
     */
    public function doesKeyExist(): void
    {
        $indices = $this->table->getIndexes($this->schema, $this->unQualifiedName)["is_primary"];
        $flag = false;
        foreach ($indices as $col) {
            if ($col) {
                $flag = true;
                break;
            }
        }
        if (!$flag) {
            throw new GC2Exception("Key not found", 404, null, "KEY_NOT_FOUND");
        }
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    private function doesConstraintExist(): void
    {
        $type = match ($this->constraint) {
            "unique" => "u",
            "check" => "c",
            default => ''
        };
        $name = "{$this->unQualifiedName}_{$this->column}_$this->constraint";
        $constraints = $this->table->getConstrains($this->schema, $this->unQualifiedName, $type)['data'];
        $exists = false;
        foreach ($constraints as $constraint) {
            if ($constraint['conname'] == $name) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            throw new GC2Exception("Constraint not found", 404, null, "CONSTRAINT_NOT_FOUND");
        }
    }

    /**
     * @throws GC2Exception
     */
    public function postWithResource(): void
    {
        throw new GC2Exception("POST with resource identifier is not allowed", 406, null, "POST_WITH_RESOURCE_IDENTIFIER");
    }

    /**
     * @return void
     */
    public function setHeaders(): void
    {
        $reflectionClass = new ReflectionClass($this);
        $attributes = $reflectionClass->getAttributes(AcceptableMethods::class);
        foreach ($attributes as $attribute) {
            $listener = $attribute->newInstance();
            if ($listener::class == AcceptableMethods::class) {
                $listener->setHeaders();
            }
        }
    }
}