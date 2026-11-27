<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;


#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Meta",
    description: "Metadata for relations (tables/views) used by clients and tools. Stored outside the physical schema.",
    required: [],
    properties: [
        new OA\Property(
            property: 'relations',
            description: 'Relation metadata provides supplementary information about database relations.
            Metadata is stored outside the physical schema and does not change table structure or constraints.
            It is used by clients such as UI applications, query builders, and access layers.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                properties: [
                    new OA\Property(property: 'title', description: 'Human-readable relation title.', type: 'string'),
                    new OA\Property(property: 'abstract', description: 'Description or summary of the relation.', type: 'string'),
                    new OA\Property(property: 'group', description: 'Logical grouping (e.g. for UI categorization).', type: 'string'),
                    new OA\Property(property: 'sort_id', description: 'Sorting weight for presentation.', type: 'integer'),
                    new OA\Property(property: 'tags', description: 'Arbitrary classification tags.', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'properties', description: 'Free-form key/value metadata.', type: 'object'),
                    new OA\Property(property: 'authentication', description: "Who may access the relation's data through the public services (OWS, WFS, OGC API, SQL, tiles). `Write`: anyone reads, writing needs credentials. `Read/write`: reading and writing need credentials. `None`: anyone reads and writes. Returned with a token; set by the database owner. The read-only `_authentication` carries the same value on all routes.", type: 'string', enum: ['Write', 'Read/write', 'None'], example: 'Write'),
                    new OA\Property(property: 'enable_ows', description: 'Whether the relation is served as a layer through OWS (WMS/WFS). Returned with a token; set by the database owner.', type: 'boolean', example: true),
                    new OA\Property(property: 'editable', description: 'Whether features may be edited through WFS-T and the Feature API. Returned with a token; set by the database owner.', type: 'boolean', example: true),
                    new OA\Property(property: 'base_layer', description: 'Marks the layer as a base layer for clients. Returned with a token; set by the database owner.', type: 'boolean', example: false),
                    new OA\Property(property: 'tile_cache', description: 'Legacy flag: clients fetch the layer through the tile cache. Returned with a token; set by the database owner.', type: 'boolean', example: false),
                    new OA\Property(property: 'not_queryable', description: 'Excludes the layer from feature info (click) queries in clients. Returned with a token; set by the database owner.', type: 'boolean', example: false),
                    new OA\Property(property: 'skip_conflict', description: 'Leaves the layer out of conflict searches. Returned with a token; set by the database owner.', type: 'boolean', example: false),
                    new OA\Property(property: 'enable_sql_filter', description: 'Lets clients filter the layer with SQL. Returned with a token; set by the database owner.', type: 'boolean', example: false),
                    new OA\Property(property: 'note', description: 'Internal note about the relation. Returned with a token; set by the database owner. null or an empty string clears it, as for the other text settings.', type: ['string', 'null'], example: 'Updated nightly from the municipality'),
                    new OA\Property(property: 'meta_url', description: 'Link to external metadata about the relation. Returned with a token; set by the database owner.', type: ['string', 'null'], example: 'https://example.com/metadata/roads'),
                    new OA\Property(property: 'legend_url', description: "URL of a picture to use as the layer's legend instead of one drawn from its classes. Returned with a token; set by the database owner.", type: ['string', 'null'], example: 'https://example.com/legends/roads.png'),
                    new OA\Property(property: 'feature_id', description: 'Column to use as the feature id in WFS instead of the primary key. Returned with a token; set by the database owner.', type: ['string', 'null'], example: 'gid'),
                    new OA\Property(property: 'wms_source', description: "URL of an external WMS (or a QGIS Server project) that draws the layer instead of MapServer with the layer's classes. Returned to and set by the database owner only. A layer's own QML is switched with `use_qml` on the layer resource, which manages this value.", type: ['string', 'null'], example: 'https://wms.example.com/ows?layers=roads&format=image/png'),
                    new OA\Property(property: 'wms_client_epsgs', description: 'Space separated EPSG codes the external WMS in `wms_source` is offered in. Owner only.', type: ['string', 'null'], example: 'EPSG:4326 EPSG:25832'),
                    new OA\Property(property: 'bitmap_source', description: 'Path or connection of a raster source for the layer. Owner only.', type: ['string', 'null']),
                    new OA\Property(property: 'trigger_table', description: 'Relation whose changes should clear the tile cache of this layer. Owner only.', type: ['string', 'null'], example: 'my_schema.roads_raw'),
                    new OA\Property(
                        property: 'fields',
                        description: 'Per-column metadata.',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(
                            properties: [
                                new OA\Property(property: 'alias', description: 'Display name used in clients/UI.', type: 'string'),
                                new OA\Property(property: 'queryable', description: 'Whether the field can be queried/filtered.', type: 'boolean'),
                                new OA\Property(property: 'sort_id', description: 'Sorting weight within the field list.', type: 'integer'),
                                new OA\Property(property: 'properties', description: 'Free-form key/value or array metadata.', oneOf: [new OA\Schema(type: 'object'), new OA\Schema(type: 'array')]),
                                new OA\Property(property: 'desc', description: 'Description of the column.', type: ['string', 'null']),
                                new OA\Property(property: 'mouseover', description: 'Show the column in mouse-over (hover) info.', type: 'boolean'),
                                new OA\Property(property: 'searchable', description: 'Include the column in free-text search in clients.', type: 'boolean'),
                                new OA\Property(property: 'filter', description: 'Disable filtering on the column in clients (true = not filterable).', type: 'boolean'),
                                new OA\Property(property: 'not_exportable', description: 'Leave the column out of exports/downloads.', type: 'boolean'),
                                new OA\Property(property: 'autocomplete', description: 'Offer autocomplete on the column when editing or filtering.', type: 'boolean'),
                                new OA\Property(property: 'conflict', description: 'Show the column in conflict search results.', type: 'boolean'),
                                new OA\Property(property: 'link', description: 'Present the value as a link (see link_prefix, link_suffix).', type: 'boolean'),
                                new OA\Property(property: 'ignore', description: 'Hide the column from clients. An ignored column is left out of the public route and of legacy metadata, but stays listed here so it can be switched back.', type: 'boolean'),
                                new OA\Property(property: 'image', description: 'Present the value as an image.', type: 'boolean'),
                                new OA\Property(property: 'content', description: 'How clients present the value, for example `plain`, `image` or `video`.', type: ['string', 'null'], example: 'plain'),
                                new OA\Property(property: 'link_prefix', description: 'Text put before the value to make the link.', type: ['string', 'null'], example: 'https://example.com/items/'),
                                new OA\Property(property: 'link_suffix', description: 'Text put after the value to make the link.', type: ['string', 'null'], example: '.html'),
                                new OA\Property(property: 'template', description: 'Template (Mustache) used to present the value.', type: ['string', 'null'], example: '<b>{{name}}</b>'),
                            ],
                            type: 'object'
                        )
                    ),
                    new OA\Property(
                        property: 'elasticsearch',
                        description: 'Per-column OpenSearch mapping overrides, keyed by column name. Consumed when the Search API (PUT /api/v4/schemas/{schema}/tables/{table}/search) (re)builds the index: each column gets the mapping derived from its PostgreSQL type, and the members given here override or extend it. Columns not listed keep the derived mapping. Any other OpenSearch field-mapping parameter (normalizer, doc_values, copy_to, ...) is passed through as well. Analyzers must exist in the database analysis settings (PUT /api/v4/search/settings). A PATCH replaces the whole block for the relation; an empty object {} clears it.',
                        type: 'object',
                        example: ['name' => ['elasticsearchtype' => 'text', 'analyzer' => 'auto_complete_analyzer', 'search_analyzer' => 'auto_complete_search_analyzer']],
                        additionalProperties: new OA\AdditionalProperties(
                            properties: [
                                new OA\Property(property: 'elasticsearchtype', description: 'OpenSearch field type. Defaults to the type derived from the column.', type: 'string', example: 'text'),
                                new OA\Property(property: 'analyzer', description: 'Index-time (and default search-time) analyzer.', type: 'string', example: 'auto_complete_analyzer'),
                                new OA\Property(property: 'search_analyzer', description: 'Search-time analyzer.', type: 'string', example: 'auto_complete_search_analyzer'),
                                new OA\Property(property: 'index', description: 'Whether the field is indexed (searchable).', type: 'boolean', example: true),
                                new OA\Property(property: 'format', description: 'Date format (date fields).', type: 'string', example: 'yyyy-MM-dd'),
                                new OA\Property(property: 'null_value', description: 'Value indexed in place of null.'),
                                new OA\Property(property: 'fielddata', description: 'Enable fielddata on a text field (sorting/aggregation).', type: 'boolean'),
                                new OA\Property(property: 'fields', description: "Multi-fields. Text fields get {\"keyword\": {\"type\": \"keyword\", \"ignore_above\": 256}} unless this is set.", type: 'object'),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            ))
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: '/api/v4/meta/[query]', scope: Scope::SUB_USER_ALLOWED)]
class Meta extends AbstractApi
{
    private const array PRIVATE_PROPERTIES = ['character_maximum_length',
        'numeric_precision', 'numeric_scale', 'max_bytes', 'is_unique',
        'default_value', 'type', 'full_type', 'is_nullable', 'restriction'];

    /**
     * Column settings whose API name differs from the name they are stored under in fieldconf:
     * API name => stored name. The API is snake_case throughout; the stored names are the legacy
     * ones the old GUI and the map clients read. ("queryable" => "querable" is mapped where
     * fieldconf is read and written.)
     */
    public const array FIELD_NAMES = ['link_prefix' => 'linkprefix', 'link_suffix' => 'linksuffix'];

    /**
     * The names of a relation in the API and where each lives in a settings row, API name =>
     * row key. processRow() reads a row through these maps and processRowReverse() writes
     * through the same ones, so a name is declared once.
     *
     * COLUMNS: descriptive metadata, read and written as it is.
     */
    public const array COLUMNS = [
        'title' => 'f_table_title', 'abstract' => 'f_table_abstract', 'group' => 'layergroup', 'sort_id' => 'sort_id',
        'tags' => 'tags', 'properties' => 'meta', 'elasticsearch' => 'elasticsearch',
    ];
    /** Facts about the relation: read only. */
    public const array READ_ONLY = [
        'desc' => 'desc', '_uuid' => 'uuid', '_schema' => 'f_table_schema', '_rel' => 'f_table_name',
        '_geometry_column' => 'f_geometry_column', '_pkey' => 'pkey', '_rel_type' => 'rel_type',
        '_coord_dimension' => 'coord_dimension', '_geom_type' => 'type', '_srid' => 'srid',
        '_authentication' => 'authentication',
    ];
    /** Per-column metadata: written to "fieldconf", read from the parsed "fields" of a row. */
    public const string FIELDS = 'fields';
    public const string FIELDS_COLUMN = 'fieldconf';

    private const array PUBLIC_PROPERTIES = ['alias', 'queryable', 'sort_id', 'properties', 'desc',
        ...Layer::FIELD_FLAGS, ...Layer::FIELD_TEXTS];

    /**
     * Relation settings beyond the descriptive metadata: API name (snake_case) => settings
     * column (legacy name). Booleans and strings are told apart for validation and storage.
     */
    public const array SETTING_FLAGS = [
        'enable_ows' => 'enableows', 'editable' => 'editable', 'base_layer' => 'baselayer', 'tile_cache' => 'tilecache',
        'not_queryable' => 'not_querable', 'skip_conflict' => 'skipconflict', 'enable_sql_filter' => 'enablesqlfilter',
    ];
    public const array SETTING_TEXTS = [
        'note' => 'note', 'meta_url' => 'meta_url', 'legend_url' => 'legend_url', 'feature_id' => 'featureid',
        'wms_source' => 'wmssource', 'wms_client_epsgs' => 'wmsclientepsgs', 'bitmap_source' => 'bitmapsource',
        'trigger_table' => 'triggertable',
    ];
    /** Where a layer's data comes from: read by the database owner only. */
    public const array OWNER_ONLY_SETTINGS = ['wms_source', 'wms_client_epsgs', 'bitmap_source', 'trigger_table'];
    public const array AUTHENTICATION_LEVELS = ['Write', 'Read/write', 'None'];

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'relations';

    }


    /**
     * @return Response
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    #[OA\Get(path: '/api/v4/meta/{query}', operationId: 'getMetaData', summary: 'Get relation metadata', security: [['bearerAuth' => []]], tags: ['Metadata'])]
    #[OA\Parameter(name: 'query', description: 'Schema-qualified relation name, schema name, or tag (tag:name). Comma-separated values are supported.', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'noRestriction', description: 'Leaves out column restrictions on the returned metadata. Restrictions can be quite extensive.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Meta"))]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $layers = new Layer(connection: $this->connection);
        $jwt = Jwt::validate()["data"];
        if (!in_array(Input::get('noRestriction'), ['', 'true', '1', 't'], true)) {
            $restriction = true; // Leave out column restrictions
        } else {
            $restriction = false;
        }
        $res = $layers->getAll(
            db: $jwt["database"],
            auth: true,
            query: $this->route->getParam("query"),
            parse: true,
            lookupForeignTables: false,
            jwt: $jwt,
            restriction: $restriction,
            // The token route is where column settings are managed: an ignored column stays listed
            includeIgnored: true,
        );
        $rows = $res["data"];
        $r = self::processRows($rows, !empty($jwt['superUser']) ? 'owner' : 'user');
        return new GetResponse(data: ['relations' => $r]);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    #[OA\Patch(path: '/api/v4/meta', operationId: 'patchMetaData', summary: 'Update relation metadata', security: [['bearerAuth' => []]], tags: ['Metadata'])]
    #[OA\RequestBody(description: 'Metadata updates.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Meta"))]
    #[OA\Response(response: 303, description: "Metadata updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    public function patch_index(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body, true);
        // Access level, OWS exposure and where a layer's data comes from are the owner's to decide
        $settings = ['authentication', ...array_keys(self::SETTING_FLAGS), ...array_keys(self::SETTING_TEXTS)];
        foreach ($data['relations'] ?? [] as $datum) {
            if (is_array($datum) && array_intersect($settings, array_keys($datum)) && empty($this->route->jwt['data']['superUser'])) {
                throw new GC2Exception("Only the database owner can change a relation's settings", 403, null, 'SUPER_USER_ONLY');
            }
        }
        $geometryJoinTable = new \app\models\Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $geometryJoinTable->withTransaction(function () use ($geometryJoinTable, $data) {
            foreach ($data['relations'] as $key => $datum) {
                $split = explode(".", $key);
                $geomFields = new Layer(connection: $this->connection)->getGeometryColumnsFromTable($split[0], $split[1]);
                foreach ($geomFields as $geomField) {
                    if (count($split) == 3) {
                        $key = $split[0] . '.' . $split[1] . '.' . $split[2];
                    } else {
                        $key = $split[0] . '.' . $split[1] . '.' . $geomField;
                    }
                    $datum['_key_'] = $key;
                    $geometryJoinTable->updateRecord(data: self::processRowReverse($datum), keyName: '_key_');
                }
            }
        });
        $baseUrl = "/api/v4/meta";
        return $this->patchResponse($baseUrl);
    }

    /**
     * @param string $settings which relation settings to include: 'none' (the public route),
     *        'user' (any token) or 'owner' (also where the data comes from)
     */
    static function processRows(array $rows, string $settings = 'none'): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = $row['f_table_schema'] . '.' . $row['f_table_name'];
            $out[$key] = self::processRow($row, $settings);
        }
        return $out;
    }

    static function processRow(array $row, string $settings = 'none'): array
    {
        $out = [];
        foreach (self::COLUMNS + self::READ_ONLY as $name => $column) {
            if (!isset($row[$column])) {
                continue;
            }
            // Empty values are left out of the free-form properties
            $out[$name] = $name === 'properties' ? array_filter((array)$row[$column], fn($value) => !empty($value)) : $row[$column];
        }
        if ($settings !== 'none') {
            $out['authentication'] = $row['authentication'] ?? null;
            foreach (self::SETTING_FLAGS as $name => $column) {
                $out[$name] = filter_var($row[$column] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            foreach (self::SETTING_TEXTS as $name => $column) {
                if ($settings !== 'owner' && in_array($name, self::OWNER_ONLY_SETTINGS, true)) {
                    continue;
                }
                $out[$name] = isset($row[$column]) && $row[$column] !== '' ? (string)$row[$column] : null;
            }
        }
        if (isset($row[self::FIELDS])) {
            $out[self::FIELDS] = self::setPropertiesToPrivate($row[self::FIELDS]);
        }
        return $out;
    }

    /**
     * A relation as sent to PATCH, as a settings row to store. Only what was sent is in it.
     */
    static function processRowReverse(array $row): array
    {
        $out = [];
        if (isset($row['_key_'])) {
            $out['_key_'] = $row['_key_'];
        }
        foreach (self::COLUMNS as $name => $column) {
            if (isset($row[$name])) {
                $out[$column] = $row[$name];
            }
        }
        // An empty elasticsearch object clears the per-column overrides (stored as NULL, not "[]").
        if (array_key_exists('elasticsearch', $out) && $out['elasticsearch'] === []) {
            $out['elasticsearch'] = null;
        }
        if (isset($row[self::FIELDS])) {
            $fields = $row[self::FIELDS];
            foreach ($fields as $field => $fieldData) {
                // Only when sent: encoding a missing "properties" stored "null" over the
                // column's existing properties on every patch of another setting.
                if (array_key_exists('properties', $fieldData)) {
                    $fields[$field]['properties'] = json_encode($fieldData['properties'], true);
                }
                foreach (self::FIELD_NAMES as $apiName => $storedName) {
                    if (array_key_exists($apiName, $fieldData)) {
                        $fields[$field][$storedName] = $fieldData[$apiName];
                        unset($fields[$field][$apiName]);
                    }
                }
            }
            $out[self::FIELDS_COLUMN] = $fields;
        }
        if (array_key_exists('authentication', $row)) {
            $out['authentication'] = $row['authentication'];
        }
        foreach (self::SETTING_FLAGS as $name => $column) {
            if (array_key_exists($name, $row)) {
                // Explicit "1"/"0": the settings update stores a bare false as NULL
                $out[$column] = $row[$name] ? '1' : '0';
            }
        }
        foreach (self::SETTING_TEXTS as $name => $column) {
            if (array_key_exists($name, $row)) {
                $out[$column] = (string)($row[$name] ?? '');
            }
        }
        return $out;
    }

    protected static function setPropertiesToPrivate(array $properties): array
    {
        $newArray = [];
        foreach ($properties as $field => $property) {
            $col = [];
            foreach ($property as $key => $value) {
                if (in_array($key, self::PRIVATE_PROPERTIES)) {
                    $col['_' . $key] = $value;
                } elseif (in_array($key, self::PUBLIC_PROPERTIES)) {
                    $col[array_search($key, self::FIELD_NAMES, true) ?: $key] = $value;
                }
            }
            $newArray[$field] = $col;
        }
        return $newArray;
    }

    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();
        error_log("Received metadata patch request with body: " . $body);

        $this->validateRequest(
            collection: self::getAssert(),
            data: $body,
            method: Input::getMethod(),
            allowPatchOnCollection: true
        );
    }

    private function getAssert(): Assert\Collection
    {
        return new Assert\Collection(
            fields: [
                'relations' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Collection(
                            fields: [
                                'title' => new Assert\Optional(new Assert\Type('string')),
                                'abstract' => new Assert\Optional(new Assert\Type('string')),
                                'group' => new Assert\Optional(new Assert\Type('string')),
                                'sort_id' => new Assert\Optional(new Assert\Type('integer')),
                                'tags' => new Assert\Optional(new Assert\Type('list', 'This value should be of type list.')),
                                'properties' => new Assert\Optional(new Assert\Type('associative_array', 'This value should be of type object.')),
                                'authentication' => new Assert\Optional(new Assert\Choice(choices: self::AUTHENTICATION_LEVELS)),
                                ...array_map(fn() => new Assert\Optional(new Assert\Type('boolean')), self::SETTING_FLAGS),
                                ...array_map(fn() => new Assert\Optional(new Assert\Type(['string', 'null'])), self::SETTING_TEXTS),
                                // An object keyed by column. {} (decoded to []) is allowed and clears the block.
                                'elasticsearch' => new Assert\Optional([
                                    new Assert\Type('array', 'This value should be of type object.'),
                                    new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                                        if (is_array($value) && $value !== [] && array_is_list($value)) {
                                            $context->buildViolation('This value should be of type object.')->addViolation();
                                        }
                                    }),
                                ]),
                                'fields' => new Assert\Optional([
                                    new Assert\Type('array'),
                                    new Assert\All([
                                        new Assert\Collection(
                                            fields: [
                                                'alias' => new Assert\Optional(new Assert\Type('string')),
                                                'queryable' => new Assert\Optional(new Assert\Type('boolean')),
                                                'sort_id' => new Assert\Optional(new Assert\Type('integer')),
                                                'properties' => new Assert\Optional(new Assert\Type('associative_array', 'This value should be of type object.')),
                                                'desc' => new Assert\Optional(new Assert\Type(['string', 'null'])),
                                                ...array_fill_keys(Layer::FIELD_FLAGS, new Assert\Optional(new Assert\Type('boolean'))),
                                                ...array_fill_keys(
                                                    array_map(fn($stored) => array_search($stored, self::FIELD_NAMES, true) ?: $stored, Layer::FIELD_TEXTS),
                                                    new Assert\Optional(new Assert\Type(['string', 'null']))
                                                ),
                                            ],
                                            allowExtraFields: false,
                                            allowMissingFields: true,
                                        )
                                    ])
                                ]),
                            ],
                            allowExtraFields: false,
                            allowMissingFields: true,
                        )
                    ])
                ]),

            ],
            allowExtraFields: false,
            allowMissingFields: true,
        );
    }

    #[Override]
    public function post_index(): Response
    {
        // TODO: Implement post_index() method.
    }

    #[Override]
    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.

    }
}
