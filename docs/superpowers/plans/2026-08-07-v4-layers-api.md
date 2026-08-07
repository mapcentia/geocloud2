# v4 Layers API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A v4 REST API for layers with sub-resources — `api/v4/layers/{layer}`, `.../classes/{id}`, `.../classes/{id}/styles/{id}`, `.../classes/{id}/labels/{id}` — with server-assigned fixed ids on classes/styles/labels.

**Architecture:** Four new v4 controllers following the existing Schema → Table → Column pattern (attribute routing, `getAssert()` validation, `AbstractApi` helpers). All class/style/label logic lives in new id-based methods on `app\models\Classification`; layer properties reuse `app\models\Tile` (the `def` JSON). Storage is unchanged: classes stay in the `class` jsonb column, properties in `def`. Every mutation regenerates the WMS/WFS mapfiles.

**Tech Stack:** PHP 8, Symfony Validator constraints, OpenAPI attributes, Codeception (unit + api suites inside Docker).

**Spec:** `docs/superpowers/specs/2026-08-07-v4-layers-api-design.md`

## Global Constraints

- **CRITICAL — never stage or commit these files** (the user's unrelated WIP): `app/api/v4/controllers/Wfs.php`, `app/wfs/Server.php`, `app/wfs/handlers/GetFeature.php`, and any other file not named in your task. Always `git add` only the exact files listed in the commit step. Never use `git add -A`, `git add .`, or `git commit -a`.
- Host PHP lacks the curl extension — run ALL Codeception commands inside Docker:
  - Unit: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
  - API: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
  - Syntax check (host PHP works for this): `php -l <file>`
- The repo is mounted into the container at `/var/www/geocloud2` — code changes are live, no build step.
- v4 controllers auto-register: `public/index.php` globs `app/api/v4/controllers/*.php` and registers classes carrying the `#[Controller(...)]` attribute. Abstract classes without the attribute are skipped.
- Ids are 8 hex chars from `bin2hex(random_bytes(4))`, server-assigned only; a client-supplied `id` in any POST/PATCH body must be rejected with 400 (this falls out of `Assert\Collection`'s extra-fields rejection — `id` is never listed as a field).
- Route2 syntax: `{param}` = required path segment, `[param]` = optional path segment.
- Layer key format is exactly `schema.table.geometry_column` (3 dot-separated parts).
- Error codes: `INVALID_LAYER_KEY` (400), `LAYER_NOT_FOUND` (404), `CLASS_NOT_FOUND` (404), `STYLE_NOT_FOUND` (404), `LABEL_NOT_FOUND` (404).
- The unit suite has 2 pre-existing skips — that is normal. All other tests must pass before every commit.

---

### Task 1: Model groundwork — Tile::DEF_KEYS and Layer key helpers

**Files:**
- Modify: `app/models/Tile.php` (the `update()` method's inline `$schema` array, ~line 89)
- Modify: `app/models/Layer.php` (add two methods at the end of the class, before the final `insertDefaultMeta()` or after it)

**Interfaces:**
- Produces: `Tile::DEF_KEYS` (public const, list of the 23 `def` JSON keys); `Layer::doesLayerExist(string $key): bool`; `Layer::getLayerKeys(?array $schemas = null): array` (returns `_key_` strings, sorted).

- [ ] **Step 1: Add `DEF_KEYS` constant to `app/models/Tile.php`**

Add right after the `public string $table;` property:

```php
    public const DEF_KEYS = [
        "theme_column",
        "label_column",
        "opacity",
        "label_max_scale",
        "label_min_scale",
        "cluster",
        "meta_tiles",
        "meta_size",
        "meta_buffer",
        "ttl",
        "auto_expire",
        "maxscaledenom",
        "minscaledenom",
        "symbolscaledenom",
        "geotype",
        "offsite",
        "format",
        "lock",
        "layers",
        "bands",
        "cache",
        "s3_tile_set",
        "label_no_clip",
        "polyline_no_clip",
    ];
```

In `update()`, replace the local `$schema = [ ...24 lines... ];` array with:

```php
        $schema = self::DEF_KEYS;
```

The key list must be copied verbatim from the existing `update()` array (order included) — do not retype it from this plan; move it.

- [ ] **Step 2: Add layer helpers to `app/models/Layer.php`**

Add these methods to the class:

```php
    /**
     * Checks whether a layer row exists in settings.geometry_columns_join.
     */
    public function doesLayerExist(string $key): bool
    {
        $sql = "SELECT 1 FROM settings.geometry_columns_join WHERE _key_=:key";
        $res = $this->prepare($sql);
        $this->execute($res, ['key' => $key]);
        return (bool)$this->fetchRow($res);
    }

    /**
     * Returns the _key_ of every layer, optionally restricted to a list of schemas.
     */
    public function getLayerKeys(?array $schemas = null): array
    {
        if ($schemas === null) {
            $sql = "SELECT _key_ FROM settings.geometry_columns_join ORDER BY _key_";
            $res = $this->prepare($sql);
            $this->execute($res);
        } else {
            $sql = "SELECT _key_ FROM settings.geometry_columns_join WHERE split_part(_key_, '.', 1) = ANY(:schemas) ORDER BY _key_";
            $res = $this->prepare($sql);
            $this->execute($res, ['schemas' => '{' . implode(',', $schemas) . '}']);
        }
        return array_column($this->fetchAll($res, "assoc"), '_key_');
    }
```

- [ ] **Step 3: Syntax check and run the unit suite**

Run: `php -l app/models/Tile.php && php -l app/models/Layer.php`
Expected: No syntax errors.

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: All pass (2 pre-existing skips OK).

- [ ] **Step 4: Commit**

```bash
git add app/models/Tile.php app/models/Layer.php
git commit -m "refactor(models): expose Tile def key list and add layer key helpers"
```

---

### Task 2: Classification static id helpers (TDD)

**Files:**
- Modify: `app/models/Classification.php` (add three static methods after `normalizeClass()`)
- Test: `app/tests/unit/ClassificationIdsTest.php` (new)

**Interfaces:**
- Consumes: `Classification::normalizeClass(array $class): array` (existing).
- Produces: `Classification::generateId(): string` (8 hex chars); `Classification::ensureIds(array $classes): array` (normalizes each class, adds missing `id` to classes and their `styles`/`labels` entries, never touches existing ids, ids unique within scope); `Classification::nextSortId(array $entries): int` (max existing sortid + 10, minimum 10).

- [ ] **Step 1: Write the failing tests**

Create `app/tests/unit/ClassificationIdsTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Classification;
use Codeception\Test\Unit;

class ClassificationIdsTest extends Unit
{
    protected UnitTester $tester;

    public function testGenerateIdReturnsEightHexChars(): void
    {
        $id = Classification::generateId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
        $this->assertNotEquals($id, Classification::generateId());
    }

    public function testEnsureIdsAddsMissingIdsEverywhere(): void
    {
        $classes = [
            [
                "sortid" => 10,
                "name" => "A",
                "styles" => [["sortid" => 10, "color" => "#008000"]],
                "labels" => [["sortid" => 10, "on" => true, "text" => "[name]"]],
            ],
        ];
        $result = Classification::ensureIds($classes);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['labels'][0]['id']);
    }

    public function testEnsureIdsIsIdempotentAndKeepsExistingIds(): void
    {
        $classes = [
            [
                "id" => "aaaaaaaa",
                "name" => "A",
                "styles" => [["id" => "bbbbbbbb", "color" => "#008000"], ["color" => "#ff0000"]],
                "labels" => [],
            ],
        ];
        $once = Classification::ensureIds($classes);
        $this->assertEquals("aaaaaaaa", $once[0]['id']);
        $this->assertEquals("bbbbbbbb", $once[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $once[0]['styles'][1]['id']);
        $this->assertNotEquals("bbbbbbbb", $once[0]['styles'][1]['id']);
        $twice = Classification::ensureIds($once);
        $this->assertEquals($once, $twice);
    }

    public function testEnsureIdsNormalizesLegacyClasses(): void
    {
        $legacy = [
            [
                "sortid" => 10,
                "name" => "Legacy",
                "color" => "#008000",
                "label" => true,
                "label_text" => "[name]",
            ],
        ];
        $result = Classification::ensureIds($legacy);
        $this->assertArrayNotHasKey('color', $result[0]);
        $this->assertCount(1, $result[0]['styles']);
        $this->assertCount(1, $result[0]['labels']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['labels'][0]['id']);
    }

    public function testNextSortId(): void
    {
        $this->assertEquals(10, Classification::nextSortId([]));
        $this->assertEquals(40, Classification::nextSortId([["sortid" => 10], ["sortid" => 30]]));
        $this->assertEquals(10, Classification::nextSortId([["name" => "no sortid"]]));
        $this->assertEquals(30, Classification::nextSortId([["sortid" => "20"]]));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit ClassificationIdsTest.php`
Expected: FAIL — `Call to undefined method ... generateId()`.

- [ ] **Step 3: Implement the three statics in `app/models/Classification.php`**

Add directly after the `normalizeClass()` method:

```php
    /**
     * Generates a short random id (8 hex chars) for classes, styles and labels.
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * Normalizes every class (see normalizeClass) and assigns a missing `id` to each
     * class and to each entry in its styles/labels arrays. Idempotent: existing ids
     * are never changed. Ids are unique among classes and among entries within a class.
     */
    public static function ensureIds(array $classes): array
    {
        $classes = array_values(array_map([self::class, 'normalizeClass'], $classes));
        $classIds = array_filter(array_column($classes, 'id'));
        foreach ($classes as $i => $class) {
            if (empty($class['id'])) {
                do {
                    $id = self::generateId();
                } while (in_array($id, $classIds, true));
                $classIds[] = $id;
                $classes[$i]['id'] = $id;
            }
            foreach (['styles', 'labels'] as $kind) {
                $entryIds = array_filter(array_column($classes[$i][$kind], 'id'));
                foreach ($classes[$i][$kind] as $j => $entry) {
                    if (empty($entry['id'])) {
                        do {
                            $id = self::generateId();
                        } while (in_array($id, $entryIds, true));
                        $entryIds[] = $id;
                        $classes[$i][$kind][$j]['id'] = $id;
                    }
                }
            }
        }
        return $classes;
    }

    /**
     * Default sortid for a new entry: highest existing sortid + 10 (10 when empty).
     */
    public static function nextSortId(array $entries): int
    {
        $max = 0;
        foreach ($entries as $entry) {
            $max = max($max, (int)($entry['sortid'] ?? 0));
        }
        return $max + 10;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: All pass, including the 5 new tests.

- [ ] **Step 5: Commit**

```bash
git add app/models/Classification.php app/tests/unit/ClassificationIdsTest.php
git commit -m "feat(classification): add fixed-id helpers generateId/ensureIds/nextSortId"
```

---

### Task 3: Classification id-based CRUD helpers

**Files:**
- Modify: `app/models/Classification.php` (add instance methods after the statics from Task 2)

**Interfaces:**
- Consumes: `Classification::ensureIds()`, `nextSortId()` (Task 2); private `store(string $class): void` (existing); `GC2Exception` (already imported).
- Produces (all on `Classification`, constructed as `new Classification(table: $layerKey, connection: $connection)`):
  - `getAllWithIds(): array` — classes normalized + ids, persisted back if anything was added/changed.
  - `getClassById(string $id): array` — throws `CLASS_NOT_FOUND` (404).
  - `replaceClasses(array $classes): array` — wholesale replace, returns stored classes.
  - `insertClasses(array $newClasses): array` — append, returns list of new class ids.
  - `patchClassById(string $id, array $props): void` — key-merge; ignores `id`/`styles`/`labels` keys in `$props`.
  - `deleteClassById(string $id): void`
  - `getEntries(string $classId, string $kind): array` — `$kind` is `'styles'` or `'labels'`.
  - `insertEntries(string $classId, string $kind, array $entries): array` — append with sortid default, returns new entry ids.
  - `patchEntryById(string $classId, string $kind, string $id, array $props): void`
  - `deleteEntryById(string $classId, string $kind, string $id): void`
  - Entry methods throw `CLASS_NOT_FOUND` for unknown class id and `STYLE_NOT_FOUND`/`LABEL_NOT_FOUND` for unknown entry id.

These methods hit the database, so they are covered by the API tests in Tasks 4–7 rather than unit tests.

- [ ] **Step 1: Add the CRUD methods to `app/models/Classification.php`**

Add after `nextSortId()`:

```php
    /**
     * Reads the raw class JSON for the layer.
     */
    private function readRawClasses(): array
    {
        $sql = "SELECT class FROM settings.geometry_columns_join WHERE _key_=:layer";
        $res = $this->prepare($sql);
        $this->execute($res, ['layer' => $this->layer]);
        $row = $this->fetchRow($res);
        return !empty($row['class']) && is_array(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [];
    }

    /**
     * Returns all classes in normalized form with ids on classes, styles and labels.
     * Missing ids are persisted back, so ids are stable from the first call onward.
     */
    public function getAllWithIds(): array
    {
        $raw = $this->readRawClasses();
        $classes = self::ensureIds($raw);
        if ($classes !== $raw) {
            $this->store(json_encode($classes));
        }
        return $classes;
    }

    /**
     * @throws GC2Exception
     */
    public function getClassById(string $id): array
    {
        foreach ($this->getAllWithIds() as $class) {
            if ($class['id'] === $id) {
                return $class;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * Replaces the whole class array (declarative provisioning). Ids are assigned.
     */
    public function replaceClasses(array $classes): array
    {
        $classes = self::ensureIds($classes);
        $this->store(json_encode($classes));
        return $classes;
    }

    /**
     * Appends new classes and returns their ids. A missing sortid defaults to
     * highest existing + 10.
     */
    public function insertClasses(array $newClasses): array
    {
        $classes = $this->getAllWithIds();
        $count = count($classes);
        foreach ($newClasses as $newClass) {
            if (!isset($newClass['sortid']) || $newClass['sortid'] === '') {
                $newClass['sortid'] = self::nextSortId($classes);
            }
            $classes[] = $newClass;
        }
        $classes = self::ensureIds($classes);
        $this->store(json_encode($classes));
        return array_column(array_slice($classes, $count), 'id');
    }

    /**
     * Key-merges $props into the class. `id`, `styles` and `labels` are ignored.
     * @throws GC2Exception
     */
    public function patchClassById(string $id, array $props): void
    {
        unset($props['id'], $props['styles'], $props['labels']);
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $id) {
                $classes[$i] = array_merge($class, $props);
                $this->store(json_encode($classes));
                return;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function deleteClassById(string $id): void
    {
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $id) {
                array_splice($classes, $i, 1);
                $this->store(json_encode($classes));
                return;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function getEntries(string $classId, string $kind): array
    {
        return $this->getClassById($classId)[$kind];
    }

    /**
     * Appends entries to a class's styles or labels and returns their ids.
     * A missing sortid defaults to highest existing + 10.
     * @throws GC2Exception
     */
    public function insertEntries(string $classId, string $kind, array $entries): array
    {
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $classId) {
                foreach ($entries as $entry) {
                    if (!isset($entry['sortid']) || $entry['sortid'] === '') {
                        $entry['sortid'] = self::nextSortId($classes[$i][$kind]);
                    }
                    $classes[$i][$kind][] = $entry;
                }
                $classes = self::ensureIds($classes);
                $this->store(json_encode($classes));
                return array_column(array_slice($classes[$i][$kind], -count($entries)), 'id');
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * Key-merges $props into a style/label entry. `id` is ignored.
     * @throws GC2Exception
     */
    public function patchEntryById(string $classId, string $kind, string $id, array $props): void
    {
        unset($props['id']);
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] !== $classId) {
                continue;
            }
            foreach ($class[$kind] as $j => $entry) {
                if ($entry['id'] === $id) {
                    $classes[$i][$kind][$j] = array_merge($entry, $props);
                    $this->store(json_encode($classes));
                    return;
                }
            }
            throw new GC2Exception(ucfirst(rtrim($kind, 's')) . " not found", 404, null, strtoupper(rtrim($kind, 's')) . "_NOT_FOUND");
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function deleteEntryById(string $classId, string $kind, string $id): void
    {
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] !== $classId) {
                continue;
            }
            foreach ($class[$kind] as $j => $entry) {
                if ($entry['id'] === $id) {
                    array_splice($classes[$i][$kind], $j, 1);
                    $this->store(json_encode($classes));
                    return;
                }
            }
            throw new GC2Exception(ucfirst(rtrim($kind, 's')) . " not found", 404, null, strtoupper(rtrim($kind, 's')) . "_NOT_FOUND");
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }
```

- [ ] **Step 2: Syntax check and run the unit suite**

Run: `php -l app/models/Classification.php`
Expected: No syntax errors.

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: All pass.

- [ ] **Step 3: Commit**

```bash
git add app/models/Classification.php
git commit -m "feat(classification): id-based CRUD helpers for classes, styles and labels"
```

---

### Task 4: AbstractLayerApi + Layer controller + API test setup (TDD)

**Files:**
- Create: `app/api/v4/AbstractLayerApi.php`
- Create: `app/api/v4/controllers/Layer.php`
- Test: `app/tests/api/LayerApiCest.php` (new)

**Interfaces:**
- Consumes: `Classification` helpers (Tasks 2–3); `Tile::DEF_KEYS`, `Layer::doesLayerExist`, `Layer::getLayerKeys` (Task 1); `AbstractApi::initiate/validateRequest/getResponse/postResponse/patchResponse/postWithResource`; `Mapfile::generateWms()/generateWfs()/writeMapfile(string, string)`.
- Produces: `app\api\v4\AbstractLayerApi` with `initiateLayer(string $layer): void` (validates key format → 400 `INVALID_LAYER_KEY`; calls `initiate(schema:, relation:)` for auth/404s; runs `insertDefaultMeta()`; checks the layer row → 404 `LAYER_NOT_FOUND`; sets `$this->layerKey` and `$this->classification`) and `writeMapFiles(): void`. Controller class `app\api\v4\controllers\Layer` at route `api/v4/layers/[layer]`. Later tasks (5–7) extend `AbstractLayerApi` and call both methods.

- [ ] **Step 1: Write the failing API test**

Create `app/tests/api/LayerApiCest.php`. The setup creates its own super user, token, and a schema with a table carrying a geometry column (v4 Table POST runs `insertDefaultMeta()`, so the layer row exists afterwards):

```php
<?php

use Codeception\Util\HttpCode;

class LayerApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $userAccessToken;
    private $schemaName;
    private $layerKey;
    private $classId;
    private $styleId;
    private $labelId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Layer api test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'layerapitest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'layer_api_test_' . $this->date->getTimestamp();
    }

    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
    }

    public function shouldPrepareUserAndToken(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $this->userId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->userAccessToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldCreateSchemaAndTableWithGeometry(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'roads',
            'columns' => [
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(LineString,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->layerKey = $this->schemaName . '.roads.the_geom';
    }

    public function shouldGetLayerWithDefaultProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals($this->layerKey, $response['name']);
        $I->assertArrayHasKey('theme_column', $response['properties']);
        $I->assertArrayHasKey('ttl', $response['properties']);
        $I->assertEquals([], $response['classes']);
    }

    public function shouldPostFullLayerResource(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->layerKey,
            'properties' => ['opacity' => '80', 'maxscaledenom' => '50000'],
            'classes' => [
                [
                    'name' => 'Main roads',
                    'sortid' => 10,
                    'expression' => "[name]='main'",
                    'styles' => [['sortid' => 10, 'color' => '#008000', 'width' => '2']],
                    'labels' => [['sortid' => 10, 'on' => true, 'text' => '[name]', 'size' => '10']],
                ],
                ['name' => 'Other roads', 'sortid' => 20],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $I->assertStringContainsString('/api/v4/layers/' . $this->layerKey, $location);

        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('80', $response['properties']['opacity']);
        $I->assertEquals('50000', $response['properties']['maxscaledenom']);
        $I->assertCount(2, $response['classes']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['id']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['styles'][0]['id']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['labels'][0]['id']);
        $this->classId = $response['classes'][0]['id'];
        $this->styleId = $response['classes'][0]['styles'][0]['id'];
        $this->labelId = $response['classes'][0]['labels'][0]['id'];

        // Ids are stable across requests
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals($this->classId, $response['classes'][0]['id']);
    }

    public function shouldPatchLayerProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->stopFollowingRedirects();
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'properties' => ['opacity' => '50'],
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('50', $response['properties']['opacity']);
        $I->assertEquals('50000', $response['properties']['maxscaledenom']); // key-merge keeps others
    }

    public function shouldListLayers(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/layers?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains($this->layerKey);
    }

    public function shouldRejectBadLayerRequests(ApiTester $I)
    {
        $this->auth($I);
        // Bad key format
        $I->sendGET('/api/v4/layers/not_a_layer_key');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Unknown table
        $I->sendGET('/api/v4/layers/' . $this->schemaName . '.nope.the_geom');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown geometry column on existing table
        $I->sendGET('/api/v4/layers/' . $this->schemaName . '.roads.wrong_geom');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown key in properties
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'properties' => ['no_such_key' => '1'],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // classes not allowed in PATCH
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'classes' => [],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // POST to a resource id
        $I->sendPOST('/api/v4/layers/' . $this->layerKey, json_encode(['name' => $this->layerKey]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }
}
```

- [ ] **Step 2: Run the Cest to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: setup tests pass; `shouldGetLayerWithDefaultProperties` and later FAIL (no `api/v4/layers` route → 404).

- [ ] **Step 3: Create `app/api/v4/AbstractLayerApi.php`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\models\Classification;
use app\models\Layer as LayerModel;
use app\models\Mapfile as MapfileModel;

/**
 * Shared behavior for the api/v4/layers/... controllers: layer-key validation,
 * authorization, layer existence check and mapfile regeneration.
 */
abstract class AbstractLayerApi extends AbstractApi
{
    public ?string $layerKey = null;
    protected Classification $classification;

    /**
     * Validates the layer key, authorizes via initiate(), ensures the layer row
     * exists and prepares the Classification model for the layer.
     *
     * @throws GC2Exception
     */
    protected function initiateLayer(string $layer): void
    {
        $parts = explode('.', $layer);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new GC2Exception("Layer key must be schema.table.geometry_column", 400, null, "INVALID_LAYER_KEY");
        }
        $this->initiate(schema: $parts[0], relation: $parts[1]);
        $layerModel = new LayerModel(connection: $this->connection);
        $layerModel->insertDefaultMeta();
        if (!$layerModel->doesLayerExist($layer)) {
            throw new GC2Exception("Layer not found", 404, null, "LAYER_NOT_FOUND");
        }
        $this->layerKey = $layer;
        $this->classification = new Classification(table: $layer, connection: $this->connection);
    }

    /**
     * Regenerates the WMS and WFS mapfiles — the API equivalent of the GUI's writeFiles().
     */
    protected function writeMapFiles(): void
    {
        $mapfile = new MapfileModel(connection: $this->connection);
        $mapfile->writeMapfile($mapfile->generateWms(), 'wms');
        $mapfile->writeMapfile($mapfile->generateWfs(), 'wfs');
    }
}
```

- [ ] **Step 4: Create `app/api/v4/controllers/Layer.php`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractLayerApi;
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
use app\models\Classification;
use app\models\Layer as LayerModel;
use app\models\Table as TableModel;
use app\models\Tile as TileModel;
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "Layer",
    description: "Layer definition: properties (the def JSON) and classes with styles and labels.",
    required: ["name"],
    properties: [
        new OA\Property(property: "name", title: "Name", description: "Layer key: schema.table.geometry_column.", type: "string", example: "my_schema.my_table.the_geom"),
        new OA\Property(property: "properties", title: "Properties", description: "Layer properties (the def JSON).", type: "object"),
        new OA\Property(property: "classes", title: "Classes", description: "Classes with styles and labels.", type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass")),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/[layer]', scope: Scope::SUB_USER_ALLOWED)]
class Layer extends AbstractLayerApi
{
    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'layers';
    }

    /**
     * Builds the full layer resource: name, properties (all def keys) and classes.
     */
    private function getLayerResource(string $key): array
    {
        $tile = new TileModel(table: $key, connection: $this->connection);
        $props = $tile->get()['data'][0];
        $properties = [];
        foreach (TileModel::DEF_KEYS as $k) {
            $properties[$k] = $props[$k] ?? "";
        }
        $classification = new Classification(table: $key, connection: $this->connection);
        return [
            'name' => $key,
            'properties' => $properties,
            'classes' => $classification->getAllWithIds(),
        ];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}', operationId: 'getLayer', description: "Get layer(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key: schema.table.geometry_column', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'namesOnly', description: 'Return only layer keys.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Layer"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Layer"))]))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        if ($this->layerKey) {
            return $this->getResponse([$this->getLayerResource($this->layerKey)], single: true);
        }
        $superUser = $this->route->jwt['data']['superUser'];
        $uid = $this->route->jwt['data']['uid'];
        $schemas = $superUser ? null : array_values(array_unique([$uid, 'public']));
        $keys = new LayerModel(connection: $this->connection)->getLayerKeys($schemas);
        if (in_array(Input::get('namesOnly'), ['', 'true', '1', 't'], true)) {
            return $this->getResponse(array_map(fn($k) => ['name' => $k], $keys));
        }
        return $this->getResponse(array_map(fn($k) => $this->getLayerResource($k), $keys));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers', operationId: 'postLayer', description: "Configure existing layer(s): set properties and replace classes.", tags: ['Layer'])]
    #[OA\RequestBody(description: 'Layer configuration.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Layer"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Layer"))]))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        $items = array_is_list($data) ? $data : [$data];
        $list = [];
        $model = new TableModel(null, connection: $this->connection);
        $model->withTransaction(function () use (&$list, $items) {
            foreach ($items as $item) {
                $this->initiateLayer($item['name']);
                if (isset($item['properties'])) {
                    new TileModel(table: $item['name'], connection: $this->connection)->update((object)$item['properties']);
                }
                if (isset($item['classes'])) {
                    $this->classification->replaceClasses($item['classes']);
                }
                $list[] = $item['name'];
            }
        });
        $this->writeMapFiles();
        return $this->postResponse('/api/v4/layers/', $list);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}', operationId: 'patchLayer', description: "Update layer properties (key-merge on the def JSON).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key: schema.table.geometry_column', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\RequestBody(description: 'Layer properties', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Layer"))]
    #[OA\Response(response: 303, description: 'Layer updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        if (isset($data['properties'])) {
            new TileModel(table: $this->layerKey, connection: $this->connection)->update((object)$data['properties']);
        }
        $this->writeMapFiles();
        return $this->patchResponse('/api/v4/layers/', [$this->layerKey]);
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $layer = $this->route->getParam("layer");
        $body = Input::getBody();

        if (empty($layer) && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH on the layer collection is not allowed", 400);
        }
        if (!empty($layer) && count(explode(',', $layer)) > 1) {
            throw new GC2Exception("Only one layer per request is allowed", 400);
        }
        if (Input::getMethod() == 'post' && $layer) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        if (!empty($layer)) {
            $this->initiateLayer($layer);
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        if (Input::getMethod() == 'post') {
            $collection->fields['name'] = new Assert\Required([
                new Assert\Type('string'),
                new Assert\Regex(pattern: '/^[^.,]+\.[^.,]+\.[^.,]+$/', message: 'Layer name must be schema.table.geometry_column'),
            ]);
            $collection->fields['classes'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([LayerClass::getAssert()]),
            ]);
        }
        $collection->fields['properties'] = new Assert\Optional([
            new Assert\Collection(array_map(fn($k) => new Assert\Optional(), array_flip(TileModel::DEF_KEYS))),
        ]);
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }
}
```

Note: `Layer::getAssert()` references `LayerClass::getAssert()` which does not exist until Task 5. To keep this task green on its own, create the `LayerClass` controller file in Task 5 — for THIS task, temporarily inline the class assert instead:

```php
            $collection->fields['classes'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
```

and leave a `// Tightened to LayerClass::getAssert() in the classes controller task` comment. Task 5 replaces this with the strict version.

- [ ] **Step 5: Run the Cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: All pass.

- [ ] **Step 6: Run the unit suite (regression)**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: All pass.

- [ ] **Step 7: Commit**

```bash
git add app/api/v4/AbstractLayerApi.php app/api/v4/controllers/Layer.php app/tests/api/LayerApiCest.php
git commit -m "feat(api): v4 layers endpoint with full-resource POST and properties PATCH"
```

---

### Task 5: LayerClass controller (TDD)

**Files:**
- Create: `app/api/v4/controllers/LayerClass.php`
- Modify: `app/api/v4/controllers/Layer.php` (swap the temporary classes assert for `LayerClass::getAssert()`)
- Test: `app/tests/api/LayerApiCest.php` (append tests)

**Interfaces:**
- Consumes: `AbstractLayerApi::initiateLayer/writeMapFiles`; `Classification::getAllWithIds/getClassById/insertClasses/patchClassById/deleteClassById`; `Style::getAssert()`/`Label::getAssert()` (Tasks 6–7 — see the temporary-assert note below).
- Produces: `app\api\v4\controllers\LayerClass` at route `api/v4/layers/{layer}/classes/[class]`; `LayerClass::getAssert(): Assert\Collection` (name required on POST; base class keys; nested styles/labels on POST only).

- [ ] **Step 1: Append failing tests to `app/tests/api/LayerApiCest.php`**

```php
    public function shouldCrudClasses(ApiTester $I)
    {
        $this->auth($I);
        // Collection GET
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(2, $response);

        // Single GET
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('Main roads', $response['name']);

        // POST a new class with nested style
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode([
            'name' => 'Paths',
            'styles' => [['color' => '#0000ff']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $newClassId = basename($location);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newClassId);

        // sortid defaulted to highest existing + 10 (10 and 20 exist)
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(30, $response['sortid']);
        $I->assertCount(1, $response['styles']);

        // PATCH (key-merge)
        $I->stopFollowingRedirects();
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId, json_encode([
            'sortid' => 5,
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(5, $response['sortid']);
        $I->assertEquals('Paths', $response['name']); // merge keeps other keys

        // DELETE
        $I->sendDELETE('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadClassRequests(ApiTester $I)
    {
        $this->auth($I);
        // Unknown class id
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Client-supplied id
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode([
            'name' => 'X', 'id' => 'cafebabe',
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // styles in PATCH body
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId, json_encode([
            'styles' => [],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // PATCH on collection
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes', json_encode(['name' => 'X']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // POST without name
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode(['sortid' => 1]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
```

- [ ] **Step 2: Run the Cest to verify the new tests fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: earlier tests pass; the two new tests FAIL (no classes route).

- [ ] **Step 3: Create `app/api/v4/controllers/LayerClass.php`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractLayerApi;
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
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "LayerClass",
    description: "Class definition with styles and labels.",
    required: [],
    properties: [
        new OA\Property(property: "id", title: "Id", description: "Fixed server-assigned id.", type: "string", example: "a1b2c3d4"),
        new OA\Property(property: "name", title: "Name", description: "Class name.", type: "string", example: "My class"),
        new OA\Property(property: "sortid", title: "Sort id", description: "Render/display order.", type: "integer", example: 10),
        new OA\Property(property: "expression", title: "Expression", description: "MapServer class expression.", type: "string", example: "[type]='road'"),
        new OA\Property(property: "styles", title: "Styles", description: "Style entries.", type: "array", items: new OA\Items(ref: "#/components/schemas/Style")),
        new OA\Property(property: "labels", title: "Labels", description: "Label entries.", type: "array", items: new OA\Items(ref: "#/components/schemas/Label")),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/{layer}/classes/[class]', scope: Scope::SUB_USER_ALLOWED)]
class LayerClass extends AbstractLayerApi
{
    private ?array $classIds = null;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'classes';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'getLayerClass', description: "Get class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id(s)', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/LayerClass"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass"))]))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $classes = $this->classification->getAllWithIds();
        if ($this->classIds) {
            $r = array_values(array_filter($classes, fn($c) => in_array($c['id'], $this->classIds, true)));
            return $this->getResponse($r, single: count($r) == 1);
        }
        return $this->getResponse($classes);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers/{layer}/classes', operationId: 'postLayerClass', description: "Create class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\RequestBody(description: 'Class to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/LayerClass"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/LayerClass"))]))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        $items = array_is_list($data) ? $data : [$data];
        $ids = $this->classification->insertClasses($items);
        $this->writeMapFiles();
        return $this->postResponse("/api/v4/layers/$this->layerKey/classes/", $ids);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'patchLayerClass', description: "Update a class (key-merge). styles/labels are managed via their own routes.", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\RequestBody(description: 'Class', required: true, content: new OA\JsonContent(ref: "#/components/schemas/LayerClass"))]
    #[OA\Response(response: 303, description: 'Class updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $this->classification->patchClassById($this->classIds[0], json_decode(Input::getBody(), true));
        $this->writeMapFiles();
        return $this->patchResponse("/api/v4/layers/$this->layerKey/classes/", [$this->classIds[0]]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/layers/{layer}/classes/{class}', operationId: 'deleteLayerClass', description: "Delete class(es).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id(s)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Response(response: 204, description: 'Class deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        foreach ($this->classIds as $id) {
            $this->classification->deleteClassById($id);
        }
        $this->writeMapFiles();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $layer = $this->route->getParam("layer");
        $class = $this->route->getParam("class");
        $body = Input::getBody();

        if (empty($class) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a class collection is not allowed", 400);
        }
        if (!empty($class) && count(explode(',', $class)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH with multiple classes is not allowed", 400);
        }
        if (Input::getMethod() == 'post' && $class) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        $this->initiateLayer($layer);
        $this->classIds = $class ? explode(',', $class) : null;
        foreach ($this->classIds ?? [] as $id) {
            $this->classification->getClassById($id); // throws CLASS_NOT_FOUND
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['name'] = Input::getMethod() == 'post'
            ? new Assert\Required([new Assert\Type('string'), new Assert\NotBlank()])
            : new Assert\Optional([new Assert\Type('string'), new Assert\NotBlank()]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        foreach (['expression', 'class_minscaledenom', 'class_maxscaledenom', 'leader', 'leader_gridstep', 'leader_maxdistance', 'leader_color'] as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        if (Input::getMethod() == 'post') {
            $collection->fields['styles'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
            $collection->fields['labels'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
```

Note: the `styles`/`labels` asserts are temporarily loose (`Type('array')` only) — Tasks 6/7 tighten them to `new Assert\All([Style::getAssert()])` / `new Assert\All([Label::getAssert()])` once those controllers exist. The client-supplied-id rejection at class level already works (extra field `id` → 400).

- [ ] **Step 4: Tighten `Layer::getAssert()`**

In `app/api/v4/controllers/Layer.php`, replace the temporary classes assert with:

```php
            $collection->fields['classes'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([LayerClass::getAssert()]),
            ]);
```

and remove the temporary comment.

- [ ] **Step 5: Run the Cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/LayerClass.php app/api/v4/controllers/Layer.php app/tests/api/LayerApiCest.php
git commit -m "feat(api): v4 classes sub-resource with fixed ids"
```

---

### Task 6: Style controller (TDD)

**Files:**
- Create: `app/api/v4/controllers/Style.php`
- Modify: `app/api/v4/controllers/LayerClass.php` (tighten the `styles` assert)
- Test: `app/tests/api/LayerApiCest.php` (append tests)

**Interfaces:**
- Consumes: `AbstractLayerApi`; `Classification::getEntries/insertEntries/patchEntryById/deleteEntryById` with `$kind = 'styles'`; `Classification::STYLE_KEYS`.
- Produces: `app\api\v4\controllers\Style` at route `api/v4/layers/{layer}/classes/{class}/styles/[style]`; `Style::getAssert(): Assert\Collection` (all STYLE_KEYS optional untyped, `sortid` optional int, `name` optional string).

- [ ] **Step 1: Append failing tests to `app/tests/api/LayerApiCest.php`**

```php
    public function shouldCrudStyles(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/styles';

        // Collection GET — one style from the layer POST
        $I->sendGET($base);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $response);
        $I->assertEquals($this->styleId, $response[0]['id']);

        // Single GET
        $I->sendGET($base . '/' . $this->styleId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('#008000', $response['color']);

        // POST without sortid → defaults to highest + 10 (10 exists)
        $I->sendPOST($base, json_encode(['color' => '#ff0000', 'width' => '4']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $newStyleId = basename($I->grabHttpHeader('Location'));
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newStyleId);
        $I->sendGET($base . '/' . $newStyleId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(20, $response['sortid']);

        // PATCH sortid to reorder
        $I->stopFollowingRedirects();
        $I->sendPatch($base . '/' . $newStyleId, json_encode(['sortid' => 5]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET($base . '/' . $newStyleId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(5, $response['sortid']);
        $I->assertEquals('#ff0000', $response['color']); // merge keeps other keys

        // DELETE
        $I->sendDELETE($base . '/' . $newStyleId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET($base . '/' . $newStyleId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadStyleRequests(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/styles';
        // Unknown style id
        $I->sendGET($base . '/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown class id in path
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/deadbeef/styles');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown property key
        $I->sendPOST($base, json_encode(['no_such_key' => '1']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Client-supplied id
        $I->sendPOST($base, json_encode(['id' => 'cafebabe', 'color' => '#fff']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
```

- [ ] **Step 2: Run the Cest to verify the new tests fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: earlier tests pass; the two new tests FAIL.

- [ ] **Step 3: Create `app/api/v4/controllers/Style.php`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractLayerApi;
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
use app\models\Classification;
use Exception;
use Override;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "Style",
    description: "Style entry of a class. Property keys follow MapServer STYLE parameters.",
    required: [],
    properties: [
        new OA\Property(property: "id", title: "Id", description: "Fixed server-assigned id.", type: "string", example: "e5f6a7b8"),
        new OA\Property(property: "sortid", title: "Sort id", description: "Render order. Defaults to highest existing + 10.", type: "integer", example: 10),
        new OA\Property(property: "name", title: "Name", description: "Display name (UI only).", type: "string", example: "Fill"),
        new OA\Property(property: "color", title: "Color", description: "Fill color.", type: "string", example: "#008000"),
        new OA\Property(property: "width", title: "Width", description: "Line width.", type: "string", example: "2"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/layers/{layer}/classes/{class}/styles/[style]', scope: Scope::SUB_USER_ALLOWED)]
class Style extends AbstractLayerApi
{
    private string $classId;
    private ?array $entryIds = null;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'styles';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'getStyle', description: "Get style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id(s)', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Style"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Style"))]))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $entries = $this->classification->getEntries($this->classId, 'styles');
        if ($this->entryIds) {
            $r = array_values(array_filter($entries, fn($e) => in_array($e['id'], $this->entryIds, true)));
            return $this->getResponse($r, single: count($r) == 1);
        }
        return $this->getResponse($entries);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/layers/{layer}/classes/{class}/styles', operationId: 'postStyle', description: "Create style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\RequestBody(description: 'Style to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Style"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Style"))]))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $data = json_decode(Input::getBody(), true);
        $items = array_is_list($data) ? $data : [$data];
        $ids = $this->classification->insertEntries($this->classId, 'styles', $items);
        $this->writeMapFiles();
        return $this->postResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/styles/", $ids);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'patchStyle', description: "Update a style (key-merge).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\RequestBody(description: 'Style', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Style"))]
    #[OA\Response(response: 303, description: 'Style updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $this->classification->patchEntryById($this->classId, 'styles', $this->entryIds[0], json_decode(Input::getBody(), true));
        $this->writeMapFiles();
        return $this->patchResponse("/api/v4/layers/$this->layerKey/classes/$this->classId/styles/", [$this->entryIds[0]]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/layers/{layer}/classes/{class}/styles/{style}', operationId: 'deleteStyle', description: "Delete style(s).", tags: ['Layer'])]
    #[OA\Parameter(name: 'layer', description: 'Layer key', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema.my_table.the_geom')]
    #[OA\Parameter(name: 'class', description: 'Class id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'a1b2c3d4')]
    #[OA\Parameter(name: 'style', description: 'Style id(s)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'e5f6a7b8')]
    #[OA\Response(response: 204, description: 'Style deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        foreach ($this->entryIds as $id) {
            $this->classification->deleteEntryById($this->classId, 'styles', $id);
        }
        $this->writeMapFiles();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $layer = $this->route->getParam("layer");
        $class = $this->route->getParam("class");
        $entry = $this->route->getParam("style");
        $body = Input::getBody();

        if (empty($entry) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a style collection is not allowed", 400);
        }
        if (!empty($entry) && count(explode(',', $entry)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("PATCH with multiple styles is not allowed", 400);
        }
        if (Input::getMethod() == 'post' && $entry) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        $this->initiateLayer($layer);
        $this->classId = $class;
        $existing = array_column($this->classification->getEntries($class, 'styles'), 'id'); // throws CLASS_NOT_FOUND
        $this->entryIds = $entry ? explode(',', $entry) : null;
        foreach ($this->entryIds ?? [] as $id) {
            if (!in_array($id, $existing, true)) {
                throw new GC2Exception("Style not found", 404, null, "STYLE_NOT_FOUND");
            }
        }
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        $collection->fields['name'] = new Assert\Optional([new Assert\Type('string')]);
        foreach (Classification::STYLE_KEYS as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
```

- [ ] **Step 4: Tighten the `styles` assert in `LayerClass::getAssert()`**

In `app/api/v4/controllers/LayerClass.php`, replace:

```php
            $collection->fields['styles'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
```

with:

```php
            $collection->fields['styles'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([Style::getAssert()]),
            ]);
```

- [ ] **Step 5: Run the Cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/Style.php app/api/v4/controllers/LayerClass.php app/tests/api/LayerApiCest.php
git commit -m "feat(api): v4 styles sub-resource with fixed ids"
```

---

### Task 7: Label controller (TDD)

**Files:**
- Create: `app/api/v4/controllers/Label.php`
- Modify: `app/api/v4/controllers/LayerClass.php` (tighten the `labels` assert)
- Test: `app/tests/api/LayerApiCest.php` (append tests)

**Interfaces:**
- Consumes: `AbstractLayerApi`; `Classification::getEntries/insertEntries/patchEntryById/deleteEntryById` with `$kind = 'labels'`; `Classification::LABEL_KEYS`.
- Produces: `app\api\v4\controllers\Label` at route `api/v4/layers/{layer}/classes/{class}/labels/[label]`; `Label::getAssert(): Assert\Collection` (LABEL_KEYS optional untyped, `sortid` optional int, `name` optional string, `on` optional bool).

- [ ] **Step 1: Append failing tests to `app/tests/api/LayerApiCest.php`**

```php
    public function shouldCrudLabels(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/labels';

        // Collection GET — one label from the layer POST
        $I->sendGET($base);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $response);
        $I->assertEquals($this->labelId, $response[0]['id']);
        $I->assertTrue($response[0]['on']);

        // POST
        $I->sendPOST($base, json_encode(['on' => false, 'text' => '[name]', 'color' => '#000000']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $newLabelId = basename($I->grabHttpHeader('Location'));
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newLabelId);
        $I->sendGET($base . '/' . $newLabelId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(20, $response['sortid']); // 10 exists → default 20
        $I->assertFalse($response['on']);

        // PATCH — toggle on
        $I->stopFollowingRedirects();
        $I->sendPatch($base . '/' . $newLabelId, json_encode(['on' => true]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET($base . '/' . $newLabelId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['on']);
        $I->assertEquals('[name]', $response['text']);

        // DELETE
        $I->sendDELETE($base . '/' . $newLabelId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET($base . '/' . $newLabelId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadLabelRequests(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/labels';
        // Unknown label id
        $I->sendGET($base . '/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown property key
        $I->sendPOST($base, json_encode(['no_such_key' => '1']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Wrong type for on
        $I->sendPOST($base, json_encode(['on' => 'yes']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
```

- [ ] **Step 2: Run the Cest to verify the new tests fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: earlier tests pass; the two new tests FAIL.

- [ ] **Step 3: Create `app/api/v4/controllers/Label.php`**

Read `app/api/v4/controllers/Style.php` from the repo (committed in Task 6) and use it as the template — the structure is identical. Write `Label.php` out in full with these substitutions:

- Class name `Label`, `$this->resource = 'labels'`.
- Route: `api/v4/layers/{layer}/classes/{class}/labels/[label]`; route param `"label"` instead of `"style"`.
- Every `'styles'` kind argument becomes `'labels'`; URIs use `/labels/`.
- OA schema `"Label"` (referenced by `LayerClass`'s OA schema), operationIds `getLabel`/`postLabel`/`patchLabel`/`deleteLabel`; example id `c9d0e1f2`; example properties `on` (bool), `text` (`[name]`).
- Error message/code: `"Label not found"` / `LABEL_NOT_FOUND`.
- `getAssert()`:

```php
    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        $collection->fields['sortid'] = new Assert\Optional([new Assert\Type('integer')]);
        $collection->fields['name'] = new Assert\Optional([new Assert\Type('string')]);
        $collection->fields['on'] = new Assert\Optional([new Assert\Type('boolean')]);
        foreach (Classification::LABEL_KEYS as $key) {
            $collection->fields[$key] = new Assert\Optional();
        }
        return $collection;
    }
```

- [ ] **Step 4: Tighten the `labels` assert in `LayerClass::getAssert()`**

In `app/api/v4/controllers/LayerClass.php`, replace:

```php
            $collection->fields['labels'] = new Assert\Optional([
                new Assert\Type('array'),
            ]);
```

with:

```php
            $collection->fields['labels'] = new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([Label::getAssert()]),
            ]);
```

- [ ] **Step 5: Run the Cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/Label.php app/api/v4/controllers/LayerClass.php app/tests/api/LayerApiCest.php
git commit -m "feat(api): v4 labels sub-resource with fixed ids"
```

---

### Task 8: Sub-user authorization test, full suites, CHANGELOG

**Files:**
- Test: `app/tests/api/LayerApiCest.php` (append)
- Modify: `CHANGELOG.md` (add bullet under `[Unreleased]`)

**Interfaces:**
- Consumes: everything from Tasks 1–7.

- [ ] **Step 1: Append the sub-user 403 test to `app/tests/api/LayerApiCest.php`**

A sub-user of the super user must not touch a layer in the super user's private schema. Add properties `private $subUserName; private $subUserAccessToken;` to the class and set `$this->subUserName = 'layerapisub' . $this->date->getTimestamp();` in the constructor. Then:

```php
    public function shouldForbidSubUserOnForeignSchema(ApiTester $I)
    {
        // Create a sub-user under the super user (session cookie flow)
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => $this->password,
            'schema' => 'public',
        ]));
        $sessionCookie = $I->grabCookie('PHPSESSID');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->subUserName,
            'email' => 'sub' . $this->userEmail,
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;

        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $subUserId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subUserAccessToken = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subUserAccessToken);
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
```

If the sub-user creation flow differs (check `DatabaseManagementCest::shouldCreateFirstSubUser` for the working payload/headers), copy the working pattern from there — the assertion that matters is the final 403 on `GET /api/v4/layers/{key}`.

- [ ] **Step 2: Run the full API suite**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api LayerApiCest.php`
Expected: All pass.

Then the full suites:

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: All pass (2 pre-existing skips OK).

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run api`
Expected: No NEW failures. (Note: some sub-user SQL api-key tests in `DatabaseManagementCest` are flaky on master — compare against a baseline if anything fails: `git stash` is NOT needed; just confirm the same test fails without your changes by checking whether the failure touches layers code at all.)

- [ ] **Step 3: Add CHANGELOG entry**

In `CHANGELOG.md`, under the `[Unreleased]` section's `### Added` (create the subsection if missing), add:

```markdown
- New v4 Layers API: `api/v4/layers/{layer}` with sub-resources `classes/{id}`, `classes/{id}/styles/{id}` and `classes/{id}/labels/{id}`. Layer properties (the Settings/def JSON) can be set via POST/PATCH, and classes, styles and labels are addressable by fixed server-assigned ids.
```

- [ ] **Step 4: Commit**

```bash
git add app/tests/api/LayerApiCest.php CHANGELOG.md
git commit -m "test(api): sub-user authorization for layers API + changelog"
```

---

### Task 9: Manual end-to-end verification (main session, not a subagent)

No new files. Verify the API and GUI cooperate, using chrome-devtools MCP against http://localhost:8080 and curl against the API:

- [ ] **Step 1:** Obtain a token for the local `mydb` database super user and GET an existing layer, e.g. `api/v4/layers/test.test3.the_geom` — confirm legacy classes come back normalized with ids, and that a second GET returns identical ids (persisted).
- [ ] **Step 2:** POST a new style to one of its classes via the API; open the layer in the admin GUI (http://localhost:8080/admin/mydb/test) and confirm the new style appears in the Symbols tab.
- [ ] **Step 3:** Edit the same style in the GUI (change a property, press Update); GET it via the API and confirm the change is there and the id is unchanged (round-trip proof).
- [ ] **Step 4:** Check the generated mapfile (`/var/www/geocloud2/app/wms/mapfiles/` in the container) contains the style added via the API.
- [ ] **Step 5:** Revert any test data changes made to `mydb` (delete the style added in Step 2 via the API).
- [ ] **Step 6:** If everything checks out, no commit is needed — this task produces verification evidence only.
