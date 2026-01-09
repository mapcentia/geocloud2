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
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

final class GraphQL
{
    function __construct(private readonly Connection $connection, private readonly bool $convertReturning = true)
    {

    }


    /**
     * Very small dispatcher for a constrained subset of GraphQL-like queries.
     *
     * @param string $query
     * @param array $variables
     * @param string|null $operationName
     * @return array{data: array|null, errors?: array}
     * @throws GC2Exception
     */
    public function executeQuery(string $query, array $variables, ?string $operationName = null): array
    {
        try {
            $doc = GraphQLParser::parse($query);
        } catch (\Throwable $e) {
            throw new GC2Exception('GraphQL parse error: ' . $e->getMessage(), 400);
        }

        // Select operation (only queries supported)
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
        $opType = $operation->operation;
        $opName = $operation->name?->value;
        if (!is_string($opName) || $opName === '') {
            // Preserve previous behavior: require explicit operation name
            throw new GC2Exception('Operation name is required', 400);
        }
        if (strtolower($opType) !== 'query') {
            throw new GC2Exception('Only query operations are allowed', 400);
        }
        $selections = $operation->selectionSet?->selections ?? [];
        if (empty($selections)) {
            throw new GC2Exception('No fields selected', 400);
        }
        $firstSel = $selections[0];
        if (!$firstSel instanceof FieldNode) {
            throw new GC2Exception('Unsupported selection at root', 400);
        }
        $field = $firstSel->name->value;
        $alias = $firstSel->alias?->value ?? $field;

        // Build args array from AST
        $args = [];
        foreach ($firstSel->arguments ?? [] as $arg) {
            $args[$arg->name->value] = $this->valueFromAst($arg->value, $variables);
        }

        // Build selection tree for nested structure
        $selection = $this->selectionTreeFromFieldNode($firstSel);

        // Maintain legacy constraint: operation name must match the root field (optionally with ById)
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*(ById)?$/', $opName)) {
            throw new GC2Exception('Invalid operation name: must start with an uppercase letter', 400);
        }
        $isById = false;
        if (str_ends_with($opName, 'ById')) {
            $isById = true;
            $base = substr($opName, 0, -4);
        } else {
            $base = $opName;
        }
        $expectedField = strtolower($base);
        $matchesBase = strcasecmp($field, $expectedField) === 0;
        $matchesByIdField = strcasecmp($field, $expectedField . 'ById') === 0;
        if ($isById) {
            if (!($matchesBase || $matchesByIdField)) {
                throw new GC2Exception("Operation name '$opName' does not match root field '$field'", 400);
            }
            $effectiveField = $expectedField . 'ById';
        } else {
            if (!$matchesBase) {
                throw new GC2Exception("Operation name '$opName' does not match root field '$field'", 400);
            }
            $effectiveField = $expectedField;
        }

        // Note: positional arguments are not part of the GraphQL spec; only named args are supported now.
        $positional = null;

        try {
            $value = $this->resolveField($effectiveField, $args, $positional, $selection);
            return ['data' => [$alias => $value]];
        } catch (GC2Exception $e) {
            throw new GC2Exception($e->getMessage(), $e->getCode());
        }
    }

    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
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

    private function fetchRelatedSingle(string $schema, string $table, string $column, mixed $value, ?array $selection, int $depth = 0): ?array
    {
        if ($value === null) {
            return null;
        }
        if ($depth > 5) {
            return null;
        }

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
        $sql = 'SELECT ' . $colsSql . ' FROM ' . $qualified . ' WHERE ' . self::quoteIdent($column) . ' = :v LIMIT 1';
        $sqlModel = new SqlModel(connection: $this->connection);
        $res = $sqlModel->sql(q: $sql, format: 'json', convertTypes: true, parameters: ['v' => $value]);
        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
        if (!is_array($row)) {
            return null;
        }

        // Map columns to aliases
        $mappedRow = [];
        foreach ($selColumns as $alias => $name) {
            $mappedRow[$alias] = $row[$name] ?? null;
        }

        // Attach deeper nested
        if (!empty($nested)) {
            foreach ($nested as $alias => $v) {
                $relName = $v['name'];
                $subSel = $v['selection'];
                $map = $fkMap[$relName];
                $localVal = $row[$map['local_col']] ?? null;
                $mappedRow[$alias] = $this->fetchRelatedSingle(
                    schema: $map['ref_schema'],
                    table: $map['ref_table'],
                    column: $map['ref_col'],
                    value: $localVal,
                    selection: is_array($subSel) ? $subSel : null,
                    depth: $depth + 1
                );
            }
        }

        return $mappedRow;
    }

    /**
     * @throws GC2Exception
     */
    private function resolveField(string $field, array $args, mixed $positional = null, ?array $selection = null): mixed
    {
        // Special-case dynamic single-row fields named like <table>ById
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*ById$/', $field)) {
            return $this->resolveDynamicTableById($field, $args, $positional, $selection);
        }
        return $this->resolveDynamicTable($field, $args, $positional, $selection);
    }

    /**
     * Dynamic table resolver (list query): supports args schema, where, limit, offset.
     * Use <table>ById(...) to fetch a single row by primary key.
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function resolveDynamicTable(string $tableField, array $args, mixed $positional, ?array $selection): mixed
    {
        $schema = $args['schema'] ?? 'public';
        if (!is_string($schema) || $schema === '') {
            $schema = 'public';
        }
        $table = $tableField; // field name is table
        // AuthZ
       // $this->initiate(schema: $schema, relation: $table);

        if ($positional !== null) {
            throw new GC2Exception("Positional argument not supported for '$table'. Use '{$table}ById(...)' to fetch by primary key.", 400);
        }

        $qualified = '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"';

        // Partition selection into columns and nested fields
        [$selColumns, $nested] = $this->partitionSelection($selection);

        // Validate columns via TableModel metaData
        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        $metaCols = array_keys($t->metaData ?? []);
        if (empty($metaCols)) {
            throw new GC2Exception('Unable to read table metadata', 500);
        }
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
        $autoFkCols = [];
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
            // Ensure local FK columns are present for each nested relation; we will remove later if not explicitly asked
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

        $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', array_unique($columns));
        $selectList = implode(', ', $colsSqlParts);

        // WHERE clause
        $clauses = [];
        $params = [];
        $where = $args['where'] ?? [];
        if (!is_array($where)) {
            $where = [];
        }
        foreach ($where as $col => $val) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            if (!in_array($col, $metaCols, true)) {
                throw new GC2Exception("Unknown column '$col'", 400);
            }
            $paramName = 'p_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
            $clauses[] = '"' . str_replace('"', '""', $col) . '" = :' . $paramName;
            $params[$paramName] = $val;
        }
        $whereSql = count($clauses) ? (' WHERE ' . implode(' AND ', $clauses)) : '';

        $limit = isset($args['first']) && is_numeric($args['first']) ? (int)$args['first'] : 100;
        $offset = isset($args['offset']) && is_numeric($args['offset']) ? (int)$args['offset'] : null;

        $sql = 'SELECT ' . $selectList . ' FROM ' . $qualified . $whereSql . ' LIMIT ' . max(0, $limit);
        if ($offset !== null) {
            $sql .= ' OFFSET ' . max(0, $offset);
        }

        $sqlModel = new SqlModel(connection: $this->connection);
        $res = $sqlModel->sql(q: $sql, format: 'json', convertTypes: true, parameters: $params);
        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $rows = is_array($rows) ? $rows : [];

        // Attach nested objects if requested
        if (!empty($rows)) {
            foreach ($rows as &$row) {
                $mappedRow = [];
                foreach ($selColumns as $alias => $name) {
                    $mappedRow[$alias] = $row[$name] ?? null;
                }
                if (!empty($nested)) {
                    foreach ($nested as $alias => $v) {
                        $relName = $v['name'];
                        $subSel = $v['selection'];
                        $map = $fkMap[$relName];
                        $localVal = $row[$map['local_col']] ?? null;
                        $mappedRow[$alias] = $this->fetchRelatedSingle(
                            schema: $map['ref_schema'],
                            table: $map['ref_table'],
                            column: $map['ref_col'],
                            value: $localVal,
                            selection: is_array($subSel) ? $subSel : null
                        );
                    }
                }
                $row = $mappedRow;
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Dynamic single-row resolver: <table>ById(positional|$var) { ... }
     * Accepts optional schema arg; selection behaves like list query.
     * @throws GC2Exception
     */
    private function resolveDynamicTableById(string $tableFieldById, array $args, mixed $positional, ?array $selection): mixed
    {
        // Derive table name from <table>ById
        if (!preg_match('/^(?P<table>[A-Za-z_][A-Za-z0-9_]*)ById$/', $tableFieldById, $mm)) {
            throw new GC2Exception('Invalid field name', 400);
        }
        $table = $mm['table'];
        $schema = $args['schema'] ?? 'public';
        if (!is_string($schema) || $schema === '') {
            $schema = 'public';
        }

        // AuthZ
//        $this->initiate(schema: $schema, relation: $table);

        $qualified = '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"';

        // Setup table metadata and columns
        $t = new TableModel(table: $schema . '.' . $table, lookupForeignTables: false, connection: $this->connection);
        if (!method_exists($t, 'hasPrimeryKey') || !$t->hasPrimeryKey($schema . '.' . $table)) {
            throw new GC2Exception('Table has no primary key; cannot use ById query', 400);
        }
        $pk = $t->primaryKey['attname'] ?? null;
        if (!$pk) {
            throw new GC2Exception('Primary key not found', 400);
        }

        // Determine selected columns
        // Partition selection into columns and nested fields
        [$selColumns, $nested] = $this->partitionSelection($selection);

        $metaCols = array_keys($t->metaData ?? []);
        if (empty($metaCols)) {
            throw new GC2Exception('Unable to read table metadata', 500);
        }
        $columns = array_values($selColumns);
        if (!empty($columns)) {
            foreach ($columns as $c) {
                if (!in_array($c, $metaCols, true)) {
                    throw new GC2Exception("Unknown column '$c' on {$schema}.{$table}", 400);
                }
            }
        } else {
            $columns = $metaCols;
            // If we default to all columns, we need to populate selColumns so they can be mapped later
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
        $autoFkCols = [];
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

        $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', array_unique($columns));
        $selectList = implode(', ', $colsSqlParts);

        // Determine id value
        $idVal = $positional;
        if ($idVal === null) {
            $idVal = $args['id'] ?? ($args['pk'] ?? ($args['key'] ?? null));
        }
        if ($idVal === null) {
            throw new GC2Exception("Missing id for {$table}ById. Provide a positional value or id/pk argument.", 400);
        }

        $sql = 'SELECT ' . $selectList . ' FROM ' . $qualified . ' WHERE ' . '"' . str_replace('"', '""', $pk) . '" = :pk LIMIT 1';
        $sqlModel = new SqlModel(connection: $this->connection);
        $res = $sqlModel->sql(q: $sql, format: 'json', convertTypes: true, parameters: ['pk' => $idVal]);
        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
        if (!is_array($row)) {
            return null;
        }

        // Map columns to aliases
        $mappedRow = [];
        foreach ($selColumns as $alias => $name) {
            $mappedRow[$alias] = $row[$name] ?? null;
        }

        // Attach nested
        if (!empty($nested)) {
            foreach ($nested as $alias => $v) {
                $relName = $v['name'];
                $subSel = $v['selection'];
                $map = $fkMap[$relName];
                $localVal = $row[$map['local_col']] ?? null;
                $mappedRow[$alias] = $this->fetchRelatedSingle(
                    schema: $map['ref_schema'],
                    table: $map['ref_table'],
                    column: $map['ref_col'],
                    value: $localVal,
                    selection: is_array($subSel) ? $subSel : null
                );
            }
        }
        return $mappedRow;
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
     * Build a selection tree map from AST FieldNode similar to previous parser output.
     * Returns [fieldName => true|subtree]
     */
    private function selectionTreeFromFieldNode(FieldNode $field): array
    {
        $tree = [];
        $selSet = $field->selectionSet?->selections ?? [];
        foreach ($selSet as $sel) {
            if ($sel instanceof FieldNode) {
                $name = $sel->name->value;
                $alias = $sel->alias?->value ?? $name;
                $child = $this->selectionTreeFromFieldNode($sel);
                $tree[$alias] = [
                    'name' => $name,
                    'selection' => empty($child) ? true : $child
                ];
            }
        }
        return $tree;
    }
}