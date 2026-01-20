<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\api\v4\controllers\Table;
use app\exceptions\GC2Exception;
use app\inc\Model as IncModel;
use app\models\Sql as SqlModel;
use app\models\Table as TableModel;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser as GraphQLParser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\GraphQL as GraphQLBase;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use function Amp\Dns\normalizeName;

class GraphQL
{
    private string $user;
    private string $schema;
    private SqlModel $api;
    private bool $subuser;
    private ?string $userGroup;
    private string $opType;
    private array $typeCache = [];

    function __construct(private readonly Connection $connection, private readonly bool $convertReturning = true)
    {

    }


    /**
     * Very small dispatcher for a constrained subset of GraphQL-like queries.
     *
     * @param string $user
     * @param SqlModel $api
     * @param string $query
     * @param string $schema
     * @param bool $subuser
     * @param string|null $userGroup
     * @param array $variables
     * @param string|null $operationName
     * @return array{data: array|null, errors?: array}
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function run(string $user, SqlModel $api, string $query, string $schema, bool $subuser, ?string $userGroup, array $variables, ?string $operationName = null): array
    {
        $this->user = $user;
        $this->api = $api;
        $this->subuser = $subuser;
        $this->userGroup = $userGroup;
        $this->schema = $schema;

        try {
            $doc = GraphQLParser::parse($query);
        } catch (\Throwable $e) {
            throw new GC2Exception('GraphQL parse error: ' . $e->getMessage(), 400);
        }

        // Select operation
        $operation = null;
        foreach ($doc->definitions as $def) {
            if ($def instanceof OperationDefinitionNode) {
                if ($operationName === null || ($def->name?->value === $operationName)) {
                    $operation = $def;
                    if ($operationName !== null) {
                        break;
                    }
                }
            }
        }
        if (!$operation) {
            throw new GC2Exception('No operation found in document', 400);
        }

        $this->opType = strtolower($operation->operation);
        $opName = $operation->name?->value;
        $isIntrospection = $this->isIntrospectionQuery($operation);

        if (!$isIntrospection) {
            if (!is_string($opName) || $opName === '') {
                throw new GC2Exception('Operation name is required', 400);
            }
            if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $opName)) {
                throw new GC2Exception('Invalid operation name: must start with an uppercase letter and be in PascalCase', 400);
            }
            $selections = $operation->selectionSet?->selections ?? [];
            if (empty($selections)) {
                throw new GC2Exception('No fields selected', 400);
            }
            foreach ($selections as $index => $selectionNode) {
                if (!$selectionNode instanceof FieldNode) {
                    throw new GC2Exception('Unsupported selection at root', 400);
                }
                $field = $selectionNode->name->value;
                if (!preg_match('/^[a-z][A-Za-z0-9]*$/', $field)) {
                    throw new GC2Exception("Invalid root field name '$field': must start with a lowercase letter and be in camelCase", 400);
                }
                if ($index === 0) {
                    $expectedField = lcfirst($opName);
                    if ($field !== $expectedField) {
                        throw new GC2Exception("Operation name '$opName' does not match root field '$field'. Expected '$expectedField'.", 400);
                    }
                }
            }
        }

        $schema = $this->buildSchema();
        $result = GraphQLBase::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: $variables,
            operationName: $operationName
        );

        $output = $result->toArray();
        if (!empty($output['errors'])) {
            throw new GC2Exception($output['errors'][0]['message'], 400);
        }
        return $output;
    }

    private function isIntrospectionQuery(OperationDefinitionNode $operation): bool
    {
        foreach ($operation->selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode && (str_starts_with($selection->name->value, '__'))) {
                return true;
            }
        }
        return false;
    }

    private function buildSchema(): Schema
    {
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => fn() => $this->getQueryFields()
        ]);

        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => fn() => $this->getMutationFields()
        ]);

        return new Schema([
            'query' => $queryType,
            'mutation' => $mutationType
        ]);
    }

    private function getQueryFields(): array
    {
        $fields = [];

        // Add tables from the schema to be visible in introspection
        $tableModel = new TableModel(table: null, connection: $this->connection);
        try {
            $tables = $tableModel->getRecords(false, $this->schema)['data'];
        } catch (\Throwable) {
            $tables = [];
        }
        foreach ($tables as $t) {
            $tableName = $t['f_table_name'];
            $fieldName = 'get' . self::snakeToPascal($tableName);
            $fields[$fieldName] = [
                'type' => Type::listOf($this->getTableType($t['f_table_schema'], $tableName)),
                'args' => [
                    'id' => ['type' => Type::string()],
                    'pk' => ['type' => Type::string()],
                    'key' => ['type' => Type::string()],
                    'where' => ['type' => $this->getGraphQLType('json')],
                    'limit' => ['type' => Type::int()],
                    'offset' => ['type' => Type::int()],
                ],
                'resolve' => function ($root, $args, $context, $info) {
                    return $this->resolveField($info->fieldName, $args, null, $this->selectionTreeFromFieldNode($info->fieldNodes[0], $info->variableValues));
                }
            ];
        }
        return $fields;
    }

    private function getMutationFields(): array
    {
        $fields = [];
        $tableModel = new TableModel(table: null, connection: $this->connection);
        try {
            $tables = $tableModel->getRecords(false, $this->schema)['data'];
        } catch (\Throwable) {
            $tables = [];
        }
        foreach ($tables as $t) {
            $tableName = $t['f_table_name'];
            $pascalName = self::snakeToPascal($tableName);

            $resolver = function ($root, $args, $context, $info) {
                return $this->resolveField($info->fieldName, $args, null, $this->selectionTreeFromFieldNode($info->fieldNodes[0], $info->variableValues));
            };

            $fields['insert' . $pascalName] = [
                'type' => Type::listOf($this->getTableType($t['f_table_schema'], $tableName)),
                'args' => [
                    'data' => ['type' => $this->getGraphQLType('json')],
                    'objects' => ['type' => Type::listOf($this->getGraphQLType('json'))]
                ],
                'resolve' => $resolver
            ];
            $fields['update' . $pascalName] = [
                'type' => Type::listOf($this->getTableType($t['f_table_schema'], $tableName)),
                'args' => [
                    'where' => ['type' => $this->getGraphQLType('json')],
                    'data' => ['type' => $this->getGraphQLType('json')]
                ],
                'resolve' => $resolver
            ];
            $fields['delete' . $pascalName] = [
                'type' => Type::listOf($this->getTableType($t['f_table_schema'], $tableName)),
                'args' => ['where' => ['type' => $this->getGraphQLType('json')]],
                'resolve' => $resolver
            ];
        }
        return $fields;
    }

    private function getTableType(string $schema, string $table): ObjectType
    {
        $typeName = self::snakeToPascal($table);
        if (isset($this->typeCache[$typeName])) {
            return $this->typeCache[$typeName];
        }
        return $this->typeCache[$typeName] = new ObjectType([
            'name' => $typeName,
            'fields' => function () use ($schema, $table) {
                try {
                    $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
                    $fields = [];
                    foreach ($t->metaData ?? [] as $col => $info) {
                        $fields[$col] = ['type' => $this->getGraphQLType($info['type'])];
                    }
                    // Add relationships
                    $fkMap = $this->buildForeignMap($schema, $table);
                    foreach ($fkMap as $relName => $map) {
                        $fieldName = self::snakeToCamel($relName);
                        $fields[$fieldName] = [
                            'type' => $this->getTableType($map['ref_schema'], $map['ref_table']),
                            'args' => [
                                'where' => ['type' => $this->getGraphQLType('json')],
                                'schema' => ['type' => Type::string()],
                            ]
                        ];
                    }
                    return $fields;
                } catch (\Throwable) {
                    return ['error' => ['type' => Type::string()]];
                }
            }
        ]);
    }

    private function getGraphQLType(string $pgType): Type
    {
        $pgType = strtolower(trim($pgType));
        if (str_ends_with($pgType, '[]')) {
            return Type::listOf($this->getGraphQLType(substr($pgType, 0, -2)));
        }

        return match ($pgType) {
            'smallint', 'int2', 'int', 'integer', 'int4', 'serial', 'serial4' => Type::int(),
            'float4', 'real', 'float8', 'double precision' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
            'numeric', 'decimal', 'bigint', 'int8', 'bigserial', 'serial8' => Type::string(),
            'json', 'jsonb' => $this->getTypeFromCache('JSON', fn() => new CustomScalarType([
                'name' => 'JSON',
                'serialize' => fn($v) => $v,
                'parseValue' => fn($v) => $v,
                'parseLiteral' => fn($node, $vars) => $this->valueFromAst($node, $vars ?? []),
            ])),
            'point' => $this->getTypeFromCache('Point', fn() => new ObjectType([
                'name' => 'Point',
                'fields' => [
                    'x' => ['type' => Type::float()],
                    'y' => ['type' => Type::float()],
                ]
            ])),
            'line' => $this->getTypeFromCache('Line', fn() => new ObjectType([
                'name' => 'Line',
                'fields' => [
                    'A' => ['type' => Type::float()],
                    'B' => ['type' => Type::float()],
                    'C' => ['type' => Type::float()],
                ]
            ])),
            'lseg' => $this->getTypeFromCache('Lseg', fn() => new ObjectType([
                'name' => 'Lseg',
                'fields' => [
                    'start' => ['type' => $this->getGraphQLType('point')],
                    'end' => ['type' => $this->getGraphQLType('point')],
                ]
            ])),
            'box' => $this->getTypeFromCache('Box', fn() => new ObjectType([
                'name' => 'Box',
                'fields' => [
                    'start' => ['type' => $this->getGraphQLType('point')],
                    'end' => ['type' => $this->getGraphQLType('point')],
                ]
            ])),
            'circle' => $this->getTypeFromCache('Circle', fn() => new ObjectType([
                'name' => 'Circle',
                'fields' => [
                    'center' => ['type' => $this->getGraphQLType('point')],
                    'radius' => ['type' => Type::float()],
                ]
            ])),
            'interval' => $this->getTypeFromCache('Interval', fn() => new ObjectType([
                'name' => 'Interval',
                'fields' => [
                    'y' => ['type' => Type::int()],
                    'm' => ['type' => Type::int()],
                    'd' => ['type' => Type::int()],
                    'h' => ['type' => Type::int()],
                    'i' => ['type' => Type::int()],
                    's' => ['type' => Type::int()],
                ]
            ])),
            'path' => $this->getJsonType(),
            'polygon' => Type::listOf($this->getGraphQLType('point')),
            'int4range', 'int8range', 'numrange', 'tsrange', 'tstzrange', 'daterange' => $this->getRangeType($pgType),
            default => Type::string(),
        };
    }

    private function getRangeType(string $pgType): Type
    {
        $name = self::snakeToPascal($pgType);
        return $this->getTypeFromCache($name, function () use ($name, $pgType) {
            $boundType = str_contains($pgType, 'int') ? Type::int() : Type::string();
            return new ObjectType([
                'name' => $name,
                'fields' => [
                    'lower' => ['type' => $boundType],
                    'upper' => ['type' => $boundType],
                    'lowerInclusive' => ['type' => Type::boolean()],
                    'upperInclusive' => ['type' => Type::boolean()],
                ]
            ]);
        });
    }

    private function getTypeFromCache(string $name, callable $creator): Type
    {
        if (!isset($this->typeCache[$name])) {
            $this->typeCache[$name] = $creator();
        }
        return $this->typeCache[$name];
    }

    public static function snakeToPascal(string $input): string
    {
        return str_replace('_', '', ucwords($input, '_'));
    }

    public static function snakeToCamel(string $input): string
    {
        return lcfirst(self::snakeToPascal($input));
    }

    /**
     * Resolve variable values from input and operation definitions (defaults).
     */
    private function resolveVariables(OperationDefinitionNode $operation, array $inputVariables): array
    {
        $resolved = [];
        foreach ($operation->variableDefinitions ?? [] as $def) {
            $name = $def->variable->name->value;
            if (array_key_exists($name, $inputVariables)) {
                $resolved[$name] = $inputVariables[$name];
            } elseif ($def->defaultValue !== null) {
                $resolved[$name] = $this->valueFromAst($def->defaultValue, []);
            }
        }
        return array_merge($inputVariables, $resolved);
    }

    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private static function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function partitionSelection(?array $selection): array
    {
        // returns [columns[alias => name], nestedMap[alias => ['name' => name, 'selection' => selection]]]
        $columns = [];
        $nested = [];
        if (is_array($selection)) {
            foreach ($selection as $alias => $v) {
                if ($v['selection'] === true) {
                    $columns[$alias] = $v['name'];
                } elseif (is_array($v['selection'])) {
                    $nested[$alias] = $v;
                }
            }
        }
        return [$columns, $nested];
    }

    private function buildForeignMap(string $schema, string $table): array
    {
        // Key by referenced table name and by constraint name for convenience
        $model = new IncModel(connection: $this->connection);
        $res = $model->getConstrains($schema, $table, 'f');
        $map = [];
        if (($res['success'] ?? false) && is_array($res['data'] ?? null)) {
            foreach ($res['data'] as $row) {
                $conDef = (string)($row['con'] ?? '');
                $conname = (string)($row['conname'] ?? '');
                $localCol = (string)($row['column_name'] ?? '');
                if ($conDef === '' || $localCol === '') {
                    continue;
                }
                $parsed = $this->parseFkConstraintDef($conDef, $schema);
                if (!$parsed) {
                    continue;
                }
                $key = $parsed['ref_table'];
                $entry = [
                    'local_col' => $localCol,
                    'ref_schema' => $parsed['ref_schema'],
                    'ref_table' => $parsed['ref_table'],
                    'ref_col' => $parsed['ref_col'],
                    'constraint' => $conname,
                ];
                // Prefer to not overwrite an existing entry if same key; but last one wins in case of duplicates
                $map[$key] = $entry;
                if ($conname !== '') {
                    $map[$conname] = $entry;
                }
            }
        }
        return $map;
    }

    private function parseFkConstraintDef(string $def, string $defaultSchema): ?array
    {
        // Example: FOREIGN KEY (child_id) REFERENCES public.parent(id) MATCH SIMPLE ...
        $m = [];
        if (!preg_match('/REFERENCES\s+([^\s(]+)\s*\(([^)]+)\)/i', $def, $m)) {
            return null;
        }
        $targetIdent = trim($m[1]);
        $refCol = trim($m[2]);
        $refCol = trim($refCol, ' "');
        // Normalize identifier, may be schema.table or just table
        $targetIdent = trim($targetIdent, ' "');
        $refSchema = $defaultSchema;
        $refTable = $targetIdent;
        if (str_contains($targetIdent, '.')) {
            $parts = explode('.', $targetIdent, 2);
            $refSchema = trim($parts[0], ' "');
            $refTable = trim($parts[1], ' "');
        }
        return [
            'ref_schema' => $refSchema,
            'ref_table' => $refTable,
            'ref_col' => $refCol,
        ];
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function fetchRelatedSingle(string $schema, string $table, string $column, mixed $value, ?array $selection, array $args = [], int $depth = 0): ?array
    {
        if ($value === null) {
            return null;
        }
        if ($depth > 5) {
            return null;
        }

        // Schema can be overridden via arguments
        $schema = $args['schema'] ?? $schema;

        [$selColumns, $nested] = $this->partitionSelection($selection);

        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        $metaCols = array_keys($t->metaData ?? []);
        if (empty($metaCols)) {
            throw new GC2Exception('Unable to read table metadata', 500);
        }

        $columns = !empty($selColumns) ? $selColumns : $metaCols;

        // If nested deeper, include required local FK columns of the referenced table
        $autoFkCols = [];
        $fkMap = [];
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
            foreach ($nested as $alias => $v) {
                $relName = $v['name'];
                if (!isset($fkMap[$relName])) {
                    throw new GC2Exception("Unknown nested relation '$relName' on {$schema}.{$table}", 400);
                }
                $localCol = $fkMap[$relName]['local_col'];
                if (!in_array($localCol, $columns, true)) {
                    $columns[] = $localCol;
                    $autoFkCols[$localCol] = true;
                }
            }
        }

        // Build SQL
        $colsSql = implode(', ', array_map(self::quoteIdent(...), array_unique($columns)));
        $qualified = self::quoteIdent($schema) . '.' . self::quoteIdent($table);

        $params = ['v' => $value];
        $whereSql = '';
        if (!empty($args['where']) && is_array($args['where'])) {
            $extraWhere = $this->buildWhere($args['where'], $metaCols, $params);
            if ($extraWhere !== '') {
                $whereSql = ' AND (' . $extraWhere . ')';
            }
        }

        $sql = 'SELECT ' . $colsSql . ' FROM ' . $qualified . ' WHERE ' . self::quoteIdent($column) . ' = :v' . $whereSql . ' LIMIT 1';

        $query['q'] = $sql;
        $query['params'] = $params;
        $query['convert_types'] = true;
        $query['format'] = 'json';
        $query['id'] = uniqid();

        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);

        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
        if (!is_array($row)) {
            return null;
        }

        // Map columns to names
        $mappedRow = [];
        foreach ($selColumns as $alias => $name) {
            $mappedRow[$name] = $row[$name] ?? null;
        }

        // Attach deeper nested
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
            foreach ($nested as $alias => $v) {
                $relName = $v['name'];
                $snakeRelName = self::camelToSnake($relName);
                if (!isset($fkMap[$snakeRelName])) {
                    continue;
                }
                $subSel = $v['selection'];
                $map = $fkMap[$snakeRelName];
                $localVal = $row[$map['local_col']] ?? null;
                $mappedRow[$relName] = $this->fetchRelatedSingle(
                    schema: $map['ref_schema'],
                    table: $map['ref_table'],
                    column: $map['ref_col'],
                    value: $localVal,
                    selection: is_array($subSel) ? $subSel : null,
                    args: $v['args'] ?? [],
                    depth: $depth + 1
                );
            }
        }

        return $mappedRow;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    protected function resolveField(string $field, array $args, mixed $positional = null, ?array $selection = null): mixed
    {
        if ($this->opType === 'mutation') {
            return $this->resolveMutation($field, $args, $selection);
        }
        return $this->resolveDynamicTable($field, $args, $positional, $selection);
    }

    /**
     * Dynamic table resolver: supports both list and single-row queries.
     * If an ID-like argument (id, pk, or key) is provided, it returns a single object.
     * Otherwise, it returns a list based on args schema, where, limit, offset.
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function resolveDynamicTable(string $tableField, array $args, mixed $positional, ?array $selection): mixed
    {
        $schema = $this->schema;

        if (str_starts_with($tableField, 'get') && strlen($tableField) > 3 && ctype_upper($tableField[3])) {
            $tableField = substr($tableField, 3);
        }
        $table = self::camelToSnake($tableField);

        // Setup table metadata and columns
        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        $metaCols = array_keys($t->metaData ?? []);
        if (empty($metaCols)) {
            throw new GC2Exception('Unable to read table metadata', 500);
        }

        // Determine if this is a single-row fetch by ID
        $idVal = $positional;
        if ($idVal === null) {
            $idVal = $args['id'] ?? ($args['pk'] ?? ($args['key'] ?? null));
        }

        $query['convert_types'] = true;
        $query['format'] = 'json';

        $statement = new Statement(connection: $this->connection, convertReturning: true);


        if ($idVal !== null) {
            if (!method_exists($t, 'hasPrimeryKey') || !$t->hasPrimeryKey($schema . '.' . $table)) {
                throw new GC2Exception('Table has no primary key; cannot use ID query', 400);
            }
            $pk = $t->primaryKey['attname'] ?? null;
            if (!$pk) {
                throw new GC2Exception('Primary key not found', 400);
            }

            // Partition selection into columns and nested fields
            [$selColumns, $nested] = $this->partitionSelection($selection);
            $columns = array_values($selColumns);
            if (!empty($columns)) {
                foreach ($columns as $c) {
                    if (!in_array($c, $metaCols, true)) {
                        throw new GC2Exception("Unknown column '$c' on {$schema}.{$table}", 400);
                    }
                }
            } else {
                $columns = $metaCols;
                foreach ($metaCols as $c) {
                    $selColumns[$c] = $c;
                }
            }

            // Include PK always to allow potential deeper joins
            if (!in_array($pk, $columns, true)) {
                $columns[] = $pk;
            }

            // Build FK map if nested requested and ensure local fk columns
            $fkMap = [];
            if (!empty($nested)) {
                $fkMap = $this->buildForeignMap($schema, $table);
                foreach ($nested as $alias => $v) {
                    $relName = $v['name'];
                    if (!isset($fkMap[$relName])) {
                        throw new GC2Exception("Unknown nested relation '$relName' on {$schema}.{$table}", 400);
                    }
                    $localCol = $fkMap[$relName]['local_col'];
                    if (!in_array($localCol, $columns, true)) {
                        $columns[] = $localCol;
                    }
                }
            }

            $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', array_unique($columns));
            $selectList = implode(', ', $colsSqlParts);
            $qualified = '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"';

            $params = ['pk' => $idVal];
            $whereSql = '';
            $where = $args['where'] ?? [];
            if (is_array($where) && !empty($where)) {
                $extraWhere = $this->buildWhere($where, $metaCols, $params);
                if ($extraWhere !== '') {
                    $whereSql = ' AND (' . $extraWhere . ')';
                }
            }

            $sql = 'SELECT ' . $selectList . ' FROM ' . $qualified . ' WHERE ' . '"' . str_replace('"', '""', $pk) . '" = :pk' . $whereSql . ' LIMIT 1';

            $query['q'] = $sql;
            $query['params'] = $params;
            $query['id'] = uniqid();

            $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);
            if (isset($res['success']) && $res['success'] === false) {
                throw new GC2Exception($res['message'] ?? 'Unknown SQL error', 400);
            }
            $rows = $res['data'] ?? ($res['rows'] ?? $res);
            $rows = is_array($rows) ? $rows : [];

            $mappedRows = $this->mapRows($rows, $selection, $schema, $table);
            return $mappedRows;
        }

        // --- List query logic ---
        $qualified = '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"';

        // Partition selection into columns and nested fields
        [$selColumns, $nested] = $this->partitionSelection($selection);

        $columns = array_values($selColumns);
        if (!empty($columns)) {
            foreach ($columns as $c) {
                if (!in_array($c, $metaCols, true)) {
                    throw new GC2Exception("Unknown column '$c' on {$schema}.{$table}", 400);
                }
            }
        } else {
            $columns = $metaCols; // default to all when no explicit scalar selection
            // If we default to all columns, we need to populate selColumns so they can be mapped later
            foreach ($metaCols as $c) {
                $selColumns[$c] = $c;
            }
        }

        // Build FK map if nested requested
        $fkMap = [];
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
            // Ensure local FK columns are present for each nested relation
            foreach ($nested as $alias => $v) {
                $relName = $v['name'];
                if (!isset($fkMap[$relName])) {
                    throw new GC2Exception("Unknown nested relation '$relName' on {$schema}.{$table}", 400);
                }
                $localCol = $fkMap[$relName]['local_col'];
                if (!in_array($localCol, $columns, true)) {
                    $columns[] = $localCol;
                }
            }
        }

        $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', array_unique($columns));
        $selectList = implode(', ', $colsSqlParts);

        // WHERE clause
        $params = [];
        $where = $args['where'] ?? [];
        if (!is_array($where)) {
            $where = [];
        }
        $whereSql = $this->buildWhere($where, $metaCols, $params);
        if ($whereSql !== '') {
            $whereSql = ' WHERE ' . $whereSql;
        }

        $limit = isset($args['limit']) && is_numeric($args['limit']) ? (int)$args['limit'] : 100;
        $offset = isset($args['offset']) && is_numeric($args['offset']) ? (int)$args['offset'] : null;

        $sql = 'SELECT ' . $selectList . ' FROM ' . $qualified . $whereSql . ' LIMIT ' . max(0, $limit);
        if ($offset !== null) {
            $sql .= ' OFFSET ' . max(0, $offset);
        }


        $query['q'] = $sql;
        $query['params'] = $params;
        $query['id'] = uniqid();

        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);
        if (isset($res['success']) && $res['success'] === false) {
            throw new GC2Exception($res['message'] ?? 'Unknown SQL error', 400);
        }
        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $rows = is_array($rows) ? $rows : [];

        return $this->mapRows($rows, $selection, $schema, $table);
    }


    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function resolveMutation(string $field, array $args, ?array $selection): mixed
    {
        if (str_starts_with($field, 'insert')) {
            $table = self::camelToSnake(substr($field, 6));
            if ($table === '') {
                throw new GC2Exception("Mutation '$field' must be followed by a table name", 400);
            }
            return $this->handleInsert($table, $args, $selection);
        }
        if (str_starts_with($field, 'update')) {
            $table = self::camelToSnake(substr($field, 6));
            if ($table === '') {
                throw new GC2Exception("Mutation '$field' must be followed by a table name", 400);
            }
            return $this->handleUpdate($table, $args, $selection);
        }
        if (str_starts_with($field, 'delete')) {
            $table = self::camelToSnake(substr($field, 6));
            if ($table === '') {
                throw new GC2Exception("Mutation '$field' must be followed by a table name", 400);
            }
            return $this->handleDelete($table, $args, $selection);
        }
        throw new GC2Exception("Unknown mutation '$field'", 400);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function handleInsert(string $table, array $args, ?array $selection): mixed
    {
        $schema = $this->schema;
        $objects = $args['objects'] ?? [];
        $isSingle = false;
        if (isset($args['object'])) {
            $objects = [$args['object']];
            $isSingle = true;
        }
        if (isset($args['data'])) {
            $objects = [$args['data']];
            $isSingle = false; // We want to return a list even for single insert now
        }

        if (empty($objects)) {
            throw new GC2Exception("No objects to insert", 400);
        }

        $columns = array_keys($objects[0]);
        $quotedTable = self::quoteIdent($schema) . '.' . self::quoteIdent($table);
        $quotedCols = array_map(self::quoteIdent(...), $columns);

        $sql = "INSERT INTO $quotedTable (" . implode(', ', $quotedCols) . ") VALUES ";

        $params = [];
        $valuesRows = [];
        foreach ($objects as $rowIndex => $obj) {
            $rowValues = [];
            foreach ($columns as $col) {
                $p = "p{$rowIndex}_{$col}";
                $params[$p] = $obj[$col] ?? null;
                $rowValues[] = ":$p";
            }
            $valuesRows[] = "(" . implode(', ', $rowValues) . ")";
        }
        $sql .= implode(', ', $valuesRows) . " RETURNING *";

        $query = $this->buildQueryArray($sql, $params, $schema, $table);

        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);

        return $this->mapMutationResult($res['returning']['data'] ?? [], $selection, $isSingle, $schema, $table);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function handleUpdate(string $table, array $args, ?array $selection): array
    {
        $schema = $this->schema;
        $where = $args['where'] ?? [];
        $set = $args['data'] ?? ($args['_set'] ?? ($args['set'] ?? []));

        if (empty($set)) {
            throw new GC2Exception("No columns to update", 400);
        }

        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        $metaCols = array_keys($t->metaData ?? []);

        $params = [];
        $setClauses = [];
        foreach ($set as $col => $val) {
            $p = 's_' . $col;
            $setClauses[] = self::quoteIdent($col) . " = :$p";
            $params[$p] = $val;
        }

        $whereSql = $this->buildWhere($where, $metaCols, $params);
        if ($whereSql === '') {
            throw new GC2Exception("Update requires a where clause", 400);
        }

        $quotedTable = self::quoteIdent($schema) . '.' . self::quoteIdent($table);
        $sql = "UPDATE $quotedTable SET " . implode(', ', $setClauses) . " WHERE $whereSql RETURNING *";

        $query = $this->buildQueryArray($sql, $params, $schema, $table);

        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);

        return $this->mapMutationResult($res['returning']['data'] ?? [], $selection, false, $schema, $table);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function handleDelete(string $table, array $args, ?array $selection): array
    {
        $schema = $this->schema;
        $where = $args['where'] ?? [];

        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        $metaCols = array_keys($t->metaData ?? []);

        $params = [];
        $whereSql = $this->buildWhere($where, $metaCols, $params);
        if ($whereSql === '') {
            throw new GC2Exception("Delete requires a where clause", 400);
        }

        $quotedTable = self::quoteIdent($schema) . '.' . self::quoteIdent($table);
        $sql = "DELETE FROM $quotedTable WHERE $whereSql RETURNING *";

        $query = $this->buildQueryArray($sql, $params, $schema, $table);

        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);
        return $this->mapMutationResult($res['returning']['data'] ?? [], $selection, false, $schema, $table);
    }

    private function executeQuery(string $sql, array $params): array
    {
        $query = [
            'q' => $sql,
            'params' => $params,
            'format' => 'json',
            'convert_types' => true,
            'id' => uniqid()
        ];
        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $res = $statement->run(user: $this->user, api: $this->api, query: $query, subuser: $this->subuser, userGroup: $this->userGroup);
        return $res['data'] ?? ($res['rows'] ?? $res);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function mapMutationResult(array $rows, ?array $selection, bool $isSingle, string $schema, string $table): mixed
    {
        return $this->mapRows($rows, $selection, $schema, $table);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function mapRows(array $rows, ?array $selection, string $schema, string $table): array
    {
        [$selColumns, $nested] = $this->partitionSelection($selection);
        if (empty($selColumns) && empty($nested) && !empty($rows)) {
            $allCols = array_keys($rows[0]);
            $selColumns = array_combine($allCols, $allCols);
        }

        $fkMap = [];
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
        }

        foreach ($rows as &$row) {
            $mappedRow = [];
            foreach ($selColumns as $alias => $name) {
                $mappedRow[$name] = $row[$name] ?? null;
            }
            if (!empty($nested)) {
                foreach ($nested as $alias => $v) {
                    $relName = $v['name'];
                    $snakeRelName = self::camelToSnake($relName);
                    if (!isset($fkMap[$snakeRelName])) {
                        throw new GC2Exception("Unknown nested relation '$relName' on {$schema}.{$table}", 400);
                    }
                    $subSel = $v['selection'];
                    $map = $fkMap[$snakeRelName];
                    $localVal = $row[$map['local_col']] ?? null;
                    $mappedRow[$relName] = $this->fetchRelatedSingle(
                        schema: $map['ref_schema'],
                        table: $map['ref_table'],
                        column: $map['ref_col'],
                        value: $localVal,
                        selection: is_array($subSel) ? $subSel : null,
                        args: $v['args'] ?? []
                    );
                }
            }
            $row = $mappedRow;
        }
        return $rows;
    }

    /**
     * Convert AST value into PHP value, using provided variables.
     */
    private function valueFromAst(ValueNode $node, array $variables): mixed
    {
        if ($node instanceof VariableNode) {
            $name = $node->name->value;
            return $variables[$name] ?? null;
        }
        if ($node instanceof StringValueNode) {
            return $node->value;
        }
        if ($node instanceof IntValueNode) {
            return (int)$node->value;
        }
        if ($node instanceof FloatValueNode) {
            return (float)$node->value;
        }
        if ($node instanceof BooleanValueNode) {
            return (bool)$node->value;
        }
        if ($node instanceof NullValueNode) {
            return null;
        }
        if ($node instanceof ListValueNode) {
            $arr = [];
            foreach ($node->values as $v) {
                $arr[] = $this->valueFromAst($v, $variables);
            }
            return $arr;
        }
        if ($node instanceof ObjectValueNode) {
            $obj = [];
            foreach ($node->fields as $f) {
                $obj[$f->name->value] = $this->valueFromAst($f->value, $variables);
            }
            return $obj;
        }
        return null;
    }

    /**
     * Build a recursive WHERE clause for operators and logical AND/OR/NOT.
     * @throws GC2Exception
     */
    private function buildWhere(array $where, array $metaCols, array &$params, string $conjunction = 'AND'): string
    {
        $clauses = [];
        foreach ($where as $key => $value) {
            // Logical operators
            if (strcasecmp($key, 'AND') === 0) {
                if (is_array($value)) {
                    $subClauses = [];
                    foreach ($value as $v) {
                        if (is_array($v)) {
                            $sub = $this->buildWhere($v, $metaCols, $params, 'AND');
                            if ($sub !== '') {
                                $subClauses[] = $sub;
                            }
                        }
                    }
                    if (!empty($subClauses)) {
                        $clauses[] = '(' . implode(' AND ', $subClauses) . ')';
                    }
                }
                continue;
            }
            if (strcasecmp($key, 'OR') === 0) {
                if (is_array($value)) {
                    $subClauses = [];
                    foreach ($value as $v) {
                        if (is_array($v)) {
                            $sub = $this->buildWhere($v, $metaCols, $params, 'AND');
                            if ($sub !== '') {
                                $subClauses[] = $sub;
                            }
                        }
                    }
                    if (!empty($subClauses)) {
                        $clauses[] = '(' . implode(' OR ', $subClauses) . ')';
                    }
                }
                continue;
            }
            if (strcasecmp($key, 'NOT') === 0) {
                if (is_array($value)) {
                    $sub = $this->buildWhere($value, $metaCols, $params, 'AND');
                    if ($sub !== '') {
                        $clauses[] = 'NOT (' . $sub . ')';
                    }
                }
                continue;
            }

            // Column names
            if (!in_array($key, $metaCols, true)) {
                throw new GC2Exception("Unknown column '$key'", 400);
            }

            $quotedCol = self::quoteIdent($key);

            if (is_array($value) && !empty($value) && $this->isOperatorArray($value)) {
                foreach ($value as $op => $opVal) {
                    $p = 'p' . count($params);
                    switch (strtolower($op)) {
                        case 'eq':
                            $clauses[] = "$quotedCol = :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'gt':
                            $clauses[] = "$quotedCol > :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'lt':
                            $clauses[] = "$quotedCol < :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'gte':
                            $clauses[] = "$quotedCol >= :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'lte':
                            $clauses[] = "$quotedCol <= :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'neq':
                            $clauses[] = "$quotedCol != :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'in':
                            if (!is_array($opVal)) {
                                throw new GC2Exception("Operator 'in' requires an array value", 400);
                            }
                            if (empty($opVal)) {
                                $clauses[] = '1=0';
                            } else {
                                $inParams = [];
                                foreach ($opVal as $i => $v) {
                                    $ip = $p . '_' . $i;
                                    $inParams[] = ":$ip";
                                    $params[$ip] = $v;
                                }
                                $clauses[] = "$quotedCol IN (" . implode(', ', $inParams) . ")";
                            }
                            break;
                        case 'like':
                            $clauses[] = "$quotedCol LIKE :$p";
                            $params[$p] = $opVal;
                            break;
                        case 'ilike':
                            $clauses[] = "$quotedCol ILIKE :$p";
                            $params[$p] = $opVal;
                            break;
                        default:
                            throw new GC2Exception("Unknown operator '$op' for column '$key'", 400);
                    }
                }
            } else {
                // Backward compatibility: simple equality
                $p = 'p' . count($params);
                $clauses[] = "$quotedCol = :$p";
                $params[$p] = $value;
            }
        }

        return !empty($clauses) ? implode(" $conjunction ", $clauses) : '';
    }

    /**
     * Check if an array contains any of the supported operators.
     */
    private function isOperatorArray(array $arr): bool
    {
        $operators = ['eq', 'gt', 'lt', 'gte', 'lte', 'neq', 'in', 'like', 'ilike'];
        return array_any($arr, fn($v, $k) => is_string($k) && in_array(strtolower($k), $operators, true));
    }

    /**
     * Build a selection tree map from AST FieldNode similar to previous parser output.
     * Returns [fieldName => ['name' => originalName, 'args' => [...], 'selection' => true|subtree]]
     */
    private function selectionTreeFromFieldNode(FieldNode $field, array $variables): array
    {
        $tree = [];
        $selSet = $field->selectionSet?->selections ?? [];
        foreach ($selSet as $sel) {
            if ($sel instanceof FieldNode) {
                $name = $sel->name->value;
                $alias = $sel->alias?->value ?? $name;

                $args = [];
                foreach ($sel->arguments ?? [] as $arg) {
                    $args[$arg->name->value] = $this->valueFromAst($arg->value, $variables);
                }

                $child = $this->selectionTreeFromFieldNode($sel, $variables);
                $tree[$alias] = [
                    'name' => $name,
                    'args' => $args,
                    'selection' => empty($child) ? true : $child
                ];
            }
        }
        return $tree;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function getTypeHints(string $schema, string $table, array $params): array
    {
        $meta = $this->api->getMetaData(table: $schema . '.' . $table, restriction: false, getEnums: false, lookupForeignTables: false );
        $arr = [];
        foreach ($params as $p => $v) {
            $arr2 = explode('_', $p);
            array_shift($arr2);
            $normalizedCol = implode('_', $arr2);
            $arr[$p] = $meta[$normalizedCol]['type'];
        }
        return $arr;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function buildQueryArray(string $sql, array $params, string $schema, string $table): array
    {
        return      [
            'q' => $sql,
            'params' => $params,
            'format' => 'json',
            'convert_types' => true,
            'id' => uniqid(),
            'type_hints' => $this->getTypeHints($schema, $table, $params)
        ];
    }
}