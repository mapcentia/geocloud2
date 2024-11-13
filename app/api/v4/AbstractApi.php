<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Model;
use app\models\Database;
use app\models\Table as TableModel;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 *
 */
abstract class AbstractApi implements ApiInterface
{
    // TODO
    public array $table;
    public ?string $schema;
    public ?array $qualifiedName;
    public ?array $unQualifiedName;
    public ?array $column;
    public ?array $index;
    public ?array $constraint;
    public array $jwt;

    abstract public function validate(): void;

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
        $this->schema = $schema;
        $this->unQualifiedName = $relation ? explode(',', $relation) : null;
        $this->column = $column ? explode(',', $column) : null;
        $this->index = $index ? explode(',', $index) : null;
        $this->constraint = $constraint ? explode(',', $constraint) : null;

        if (!$superUser && !($userName == $this->schema || $this->schema == "public")) {
            throw new GC2Exception("Not authorized", 403, null, "UNAUTHORIZED");
        }
        $this->qualifiedName = $relation ? array_map(fn($r) => $schema . "." . $r, explode(',', $relation)) : null;
        if (!empty($this->schema)) {
            $this->doesSchemaExist();
        }
        if ($this->qualifiedName) {
            $this->doesTableExist();
            $this->table = array_map(fn($n) => new TableModel($n, false, false), $this->qualifiedName);
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
        foreach ($this->qualifiedName as $name) {
            if (!$db->doesRelationExist($name)) {
                throw new GC2Exception("Table not found", 404, null, "TABLE_NOT_FOUND");
            }
        }
    }

    /**
     * @throws GC2Exception
     */
    public function doesColumnExist(): void
    {
        foreach ($this->column as $column) {
            if (!isset($this->table[0]->metaData[$column])) {
                throw new GC2Exception("Column not found", 404, null, "COLUMN_NOT_FOUND");
            }
        }
    }

    /**
     * @return void
     * @throws GC2Exception
     */
    public function doesIndexExist(): void
    {
        $indices = $this->table[0]->getIndexes($this->schema, $this->unQualifiedName[0])["indices"];
        foreach ($this->index as $index) {
            foreach ($indices as $i) {
                if ($index === $i["index"]) {
                    continue 2;
                }
            }
            throw new GC2Exception("Index not found", 404, null, "INDEX_NOT_FOUND");
        }
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    private function doesConstraintExist(): void
    {
        foreach ($this->constraint as $constraint) {
            $type = match ($constraint) {
                "primary" => "p",
                "foreign" => "f",
                "unique" => "u",
                "check" => "c",
                "notnull" => "n",
                default => ''
            };
            if ($type != "n") {
                $constraints = $this->table[0]->getConstrains($this->schema, $this->unQualifiedName[0], $type)['data'];
                $exists = false;
                foreach ($constraints as $c) {
                    if ($c['conname'] == $constraint) {
                        $exists = true;
                        break;
                    }
                }
            } else {
                $exists = !$this->table[0]->metaData[$this->column]["is_nullable"];
            }
            if (!$exists) {
                throw new GC2Exception("Constraint not found", 404, null, "CONSTRAINT_NOT_FOUND");
            }
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

    /**
     * Executes the specified method on all pre-processor classes found in the processors directory.
     *
     * @param string $method The method to be executed on each pre-processor class.
     * @param array|null $data The data to be passed to the method of each pre-processor.
     * @return array The modified data after being processed by all pre-processor classes.
     */
    public function runExtension(string $method, Model $model, ?array $data = []): array
    {
        foreach (glob(dirname(__FILE__) . "/processors/*/classes/pre/*.php") as $filename) {
            $class = "app\\api\\v4\\processors\\" . array_reverse(explode("/", $filename))[3] .
                "\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
            $preProcessor = new $class($this->jwt);
            $data = $preProcessor->{$method}($model, $data);
        }
        return $data;
    }

    /**
     * @throws GC2Exception
     */
    public function validateRequest(Collection $collection, string $data, string $resource): void
    {
        if (!json_validate($data)) {
            throw new GC2Exception("Invalid request data", 400, null, "INVALID_DATA");
        }
        $data = json_decode($data, true);
        $validator = Validation::createValidator();

        if (isset($data[$resource]) && is_array($data[$resource])) {
            foreach ($data[$resource] as $datum) {
                $violations = $validator->validate($datum, $collection);
                $this->checkViolations($violations);
            }
        } else {
            $violations = $validator->validate($data, $collection);
            $this->checkViolations($violations);
        }
    }

    private function checkViolations(ConstraintViolationListInterface $list): void
    {
        if (count($list) > 0) {
            $v = [];
            foreach ($list as $violation) {
                $v[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new GC2Exception(implode(' ', $v), 400, null, "INPUT_VALIDATION_ERROR");
        }
    }
}