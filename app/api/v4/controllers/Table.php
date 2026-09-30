<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\ApiInterface;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\models\Layer;
use app\models\Table as TableModel;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Table",
    description: "Table definition including columns, indexes, constraints, and comments.",
    required: ["name"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name",
            description: "Table name.",
            type: "string",
            example: "my-column",
        ),
        new OA\Property(property: "_type", description: "Read-only. TABLE, VIEW or MATERIALIZED VIEW.", type: "string", readOnly: true, example: "TABLE"),
        new OA\Property(property: "_events", description: "Read-only. Whether realtime change events are enabled on the table.", type: "boolean", readOnly: true, example: false),
        new OA\Property(property: "_column_count", description: "Read-only. Number of columns. Always present, also with namesOnly=true, so a listing can show sizes without loading every column.", type: "integer", readOnly: true, example: 7),
    new OA\Property(property: "_columns", description: "Read-only. The relation's column names in table column order. Always present, also with namesOnly=true, so a client can offer autocompletion over a schema without loading every definition. Names only: types, comments and the rest stay in the full shape and on /columns.", type: "array", items: new OA\Items(type: "string"), readOnly: true, example: ["gid", "navn", "the_geom"]),
    new OA\Property(property: "_geometry_columns", description: "Read-only. The relation's geometry and geography columns, in column order; an empty array for a relation without any. Always present, also with namesOnly=true, so a client can find the spatial relations of a schema from the listing alone. Type and SRID are read off the column's typmod, the same source PostGIS' geometry_columns/geography_columns views use, so a column declared plain `geometry` reports type Geometry and SRID 0. Relations owned by an extension report an empty array: public.raster_columns.extent is PostGIS' own, not the user's data.", type: "array", items: new OA\Items(properties: [new OA\Property(property: "name", type: "string", example: "the_geom"), new OA\Property(property: "type", type: "string", example: "MultiPolygon"), new OA\Property(property: "srid", type: "integer", example: 25832)], type: "object"), readOnly: true),
        new OA\Property(
            property: "columns",
            title: "Columns",
            description: "Columns in the table.",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Column"),
        ),
        new OA\Property(
            property: "indices",
            title: "Indices",
            description: "Indexes defined on the table.",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Index"),
        ),
        new OA\Property(
            property: "constraints",
            title: "Constraints",
            description: "Constraints defined on the table.",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Constraint"),
        ),
        new OA\Property(
            property: "comment",
            title: "Comment",
            description: "Comment for the table.",
            type: "string",
            example: "This is a comment on the table",
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/[table]', scope: Scope::SUB_USER_ALLOWED)]
class Table extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'tables';
    }

    /**
     * @return Response
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'getTable', description: "Get table(s).", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'namesOnly', description: 'Return only table names with the read-only facts (_type, _events, _column_count, _links); omit columns, comment, indexes and constraints. One catalog query for the whole schema, so use it for listings.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Table"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Table"))]),
        links: [
            new OA\Link(
                link: "getColumn",
                operationId: "getColumn",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table" => '$request.path.table',
                ],
                description: "Link to columns."
            ),
            new OA\Link(
                link: "getConstraint",
                operationId: "getConstraint",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table" => '$request.path.table',
                ],
                description: "Link to constraints."
            ),
            new OA\Link(
                link: "getIndex",
                operationId: "getIndex",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table" => '$request.path.table',
                ],
                description: "Link to indexes."
            ),
            new OA\Link(
                link: "getPrivileges",
                operationId: "getPrivileges",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table" => '$request.path.table',
                ],
                description: "Link to privileges."
            )
        ])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $r = [];
        if (!empty($this->qualifiedName)) {
            for ($i = 0; sizeof($this->qualifiedName) > $i; $i++) {
                $r[] = self::getTable($this->table[$i], $this);
            }
            return $this->getResponse($r, single: count($r) == 1);
        } else {
            $r = self::getTables($this->schema[0], $this);
        }
        return $this->getResponse($r);
    }

    /**
     * @return Response
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables', operationId: 'postTable', description: "Create table(s).", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\RequestBody(description: 'Table to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Table"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Table"))])
    )]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table[0] = new TableModel(table: null, connection: $this->connection);
        $this->table[0]->postgisschema = $this->schema[0];
        $list = [];

        $this->table[0]->withTransaction(function () use (&$list, $data) {
            if (is_array($data)) {
                foreach ($data as $datum) {
                    $r = self::addTable($this->table[0], (object)$datum, $this);
                    $list[] = $r['tableName'];
                }
            } else {
                $r = self::addTable($this->table[0], (object)$data, $this);
                $list[] = $r['tableName'];
            }
        });
        new Layer(connection: $this->connection)->insertDefaultMeta();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/tables/";
        return $this->postResponse($baseUri, $list);
    }

    /**
     * @return Response
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'patchTable', description: "Rename or move existing table(s).", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\RequestBody(description: 'Updated table', required: true, content: new OA\JsonContent(
        allOf: [
            new OA\Schema(
                properties: [
                    new OA\Property(property: "name", description: "New name of table", type: "string", example: "my_table_with_new_name"),
                    new OA\Property(property: "schema", description: "Target schema to move the table to.", type: "string", example: "my_other_schema"),
                    new OA\Property(
                        property: "comment",
                        title: "Comment",
                        description: "Comment on the table",
                        type: "string",
                        example: "This is a comment on the table",
                    ),
                ]
            )
        ]
    ))]
    #[OA\Response(response: 303, description: 'Table updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $layer = new Layer(connection: $this->connection);
        $body = Input::getBody();
        $data = json_decode($body);
        $r = [];
        $layer->withTransaction(function () use (&$r, $layer, $data) {
            for ($i = 0; sizeof($this->unQualifiedName) > $i; $i++) {
                if (isset($data->name) && $data->name != $this->unQualifiedName[$i]) {
                    $r[] = $layer->rename($this->qualifiedName[$i], $data->name)['name'];
                }
                $relName = $r[$i] ?? $this->qualifiedName[$i];
                if (isset($data->schema) && $data->schema != $this->schema[0]) {
                    if (!$this->route->jwt["data"]['superUser']) {
                        throw new GC2Exception('Only super user can move tables between schemas');
                    }
                    $layer->setSchema([$relName], $data->schema);
                }
                $schemaName = $data->schema ?? $this->schema[0];
                // Set comment
                if (property_exists($data, 'comment')) {
                    $layer->table = $schemaName . "." . $relName;
                    $layer->setTableComment($data->comment);
                }
                // Emit events
                if (property_exists($data, 'emit_events')) {
                    if ($data->emit_events === true) {
                        $layer->installNotifyTrigger($this->qualifiedName[$i]);
                    } elseif ($data->emit_events === false) {
                        $layer->removeNotifyTrigger($this->qualifiedName[$i]);
                    }
                }
            }
        });
        $schema = $data->schema ?? $this->schema[0];
        $baseUrl = "/api/v4/schemas/$schema/tables/";
        $list = count($r) > 0 ? $r : $this->unQualifiedName;
        return $this->patchResponse($baseUrl, $list);
    }

    /**
     * @return Response
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'deleteTable', description: "Delete table(s).", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 204, description: 'Table deleted')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        $this->table[0]->withTransaction(function () {
            foreach ($this->table as $t) {
                $t->destroy();
            }
        });
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function addTable(TableModel $table, stdClass $data, AbstractApi $caller): array
    {
        // Load pre extensions and run processAddTable
        $caller->runPreExtension(method: 'processAddTable', model: $table);

        $r = $table->create($data->name, null, null, true, $data->comment);
        // Add columns
        if (!empty($data->columns)) {
            foreach ($data->columns as $column) {
                Column::addColumn(
                    table: $table,
                    column: $column->name,
                    type: $column->type,
                    defaultValue: $column->default_value,
                    isNullable: $column->is_nullable,
                    identity: $column->identity_generation,
                    comment: $column->comment
                );
            }
        }
        // Add indices
        if (!empty($data->indices)) {
            foreach ($data->indices as $index) {
                Index::addIndices($table, $index->columns, $index->method, $index->name);
            }
        }
        // Add constraints
        if (!empty($data->constraints)) {
            foreach ($data->constraints as $constraint) {
                Constraint::addConstraint($table, $constraint->constraint, $constraint->columns, $constraint->check, $constraint->name, $constraint->referenced_table, $constraint->referenced_columns);
            }
        }
        return $r;
    }

    /**
     * @param TableModel $table
     * @param ApiInterface $self
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    private static function namesOnly(): bool
    {
        return in_array(Input::get('namesOnly'), ['', 'true', '1', 't'], true);
    }

    /**
     * One catalog query for the cheap facts about the relations of a schema
     * (or one relation): kind, column names and whether the notify trigger
     * is installed. Backs the namesOnly listings, which must not build a
     * TableModel per relation — that costs a handful of queries each.
     *
     * The column count is the length of the name list, so pg_attribute is
     * scanned once per relation rather than once for the names and once to
     * count them.
     *
     * @return array<string, array{type:string, column_count:int, columns:list<string>, events:bool, geometry_columns:list<array{name:string, type:string, srid:int}>}> keyed by relation name
     */
    private static function summaries(ApiInterface $self, string $schema, ?string $relation = null): array
    {
        $geometry = self::geometryColumns($self, $schema, $relation);
        $model = new Model(connection: $self->connection);
        $sql = "SELECT c.relname AS name, c.relkind,
                       (SELECT array_to_json(array_agg(a.attname ORDER BY a.attnum))
                          FROM pg_attribute a
                         WHERE a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped) AS columns,
                       EXISTS (SELECT 1 FROM pg_trigger t WHERE t.tgrelid = c.oid AND t.tgname = '_gc2_notify_transaction_trigger') AS events
                FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = :schema AND c.relkind IN ('r', 'p', 'v', 'm')"
            . ($relation !== null ? " AND c.relname = :relation" : "")
            . " ORDER BY c.relname";
        $res = $model->prepare($sql);
        $params = ['schema' => $schema];
        if ($relation !== null) {
            $params['relation'] = $relation;
        }
        $model->execute($res, $params);
        $types = ['r' => 'TABLE', 'p' => 'TABLE', 'v' => 'VIEW', 'm' => 'MATERIALIZED VIEW'];
        $out = [];
        while ($row = $model->fetchRow($res)) {
            // A relation with no columns at all aggregates to SQL NULL, not to an empty array.
            $columns = $row['columns'] !== null ? (json_decode($row['columns'], true) ?: []) : [];
            $out[$row['name']] = [
                'type' => $types[$row['relkind']],
                'column_count' => count($columns),
                'columns' => $columns,
                'events' => filter_var($row['events'], FILTER_VALIDATE_BOOLEAN),
                'geometry_columns' => $geometry[$row['name']] ?? [],
            ];
        }
        return $out;
    }

    /**
     * The geometry and geography columns of a schema's relations, from one catalog
     * query: name, type and SRID per column, keyed by relation name.
     *
     * Type and SRID are read off the column's typmod with PostGIS' own helpers, the
     * same source its geometry_columns/geography_columns views use, so a column
     * declared plain `geometry` reports type Geometry and SRID 0 rather than costing
     * a scan of the data.
     *
     * Relations owned by an extension are left out: PostGIS ships views of its own,
     * and public.raster_columns.extent is a geometry column that says nothing about
     * the user's data. /api/v4/meta never showed them either, because it only knows
     * relations registered as layers.
     *
     * @return array<string, list<array{name:string, type:string, srid:int}>>
     */
    private static function geometryColumns(ApiInterface $self, string $schema, ?string $relation = null): array
    {
        $model = new Model(connection: $self->connection);
        $sql = "SELECT c.relname AS relation, a.attname AS name,
                       postgis_typmod_type(a.atttypmod) AS type,
                       postgis_typmod_srid(a.atttypmod) AS srid
                FROM pg_attribute a
                JOIN pg_class c ON c.oid = a.attrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                JOIN pg_type t ON t.oid = a.atttypid
                WHERE n.nspname = :schema
                  AND c.relkind IN ('r', 'p', 'v', 'm')
                  AND t.typname IN ('geometry', 'geography')
                  AND a.attnum > 0 AND NOT a.attisdropped
                  AND NOT EXISTS (SELECT 1 FROM pg_depend d
                                  WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.deptype = 'e')"
            . ($relation !== null ? " AND c.relname = :relation" : "")
            . " ORDER BY c.relname, a.attnum";
        $res = $model->prepare($sql);
        $params = ['schema' => $schema];
        if ($relation !== null) {
            $params['relation'] = $relation;
        }
        $model->execute($res, $params);
        $out = [];
        while ($row = $model->fetchRow($res)) {
            $out[$row['relation']][] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'srid' => (int)$row['srid'],
            ];
        }
        return $out;
    }

    /** The namesOnly shape of one relation: name, read-only facts and links, no definition. */
    private static function summaryResponse(string $schema, string $name, array $summary): array
    {
        return [
            'name' => $name,
            '_type' => $summary['type'],
            '_events' => $summary['events'],
            '_column_count' => $summary['column_count'],
            '_columns' => $summary['columns'],
            '_geometry_columns' => $summary['geometry_columns'],
            '_links' => self::links($schema, $name),
        ];
    }

    private static function links(string $schema, string $name): array
    {
        return [
            'columns' => "/api/v4/schemas/$schema/tables/$name/columns",
            'indices' => "/api/v4/schemas/$schema/tables/$name/indices",
            'constraints' => "/api/v4/schemas/$schema/tables/$name/constraints",
            'privileges' => "/api/v4/schemas/$schema/tables/$name/privileges",
        ];
    }

    /**
     * @param list<array{name:string, type:string, srid:int}>|null $geometryColumns Prefetched for
     *     this relation, so a full listing pays one catalog query for the whole schema
     *     instead of one per table. Null looks them up for this relation alone.
     */
    public static function getTable(TableModel $table, ApiInterface $self, ?array $geometryColumns = null): array
    {
        if (self::namesOnly()) {
            $summary = self::summaries($self, $table->schema, $table->tableWithOutSchema)[$table->tableWithOutSchema] ?? null;
            if ($summary !== null) {
                return $self->runPostExtension('processGetTable', $table, self::summaryResponse($table->schema, $table->tableWithOutSchema, $summary));
            }
        }
        $response['name'] = $table->tableWithOutSchema;
        $response['columns'] = Column::getColumns($table);
        $response['comment'] = $table->getComment();
        $response['indices'] = Index::getIndices($table);
        $response['constraints'] = Constraint::getConstraints($table);
        $response['_type'] = $table->relType;
        $response['_events'] = $table->isNotifyTriggerInstalled();
        $response['_column_count'] = count($response['columns']);
        $response['_columns'] = array_column($response['columns'], 'name');
        $response['_geometry_columns'] = $geometryColumns
            ?? (self::geometryColumns($self, $table->schema, $table->tableWithOutSchema)[$table->tableWithOutSchema] ?? []);
        $response['_links'] = [
            'columns' => "/api/v4/schemas/$table->schema/tables/$table->tableWithOutSchema/columns",
            'indices' => "/api/v4/schemas/$table->schema/tables/$table->tableWithOutSchema/indices",
            'constraints' => "/api/v4/schemas/$table->schema/tables/$table->tableWithOutSchema/constraints",
            'privileges' => "/api/v4/schemas/$table->schema/tables/$table->tableWithOutSchema/privileges",
        ];
        return $self->runPostExtension('processGetTable', $table, $response);
    }

    /**
     * @param string $schema
     * @param ApiInterface $self
     * @return array[]
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getTables(string $schema, ApiInterface $self): array
    {
        $rels = [];
        if (self::namesOnly()) {
            // One query for the whole schema instead of a TableModel per relation.
            foreach (self::summaries($self, $schema) as $name => $summary) {
                $rels[] = self::summaryResponse($schema, $name, $summary);
            }
            return $rels;
        }
        $tables = new Model(connection: $self->connection)->getTableNamesFromSchema($schema);
        $views = new Model(connection: $self->connection)->getViewNamesFromSchema($schema);
        // Once for the schema, not once per relation.
        $geometry = self::geometryColumns($self, $schema);
        foreach ([...$tables, ...$views] as $name) {
            $rels[] = self::getTable(new TableModel(table: $schema . "." . $name, lookupForeignTables: false, connection: $self->connection), $self, $geometry[$name] ?? []);
        }
        return $rels;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = $this->route->getParam("table");
        $schema = $this->route->getParam("schema");
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("Patch and delete on a table collection is not allowed", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $table) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate(schema: $schema, relation: $table);
    }

    /**
     * @return Assert\Collection
     */
    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        if (Input::getMethod() == 'post') {
            $collection->fields['name'] = new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
        } else {
            $collection->fields['name'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]
            );
            $collection->fields['emit_events'] = new Assert\Optional(
                new Assert\Type('boolean')
            );
            $collection->fields['schema'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
        }
        $collection->fields['comment'] = new Assert\Optional(
            new Assert\Type('string'),
        );
        $collection->fields['columns'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank(),
                    Column::getAssert(),
                ]),
            ]
        );
        $collection->fields['indices'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank(),
                    Index::getAssert(),
                ]),
            ]
        );
        $collection->fields['constraints'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank(),
                    Constraint::getAssert(),
                ]),
            ]
        );
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
