<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response as ApiResponse;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Table as TableModel;
use app\models\Sql as SqlModel;
use app\inc\Model as IncModel;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Minimal GraphQL endpoint for Postgres.
 *
 * This implementation supports a constrained subset of queries and translates them to
 * existing REST/model functionality. It is intended as a lightweight starting point.
 *
 * Supported root fields (examples):
 * 1) tables(schema: "public", namesOnly: true)
 *    query { tables(schema: "public", namesOnly: true) }
 *
 * 2) table(schema: "public", name: "my_table")
 *    query { table(schema: "public", name: "my_table") }
 *
 * 3) rows(schema: "public", table: "my_table", limit: 100, offset: 0, where: {"status": "active"})
 *    - where supports simple equality filters only (column = value)
 *
 * 4) Dynamic table field selection (list) and single by-id query:
 *    - query { user(schema: "public") { id } } // list rows, only id column
 *    - query { user(schema: "my", where: {"status":"active"}, limit: 10) { id name } }
 *    - query { userById(5) { id name } }       // selects row from public.user with primary key = 5
 *    - query { userById(i) { id name } }       // same, using variables: { "variables": { "i": 5 } }
 */
#[AcceptableMethods(['POST', 'OPTIONS'])]
#[Controller(route: 'api/v4/graphql', scope: Scope::SUB_USER_ALLOWED)]
class Graphql extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct(connection: $connection);
        $this->resource = 'graphql';
    }

    #[Override]
    public function get_index(): ApiResponse
    {
        // Not used for GraphQL
        return new NoContentResponse();
    }

    /**
     * GraphQL POST endpoint.
     * Accepts a JSON body: { "query": string, "variables": object|null }
     *
     * @return ApiResponse
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): ApiResponse
    {
        $body = Input::getBody();
        $payload = json_decode($body ?? 'null', true);
        if (!is_array($payload)) {
            throw new GC2Exception('Invalid JSON body', 400);
        }
        $query = $payload['query'] ?? null;
        $variables = $payload['variables'] ?? [];
        if (!is_string($query) || $query === '') {
            throw new GC2Exception('Missing GraphQL query', 400);
        }
        // Execute the query in a very limited fashion
        $result = $this->executeQuery($query, is_array($variables) ? $variables : []);
        return new GetResponse(data: $result);
    }

    #[Override]
    public function put_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    #[Override]
    public function patch_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    #[Override]
    public function delete_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        // GraphQL endpoint does not take route parameters; basic body validation only
        $collection = $this->getAssert();
        $this->validateRequest($collection, Input::getBody(), Input::getMethod(), allowPatchOnCollection: false);
    }

    private function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'query' => new Assert\Optional(new Assert\Type('string')),
                'variables' => new Assert\Optional(new Assert\Type('array')),
                'operationName' => new Assert\Optional(new Assert\Type('string')),
            ],
            'allowExtraFields' => true,
            'allowMissingFields' => true,
        ]);
    }

    /**
     * Very small dispatcher for a constrained subset of GraphQL-like queries.
     *
     * @param string $query
     * @param array $variables
     * @return array{data: array|null, errors?: array}
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function executeQuery(string $query, array $variables): array
    {
        $normalized = trim($query);

        // Extract optional operation name from the header before the first top-level '{'
        $opName = null;
        $opType = null;
        $firstBracePos = strpos($normalized, '{');
        if ($firstBracePos !== false) {
            $header = substr($normalized, 0, $firstBracePos);
            if (preg_match('/\b(query|mutation)\b\s*([A-Za-z_][A-Za-z0-9_]*)?/i', $header, $hm)) {
                $opType = isset($hm[1]) ? strtolower($hm[1]) : null;
                $opName = $hm[2] ?? null;
            }
            $body = substr($normalized, $firstBracePos);
        } else {
            // Fallback: keep previous behavior if no braces were found
            $body = preg_replace('/^(query|mutation)\s*/i', '', $normalized ?? '') ?? $normalized;
        }

        // Find first top-level field within the first {...}
        if (!preg_match('/\{\s*(\w+)\s*(\(([^)]*)\))?/s', $body, $m, PREG_OFFSET_CAPTURE)) {
            return ['data' => null, 'errors' => [['message' => 'Unable to parse query']]];
        }
        $field = $m[1][0];
        $argString = $m[3][0] ?? '';
        $matchEndPos = $m[0][1] + strlen($m[0][0]);

        // Parse optional selection set following the field
        $selection = null;
        $rest = substr($body, $matchEndPos);
        $rest = ltrim($rest);
        if (isset($rest[0]) && $rest[0] === '{') {
            [$selContent, $_endPos] = $this->extractBracedBlock($rest, 0);
            $selection = $this->parseSelectionTree($selContent);
        }

        // Support single positional arg (e.g., user(5) or user(i))
        $positional = null;
        $argStringTrim = trim($argString ?? '');
        if ($argStringTrim !== '' && !str_contains($argStringTrim, ':')) {
            $positional = $this->parseValueToken($argStringTrim, $variables);
        }
        $args = $this->parseArgs($argString, $variables);

        // Require an operation name and validate it (must start with uppercase). Map to lowercase table logic.
        if (!is_string($opName) || $opName === '') {
            return ['data' => null, 'errors' => [['message' => 'Operation name is required']]];
        }
        if ($opType !== 'query') {
            return ['data' => null, 'errors' => [['message' => 'Only query operations are allowed']]];
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*(ById)?$/', $opName)) {
            return ['data' => null, 'errors' => [['message' => 'Invalid operation name: must start with an uppercase letter']]];
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
                return ['data' => null, 'errors' => [['message' => "Operation name '$opName' does not match root field '$field'"]]];
            }
            $effectiveField = $expectedField . 'ById';
        } else {
            if (!$matchesBase) {
                return ['data' => null, 'errors' => [['message' => "Operation name '$opName' does not match root field '$field'"]]];
            }
            $effectiveField = $expectedField;
        }

        try {
            $value = $this->resolveField($effectiveField, $args, $positional, $selection);
            // Keep the response key equal to the requested field name, not the effective resolver name
            return ['data' => [$field => $value]];
        } catch (GC2Exception $e) {
            return ['data' => null, 'errors' => [[
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]]];
        }
    }

    /**
     * Parse a very limited argument list of the form: key: value, key2: value2
     * Supported values: quoted strings, numbers, booleans, and JSON objects (with quoted keys).
     */
    private function parseArgs(string $argString, array $variables): array
    {
        $args = [];
        $s = trim($argString ?? '');
        if ($s === '') {
            return $args;
        }
        $parts = $this->splitTopLevel($s);
        foreach ($parts as $part) {
            $kv = explode(':', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }
            $key = trim($kv[0]);
            $valRaw = trim($kv[1]);
            // Variable reference
            if (str_starts_with($valRaw, '$')) {
                $varName = ltrim($valRaw, '$');
                $args[$key] = $variables[$varName] ?? null;
                continue;
            }
            // Quoted string
            if ((str_starts_with($valRaw, '"') && str_ends_with($valRaw, '"')) || (str_starts_with($valRaw, "'") && str_ends_with($valRaw, "'"))) {
                $args[$key] = trim($valRaw, "\"'");
                continue;
            }
            // JSON object literal (must have quoted keys to be valid JSON)
            if (str_starts_with($valRaw, '{') && str_ends_with($valRaw, '}')) {
                $decoded = json_decode($valRaw, true);
                $args[$key] = $decoded ?? $valRaw;
                continue;
            }
            // Booleans
            if (strcasecmp($valRaw, 'true') === 0) {
                $args[$key] = true;
                continue;
            }
            if (strcasecmp($valRaw, 'false') === 0) {
                $args[$key] = false;
                continue;
            }
            // Numbers
            if (is_numeric($valRaw)) {
                $args[$key] = $valRaw + 0; // cast to int/float
                continue;
            }
            // Fallback as string without quotes
            $args[$key] = $valRaw;
        }
        return $args;
    }

    /**
     * Split a comma-separated list at top-level only (not inside braces or quotes).
     */
    private function splitTopLevel(string $s): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $inStr = false;
        $strChar = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                $buf .= $ch;
                if ($ch === $strChar && ($i === 0 || $s[$i - 1] !== '\\')) {
                    $inStr = false;
                    $strChar = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strChar = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === '{' || $ch === '[' || $ch === '(') {
                $depth++;
                $buf .= $ch;
                continue;
            }
            if ($ch === '}' || $ch === ']' || $ch === ')') {
                $depth--;
                $buf .= $ch;
                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $parts[] = trim($buf);
        }
        return $parts;
    }

    /**
     * Extract content of a braced block starting at $startPos in $s (which must be a '{').
     * Returns array [string contentInside, int endPosAfterBlock].
     */
    private function extractBracedBlock(string $s, int $startPos): array
    {
        $len = strlen($s);
        if ($startPos >= $len || $s[$startPos] !== '{') {
            return ['', $startPos];
        }
        $depth = 0;
        $inStr = false;
        $strChar = '';
        $content = '';
        for ($i = $startPos; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                $content .= $ch;
                if ($ch === $strChar && $s[$i - 1] !== '\\') {
                    $inStr = false;
                    $strChar = '';
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strChar = $ch;
                $content .= $ch;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                if ($depth > 1) {
                    $content .= $ch;
                }
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return [$content, $i + 1];
                }
                $content .= $ch;
                continue;
            }
            $content .= $ch;
        }
        return [$content, $len];
    }

    /**
     * Parse a selection set body into a nested tree.
     * Example: "id name address { id street city { name } }" ->
     * ['id'=>true, 'name'=>true, 'address'=>['id'=>true,'street'=>true,'city'=>['name'=>true]]]
     */
    private function parseSelectionTree(string $sel): array
    {
        $s = trim($sel);
        if ($s === '') {
            return [];
        }
        $i = 0;
        $len = strlen($s);
        $result = [];
        while ($i < $len) {
            // Skip whitespace and commas
            while ($i < $len && (ctype_space($s[$i]) || $s[$i] === ',')) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            // Read token (possibly with alias and/or args), stop before '{' or comma/whitespace at top-level
            $start = $i;
            $depthPar = 0;
            $inStr = false;
            $strChar = '';
            while ($i < $len) {
                $ch = $s[$i];
                if ($inStr) {
                    if ($ch === $strChar && $s[$i - 1] !== '\\') {
                        $inStr = false;
                        $strChar = '';
                    }
                    $i++;
                    continue;
                }
                if ($ch === '"' || $ch === "'") {
                    $inStr = true;
                    $strChar = $ch;
                    $i++;
                    continue;
                }
                if ($ch === '(') {
                    $depthPar++;
                    $i++;
                    continue;
                }
                if ($ch === ')') {
                    if ($depthPar > 0) {
                        $depthPar--;
                    }
                    $i++;
                    continue;
                }
                if ($depthPar === 0 && ($ch === '{' || $ch === ',' || ctype_space($ch))) {
                    break;
                }
                $i++;
            }
            $token = trim(substr($s, $start, $i - $start));
            if ($token === '') {
                $i++;
                continue;
            }
            // Remove alias 'a: b' and arguments 'name(args)'
            $colon = strpos($token, ':');
            if ($colon !== false) {
                $token = substr($token, $colon + 1);
            }
            $token = preg_replace('/\(.*\)$/', '', $token) ?? $token;
            $token = trim($token);
            // Validate field name
            if ($token === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token)) {
                // Skip unknown tokens quietly
                $fieldName = null;
            } else {
                $fieldName = $token;
            }
            // Skip whitespace
            while ($i < $len && ctype_space($s[$i])) {
                $i++;
            }
            // If next char is '{', parse nested block
            if ($i < $len && $s[$i] === '{') {
                [$inner, $endPos] = $this->extractBracedBlock(substr($s, $i), 0);
                $i += $endPos;
                if ($fieldName !== null) {
                    $result[$fieldName] = $this->parseSelectionTree($inner);
                }
            } else {
                if ($fieldName !== null) {
                    $result[$fieldName] = true;
                }
            }
        }
        return $result;
    }

    /**
     * Parse a single value token possibly referring to a variable.
     */
    private function parseValueToken(string $token, array $variables): mixed
    {
        $t = trim($token);
        if ($t === '') {
            return null;
        }
        if ($t[0] === '$') {
            $name = ltrim($t, '$');
            return $variables[$name] ?? null;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $t)) {
            return $variables[$t] ?? $t;
        }
        if ((str_starts_with($t, '"') && str_ends_with($t, '"')) || (str_starts_with($t, "'") && str_ends_with($t, "'"))) {
            return trim($t, "\"'");
        }
        if (strcasecmp($t, 'true') === 0) {
            return true;
        }
        if (strcasecmp($t, 'false') === 0) {
            return false;
        }
        if (strcasecmp($t, 'null') === 0) {
            return null;
        }
        if (is_numeric($t)) {
            return $t + 0;
        }
        return $t;
    }

    private static function quoteIdent(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function partitionSelection(?array $selection): array
    {
        // returns [columns[], nestedMap]
        $columns = [];
        $nested = [];
        if (is_array($selection)) {
            foreach ($selection as $k => $v) {
                if ($v === true) {
                    $columns[] = (string)$k;
                } elseif (is_array($v)) {
                    $nested[(string)$k] = $v;
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

    private function fetchRelatedSingle(string $schema, string $table, string $column, mixed $value, ?array $selection, int $depth = 0): mixed
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
            foreach ($nested as $relName => $_) {
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
        $colsSql = implode(', ', array_map(self::quoteIdent(...), $columns));
        $qualified = self::quoteIdent($schema) . '.' . self::quoteIdent($table);
        $sql = 'SELECT ' . $colsSql . ' FROM ' . $qualified . ' WHERE ' . self::quoteIdent($column) . ' = :v LIMIT 1';
        $sqlModel = new SqlModel(connection: $this->connection);
        $res = $sqlModel->sql(q: $sql, format: 'json', convertTypes: true, parameters: ['v' => $value]);
        $rows = $res['data'] ?? ($res['rows'] ?? $res);
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
        if (!is_array($row)) {
            return null;
        }

        // Attach deeper nested
        if (!empty($nested)) {
            foreach ($nested as $relName => $subSel) {
                $map = $fkMap[$relName];
                $localVal = $row[$map['local_col']] ?? null;
                $row[$relName] = $this->fetchRelatedSingle(
                    schema: $map['ref_schema'],
                    table: $map['ref_table'],
                    column: $map['ref_col'],
                    value: $localVal,
                    selection: is_array($subSel) ? $subSel : null,
                    depth: $depth + 1
                );
            }
            foreach ($autoFkCols as $fkCol => $_) {
                if (!in_array($fkCol, $selColumns, true)) {
                    unset($row[$fkCol]);
                }
            }
        }

        return $row;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function resolveField(string $field, array $args, mixed $positional = null, ?array $selection = null): mixed
    {
        // Special-case dynamic single-row fields named like <table>ById
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*ById$/', $field)) {
            return $this->resolveDynamicTableById($field, $args, $positional, $selection);
        }
        return match ($field) {
            'tables' => $this->resolveTables($args),
            'table' => $this->resolveTable($args),
            'rows' => $this->resolveRows($args),
            default => $this->resolveDynamicTable($field, $args, $positional, $selection)
        };
    }

    /**
     * tables(schema: String!, namesOnly: Boolean)
     */
    private function resolveTables(array $args): array
    {
        $schema = $args['schema'] ?? null;
        if (!is_string($schema) || $schema === '') {
            throw new GC2Exception('Argument "schema" is required for tables', 400);
        }
        // Ensure access/authorization rules are applied
        $this->initiate(schema: $schema, relation: null);
        return Table::getTables(schema: $schema, self: $this);
    }

    /**
     * table(schema: String!, name: String!)
     */
    private function resolveTable(array $args): array
    {
        $schema = $args['schema'] ?? null;
        $name = $args['name'] ?? null;
        if (!is_string($schema) || !is_string($name) || $schema === '' || $name === '') {
            throw new GC2Exception('Arguments "schema" and "name" are required for table', 400);
        }
        $qualified = $schema . '.' . $name;
        $this->initiate(schema: $schema, relation: $name);
        $t = new TableModel(table: $qualified, lookupForeignTables: false, connection: $this->connection);
        return Table::getTable(table: $t, self: $this);
    }

    /**
     * rows(schema: String!, table: String!, limit: Int, offset: Int, where: JSON)
     * Supports simple equality filters only (column = value).
     */
    private function resolveRows(array $args): array
    {
        $schema = $args['schema'] ?? null;
        $table = $args['table'] ?? null;
        if (!is_string($schema) || !is_string($table) || $schema === '' || $table === '') {
            throw new GC2Exception('Arguments "schema" and "table" are required for rows', 400);
        }
        $limit = isset($args['limit']) && is_numeric($args['limit']) ? (int)$args['limit'] : 100;
        $offset = isset($args['offset']) && is_numeric($args['offset']) ? (int)$args['offset'] : 0;
        $where = $args['where'] ?? [];
        if (!is_array($where)) {
            $where = [];
        }

        // AuthZ
        $this->initiate(schema: $schema, relation: $table);

        // Build SQL
        $qualified = '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $table) . '"';
        $clauses = [];
        $params = [];
        foreach ($where as $col => $val) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            $paramName = 'p_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
            $clauses[] = '"' . str_replace('"', '""', $col) . '" = :' . $paramName;
            $params[$paramName] = $val;
        }
        $whereSql = count($clauses) ? (' WHERE ' . implode(' AND ', $clauses)) : '';
        $sql = 'SELECT * FROM ' . $qualified . $whereSql . ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);

        // Execute via Sql model for proper type handling
        $sqlModel = new SqlModel(connection: $this->connection);
        $res = $sqlModel->sql(q: $sql, format: 'json', convertTypes: true, parameters: $params);
        // Normalize: Sql model returns either ['data'=>rows] or raw rows
        $data = $res['data'] ?? ($res['rows'] ?? $res);
        return is_array($data) ? $data : [];
    }

    /**
     * Dynamic table resolver (list query): supports args schema, where, limit, offset.
     * Use <table>ById(...) to fetch a single row by primary key.
     */
    private function resolveDynamicTable(string $tableField, array $args, mixed $positional, ?array $selection): mixed
    {
        $schema = $args['schema'] ?? 'public';
        if (!is_string($schema) || $schema === '') {
            $schema = 'public';
        }
        $table = $tableField; // field name is table
        // AuthZ
        $this->initiate(schema: $schema, relation: $table);

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
        $columns = $selColumns;
        if (!empty($columns)) {
            foreach ($columns as $c) {
                if (!in_array($c, $metaCols, true)) {
                    throw new GC2Exception("Unknown column '$c' on {$schema}.{$table}", 400);
                }
            }
        } else {
            $columns = $metaCols; // default to all when no explicit scalar selection
        }

        // Build FK map if nested requested
        $fkMap = [];
        $autoFkCols = [];
        if (!empty($nested)) {
            $fkMap = $this->buildForeignMap($schema, $table);
            // Ensure local FK columns are present for each nested relation; we will remove later if not explicitly asked
            foreach ($nested as $relName => $_) {
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

        $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $columns);
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

        $limit = isset($args['limit']) && is_numeric($args['limit']) ? (int)$args['limit'] : 100;
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
        if (!empty($nested) && !empty($rows)) {
            foreach ($rows as &$row) {
                foreach ($nested as $relName => $subSel) {
                    $map = $fkMap[$relName];
                    $localVal = $row[$map['local_col']] ?? null;
                    $row[$relName] = $this->fetchRelatedSingle(
                        schema: $map['ref_schema'],
                        table: $map['ref_table'],
                        column: $map['ref_col'],
                        value: $localVal,
                        selection: is_array($subSel) ? $subSel : null
                    );
                }
                // Remove auto-added fk cols that were not explicitly requested
                foreach ($autoFkCols as $fkCol => $_) {
                    if (!in_array($fkCol, $selColumns, true)) {
                        unset($row[$fkCol]);
                    }
                }
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Dynamic single-row resolver: <table>ById(positional|$var) { ... }
     * Accepts optional schema arg; selection behaves like list query.
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
        $this->initiate(schema: $schema, relation: $table);

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
        $columns = $selColumns;
        if (!empty($columns)) {
            foreach ($columns as $c) {
                if (!in_array($c, $metaCols, true)) {
                    throw new GC2Exception("Unknown column '$c' on {$schema}.{$table}", 400);
                }
            }
        } else {
            $columns = $metaCols;
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
            foreach ($nested as $relName => $_) {
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

        $colsSqlParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $columns);
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

        // Attach nested
        if (!empty($nested)) {
            foreach ($nested as $relName => $subSel) {
                $map = $fkMap[$relName];
                $localVal = $row[$map['local_col']] ?? null;
                $row[$relName] = $this->fetchRelatedSingle(
                    schema: $map['ref_schema'],
                    table: $map['ref_table'],
                    column: $map['ref_col'],
                    value: $localVal,
                    selection: is_array($subSel) ? $subSel : null
                );
            }
            // Remove auto-added fk cols that were not explicitly requested
            foreach ($autoFkCols as $fkCol => $_) {
                if (!in_array($fkCol, $selColumns, true)) {
                    unset($row[$fkCol]);
                }
            }
        }

        return $row;
    }
}
