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
use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 */
abstract class AbstractApi implements ApiInterface
{
    public array $table;
    public ?array $schema;
    public ?array $qualifiedName;
    public ?array $unQualifiedName;
    public ?array $column;
    public ?array $index;
    public ?array $constraint;
    public array $jwt;
    private const array PRIVATE_PROPERTIES = ['num', 'typname', 'full_type', 'character_maximum_length',
        'numeric_precision', 'numeric_scale', 'max_bytes', 'reference', 'restriction', 'is_primary', 'is_unique',
        'index_method', 'checks', 'geom_type', 'srid', 'is_array', 'udt_name'];

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
        $this->schema = $schema ? explode(',', $schema) : null;
        $this->unQualifiedName = $relation ? explode(',', $relation) : null;
        $this->column = $column ? explode(',', $column) : null;
        $this->index = $index ? explode(',', $index) : null;
        $this->constraint = $constraint ? explode(',', $constraint) : null;

        if (!$superUser && !($userName == $this->schema[0] || $this->schema[0] == "public")) {
            throw new GC2Exception("Not authorized", 403, null, "UNAUTHORIZED");
        }
        $this->qualifiedName = $relation ? array_map(fn($r) => $schema . "." . $r, explode(',', $relation)) : null;
        if (!empty($this->schema)) {
            $this->doesSchemaExist();
        }
        if ($this->qualifiedName) {
            $this->doesTableExist();
            $this->table = array_map(fn($n) => new TableModel($n, false, false, false), $this->qualifiedName);
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
        foreach ($this->schema as $name) {
            if (!$db->doesSchemaExist($name)) {
                throw new GC2Exception("Schema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
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
        $indices = $this->table[0]->getIndexes($this->schema[0], $this->unQualifiedName[0])["indices"];
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
     * Validates the existence of specified constraints on a database table or column.
     * Each constraint is checked against the metadata or existing constraints of the table.
     * Throws an exception if any constraint is not found or adheres to invalid properties.
     *
     * @return void
     *
     * @throws GC2Exception If a constraint is not found or validation fails for the specified constraints.
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
                $constraints = $this->table[0]->getConstrains($this->schema[0], $this->unQualifiedName[0], $type)['data'];
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
    public function runPreExtension(string $method, Model $model, ?array $data = []): array
    {
        foreach (glob(dirname(__FILE__) . "/processors/*/classes/pre/*.php") as $filename) {
            $class = "app\\api\\v4\\processors\\" . array_reverse(explode("/", $filename))[3] .
                "\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
            $preProcessor = new $class($this->jwt);
            $data = $preProcessor->{$method}($model, $data);
        }
        return $data;
    }

    public function runPostExtension(string $method, Model $model, ?array $data = []): array
    {
        foreach (glob(dirname(__FILE__) . "/processors/*/classes/post/*.php") as $filename) {
            $class = "app\\api\\v4\\processors\\" . array_reverse(explode("/", $filename))[3] .
                "\\classes\\post\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
            $postProcessor = new $class($this->jwt);
            $data = $postProcessor->{$method}($model, $data);
        }
        return $data;
    }

    /**
     * Validates the request based on the provided collection, data, resource, and method.
     * Ensures that the data is in a valid format and adheres to the defined validation rules.
     * Throws exceptions for invalid data or disallowed operations based on the method and requirements.
     *
     * @param Collection $collection The validation rules or constraints to be applied to the data.
     * @param string|null $data The JSON-encoded payload of the request, or null if no data is provided.
     * @param string $resource The resource being validated within the request.
     * @param string $method The HTTP method used in the request (e.g., GET, POST, PATCH, DELETE).
     * @param bool $allowPatchOnCollection Whether patching on a collection of resources is allowed.
     *
     * @return void
     *
     * @throws GC2Exception If the data is invalid, contains a payload in disallowed methods,
     *                      or violates the provided constraints.
     */
    public function validateRequest(Collection $collection, ?string $data, string $resource, string $method, bool $allowPatchOnCollection = false): void
    {
        if (!empty($data) && !json_validate($data)) {
            throw new GC2Exception("Invalid JSON. Check your request", 400, null, "INVALID_DATA");
        }

        $data = $data == null ? null : json_decode($data, true);

        $data = is_array($data) ? self::removeUnderscoreKeys($data) : $data;

        if (in_array($method, ['delete', 'get']) && !empty($data)) {
            throw new GC2Exception("You can't use a payload in DELETE or GET", 400, null, "INVALID_DATA");
        }

        if (!$allowPatchOnCollection && $method == 'patch' && isset($data[$resource])) {
            throw new GC2Exception("You can't PATCH with a collection of $resource", 400, null, "INVALID_DATA");
        }

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

    static private function removeUnderscoreKeys(array $array): array {
        $filteredArray = [];
        foreach ($array as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, '_')) {
                $filteredArray[$key] = is_array($value) ? self::removeUnderscoreKeys($value) : $value;
            }
        }
        return $filteredArray;
    }

    /**
     * Checks the provided list of constraint violations and throws an exception if any violations are present.
     *
     * @param ConstraintViolationListInterface $list A list of constraint violations to check.
     *
     * @throws GC2Exception Thrown if there are validation errors in the provided list, with details about the violated constraints.
     */
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

    /**
     * Adjusts the given array of properties by prefixing private property keys with an underscore.
     *
     * @param array $properties An array of properties where keys may match private properties.
     * @return array An array with private properties prefixed by an underscore.
     */
    protected static function setPropertiesToPrivate(array $properties): array
    {
        $newArray = [];
        foreach ($properties as $property) {
            $col = [];
            foreach ($property as $key => $value) {
                if (in_array($key, self::PRIVATE_PROPERTIES)) {
                    $col['_' . $key] = $value;
                } else {
                    $col[$key] = $value;
                }
            }
            $newArray[] = $col;
        }
        return $newArray;
    }

    public static function getRpcAssert(): Collection
    {
        return new Assert\Collection([
            'jsonrpc' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\Choice(choices: ['2.0']),
            ]),
            'method' => new Assert\Required(([
                new Assert\NotBlank(),
                new Assert\Type('string'),
            ])),
            'params' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
            ]),
            'id' => new Assert\Optional([
                new Assert\NotBlank(),
            ]),
        ]);
    }
}