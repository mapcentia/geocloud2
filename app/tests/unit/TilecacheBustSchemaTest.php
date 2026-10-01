<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\controllers\Tilecache;
use app\inc\Connection;
use app\models\SchemaSettings;
use Codeception\Test\Unit;

/**
 * Tilecache::cacheBackendFor() — which backend "clear cache" deletes from.
 *
 * A name with no dot is a merged per-schema tileset, so its backend comes from
 * settings.schema_settings, not from a layer's def and not from the install
 * default. Without this, clearing a schema tileset configured for disk or s3
 * looks in sqlite, finds nothing, and reports success — a button that lies.
 */
class TilecacheBustSchemaTest extends Unit
{
    protected UnitTester $tester;
    private Connection $connection;
    private SchemaSettings $settings;
    private string $schema;

    private function installDefault(): string
    {
        return !empty(app\conf\App::$param['mapCache']['type']) ? app\conf\App::$param['mapCache']['type'] : 'sqlite';
    }

    protected function _before(): void
    {
        $this->connection = new Connection(database: 'mydb');
        $this->settings = new SchemaSettings(connection: $this->connection);
        $this->schema = 'zz_bust_' . uniqid();
    }

    protected function _after(): void
    {
        $this->settings->delete($this->schema);
    }

    public function testASchemaWithoutSettingsUsesTheInstallDefault(): void
    {
        $this->assertSame($this->installDefault(), Tilecache::cacheBackendFor($this->schema, $this->connection));
    }

    public function testASchemaWithSettingsUsesItsOwnBackend(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'disk']);
        $this->assertSame('disk', Tilecache::cacheBackendFor($this->schema, $this->connection));
    }

    /**
     * The vector tileset of a schema is <schema>.mvt — it has a dot but is not a
     * layer, so the suffix has to be stripped before the lookup.
     */
    public function testTheVectorTilesetOfASchemaResolvesToTheSameBackend(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'disk']);
        $this->assertSame('disk', Tilecache::cacheBackendFor($this->schema . '.mvt', $this->connection));
    }

    /**
     * The layer branch — a name that does contain a dot — is deliberately not
     * exercised here: it calls Layer::getAll(), which faults under the CLI on a
     * null cache item (pre-existing, unrelated to this change). It is unchanged
     * behaviour, and MapcacheDeleteApiCest and MapcacheWipeApiCest cover it from
     * the web context where that cache exists.
     *
     * What this test can pin is that a schema name never reaches that branch,
     * which is the whole point of the ordering: a GC2 layer key is always
     * schema.table, so a bare name cannot be a layer.
     */
    public function testASchemaNameNeverReachesTheLayerLookup(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'memcache']);
        // Would raise "Call to a member function set() on null" if it consulted
        // the Layer model first, as the original bust() did.
        $this->assertSame('memcache', Tilecache::cacheBackendFor($this->schema, $this->connection));
        $this->assertSame('memcache', Tilecache::cacheBackendFor($this->schema . '.json', $this->connection));
    }

    /**
     * The clear-cache endpoint's schema mode is DELETE
     * /controllers/tilecache/schema/<schema>, where part(4) is the literal word
     * "schema" and part(5) is the name. It used to resolve the backend by looking
     * part(4) — the word "schema" — up as a layer, so it always got the install
     * default: with cache "s3" or "memcache" on a schema the switch matched no
     * branch and the method returned an empty response, reporting nothing at all
     * while deleting nothing. This pins the resolution those two segments imply.
     */
    public function testTheSchemaModeOfTheEndpointResolvesTheSchemaNotTheWordSchema(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'disk']);
        // What delete_index() must ask about: part(5), the schema.
        $this->assertSame('disk', Tilecache::cacheBackendFor($this->schema, $this->connection));
        // What it used to ask about: the literal segment, which is no layer and no
        // schema, so it can only ever answer the install default.
        $this->assertSame($this->installDefault(), Tilecache::cacheBackendFor('schema', $this->connection));
        $this->assertNotSame(
            Tilecache::cacheBackendFor('schema', $this->connection),
            Tilecache::cacheBackendFor($this->schema, $this->connection),
            'if these agreed, this test could not tell the two readings apart'
        );
    }
}
