<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Database;
use app\models\Mapcachefile;
use app\models\SchemaSettings;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The merged per-schema tileset's settings — <schema> and <schema>.mvt, the ones
 * drawn from every layer in the schema at once. A layer's own tileset is
 * configured through the Layer API; this is its counterpart for the schema.
 *
 * The row outlives its schema on purpose (dropping and recreating a schema is
 * normal here), which is why GET and DELETE work without the schema while PATCH
 * does not: a write to a schema that is not there is how a typo becomes a row
 * that silently takes effect months later, and nothing in the config would point
 * at the cause.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: 'SchemaTileSettingsInput',
    description: 'The PATCH body. Every field is optional; an explicit null removes that setting and returns it to its fallback. Unknown fields are rejected.',
    properties: [
        new OA\Property(property: 'cache', description: 'Cache backend.', type: 'string', enum: ['sqlite', 'disk', 'memcache', 's3'], nullable: true),
        new OA\Property(property: 'format', description: "The image tileset's format — PNG or a jpeg_* quality. The .mvt tileset is always MVT, which is its only possible value, so there is nothing to configure there and 'MVT' is not accepted here; the response reports it as the read-only vector_format. 'JSON' is not accepted either, because no merged .json tileset exists.", type: 'string', enum: ['PNG', 'jpeg_low', 'jpeg_medium', 'jpeg_high'], nullable: true),
        new OA\Property(property: 'ttl', description: 'Seconds a tile stays valid (mapcache expires). Any positive value is accepted and then floored at 30 when the config is generated, so 5 behaves as 30. Defaults to 60.', type: 'integer', nullable: true, minimum: 1, example: 86400),
        new OA\Property(property: 'auto_expire', description: 'Seconds after which an existing tile is refreshed on next access.', type: 'integer', nullable: true, minimum: 1, example: 3600),
        new OA\Property(property: 'meta_size', description: 'Metatile size N, rendered as N x N tiles per WMS request. 1 to 16; defaults to 3.', type: 'integer', nullable: true, maximum: 16, minimum: 1, example: 5),
        new OA\Property(property: 'meta_buffer', description: 'Pixels drawn around each metatile and cropped afterwards. 0 to 512; defaults to 0.', type: 'integer', nullable: true, maximum: 512, minimum: 0, example: 10),
        new OA\Property(property: 's3_tile_set', description: 'With cache "s3", the object-path segment for this schema. Letters, digits, dot, dash and underscore only, and not dots alone: it is interpolated into the cache URL, and a dot-only value would put the tiles at the bucket root.', type: 'string', maxLength: 255, pattern: '^[A-Za-z0-9_\-.]+$', nullable: true),
        new OA\Property(property: 'title', description: 'Shown in WMTS capabilities. Defaults to the schema name. Must not contain "]]>", which would close the CDATA section it is written into.', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'abstract', description: 'Shown in WMTS capabilities. Must not contain "]]>", which would close the CDATA section it is written into.', type: 'string', maxLength: 2048, nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SchemaTileSettings',
    description: 'The settings the config generator will actually use: stored values merged over the fallbacks. _stored carries only what is stored, so a form can tell a set value from a defaulted one, and schema_exists is false for settings left behind by a dropped schema.',
    // Every field is always present, because the response is the stored values
    // merged over the fallbacks — that is the whole reason this is a separate
    // schema from SchemaTileSettingsInput, where every field is optional. Without
    // the list a generator makes them all optional and a client has to null-check
    // values the server always sends. auto_expire and s3_tile_set are required AND
    // nullable: the key is always there, its value may be null. (Note the contrast
    // with the tileseeder round's trap, which was a field required on INPUT while
    // nullable in the response — that one misleads, this one does not.)
    required: ['schema', 'schema_exists', 'cache', 'format', 'vector_format', 'ttl', 'auto_expire',
        'meta_size', 'meta_buffer', 's3_tile_set', 'title', 'abstract', '_stored', '_defaults'],
    properties: [
        new OA\Property(property: 'schema', type: 'string', readOnly: true, example: 'dagi'),
        new OA\Property(property: 'schema_exists', description: 'False when the settings are waiting for their schema to come back.', type: 'boolean', readOnly: true),
        new OA\Property(property: 'cache', type: 'string', example: 'sqlite'),
        new OA\Property(property: 'format', description: "The image tileset's format. Configurable.", type: 'string', example: 'PNG'),
        new OA\Property(property: 'vector_format', description: "The .mvt tileset's format. Always MVT and not configurable — reported so a client does not have to assume it.", type: 'string', readOnly: true, example: 'MVT'),
        new OA\Property(property: 'ttl', type: 'integer', example: 60),
        new OA\Property(property: 'auto_expire', type: 'integer', nullable: true),
        new OA\Property(property: 'meta_size', type: 'integer', example: 3),
        new OA\Property(property: 'meta_buffer', type: 'integer', example: 0),
        new OA\Property(property: 's3_tile_set', type: 'string', nullable: true),
        new OA\Property(property: 'title', type: 'string', example: 'dagi'),
        new OA\Property(property: 'abstract', type: 'string', example: ''),
        new OA\Property(property: '_stored', description: 'Only the keys actually stored, so a form can tell a set value from a defaulted one. An explicit null in a PATCH removes a key from here.', type: 'object', readOnly: true),
        new OA\Property(property: '_defaults', description: 'What each setting would be if it were not stored — the patchable keys only. Reported even for a field that IS stored, which is when a form needs it: to show the default beside the value the user is about to clear.', type: 'object', readOnly: true),
    ],
    type: 'object'
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tile', scope: Scope::SUPER_USER_ONLY)]
final class SchemaTileSettings extends AbstractApi
{
    private SchemaSettings $settings;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'tile';
        $this->settings = new SchemaSettings(connection: $connection);
    }

    /**
     * The schema from the path, checked against the shape a PostgreSQL schema
     * name and a varchar(255) primary key can hold.
     *
     * Deliberately NOT AbstractApi::initiate(), which raises 404 SCHEMA_NOT_FOUND
     * for any schema it is given: GET and DELETE must keep working after the
     * schema is dropped, or a row left behind could never be read or cleared
     * through the API.
     *
     * @throws GC2Exception
     */
    private function schemaName(): string
    {
        $schema = (string)$this->route->getParam('schema');
        if ($schema === '' || strlen($schema) > 255 || !preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*$/', $schema)) {
            throw new GC2Exception('Invalid schema name', 400, null, 'INVALID_REQUEST');
        }
        return $schema;
    }

    private function schemaExists(string $schema): bool
    {
        return (new Database($this->connection))->doesSchemaExist($schema);
    }

    private function defaultCache(): string
    {
        return !empty(App::$param['mapCache']['type']) ? App::$param['mapCache']['type'] : 'sqlite';
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/tile', operationId: 'getSchemaTileSettings', description: "The merged per-schema tileset's effective tile settings — the stored values over the fallbacks, so a client sees what the config generator will actually do. Answers the fallbacks when nothing is stored, and still answers when the schema itself has been dropped.", tags: ['SchemaTileSettings'],
        parameters: [new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: '#/components/schemas/SchemaTileSettings')),
            new OA\Response(response: 400, description: 'The schema name is not a possible one'),
        ])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $schema = $this->schemaName();
        $row = $this->settings->get($schema);
        $stored = $row && $row['def'] ? (array)json_decode($row['def'], true) : [];
        $set = Mapcachefile::schemaSettings($row, $this->defaultCache(), $schema);
        // What each setting would be if it were not stored. A form needs this even
        // for a field that IS stored — that is precisely when it wants to show
        // "default is 60" beside the value the user is about to clear, and the
        // effective values cannot supply it, because once a field is stored the
        // effective value is the stored one. Resolving a null row is the same code
        // path the generator takes, so these cannot drift from the real defaults.
        $fallback = Mapcachefile::schemaSettings(null, $this->defaultCache(), $schema);
        $defaults = [
            'cache' => $fallback['cache'],
            'format' => $fallback['imageFormat'],
            'ttl' => $fallback['expires'],
            'auto_expire' => $fallback['autoExpire'],
            'meta_size' => $fallback['metaSize'],
            'meta_buffer' => $fallback['metaBuffer'],
            's3_tile_set' => $fallback['s3TileSet'],
            'title' => $fallback['title'],
            'abstract' => $fallback['abstract'],
        ];
        return $this->getResponse([[
            'schema' => $schema,
            'schema_exists' => $this->schemaExists($schema),
            'cache' => $set['cache'],
            'format' => $set['imageFormat'],
            'vector_format' => $set['vectorFormat'],
            'ttl' => $set['expires'],
            'auto_expire' => $set['autoExpire'],
            'meta_size' => $set['metaSize'],
            'meta_buffer' => $set['metaBuffer'],
            's3_tile_set' => $set['s3TileSet'],
            'title' => $set['title'],
            'abstract' => $set['abstract'],
            // (object) so an empty set serialises as {} rather than [], which is
            // what the OpenAPI schema declares and what a generated client with a
            // typed map expects.
            '_stored' => (object)$stored,
            '_defaults' => (object)$defaults,
        ]], single: true);
    }

    #[OA\Patch(path: '/api/v4/schemas/{schema}/tile', operationId: 'patchSchemaTileSettings', description: "Merge settings into the schema's stored ones. An explicit null removes a setting, returning it to its fallback. The schema must exist: a write to one that does not is how a typo becomes a row that silently takes effect later.", tags: ['SchemaTileSettings'],
        parameters: [new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SchemaTileSettingsInput')),
        responses: [
            new OA\Response(response: 303, description: 'Stored; see the Location header'),
            new OA\Response(response: 400, description: 'A value is not allowed, or an unknown field was sent'),
            new OA\Response(response: 404, description: 'The schema does not exist'),
        ])]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $schema = $this->schemaName();
        if (!$this->schemaExists($schema)) {
            throw new GC2Exception('Schema not found', 404, null, 'SCHEMA_NOT_FOUND');
        }
        $body = json_decode(Input::getBody(), true);
        $this->settings->patch($schema, is_array($body) ? $body : []);
        // patchResponse() appends the id list to the base, so the base ends at the
        // schema and 'tile' is the last segment — passing the full path plus the
        // schema would answer Location: .../tile<schema>.
        return $this->patchResponse('/api/v4/schemas/' . $schema . '/', ['tile']);
    }

    #[OA\Delete(path: '/api/v4/schemas/{schema}/tile', operationId: 'deleteSchemaTileSettings', description: 'Remove the stored settings, returning the schema to the defaults. Idempotent, and works when the schema is gone so a leftover row can be cleared.', tags: ['SchemaTileSettings'],
        parameters: [new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 204, description: 'Removed, or there was nothing to remove')])]
    #[Override]
    public function delete_index(): Response
    {
        $this->settings->delete($this->schemaName());
        return $this->deleteResponse();
    }

    /**
     * Required by ApiInterface but unreachable: AcceptableMethods answers 406
     * for POST and PUT before anything is dispatched. There is no create — a
     * schema's settings are a sub-resource that always exists, as the fallbacks.
     *
     * @throws GC2Exception
     */
    #[Override]
    public function post_index(): Response
    {
        throw new GC2Exception('Method not allowed', 406, null, 'NOT_ACCEPTABLE');
    }

    /**
     * Required by ApiInterface but unreachable; see post_index(). PATCH is the
     * write, because a partial update is the whole point: a form sets one field
     * without having to resend the rest.
     *
     * @throws GC2Exception
     */
    #[Override]
    public function put_index(): Response
    {
        throw new GC2Exception('Method not allowed', 406, null, 'NOT_ACCEPTABLE');
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        if (Input::getMethod() === 'patch') {
            $this->validateRequest(self::getAssert('patch'), Input::getBody(), 'patch');
        }
    }

    /**
     * Every field optional, null allowed as "remove this", nothing else accepted.
     *
     * The ranges are not politeness: this config is one file per database, so a
     * metatile of 0 would make MapCache reject the whole document and every
     * tileset in that database would stop serving. s3_tile_set is a single path
     * segment because it is interpolated into the S3 object URL.
     */
    static public function getAssert(string $method = 'patch'): Assert\Collection
    {
        $nullOr = fn(Constraint ...$c) => new Assert\Optional(
            new Assert\AtLeastOneOf([new Assert\IsNull(), new Assert\Sequentially($c)])
        );
        return new Assert\Collection(
            fields: [
                'cache' => $nullOr(new Assert\Choice(
                    choices: Mapcachefile::SCHEMA_CACHES,
                    message: 'cache must be one of: ' . implode(', ', Mapcachefile::SCHEMA_CACHES))),
                'format' => $nullOr(new Assert\Choice(
                    choices: Mapcachefile::SCHEMA_INPUT_FORMATS,
                    message: 'format must be one of: ' . implode(', ', Mapcachefile::SCHEMA_INPUT_FORMATS))),
                'ttl' => $nullOr(new Assert\Type('integer'), new Assert\Positive()),
                'auto_expire' => $nullOr(new Assert\Type('integer'), new Assert\Positive()),
                'meta_size' => $nullOr(new Assert\Type('integer'), new Assert\Range(min: 1, max: 16)),
                'meta_buffer' => $nullOr(new Assert\Type('integer'), new Assert\Range(min: 0, max: 512)),
                // A plain path segment, and not a dot-only one: libcurl normalises
                // '.' and '..' away, so those would put this schema's tiles at the
                // bucket root, over every other schema's objects.
                's3_tile_set' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 255),
                    new Assert\Regex('/^[A-Za-z0-9_\-.]+$/'), new Assert\Regex('/[^.]/')),
                // "]]>" would close the CDATA section these are written into and let
                // the rest be parsed as MapCache configuration. renderTileset()
                // escapes it, but there is no reason to store it either.
                'title' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 255),
                    new Assert\Regex(pattern: '/\]\]>/', match: false, message: 'title must not contain "]]>"')),
                'abstract' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 2048),
                    new Assert\Regex(pattern: '/\]\]>/', match: false, message: 'abstract must not contain "]]>"')),
            ],
            allowExtraFields: false,
        );
    }
}
