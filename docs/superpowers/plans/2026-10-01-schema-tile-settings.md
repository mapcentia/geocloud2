# Schema-level tile settings — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an operator configure the merged per-schema tileset — cache backend, format, TTL, metatiles, S3 path, title, abstract — the way they already can for a single layer.

**Architecture:** A new `settings.schema_settings` table holds one `def` JSONB per schema. A pure resolver, `Mapcachefile::schemaSettings()`, turns a row (or no row) into the values the per-schema loop already passes to `renderTileset()`, with today's hardcoded literals as the fallbacks. A v4 resource at `api/v4/schemas/{schema}/tile` reads and writes the row, and `Tilecache::bust()` learns to resolve a schema tileset's backend from the same table.

**Tech Stack:** PHP 8.4, PostgreSQL/PostGIS, MapCache, Codeception (unit + api), Symfony Validator, swagger-php attributes.

**Spec:** `docs/superpowers/specs/2026-10-01-schema-tile-settings-design.md`

## Global Constraints

- **Worker-safe (AGENTS.md §4):** thread an explicit `app\inc\Connection` into every model. Never call `Database::setDb()`, never read `\app\conf\Connection::$param['postgisdb']` in new code. Pass `connection:` on to every model you construct.
- **One config file per database.** `app/wms/mapcache/<db>.xml` holds every tileset in the database. A single invalid value written by this feature breaks *every* tileset in that database, not just one schema's. That is why validation happens at the API, with a positive list or a positive regex, before the value can ever reach the file.
- **The fallbacks are the schema loop's current literals, not the layer's:** `expires 60`, `metaSize 3`, `metaBuffer 0`, `cache` = `App::$param['mapCache']['type'] ?: 'sqlite'`, image format `PNG`, vector format `MVT`, `title` = the schema name, `abstract` empty, no `auto_expire`. `layerSettings()` uses `30` and `null` — copying it changes every install's config silently.
- **Allowed values.** `cache`: `sqlite`, `disk`, `memcache`, `s3`. `format`: `PNG`, `jpeg_low`, `jpeg_medium`, `jpeg_high`, `MVT`. `JSON` is rejected — there is no merged `<schema>.json` tileset for it to apply to.
- **Settings outlive their schema** (spec §3.3). `Schema`'s delete must not touch `settings.schema_settings`. `PATCH` requires the schema to exist; `GET` and `DELETE` must work without it.
- **Migration DDL is idempotent** and goes in `app/migration/Sql.php` before the closing `return $sqls;`, using `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`.
- **Never commit or print anything from `app/conf/App.php`.** New settings, if any, go in the template `docker/conf/gc2/App.php` with a comment.
- **Host PHP cannot parse this codebase** (PHP 8.4 syntax). Lint and test inside the dev container, repo mounted at `/var/www/geocloud2`:
  `docker exec -w /var/www/geocloud2/app docker-dev-1 php -l <file>`
  `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <TestName>`
  `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api <CestName>`
  One suite per command. `error_log()` lands in `docker logs docker-dev-1`. Postgres: `docker exec postgres psql -U mydb -d mydb`. API at `http://localhost:8080`.
- **Never stop, restart or interfere with any container, and never DROP a database.** Clean up rows and files you create.
- **Commit on the current branch. Never push.** End every commit message with the attribution lines the session provides.

## Review Focus

These are the inputs the spec implies but does not dwell on, most likely to bite first. Each one's test is added to the task that owns the code.

1. **`meta_size: 0` or a negative value** — would emit `<metatile>0 0</metatile>` and MapCache rejects the whole document, taking down every tileset in the database. Must be refused at the API (Task 3) *and* defended in the resolver (Task 2), because a hand-edited row bypasses the API.
2. **`s3_tile_set` containing `/`, `..` or whitespace** — it is interpolated into the cache's object URL, so it can redirect writes to another prefix. Positive regex at the API (Task 3) and in the resolver (Task 2).
3. **A `def` that is not a JSON object** — `null`, a JSON array, or a scalar, from a hand-edited row. Must fall back to every default rather than raise (Task 2).
4. **`ttl` below the floor** — `0`, `5`, or negative. The spec says floored at 30, like `layerSettings()` (Task 2).
5. **A schema name that is not a plain identifier** — mixed case, a dash, a leading digit, or 300 characters. The name is the primary key and reaches the config as a tileset name (Task 1 for the column width, Task 3 for the request).

---

## File Structure

| Path | Responsibility |
|---|---|
| `app/migration/Sql.php` | The `settings.schema_settings` DDL, idempotent, appended to the upgrade list. |
| `app/models/SchemaSettings.php` | **Create.** The row: read one, read all keyed by schema, merge values into `def`, delete. Nothing about XML or HTTP. |
| `app/models/Mapcachefile.php` | **Modify.** Add the pure `schemaSettings()` resolver; the per-schema loop passes its values to the existing `renderTileset()`/`renderS3Cache()`. |
| `app/api/v4/controllers/SchemaTileSettings.php` | **Create.** The v4 resource: GET/PATCH/DELETE/OPTIONS/HEAD, validation, OpenAPI. |
| `app/controllers/Tilecache.php` | **Modify.** `bust()` resolves a schema tileset's backend from the new table. |
| `app/tests/unit/MapcachefileRenderTest.php` | **Modify.** The resolver's cases, beside the existing `layerSettings()` ones. |
| `app/tests/unit/SchemaSettingsModelTest.php` | **Create.** The model against Postgres. |
| `app/tests/unit/MapcacheConfigUnchangedTest.php` | **Create.** The byte-identical guard. |
| `app/tests/api/SchemaTileSettingsV4ApiCest.php` | **Create.** The endpoint end to end. |

---

## Task 1: The table and the model

**Files:**
- Modify: `app/migration/Sql.php` (append before the final `return $sqls;`)
- Create: `app/models/SchemaSettings.php`
- Test: `app/tests/unit/SchemaSettingsModelTest.php`

**Interfaces:**
- Consumes: `app\inc\Model` (`prepare()`, `execute()`, `fetchRow()`, `fetchAll()`), `app\inc\Connection`.
- Produces:
  ```php
  namespace app\models;
  final class SchemaSettings extends Model {
      public function __construct(?Connection $connection = null)
      public function get(string $schema): ?array          // the row, or null
      public function all(): array                          // [schema => row], every row
      public function patch(string $schema, array $values): array  // merge into def, null removes a key; returns the row
      public function delete(string $schema): void          // no-op when absent
  }
  ```
  A row is `['schema' => string, 'def' => ?string (JSON), 'created' => string]`.

- [ ] **Step 1: Add the DDL**

In `app/migration/Sql.php`, immediately before the closing `return $sqls;` of the upgrade list (the line after the `started_jobs_db_started_idx` index):

```php
        // Per-schema tile settings for the merged <schema> tileset
        // (docs/superpowers/specs/2026-10-01-schema-tile-settings-design.md).
        // The row deliberately outlives its schema: dropping and recreating a
        // schema is normal here, and the settings should survive it. A row for a
        // schema that does not exist is never read, because Mapcachefile's
        // per-schema loop iterates the schemas that actually have layers.
        $sqls[] = "CREATE TABLE IF NOT EXISTS settings.schema_settings
                    (
                      schema  CHARACTER VARYING(255)    NOT NULL  PRIMARY KEY,
                      def     JSONB,
                      created TIMESTAMP WITH TIME ZONE  NOT NULL  DEFAULT now()
                    )";
```

`schema` is a non-reserved keyword in PostgreSQL and works unquoted as a column
name; this was verified against the running database. 255 characters matches
`f_table_schema` in `settings.geometry_columns_join`.

- [ ] **Step 2: Apply the migration and confirm the table exists**

`app/migration/run.php` is what applies the list — it walks every database on the
node and runs each statement in its own transaction, tolerating the ones that
already applied. The same list is reachable over HTTP as
`GET /api/v3/admin/migrations`.

```bash
docker exec -w /var/www/geocloud2/app docker-dev-1 php -f migration/run.php 2>&1 | tail -5
docker exec postgres psql -U mydb -d mydb -c "\d settings.schema_settings"
```
Expected: the three columns, with `schema` as the primary key. Do not create the
table by hand — the DDL you wrote is the thing being tested, and a hand-made
table would hide a typo in it until someone else upgraded.

Note what `run.php` does, so its output does not alarm you: it prints a line per
failing statement, and on an existing install most statements fail because they
already applied. Only your new `CREATE TABLE IF NOT EXISTS` matters here, and it
should not appear as a failure at all.

- [ ] **Step 3: Write the failing model test**

Create `app/tests/unit/SchemaSettingsModelTest.php`:

```php
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

    public function testPatchCreatesThenMerges(): void
    {
        $row = $this->model->patch($this->schema, ['cache' => 's3', 'ttl' => 86400]);
        $this->assertSame($this->schema, $row['schema']);
        $this->assertSame(['cache' => 's3', 'ttl' => 86400], json_decode($row['def'], true));

        // A second patch merges rather than replaces.
        $row = $this->model->patch($this->schema, ['meta_size' => 5]);
        $this->assertSame(['cache' => 's3', 'ttl' => 86400, 'meta_size' => 5], json_decode($row['def'], true));
    }

    public function testAnExplicitNullRemovesOneKey(): void
    {
        $this->model->patch($this->schema, ['cache' => 's3', 'ttl' => 86400]);
        $row = $this->model->patch($this->schema, ['ttl' => null]);
        $this->assertSame(['cache' => 's3'], json_decode($row['def'], true));
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
}
```

- [ ] **Step 4: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SchemaSettingsModelTest`
Expected: FAIL — `Class "app\models\SchemaSettings" not found`.

- [ ] **Step 5: Write the model**

Create `app/models/SchemaSettings.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\inc\Connection;
use app\inc\Model;

/**
 * settings.schema_settings — the tile settings of the merged per-schema tileset
 * (<schema> and <schema>.mvt), in a def JSONB of the same shape as a layer's.
 *
 * The row deliberately outlives its schema: dropping and recreating a schema is
 * a normal workflow, and the settings should survive it. That is safe by
 * construction, because Mapcachefile's per-schema loop iterates the schemas
 * that actually have layers, so a row for an absent schema is never read.
 *
 * This class knows nothing about XML or HTTP: Mapcachefile resolves the row into
 * config values, and the v4 controller validates what may enter it.
 */
final class SchemaSettings extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $schema): ?array
    {
        $res = $this->prepare('SELECT schema, def, created FROM settings.schema_settings WHERE schema = :schema');
        $this->execute($res, ['schema' => $schema]);
        return $this->fetchRow($res) ?: null;
    }

    /**
     * Every row, keyed by schema name. Mapcachefile reads this once per config
     * generation rather than querying per schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $res = $this->prepare('SELECT schema, def, created FROM settings.schema_settings');
        $this->execute($res);
        $out = [];
        foreach ($this->fetchAll($res, 'assoc') as $row) {
            $out[$row['schema']] = $row;
        }
        return $out;
    }

    /**
     * Merge $values into the schema's def, creating the row when absent. A value
     * of null removes that key, returning it to its fallback — which is why this
     * cannot be a plain jsonb_concat in SQL.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed> the stored row
     */
    public function patch(string $schema, array $values): array
    {
        $current = $this->get($schema);
        $def = $current && $current['def'] ? (array)json_decode($current['def'], true) : [];
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($def[$key]);
            } else {
                $def[$key] = $value;
            }
        }
        $res = $this->prepare('INSERT INTO settings.schema_settings (schema, def) VALUES (:schema, :def)
                               ON CONFLICT (schema) DO UPDATE SET def = :def
                               RETURNING schema, def, created');
        $this->execute($res, ['schema' => $schema, 'def' => json_encode($def)]);
        return $this->fetchRow($res);
    }

    public function delete(string $schema): void
    {
        $res = $this->prepare('DELETE FROM settings.schema_settings WHERE schema = :schema');
        $this->execute($res, ['schema' => $schema]);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SchemaSettingsModelTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Confirm no rows are left behind**

Run: `docker exec postgres psql -U mydb -d mydb -tAc "select count(*) from settings.schema_settings where schema like 'zz_test_%' or schema like 'Zz-Test_%'"`
Expected: `0`.

- [ ] **Step 8: Commit**

```bash
git add app/migration/Sql.php app/models/SchemaSettings.php app/tests/unit/SchemaSettingsModelTest.php
git commit -m "feat(mapcache): settings.schema_settings, the per-schema tile settings row"
```

---

## Task 2: The resolver, and the config that must not change

**Files:**
- Modify: `app/models/Mapcachefile.php` (add `schemaSettings()`; rewrite the per-schema loop at the end of `generate()`)
- Test: `app/tests/unit/MapcachefileRenderTest.php` (add cases beside the existing `layerSettings()` ones)
- Test: `app/tests/unit/MapcacheConfigUnchangedTest.php` (create)

**Interfaces:**
- Consumes: `SchemaSettings::all()` from Task 1; the existing `Mapcachefile::renderTileset()`, `renderWmsSource()`, `renderS3Cache()`.
- Produces:
  ```php
  public static function schemaSettings(?array $row, string $defaultCache, string $schema): array
  // returns:
  // [
  //   'cache'        => string,   // sqlite|disk|memcache|s3
  //   'imageFormat'  => string,   // PNG|jpeg_low|jpeg_medium|jpeg_high
  //   'vectorFormat' => string,   // MVT
  //   'expires'      => int,      // >= 30
  //   'metaSize'     => ?int,     // null or >= 1
  //   'metaBuffer'   => ?int,     // >= 0
  //   'autoExpire'   => ?int,     // null when unset
  //   's3TileSet'    => ?string,
  //   'title'        => string,
  //   'abstract'     => string,
  // ]
  ```

- [ ] **Step 1: Capture the current config as a fixture**

The guard this task rests on is that an install with no rows generates exactly
what it generates today. Capture that before touching the generator:

```bash
docker exec postgres psql -U mydb -d mydb -tAc "select count(*) from settings.schema_settings"
docker exec docker-dev-1 php -r '
require "/var/www/geocloud2/app/vendor/autoload.php";
require_once "/var/www/geocloud2/app/conf/App.php";
$m = new app\models\Mapcachefile(new app\inc\Connection(database: "mydb"));
file_put_contents("/var/www/geocloud2/app/tests/_data/mapcache_mydb_before.xml", $m->generate());
echo strlen(file_get_contents("/var/www/geocloud2/app/tests/_data/mapcache_mydb_before.xml")), "\n";
'
```
The table must be empty for this to be a valid baseline — the first command must
print `0`. Record the byte count in your report.

- [ ] **Step 2: Write the failing resolver tests**

Append to `app/tests/unit/MapcachefileRenderTest.php`:

```php
    /**
     * The fallbacks are the per-schema loop's current literals — expires 60,
     * metatile 3, metabuffer 0, PNG and MVT — and NOT layerSettings()' 30 and
     * null. Getting this wrong changes every existing install's config silently.
     */
    public function testSchemaSettingsDefaults(): void
    {
        $set = Mapcachefile::schemaSettings(null, 'sqlite', 'dagi');
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('PNG', $set['imageFormat']);
        $this->assertSame('MVT', $set['vectorFormat']);
        $this->assertSame(60, $set['expires']);
        $this->assertSame(3, $set['metaSize']);
        $this->assertSame(0, $set['metaBuffer']);
        $this->assertNull($set['autoExpire']);
        $this->assertNull($set['s3TileSet']);
        $this->assertSame('dagi', $set['title']);
        $this->assertSame('', $set['abstract']);
    }

    public function testSchemaSettingsFromDef(): void
    {
        $def = json_encode(['cache' => 'disk', 'ttl' => 86400, 'meta_size' => 5, 'meta_buffer' => 10,
            'auto_expire' => 3600, 'title' => 'Danmarks administrative geografi', 'abstract' => 'DAGI']);
        $set = Mapcachefile::schemaSettings(['schema' => 'dagi', 'def' => $def], 'sqlite', 'dagi');
        $this->assertSame('disk', $set['cache']);
        $this->assertSame(86400, $set['expires']);
        $this->assertSame(5, $set['metaSize']);
        $this->assertSame(10, $set['metaBuffer']);
        $this->assertSame(3600, $set['autoExpire']);
        $this->assertSame('Danmarks administrative geografi', $set['title']);
        $this->assertSame('DAGI', $set['abstract']);
    }

    /**
     * A schema has two tilesets of different kinds, so one stored format can
     * only apply to the one it matches. A stored MVT must not turn the image
     * tileset into MVT, and a stored jpeg must not touch the vector one.
     */
    public function testSchemaFormatAppliesOnlyToTheMatchingTileset(): void
    {
        $mvt = Mapcachefile::schemaSettings(['def' => json_encode(['format' => 'MVT'])], 'sqlite', 'dagi');
        $this->assertSame('PNG', $mvt['imageFormat']);
        $this->assertSame('MVT', $mvt['vectorFormat']);

        $jpeg = Mapcachefile::schemaSettings(['def' => json_encode(['format' => 'jpeg_medium'])], 'sqlite', 'dagi');
        $this->assertSame('jpeg_medium', $jpeg['imageFormat']);
        $this->assertSame('MVT', $jpeg['vectorFormat']);
    }

    /** Review Focus 4: ttl is floored at 30, as layerSettings() floors it. */
    public function testSchemaTtlIsFloored(): void
    {
        foreach ([0, 5, -100] as $ttl) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['ttl' => $ttl])], 'sqlite', 'dagi');
            $this->assertSame(30, $set['expires'], "ttl $ttl must floor to 30");
        }
    }

    /**
     * Review Focus 1: the config is ONE file for the whole database, so
     * <metatile>0 0</metatile> from a hand-edited row would make MapCache reject
     * the document and take down every tileset in that database. The resolver is
     * the second line of defence behind the API's validation.
     */
    public function testSchemaMetaSizeBelowOneFallsBack(): void
    {
        foreach ([0, -1] as $bad) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['meta_size' => $bad])], 'sqlite', 'dagi');
            $this->assertSame(3, $set['metaSize'], "meta_size $bad must fall back to 3");
        }
        $this->assertSame(1, Mapcachefile::schemaSettings(['def' => json_encode(['meta_size' => 1])], 'sqlite', 'dagi')['metaSize']);
    }

    /**
     * Review Focus 2: s3_tile_set is interpolated into the cache's object URL,
     * so a value with a slash, ".." or whitespace could redirect writes to
     * another prefix. Refuse it in the resolver as well as at the API.
     */
    public function testSchemaS3TileSetRejectsPathCharacters(): void
    {
        foreach (['other/prefix', '../escape', 'with space', 'semi;colon'] as $bad) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['s3_tile_set' => $bad])], 'sqlite', 'dagi');
            $this->assertNull($set['s3TileSet'], "s3_tile_set '$bad' must be ignored");
        }
        $this->assertSame('dagi-tiles', Mapcachefile::schemaSettings(['def' => json_encode(['s3_tile_set' => 'dagi-tiles'])], 'sqlite', 'dagi')['s3TileSet']);
    }

    /**
     * Review Focus 3: a hand-edited row can hold a def that is not an object.
     * Every one of these must give the plain defaults rather than raise.
     */
    public function testSchemaSettingsSurvivesANonObjectDef(): void
    {
        foreach ([null, '', 'null', '[]', '[1,2]', '"a string"', '42', 'not json at all'] as $def) {
            $set = Mapcachefile::schemaSettings(['def' => $def], 'sqlite', 'dagi');
            $this->assertSame(60, $set['expires'], 'def ' . var_export($def, true) . ' must give the defaults');
            $this->assertSame('sqlite', $set['cache']);
            $this->assertSame(3, $set['metaSize']);
        }
    }

    /** An unknown cache or format in a hand-edited row falls back rather than reaching the file. */
    public function testSchemaUnknownCacheAndFormatFallBack(): void
    {
        $set = Mapcachefile::schemaSettings(['def' => json_encode(['cache' => 'redis', 'format' => 'WEBP'])], 'sqlite', 'dagi');
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('PNG', $set['imageFormat']);
        $this->assertSame('MVT', $set['vectorFormat']);
    }
```

- [ ] **Step 3: Run them to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit MapcachefileRenderTest`
Expected: FAIL — `Call to undefined method app\models\Mapcachefile::schemaSettings()`.

- [ ] **Step 4: Write the resolver**

In `app/models/Mapcachefile.php`, immediately after `layerSettings()`:

```php
    /** The cache backends a schema may name. */
    public const SCHEMA_CACHES = ['sqlite', 'disk', 'memcache', 's3'];
    /** Image formats for the <schema> tileset. */
    public const SCHEMA_IMAGE_FORMATS = ['PNG', 'jpeg_low', 'jpeg_medium', 'jpeg_high'];
    /** Raw formats for the <schema>.mvt tileset. JSON is excluded: there is no merged .json tileset. */
    public const SCHEMA_VECTOR_FORMATS = ['MVT'];

    /**
     * Resolve a settings.schema_settings row into the values the per-schema loop
     * passes to renderTileset(). Pure — safe to unit test without a database.
     *
     * The fallbacks are the literals this loop used before the settings existed:
     * expires 60, metatile 3, metabuffer 0, PNG and MVT, the schema name as the
     * title, no abstract and no auto_expire. They are deliberately NOT
     * layerSettings()' fallbacks (30, null), because reusing those would change
     * every existing install's generated config without anyone asking.
     *
     * Every value is re-checked here even though the API validates on the way in:
     * a row can be edited by hand, and this config is one file for the whole
     * database, so a single bad value would make MapCache reject the document and
     * take down every tileset in it. Anything unrecognised falls back.
     *
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    public static function schemaSettings(?array $row, string $defaultCache, string $schema): array
    {
        $def = !empty($row['def']) ? json_decode($row['def']) : null;
        $def = $def instanceof stdClass ? $def : new stdClass();

        $format = !empty($def->format) ? (string)$def->format : null;
        $metaSize = isset($def->meta_size) ? (int)$def->meta_size : 3;
        $s3TileSet = !empty($def->s3_tile_set) ? (string)$def->s3_tile_set : null;

        return [
            'cache' => !empty($def->cache) && in_array($def->cache, self::SCHEMA_CACHES, true)
                ? (string)$def->cache : $defaultCache,
            'imageFormat' => $format !== null && in_array($format, self::SCHEMA_IMAGE_FORMATS, true) ? $format : 'PNG',
            'vectorFormat' => $format !== null && in_array($format, self::SCHEMA_VECTOR_FORMATS, true) ? $format : 'MVT',
            'expires' => !empty($def->ttl) ? max(30, (int)$def->ttl) : 60,
            // A metatile below 1 is not a smaller metatile, it is a document
            // MapCache refuses; fall back rather than emit it.
            'metaSize' => $metaSize >= 1 ? $metaSize : 3,
            'metaBuffer' => isset($def->meta_buffer) && (int)$def->meta_buffer >= 0 ? (int)$def->meta_buffer : 0,
            'autoExpire' => !empty($def->auto_expire) ? (int)$def->auto_expire : null,
            // Goes straight into the S3 object URL, so only a plain segment.
            's3TileSet' => $s3TileSet !== null && preg_match('/^[A-Za-z0-9_\-.]+$/', $s3TileSet) ? $s3TileSet : null,
            'title' => !empty($def->title) ? (string)$def->title : $schema,
            'abstract' => !empty($def->abstract) ? (string)$def->abstract : '',
        ];
    }
```

- [ ] **Step 5: Run the resolver tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit MapcachefileRenderTest`
Expected: PASS — the 9 existing tests plus the 8 new ones.

- [ ] **Step 6: Write the byte-identical guard**

Create `app/tests/unit/MapcacheConfigUnchangedTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\models\Mapcachefile;
use app\models\SchemaSettings;
use Codeception\Test\Unit;

/**
 * The one test that protects every existing install: with no schema settings
 * stored, the generated MapCache document must be exactly what it was before
 * schema settings existed. The fixture was captured from the previous code.
 *
 * If this fails after a change to the per-schema loop, the likely cause is
 * reusing layerSettings()' fallbacks (expires 30, metaSize null) instead of the
 * loop's own (60 and 3).
 */
class MapcacheConfigUnchangedTest extends Unit
{
    protected UnitTester $tester;

    public function testAnInstallWithNoSchemaSettingsGeneratesTheSameDocument(): void
    {
        $fixture = codecept_data_dir('mapcache_mydb_before.xml');
        if (!file_exists($fixture)) {
            $this->markTestSkipped('baseline fixture not captured');
        }
        $settings = new SchemaSettings(connection: new Connection(database: 'mydb'));
        if ($settings->all() !== []) {
            $this->markTestSkipped('settings.schema_settings is not empty, so this is not a clean baseline');
        }
        $generated = (new Mapcachefile(new Connection(database: 'mydb')))->generate();
        $this->assertSame(file_get_contents($fixture), $generated,
            'the generated config changed while no schema settings are stored');
    }
}
```

- [ ] **Step 7: Rewrite the per-schema loop**

In `app/models/Mapcachefile.php`, read the settings once before the layer loop
(beside the other `generate()` locals):

```php
        $schemaSettingRows = (new SchemaSettings(connection: $this->connection))->all();
```

Add `use app\models\SchemaSettings;` to the file's imports — or, since this is
already the `app\models` namespace, no import is needed; reference it directly.

Then replace the per-schema loop with:

```php
        // Merged per-schema source/tileset with all the schema's layers. Its
        // settings come from settings.schema_settings; with no row the resolver
        // returns exactly the literals this loop used before.
        foreach ($layersPerSchema as $schema => $layers) {
            $s .= "\n  <!-- $schema -->\n";
            $set = self::schemaSettings($schemaSettingRows[$schema] ?? null, $defaultCache, $schema);
            $cache = $set['cache'];
            if ($cache == 's3' && $set['s3TileSet']) {
                $cache = "s3_" . $schema;
                $s .= self::renderS3Cache($cache, $set['s3TileSet'], perTileSet: false);
            }
            $schemaUrl = empty(App::$param['useQgisForMergedLayers'][$schema])
                ? $this->mapserverUrl($schema, 'wms')
                : App::$param['mapCache']['wmsHost'] . "/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2/app/wms/qgsfiles/parsed_"
                . App::$param['useQgisForMergedLayers'][$schema] . "&transparent=true";
            $s .= self::renderWmsSource($schema, 'image/png', implode(',', $layers), $schemaUrl);
            $s .= self::renderTileset($schema, $schema, $cache, $gridNames, $set['imageFormat'], $set['expires'],
                metaSize: $set['metaSize'], metaBuffer: $set['metaBuffer'], autoExpire: $set['autoExpire'],
                title: $set['title'], abstract: $set['abstract']);
            if ($mvtEnabled) {
                $s .= self::renderWmsSource("$schema.mvt", 'mvt', implode(',', $layers), $this->mapserverUrl($schema, 'wfs'));
                $s .= self::renderTileset("$schema.mvt", "$schema.mvt", $cache, $gridNames, $set['vectorFormat'], $set['expires'],
                    autoExpire: $set['autoExpire'], title: $set['title'], abstract: $set['abstract']);
            }
        }
```

Two things to keep exactly as they were, or the byte-identical test will fail and
it will be right to: the image tileset passes no `wgs84bbox` (the old call did
not), and the `.mvt` tileset passes no `metaSize`/`metaBuffer` (the old call did
not, so those stay at `renderTileset()`'s own `null` defaults and emit nothing).

- [ ] **Step 8: Run both unit suites to verify the config is unchanged**

Run:
```bash
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit MapcachefileRenderTest
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit MapcacheConfigUnchangedTest
```
Expected: both PASS, and the second must have **run**, not skipped — if it
skipped, the fixture is missing or the table is not empty, and the guard proved
nothing. Say in your report which it was.

- [ ] **Step 9: Prove the settings actually reach the document**

```bash
docker exec postgres psql -U mydb -d mydb -c "insert into settings.schema_settings (schema, def) values ('dagi', '{\"cache\":\"disk\",\"ttl\":86400,\"meta_size\":5,\"title\":\"DAGI\"}') on conflict (schema) do update set def = excluded.def"
docker exec docker-dev-1 php -r '
require "/var/www/geocloud2/app/vendor/autoload.php";
require_once "/var/www/geocloud2/app/conf/App.php";
$xml = (new app\models\Mapcachefile(new app\inc\Connection(database: "mydb")))->generate();
preg_match("/<tileset name=\"dagi\">.*?<\/tileset>/s", $xml, $m);
echo $m[0], "\n";
'
docker exec postgres psql -U mydb -d mydb -c "delete from settings.schema_settings where schema = 'dagi'"
```
Expected: `<cache>disk</cache>`, `<expires>86400</expires>`,
`<metatile>5 5</metatile>` and `<title><![CDATA[DAGI]]></title>`. Quote the
output in your report, and confirm the row is deleted afterwards.

- [ ] **Step 10: Prove it through the file the server actually reads**

Step 9 called `generate()` directly. Spec §8 asks for one check through the real
artifact, because the tileseeder round shipped two Criticals behind tests that
never touched it. `app/scripts/mapcache_conf.php` is what cron runs to write
`app/wms/mapcache/<db>.xml`, and `Mapcachefile::write()` only writes when the
content changed — so this also proves the change is detected:

```bash
docker exec postgres psql -U mydb -d mydb -c "insert into settings.schema_settings (schema, def) values ('dagi','{\"cache\":\"disk\",\"ttl\":7200}') on conflict (schema) do update set def = excluded.def"
docker exec docker-dev-1 php -f /var/www/geocloud2/app/scripts/mapcache_conf.php > /dev/null 2>&1
docker exec docker-dev-1 sh -c "grep -A 8 '<tileset name=\"dagi\">' /var/www/geocloud2/app/wms/mapcache/mydb.xml"
docker exec postgres psql -U mydb -d mydb -c "delete from settings.schema_settings where schema = 'dagi'"
docker exec docker-dev-1 php -f /var/www/geocloud2/app/scripts/mapcache_conf.php > /dev/null 2>&1
docker exec docker-dev-1 sh -c "grep -A 8 '<tileset name=\"dagi\">' /var/www/geocloud2/app/wms/mapcache/mydb.xml"
```
Expected: the first grep shows `<cache>disk</cache>` and `<expires>7200</expires>`
in the file on disk; the second, after the row is deleted, shows the defaults
back. Quote both. If `mapcache_conf.php` takes arguments on this install, read
its head and say what you passed.

Then confirm the config is still valid where it counts — a request through the
MapCache proxy, which parses the file:

```bash
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8080/api/v4/mapcache/database/mydb/wmts?SERVICE=WMTS&REQUEST=GetCapabilities"
```
Expected: `200`. A malformed document would fail here and nowhere in the unit
tests.

- [ ] **Step 11: Commit**

```bash
git add app/models/Mapcachefile.php app/tests/unit/MapcachefileRenderTest.php app/tests/unit/MapcacheConfigUnchangedTest.php app/tests/_data/mapcache_mydb_before.xml
git commit -m "feat(mapcache): the merged per-schema tileset reads its own settings"
```

---

## Task 3: The v4 resource

**Files:**
- Create: `app/api/v4/controllers/SchemaTileSettings.php`
- Test: `app/tests/api/SchemaTileSettingsV4ApiCest.php`

**Interfaces:**
- Consumes: `SchemaSettings` (Task 1); `Mapcachefile::SCHEMA_CACHES`, `SCHEMA_IMAGE_FORMATS`, `SCHEMA_VECTOR_FORMATS` and `schemaSettings()` (Task 2).
- Produces: `GET|PATCH|DELETE|OPTIONS|HEAD /api/v4/schemas/{schema}/tile`, OpenAPI schemas `SchemaTileSettings` (response) and `SchemaTileSettingsInput` (request).

**The one trap in this task.** `AbstractApi::initiate()` throws `404
SCHEMA_NOT_FOUND` for any schema parameter it is given. The spec requires `GET`
and `DELETE` to work for a schema that no longer exists (spec §3.4), so this
controller must **not** route its schema through `initiate()`'s existence check.
Check existence explicitly with `(new Database($this->connection))->doesSchemaExist($name)`
and raise `404` only in `PATCH`.

- [ ] **Step 1: Write the failing Cest**

Create `app/tests/api/SchemaTileSettingsV4ApiCest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

/**
 * GET/PATCH/DELETE /api/v4/schemas/{schema}/tile — the merged per-schema
 * tileset's settings.
 *
 * The settings outlive their schema on purpose, so the interesting cases are
 * asymmetric: PATCH needs the schema, GET and DELETE do not.
 */
class SchemaTileSettingsV4ApiCest
{
    private string $user;
    private string $token;
    private string $subToken;
    private string $schema = 'zz_tile_settings';

    public function _before(ApiTester $I): void
    {
        $this->user = 'tilesettings' . time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v2/user', ['data' => [
            'name' => $this->user, 'email' => $this->user . '@example.com', 'password' => 'Abc12345!',
        ]]);
        $I->sendPost('/api/v4/oauth', [
            'grant_type' => 'password', 'username' => $this->user,
            'password' => 'Abc12345!', 'database' => $this->user, 'client_id' => 'gc2-cli',
        ]);
        $this->token = $I->grabDataFromResponseByJsonPath('$.access_token')[0];
        $I->amBearerAuthenticated($this->token);
        $I->sendPost('/api/v4/schemas', ['name' => $this->schema]);

        // A sub user, to prove the scope.
        $I->sendPost('/api/v4/users', ['name' => 'sub' . $this->user, 'email' => 'sub' . $this->user . '@example.com', 'password' => 'Abc12345!']);
        $I->sendPost('/api/v4/oauth', [
            'grant_type' => 'password', 'username' => 'sub' . $this->user,
            'password' => 'Abc12345!', 'database' => $this->user, 'client_id' => 'gc2-cli',
        ]);
        $this->subToken = $I->grabDataFromResponseByJsonPath('$.access_token')[0] ?? '';
        $I->amBearerAuthenticated($this->token);
    }

    public function _after(ApiTester $I): void
    {
        $I->amBearerAuthenticated($this->token);
        $I->sendDelete('/api/v4/schemas/' . $this->schema . '/tile');
        $I->sendDelete('/api/v4/schemas/' . $this->schema);
    }

    public function shouldFallBackBeforeAnythingIsStored(ApiTester $I): void
    {
        $I->sendGet('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['ttl' => 60, 'meta_size' => 3, 'meta_buffer' => 0, 'format' => 'PNG']);
        $I->assertSame($this->schema, $I->grabDataFromResponseByJsonPath('$.title')[0]);
        $I->assertTrue($I->grabDataFromResponseByJsonPath('$.schema_exists')[0]);
        $I->assertSame([], (array)$I->grabDataFromResponseByJsonPath('$._stored')[0],
            'nothing is stored yet, so _stored must be empty');
    }

    public function shouldStoreMergeAndReadBack(ApiTester $I): void
    {
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['cache' => 'disk', 'ttl' => 86400]);
        $I->seeResponseCodeIs(303);

        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['meta_size' => 5]);
        $I->seeResponseCodeIs(303);

        $I->sendGet('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseContainsJson(['cache' => 'disk', 'ttl' => 86400, 'meta_size' => 5]);
        $stored = (array)$I->grabDataFromResponseByJsonPath('$._stored')[0];
        $I->assertSame(['cache' => 'disk', 'ttl' => 86400, 'meta_size' => 5], $stored,
            '_stored must carry only what was actually set');
    }

    public function shouldRemoveOneKeyWithAnExplicitNull(ApiTester $I): void
    {
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['cache' => 'disk', 'ttl' => 86400]);
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['ttl' => null]);
        $I->sendGet('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseContainsJson(['cache' => 'disk', 'ttl' => 60]);
        $I->assertSame(['cache' => 'disk'], (array)$I->grabDataFromResponseByJsonPath('$._stored')[0]);
    }

    public function shouldRefuseValuesThatWouldBreakTheConfig(ApiTester $I): void
    {
        // Review Focus 1: the config is one file per database, so a bad metatile
        // would break every tileset in it, not just this schema's.
        foreach ([['meta_size' => 0], ['meta_size' => -1], ['meta_buffer' => -5]] as $body) {
            $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', $body);
            $I->seeResponseCodeIs(400);
            $I->seeResponseContainsJson(['errorCode' => 'INVALID_REQUEST']);
        }
        // Review Focus 2: s3_tile_set is interpolated into the S3 object URL.
        foreach (['other/prefix', '../escape', 'with space'] as $bad) {
            $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['s3_tile_set' => $bad]);
            $I->seeResponseCodeIs(400);
        }
        // Unknown backend and format, with the allowed values named.
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['cache' => 'redis']);
        $I->seeResponseCodeIs(400);
        $I->assertStringContainsString('sqlite', $I->grabDataFromResponseByJsonPath('$.message')[0]);
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['format' => 'WEBP']);
        $I->seeResponseCodeIs(400);
        // JSON is a valid layer format but has no merged tileset to apply to.
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['format' => 'JSON']);
        $I->seeResponseCodeIs(400);
        // An unknown field: allowExtraFields is false.
        $I->sendPatch('/api/v4/schemas/' . $this->schema . '/tile', ['theme_column' => 'x']);
        $I->seeResponseCodeIs(400);

        // None of that may have been stored.
        $I->sendGet('/api/v4/schemas/' . $this->schema . '/tile');
        $I->assertSame([], (array)$I->grabDataFromResponseByJsonPath('$._stored')[0]);
    }

    /** Review Focus 5: a 300-character name cannot reach a varchar(255) column as a 500. */
    public function shouldRefuseAnImpossibleSchemaName(ApiTester $I): void
    {
        $I->sendPatch('/api/v4/schemas/' . str_repeat('a', 300) . '/tile', ['cache' => 'disk']);
        $I->seeResponseCodeIsClientError();
        $I->dontSeeResponseContains('SQLSTATE');
    }

    public function shouldKeepSettingsWhenTheSchemaIsGone(ApiTester $I): void
    {
        $gone = 'zz_gone_' . time();
        $I->sendPost('/api/v4/schemas', ['name' => $gone]);
        $I->sendPatch('/api/v4/schemas/' . $gone . '/tile', ['cache' => 'disk']);
        $I->seeResponseCodeIs(303);
        $I->sendDelete('/api/v4/schemas/' . $gone);

        // GET still answers, and says the schema is not there.
        $I->sendGet('/api/v4/schemas/' . $gone . '/tile');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['cache' => 'disk']);
        $I->assertFalse($I->grabDataFromResponseByJsonPath('$.schema_exists')[0]);

        // PATCH does not: a write to a schema that is not there is how a typo
        // becomes a ghost row.
        $I->sendPatch('/api/v4/schemas/' . $gone . '/tile', ['ttl' => 900]);
        $I->seeResponseCodeIs(404);
        $I->seeResponseContainsJson(['errorCode' => 'SCHEMA_NOT_FOUND']);

        // DELETE does, so a leftover row can be cleared.
        $I->sendDelete('/api/v4/schemas/' . $gone . '/tile');
        $I->seeResponseCodeIs(204);
        $I->sendGet('/api/v4/schemas/' . $gone . '/tile');
        $I->assertSame([], (array)$I->grabDataFromResponseByJsonPath('$._stored')[0]);
    }

    public function shouldBeSuperUserOnly(ApiTester $I): void
    {
        if ($this->subToken === '') {
            $I->fail('no sub-user token: the scope assertion would pass for the wrong reason');
        }
        $I->amBearerAuthenticated($this->subToken);
        $I->sendGet('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(403);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
        $I->amBearerAuthenticated($this->token);
    }

    public function shouldAnswerDeleteIdempotently(ApiTester $I): void
    {
        $I->sendDelete('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(204);
        $I->sendDelete('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(204);
    }

    public function shouldAnswerOptions(ApiTester $I): void
    {
        $I->sendOptions('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIsSuccessful();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SchemaTileSettingsV4ApiCest`
Expected: FAIL — the route does not exist, so the requests answer 404/406 rather
than the asserted codes.

- [ ] **Step 3: Write the controller**

Create `app/api/v4/controllers/SchemaTileSettings.php`:

```php
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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The merged per-schema tileset's settings — <schema> and <schema>.mvt, the ones
 * drawn from every layer in the schema at once. A layer's own tileset is
 * configured through the Layer API; this is its counterpart for the schema.
 *
 * The row outlives its schema on purpose (dropping and recreating a schema is
 * normal here), which is why GET and DELETE work without the schema while PATCH
 * does not: a write to a schema that is not there is how a typo becomes a row
 * that silently takes effect months later.
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
        new OA\Property(property: 'format', description: 'PNG or a jpeg_* quality for the image tileset, or MVT for the vector one. Applied to the tileset whose kind it matches; JSON is not accepted because there is no merged .json tileset.', type: 'string', enum: ['PNG', 'jpeg_low', 'jpeg_medium', 'jpeg_high', 'MVT'], nullable: true),
        new OA\Property(property: 'ttl', description: 'Seconds a tile stays valid (mapcache expires). Floored at 30.', type: 'integer', nullable: true, example: 86400),
        new OA\Property(property: 'auto_expire', description: 'Seconds after which an existing tile is refreshed on next access.', type: 'integer', nullable: true),
        new OA\Property(property: 'meta_size', description: 'Metatile size N, rendered as N x N tiles per WMS request. At least 1.', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'meta_buffer', description: 'Pixels drawn around each metatile and cropped afterwards.', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 's3_tile_set', description: 'With cache "s3", the object-path segment for this schema. Letters, digits, dot, dash and underscore only: it is interpolated into the cache URL.', type: 'string', nullable: true),
        new OA\Property(property: 'title', description: 'Shown in WMTS capabilities. Defaults to the schema name.', type: 'string', nullable: true),
        new OA\Property(property: 'abstract', description: 'Shown in WMTS capabilities.', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SchemaTileSettings',
    description: 'The settings the config generator will actually use: stored values merged over the fallbacks. _stored carries only what is stored, so a form can tell a set value from a defaulted one, and schema_exists is false for settings left behind by a dropped schema.',
    properties: [
        new OA\Property(property: 'schema', type: 'string', readOnly: true),
        new OA\Property(property: 'schema_exists', description: 'False when the settings are waiting for their schema to come back.', type: 'boolean', readOnly: true),
        new OA\Property(property: 'cache', type: 'string', example: 'sqlite'),
        new OA\Property(property: 'format', description: 'The image tileset\'s format.', type: 'string', example: 'PNG'),
        new OA\Property(property: 'vector_format', description: 'The .mvt tileset\'s format.', type: 'string', example: 'MVT'),
        new OA\Property(property: 'ttl', type: 'integer', example: 60),
        new OA\Property(property: 'auto_expire', type: 'integer', nullable: true),
        new OA\Property(property: 'meta_size', type: 'integer', example: 3),
        new OA\Property(property: 'meta_buffer', type: 'integer', example: 0),
        new OA\Property(property: 's3_tile_set', type: 'string', nullable: true),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'abstract', type: 'string'),
        new OA\Property(property: '_stored', description: 'Only the keys actually stored.', type: 'object', readOnly: true),
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
     * name and a varchar(255) primary key can hold. Deliberately not
     * AbstractApi::initiate(), which 404s on a missing schema — GET and DELETE
     * must work after the schema is dropped.
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

    #[OA\Get(path: '/api/v4/schemas/{schema}/tile', operationId: 'getSchemaTileSettings', description: "The merged per-schema tileset's effective tile settings. Answers the fallbacks when nothing is stored, and still answers when the schema itself is gone.", tags: ['SchemaTileSettings'],
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
            '_stored' => $stored,
        ]], single: true);
    }

    #[OA\Patch(path: '/api/v4/schemas/{schema}/tile', operationId: 'patchSchemaTileSettings', description: 'Merge settings into the schema\'s stored ones. An explicit null removes a setting. The schema must exist.', tags: ['SchemaTileSettings'],
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
        return $this->patchResponse('/api/v4/schemas/' . $schema . '/tile', [$schema]);
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

    public function options_index(): Response
    {
        return $this->deleteResponse();
    }

    private function defaultCache(): string
    {
        return !empty(\app\conf\App::$param['mapCache']['type']) ? \app\conf\App::$param['mapCache']['type'] : 'sqlite';
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

    static public function getAssert(string $method = 'patch'): Assert\Collection
    {
        $nullOr = fn(Assert\Constraint ...$c) => new Assert\Optional(
            new Assert\AtLeastOneOf([new Assert\IsNull(), new Assert\Sequentially($c)])
        );
        return new Assert\Collection(
            fields: [
                'cache' => $nullOr(new Assert\Choice(Mapcachefile::SCHEMA_CACHES)),
                'format' => $nullOr(new Assert\Choice(array_merge(Mapcachefile::SCHEMA_IMAGE_FORMATS, Mapcachefile::SCHEMA_VECTOR_FORMATS))),
                'ttl' => $nullOr(new Assert\Type('integer'), new Assert\Positive()),
                'auto_expire' => $nullOr(new Assert\Type('integer'), new Assert\Positive()),
                'meta_size' => $nullOr(new Assert\Type('integer'), new Assert\Range(min: 1, max: 16)),
                'meta_buffer' => $nullOr(new Assert\Type('integer'), new Assert\Range(min: 0, max: 512)),
                's3_tile_set' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 255), new Assert\Regex('/^[A-Za-z0-9_\-.]+$/')),
                'title' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 255)),
                'abstract' => $nullOr(new Assert\Type('string'), new Assert\Length(max: 2048)),
            ],
            allowExtraFields: false,
        );
    }
}
```

The `Assert\Choice` messages must name the allowed values, the way the
tileseeder's `UNKNOWN_GRID` names a tileset's real grids — check the 400 body in
Step 5 and, if the default message does not list them, pass an explicit
`message:` that does.

- [ ] **Step 4: Run the Cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SchemaTileSettingsV4ApiCest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Check the document and the error messages by hand**

```bash
docker exec docker-dev-1 curl -s 'http://localhost/swagger/api.php?v=4' | python3 -c '
import json,sys
d=json.load(sys.stdin)
print([p for p in d["paths"] if "tile" in p and "schemas" in p])
print([s for s in d["components"]["schemas"] if "SchemaTileSettings" in s])
'
```
Expected: the path and both schemas. Quote the 400 body for `{"cache":"redis"}`
in your report and confirm it names the allowed backends.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/SchemaTileSettings.php app/tests/api/SchemaTileSettingsV4ApiCest.php
git commit -m "feat(mapcache): v4 resource for the per-schema tile settings"
```

---

## Task 4: Clearing the cache must look in the right place

**Files:**
- Modify: `app/controllers/Tilecache.php` (`bust()`, around line 132)
- Test: `app/tests/unit/TilecacheBustSchemaTest.php` (create)

**Interfaces:**
- Consumes: `SchemaSettings::get()` (Task 1).
- Produces: no new signature; `bust()` keeps `bust(string $layerName, ?Connection $connection = null): array`.

**Why this is part of the feature, not a follow-up.** `bust()` decides which
backend to delete from by looking its argument up as a *layer* and reading
`def->cache`, falling back to the install default. Give a schema `cache: s3` and
"clear cache" on `dagi` will look in sqlite, find nothing and report success — a
button that lies the moment this feature is used.

- [ ] **Step 1: Write the failing test**

Create `app/tests/unit/TilecacheBustSchemaTest.php`:

```php
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
 * looks in sqlite, finds nothing, and reports success.
 */
class TilecacheBustSchemaTest extends Unit
{
    protected UnitTester $tester;
    private Connection $connection;
    private SchemaSettings $settings;
    private string $schema;

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
        $this->assertSame(
            !empty(app\conf\App::$param['mapCache']['type']) ? app\conf\App::$param['mapCache']['type'] : 'sqlite',
            Tilecache::cacheBackendFor($this->schema, $this->connection)
        );
    }

    public function testASchemaWithSettingsUsesItsOwnBackend(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'disk']);
        $this->assertSame('disk', Tilecache::cacheBackendFor($this->schema, $this->connection));
    }

    public function testTheVectorTilesetOfASchemaResolvesToTheSameBackend(): void
    {
        $this->settings->patch($this->schema, ['cache' => 'disk']);
        $this->assertSame('disk', Tilecache::cacheBackendFor($this->schema . '.mvt', $this->connection));
    }

    public function testAnUnknownLayerNameStillFallsBack(): void
    {
        $this->assertSame(
            !empty(app\conf\App::$param['mapCache']['type']) ? app\conf\App::$param['mapCache']['type'] : 'sqlite',
            Tilecache::cacheBackendFor('nosuchschema.nosuchtable', $this->connection)
        );
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit TilecacheBustSchemaTest`
Expected: FAIL — `Call to undefined method app\controllers\Tilecache::cacheBackendFor()`.

- [ ] **Step 3: Extract the backend decision and teach it about schemas**

In `app/controllers/Tilecache.php`, add:

```php
    /**
     * Which cache backend a tileset name lives in.
     *
     * A name with a dot is a layer's tileset and the layer's own def decides. A
     * name without one — or one whose only dot is the .mvt/.json suffix of a
     * merged per-schema tileset — is a schema, and settings.schema_settings
     * decides. Anything unresolved falls back to the install default.
     *
     * Without the schema branch, clearing a schema tileset configured for disk or
     * s3 would look in sqlite, delete nothing and report success.
     */
    static function cacheBackendFor(string $tilesetName, ?\app\inc\Connection $connection = null): string
    {
        $default = !empty(App::$param["mapCache"]["type"]) ? App::$param["mapCache"]["type"] : 'sqlite';
        $db = $connection ? $connection->database : Database::getDb();

        $layer = $connection ? new \app\models\Layer(connection: $connection) : new \app\models\Layer();
        $meta = $layer->getAll($db, true, $tilesetName, false, true);
        $fromLayer = $meta["data"][0]["def"]->cache ?? null;
        if (!empty($fromLayer)) {
            return $fromLayer;
        }

        // Not a layer: a merged per-schema tileset, possibly with its format suffix.
        $schema = preg_replace('/\.(mvt|json)$/', '', $tilesetName);
        if (!str_contains($schema, '.')) {
            $row = (new \app\models\SchemaSettings(connection: $connection))->get($schema);
            $def = $row && $row['def'] ? json_decode($row['def']) : null;
            if (!empty($def->cache)) {
                return (string)$def->cache;
            }
        }
        return $default;
    }
```

Then replace the opening of `bust()` so it uses it, leaving the rest of the
method untouched:

```php
    static function bust(string $layerName, ?\app\inc\Connection $connection = null): array
    {
        $db = $connection ? $connection->database : Database::getDb();
        $cache = self::cacheBackendFor($layerName, $connection);
        $response = [];
        $res = null;
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit TilecacheBustSchemaTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Verify the behaviour it protects**

The `disk` branch of `bust()` deletes
`app/wms/mapcache/disk/<db>/<layerName>`. Prove the schema branch reaches it:

```bash
docker exec postgres psql -U mydb -d mydb -c "insert into settings.schema_settings (schema, def) values ('zz_bust_demo','{\"cache\":\"disk\"}')"
docker exec docker-dev-1 sh -c 'mkdir -p /var/www/geocloud2/app/wms/mapcache/disk/mydb/zz_bust_demo && touch /var/www/geocloud2/app/wms/mapcache/disk/mydb/zz_bust_demo/a.png && ls /var/www/geocloud2/app/wms/mapcache/disk/mydb/zz_bust_demo'
docker exec docker-dev-1 php -r '
require "/var/www/geocloud2/app/vendor/autoload.php";
require_once "/var/www/geocloud2/app/conf/App.php";
var_dump(app\controllers\Tilecache::cacheBackendFor("zz_bust_demo", new app\inc\Connection(database: "mydb")));
'
docker exec postgres psql -U mydb -d mydb -c "delete from settings.schema_settings where schema = 'zz_bust_demo'"
docker exec docker-dev-1 rm -rf /var/www/geocloud2/app/wms/mapcache/disk/mydb/zz_bust_demo
```
Expected: `string(4) "disk"`, where before the change it would have been the
install default. Quote it, and confirm both cleanups ran.

- [ ] **Step 6: Run every affected suite**

```bash
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api
```
Expected: no new failures against the counts recorded before this plan started
(unit 512 tests / 1808 assertions with 2 pre-existing skips; api 430 tests /
2255 assertions with 1 pre-existing skip), plus this plan's new tests.

- [ ] **Step 7: Commit**

```bash
git add app/controllers/Tilecache.php app/tests/unit/TilecacheBustSchemaTest.php
git commit -m "fix(mapcache): clearing a schema tileset looks in the backend it actually uses"
```

---

## Final check before review

- [ ] `docker exec postgres psql -U mydb -d mydb -tAc "select count(*) from settings.schema_settings"` prints `0` — no demo rows left.
- [ ] `git status --short` shows nothing but the intended files.
- [ ] The byte-identical test **ran** rather than skipped in Task 2 Step 8.
- [ ] One sentence in the report on what a GUI must tell the user: after changing
  `cache` or `format`, tiles already cached live in the old backend or format and
  are neither served nor cleaned up — clearing the tileset afterwards is the
  operator's call (spec §3.5).
