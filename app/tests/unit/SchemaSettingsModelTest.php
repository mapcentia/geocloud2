<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\models\SchemaSettings;
use Codeception\Test\Unit;

/**
 * settings.schema_settings, the row behind the merged per-schema tileset's
 * configuration. The row outlives its schema on purpose (spec §3.3), so these
 * tests use schema names that do not exist in the database.
 */
class SchemaSettingsModelTest extends Unit
{
    protected UnitTester $tester;
    private SchemaSettings $model;
    private string $schema;

    protected function _before(): void
    {
        $this->model = new SchemaSettings(connection: new Connection(database: 'mydb'));
        $this->schema = 'zz_test_' . uniqid();
    }

    protected function _after(): void
    {
        $this->model->delete($this->schema);
    }

    public function testAnUnknownSchemaHasNoRow(): void
    {
        $this->assertNull($this->model->get($this->schema));
    }

    /**
     * Key order is asserted canonically on purpose: jsonb normalises its own key
     * order (by length, then bytewise), so insertion order is not a behaviour the
     * column offers and a test that demanded it would be testing PostgreSQL.
     */
    public function testPatchCreatesThenMerges(): void
    {
        $row = $this->model->patch($this->schema, ['cache' => 's3', 'ttl' => 86400]);
        $this->assertSame($this->schema, $row['schema']);
        $this->assertEqualsCanonicalizing(['cache' => 's3', 'ttl' => 86400], json_decode($row['def'], true));

        // A second patch merges rather than replaces.
        $row = $this->model->patch($this->schema, ['meta_size' => 5]);
        $this->assertEqualsCanonicalizing(['cache' => 's3', 'ttl' => 86400, 'meta_size' => 5], json_decode($row['def'], true));
    }

    public function testAnExplicitNullRemovesOneKey(): void
    {
        $this->model->patch($this->schema, ['cache' => 's3', 'ttl' => 86400]);
        $row = $this->model->patch($this->schema, ['ttl' => null]);
        $this->assertEqualsCanonicalizing(['cache' => 's3'], json_decode($row['def'], true));
    }

    public function testAllIsKeyedBySchema(): void
    {
        $this->model->patch($this->schema, ['cache' => 'disk']);
        $all = $this->model->all();
        $this->assertArrayHasKey($this->schema, $all);
        $this->assertSame('disk', json_decode($all[$this->schema]['def'], true)['cache']);
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->model->patch($this->schema, ['cache' => 'disk']);
        $this->model->delete($this->schema);
        $this->assertNull($this->model->get($this->schema));
        $this->model->delete($this->schema);   // again: must not raise
        $this->assertNull($this->model->get($this->schema));
    }

    /**
     * Review Focus 5: the schema name is the primary key and ends up as a
     * tileset name in the config. A name with mixed case and a dash must round
     * trip exactly — no lowercasing, no trimming.
     */
    public function testAnUnusualSchemaNameRoundTrips(): void
    {
        $odd = 'Zz-Test_' . uniqid();
        try {
            $row = $this->model->patch($odd, ['cache' => 'disk']);
            $this->assertSame($odd, $row['schema']);
            $this->assertSame($odd, $this->model->get($odd)['schema']);
        } finally {
            $this->model->delete($odd);
        }
    }

    /**
     * Mapcachefile::generate() calls all() unconditionally, so on an install where
     * the code lands before migration/run.php has created the table, raising here
     * would stop EVERY database from regenerating a config that worked before.
     * Proven against a connection to a database that has no settings schema at all.
     */
    public function testAllAnswersEmptyWhenTheTableIsNotThereYet(): void
    {
        $model = new SchemaSettings(connection: new Connection(database: 'postgres'));
        $this->assertSame([], $model->all());
    }
}
