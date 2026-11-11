# v4 Configurations API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve Vidi configurations through v4 — token-based management for the database owner and anonymous read of published ones — on the same `settings.key_value` rows v2 uses.

**Architecture:** One model (`app\models\Configuration`) owns the key prefix, key generation, validation, row decoding and SQL. Two thin v4 controllers sit on it: `Configuration` (`SUPER_USER_ONLY`, CRUD) and `ConfigurationPublic` (`PUBLIC`, read-only, database from the path). v2 is untouched; rows are written exactly as v2 writes them.

**Tech Stack:** PHP 8.4, PostgreSQL (`settings.key_value`), Symfony Validator, swagger-php attributes, Codeception (unit + api).

**Spec:** `docs/superpowers/specs/2026-11-11-configurations-v4-design.md` — read it first; it is the authority.

## Global Constraints

- Follow `AGENTS.md` §3 (v4 API principles) and §4 (worker safety): thread `$this->connection` into the model, no `Database::setDb()`, no statics holding request state, headers only on response objects.
- Do not modify `app/api/v2/Configuration.php`, the Dashboard, `app/migration/Sql.php` or `public/index.php`.
- Stored row: `key` = the configuration key; `value` = JSON object `{name, published, description, body, key}`; `owner` NULL; `public` at column default.
- Key prefix: `configuration_`. Accepted key pattern: `^configuration_[A-Za-z0-9_@+\-]+$`. Generated keys contain only `[A-Za-z0-9_]`.
- `body` is a string holding valid JSON whose decoded value is an object or an array (empty allowed). It is stored and returned byte for byte.
- Error codes: `CONFIGURATION_NOT_FOUND` (404), `INVALID_REQUEST` (400), `DATABASE_NOT_FOUND` (404), `SUPER_USER_ONLY` (403, from the scope).
- Lists are bare arrays; single resources are bare objects with exactly `key, name, description, published, body`.
- Work on branch `feat/configurations-v4` cut from `master` in the main checkout (no worktree). The uncommitted Dashboard button on `feat/dashboard-try-new-app` is unrelated: do not stage `dashboard/`.
- **Commit only when the owner has asked for commits.** The commit steps below give the message to use; skip them otherwise and leave the work staged-free. Never push.
- Tests run in the container, one suite per command:
  `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <Name>` / `run api <Name>`.
- Cests create their own users (`POST /api/v2/user`, `POST /api/v4/oauth` password grant, `client_id: gc2-cli`) and delete only what they created. No secrets in tests.

## Review Focus

1. **A name with no ASCII letters** (`"地図"`, `"!!!"`): POST still succeeds and returns a key matching the accepted pattern. → Task 1 unit test.
2. **A row with the prefix but a foreign value** (not an object, or missing fields, e.g. written by hand through `/api/v4/keyvalue`): lists skip it, direct read is 404, nothing is a 500. → Task 1 unit test (`decode`), Task 3 Cest.
3. **`body` with unicode, slashes and odd whitespace** (`"{ \"a\":\"æ/ø\" ,\n\"b\":[1 ,2]}"`): returned byte-identical by GET, by the public `.json`, and after a PATCH that only changes `name`. → Task 2 and Task 3 Cest.
4. **PATCH with an unknown or forbidden field** (`key`, `id`, `owner`) or a non-boolean `published` (`"true"`): 400, row unchanged. → Task 2 Cest.
5. **DELETE with a list where one key is missing or repeated** (`a,missing`, `a,a`): the missing case deletes nothing and is 404; the repeated case deletes `a` once and is 204. → Task 2 Cest.

---

### Task 1: `Configuration` model

**Files:**
- Create: `app/models/Configuration.php`
- Test: `app/tests/unit/ConfigurationModelTest.php`

**Interfaces:**
- Consumes: `app\inc\Model` (`prepare`, `fetchAll`, `fetchRow`, `withTransaction`), `app\inc\Connection`, `app\exceptions\GC2Exception`, `app\inc\Model::toAscii`.
- Produces (`namespace app\models; class Configuration extends Model`):
  - `const string PREFIX = 'configuration_';`
  - `const string KEY_PATTERN = '/^configuration_[A-Za-z0-9_@+\-]+$/';`
  - `__construct(?Connection $connection = null)`
  - `static isValidKey(string $key): bool`
  - `static assertKey(string $key): void` — throws `GC2Exception(…, 400, null, 'INVALID_REQUEST')`.
  - `static generateKey(string $name): string`
  - `static assertBody(mixed $body): void` — throws 400 `INVALID_REQUEST` unless a string that `json_decode`s to an array (object or list).
  - `static decode(array $row): ?array` — row `{key, value}` → `{key, name, description, published, body}` or `null` when the value is not a configuration.
  - `static merge(array $stored, array $patch): array` — overlays any of `name, description, published, body`; keeps `key`.
  - `list(bool $publishedOnly): array` — list of decoded configurations, newest first.
  - `get(string $key, bool $publishedOnly): array` — one decoded configuration; throws 404 `CONFIGURATION_NOT_FOUND`.
  - `create(array $data): string` — returns the new key.
  - `patch(string $key, array $data): void` — throws 404 when missing.
  - `remove(array $keys): void` — throws 404 if any key is missing, before deleting anything.

- [ ] **Step 1: Write the failing unit tests** in `ConfigurationModelTest` (extends `Codeception\Test\Unit`, pure statics only, no DB):

```php
public function testIsValidKey(): void
{
    $this->assertTrue(Configuration::isValidKey('configuration_my_map_673211a0b1c2d3'));
    $this->assertTrue(Configuration::isValidKey('configuration_a@bdk_673211a0b1c2d3'));
    $this->assertFalse(Configuration::isValidKey('state_snapshot_1'));
    $this->assertFalse(Configuration::isValidKey('configuration_'));
    $this->assertFalse(Configuration::isValidKey('configuration_a,configuration_b'));
    $this->assertFalse(Configuration::isValidKey("configuration_a'; --"));
    $this->assertFalse(Configuration::isValidKey('configuration_a.json'));
}

public function testGenerateKey(): void
{
    foreach (['My map', 'a@b.dk', '地図', '!!!', ''] as $name) {
        $key = Configuration::generateKey($name);
        $this->assertMatchesRegularExpression('/^configuration_[A-Za-z0-9_]+$/', $key, $name);
    }
    $this->assertStringStartsWith('configuration_my_map_', Configuration::generateKey('My map'));
    $this->assertNotSame(Configuration::generateKey('x'), Configuration::generateKey('x'));
}

public function testAssertBody(): void
{
    Configuration::assertBody('{"a":1}');
    Configuration::assertBody('[]');
    Configuration::assertBody('{}');
    foreach (['{a:1}', '', 'null', '1', '"x"', 'true', ['a' => 1], null] as $bad) {
        try { Configuration::assertBody($bad); $this->fail(var_export($bad, true)); }
        catch (GC2Exception $e) { $this->assertSame(400, $e->getCode()); }
    }
}

public function testDecode(): void
{
    $value = json_encode(['name' => 'n', 'published' => true, 'description' => '', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1']);
    $this->assertSame(
        ['key' => 'configuration_n_1', 'name' => 'n', 'description' => '', 'published' => true, 'body' => '{ "a":1 }'],
        Configuration::decode(['key' => 'configuration_n_1', 'value' => $value])
    );
    $this->assertNull(Configuration::decode(['key' => 'configuration_x_1', 'value' => '"just a string"']));
    $this->assertNull(Configuration::decode(['key' => 'configuration_x_1', 'value' => '{"foo":1}']));
    $this->assertNull(Configuration::decode(['key' => 'configuration_x y', 'value' => $value]));
}

public function testMerge(): void
{
    $stored = ['name' => 'n', 'published' => false, 'description' => 'd', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1'];
    $merged = Configuration::merge($stored, ['name' => 'n2', 'published' => true]);
    $this->assertSame(['name' => 'n2', 'published' => true, 'description' => 'd', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1'], $merged);
}
```

- [ ] **Step 2: Run and see it fail**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit ConfigurationModelTest`
Expected: FAIL, class `app\models\Configuration` not found.

- [ ] **Step 3: Implement the statics.**
  - `generateKey`: `uniqid(self::PREFIX . self::toAscii($name, null, '_') . '_', true)`, then remove every character outside `[A-Za-z0-9_]` (this also drops the `.` that v2 strips). Same shape as `app/api/v2/Configuration.php:232-233`.
  - `decode`: `json_decode($row['value'], true)`; return `null` unless the key is valid, the result is an array, and `name` and `body` are strings. `published` is cast `(bool)`, a missing `description` becomes `''`. The returned `key` is the row key, not the one inside the value.

- [ ] **Step 4: Implement the DB methods.** Every statement targets `settings.key_value`, binds its parameters, and carries `key LIKE 'configuration\_%'` in the `WHERE`.
  - `list`: `SELECT key, value … ORDER BY updated DESC, id DESC`; decode in PHP, drop `null`s, then drop unpublished when `$publishedOnly`.
  - `get`: `assertKey`, select, decode; missing, undecodable, or unpublished-when-`$publishedOnly` all throw the same 404.
  - `create`: `assertBody`, `generateKey`, insert `(key, value)` only (owner/public untouched), value = `json_encode` of `{name, published, description, body, key}` in that order.
  - `patch`: `get($key, false)`; `assertBody` if `body` is present; `merge`; `UPDATE … SET value = :value, updated = default`, the same statement shape as `Keyvalue::update` (`app/models/Keyvalue.php:326`).
  - `remove`: de-duplicate, `get()` each first, then delete.

- [ ] **Step 5: Run the unit test**

Run: same command as Step 2. Expected: PASS (5 tests).

- [ ] **Step 6: Commit** — `feat(configuration): add configuration model on the keyvalue store`

---

### Task 2: Management controller `api/v4/configurations/[key]`

**Files:**
- Create: `app/api/v4/controllers/Configuration.php`
- Test: `app/tests/api/ConfigurationV4ApiCest.php`

**Interfaces:**
- Consumes: Task 1's `app\models\Configuration` (alias it `ConfigurationModel`), `AbstractApi` helpers (`getResponse`, `postResponse`, `patchResponse`, `deleteResponse`, `validateRequest`).
- Produces: class `app\api\v4\controllers\Configuration`; OpenAPI schema `Configuration`; `static getAssert(): Assert\Collection`. The Cest's setup (owner token, sub-user token, created keys held in private properties) is extended by Tasks 3 and 4.

Use `app/api/v4/controllers/Keyvalue.php` as the structural template (constructor, attributes, `validate`, empty `put_index`).

- [ ] **Step 1: Write the Cest.** Setup as `KeyvalueV4ApiCest::shouldPrepareUsersAndTokens` (owner + one sub-user, both with tokens). Tests, in order; `$body = "{ \"a\":\"æ/ø\" ,\n\"b\":[1 ,2]}"`:

| Test | Request (as owner unless stated) | Asserts |
|---|---|---|
| `shouldCreateOne` | `POST /api/v4/configurations` `{name:"My map", published:true, description:"d", body:$body}` | 201; `Location` matches `#^/api/v4/configurations/configuration_my_map_[A-Za-z0-9_]+$#`; store key as `$published` |
| `shouldCreateMany` | POST array of two, both `published:false` | 201; `Location` holds two comma-separated keys; store as `$draftA`, `$draftB` |
| `shouldGetOne` | `GET …/{$published}` | 200; body equals exactly `{key, name:"My map", description:"d", published:true, body:$body}` (`seeResponseEquals` on the encoded object, so no extra fields and `body` byte-identical) |
| `shouldGetList` | `GET …/{$published},{$draftA}` | 200; JSON array of 2 |
| `shouldListAll` | `GET /api/v4/configurations` | 200; bare array containing all three keys |
| `shouldPatch` | `PATCH …/{$published}` `{name:"Renamed"}` | 303 with `Location`; follow-up GET shows `name:"Renamed"`, same key, `body` still `$body` |
| `shouldRejectBadBody` | POST with `body:"{a:1}"`, then `body:"null"`; PATCH `…/{$published}` `{body:"1"}` | each 400 `INVALID_REQUEST`; GET still shows `$body` |
| `shouldRejectBadFields` | POST missing `name`; POST with extra `key`; PATCH `{owner:"x"}`; PATCH `{published:"true"}` | each 400; row unchanged |
| `shouldRejectBadKey` | `GET …/state_snapshot_1`; `GET …/{$published}.json` | 400 `INVALID_REQUEST` |
| `shouldReturn404` | `GET …/configuration_nope_1`; `PATCH` same; `GET …/{$published},configuration_nope_1` | 404 `CONFIGURATION_NOT_FOUND` |
| `shouldForbidSubUser` | as sub-user: GET list, GET one, POST, PATCH, DELETE | each 403 `SUPER_USER_ONLY` |
| `shouldRequireToken` | no `Authorization`: GET list | 401 |
| `shouldNotDeleteOnBadList` | `DELETE …/{$draftA},configuration_nope_1` | 404; `GET …/{$draftA}` still 200 |
| `shouldDeleteRepeated` | `DELETE …/{$draftA},{$draftA}` | 204; GET is 404 |
| `shouldCleanUp` (last) | `DELETE` remaining keys | 204 |

- [ ] **Step 2: Run and see it fail**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api ConfigurationV4ApiCest`
Expected: FAIL on `shouldCreateOne` with 404 (no route).

- [ ] **Step 3: Implement the controller.**
  - Attributes: `#[OA\Schema(schema: 'Configuration', required: ['name','published','description','body'], properties: key(readOnly), name, description, published(boolean), body(string, "JSON text of the Vidi configuration"))]`, `#[AcceptableMethods(['GET','POST','PATCH','DELETE','HEAD','OPTIONS'])]`, `#[Controller(route: 'api/v4/configurations/[key]', scope: Scope::SUPER_USER_ONLY)]`.
  - Operation ids: `getConfiguration`, `postConfiguration`, `patchConfiguration`, `deleteConfiguration`; tag `Configuration`. GET 200 and POST request body are `oneOf: [ref Configuration, array of ref Configuration]`.
  - `get_index`: no key → `getResponse($model->list(false))`; key → split on `,`, `get($k, false)` each, `getResponse($rows, single: count($rows) === 1)`.
  - `post_index`: body is one object or `array_is_list`; create all inside one `withTransaction`; `postResponse('/api/v4/configurations/', $keys)`.
  - `patch_index`: single key; `patchResponse('/api/v4/configurations/', [$key])`.
  - `delete_index`: split on `,`, `remove($keys)` in a transaction, `deleteResponse()`.
  - `validate()`: POST with a key or PATCH/DELETE without one → 400 `INVALID_REQUEST`; PATCH with a `,` in the key → 400; every key through `ConfigurationModel::assertKey`; for POST loop the list and call `validateRequest(self::getAssert(), json_encode($item), 'post')` per item (pattern: `SqlNoToken::validate`).
  - `getAssert()`: `name` `Type('string')` + `NotBlank`; `description` `Type('string')`; `published` `Type('bool')`; `body` `Type('string')`. All `Required` on POST, all `Optional` on PATCH. `Assert\Collection` rejects unknown fields by default — keep that.

- [ ] **Step 4: Run the Cest**

Run: same command as Step 2. Expected: PASS, all tests.

- [ ] **Step 5: Commit** — `feat(configuration): add v4 configurations management endpoint`

---

### Task 3: Public controller `api/v4/configurations/database/{database}/[key]`

**Files:**
- Create: `app/api/v4/controllers/ConfigurationPublic.php`
- Modify: `app/tests/api/ConfigurationV4ApiCest.php` (add tests before the cleanup test)
- Modify: `app/tests/unit/Route2MatchTest.php` (two tests)

**Interfaces:**
- Consumes: Task 1's model; Task 2's Cest properties (`$userId` = database, `$published`, `$draftB`, `$body`); `app\api\v4\Responses\StreamedResponse(string $contentType, Closure $callback, int $status = 200, array $headers = [])`.
- Produces: class `app\api\v4\controllers\ConfigurationPublic`, operation id `getPublicConfiguration`.

- [ ] **Step 1: Add the unit tests** to `Route2MatchTest`:

```php
public function testPublicConfigurationRouteKeepsJsonSuffix(): void
{
    $match = Route2::matchSignature('api/v4/configurations/database/{database}/[key]', 'api/v4/configurations/database/mydb/configuration_a_1.json');
    $this->assertSame(['database' => 'mydb', 'key' => 'configuration_a_1.json'], $match['params']);
}

public function testManagementConfigurationRouteDoesNotMatchPublicUrl(): void
{
    $this->assertNull(Route2::matchSignature('api/v4/configurations/[key]', 'api/v4/configurations/database/mydb'));
}
```

Run: `… codecept run unit Route2MatchTest`. Expected: PASS already (they pin routing behaviour the controller relies on). If either fails, stop and report — the route shape in the spec then needs revisiting.

- [ ] **Step 2: Add the Cest tests** (no `Authorization` header; base = `/api/v4/configurations/database/{$userId}`):

| Test | Request | Asserts |
|---|---|---|
| `anonShouldListPublishedOnly` | `GET base` | 200; bare array; contains `$published`; does not contain `$draftB` |
| `anonShouldGetPublished` | `GET base/{$published}` | 200; object with exactly the five fields |
| `anonShouldGetJsonFile` | `GET base/{$published}.json` | 200; `Content-Type` starts `application/json`; `Content-Disposition` = `attachment; filename={$published}.json`; response body `=== $body` |
| `anonShouldNotSeeDraft` | `GET base/{$draftB}` and `base/{$draftB}.json` | 404 `CONFIGURATION_NOT_FOUND` |
| `anonShouldGet404ForUnknown` | `GET base/configuration_nope_1` | 404, same body shape as the draft case |
| `anonShouldRejectForeignKey` | `GET base/state_snapshot_1`; `GET base/{$published},{$draftB}` | 400 `INVALID_REQUEST` |
| `anonShouldSkipForeignRow` | as owner `POST /api/v4/keyvalue/configuration_junk_{ts}` `{value:{foo:1}, public:true}`; then `GET base` and `GET base/configuration_junk_{ts}` | list is 200 and lacks the junk key; direct read 404; delete the junk key through `/api/v4/keyvalue` at the end of the test |
| `anonShouldGet404ForUnknownDatabase` | `GET /api/v4/configurations/database/no_such_db_{ts}` | 404 `DATABASE_NOT_FOUND` |
| `anonShouldIgnoreToken` | with the owner's `Authorization`: `GET base/{$draftB}` | 404 |
| `anonShouldNotWrite` | `POST base`, `DELETE base/{$published}` | 405 |
| `anonPreflight` | `OPTIONS base/{$published}.json` | 2xx, empty body |

- [ ] **Step 3: Run and see them fail**

Run: `… codecept run api ConfigurationV4ApiCest`. Expected: the new tests FAIL with 404 route miss / 401; Task 2's still PASS.

- [ ] **Step 4: Implement the controller.**
  - `#[AcceptableMethods(['GET','HEAD','OPTIONS'])]`, `#[Controller(route: 'api/v4/configurations/database/{database}/[key]', scope: Scope::PUBLIC)]`, `#[AcceptableAccepts(['application/json', '*/*'])]`.
  - Constructor as `SqlNoToken`. In `get_index`, set `$this->connection->database = $this->route->getParam('database')` **before** constructing the model with `$this->connection`. Never read `$this->route->jwt`.
  - Key handling: if the key ends in `.json`, strip it and set a flag; then `ConfigurationModel::assertKey` (a comma fails the pattern, which gives the 400).
  - No key → `getResponse($model->list(true))`. Key → `$model->get($key, true)`; with the flag return `new StreamedResponse('application/json', fn() => print($c['body']), 200, ['Content-Disposition' => "attachment; filename=$key.json"])`, else `getResponse([$c], single: true)`.
  - Unknown database: catch the connection failure raised on first query for a database that does not exist and rethrow `GC2Exception('Database not found', 404, null, 'DATABASE_NOT_FOUND')`. Find the exact exception by running the unknown-database test once and reading `docker logs docker-dev-1`; catch only that case (SQLSTATE `3D000`), not every `PDOException`.
  - `validate()` only validates the key as above; `post_index`/`patch_index`/`delete_index`/`put_index` are empty stubs as in `SqlNoToken`.
  - OA: `#[OA\Get(path: '/api/v4/configurations/database/{database}/{key}', operationId: 'getPublicConfiguration', …)]`, no `security`, describe the `.json` form in the `key` parameter description, 200 as `oneOf` object/array, 404 documented.

- [ ] **Step 5: Run the Cest**

Run: same command as Step 3. Expected: PASS, all tests.

- [ ] **Step 6: Commit** — `feat(configuration): add anonymous v4 read of published configurations`

---

### Task 4: v2 interchange, OpenAPI and docs

**Files:**
- Modify: `app/tests/api/ConfigurationV4ApiCest.php`
- Create: `docs/pages/…` — only if a v4 API page index exists there; otherwise skip and say so.
- Modify: `docs/superpowers/specs/2026-11-11-configurations-v4-design.md` (status line; any deviation found during Tasks 1–3)

**Interfaces:**
- Consumes: Tasks 2–3 endpoints; v2 session login as in `app/tests/api/ConfigurationManagementCest.php:29-50` (`POST /api/v2/session/start`, `PHPSESSID` cookie).
- Produces: nothing new for later tasks.

- [ ] **Step 1: Add interchange tests** (before cleanup). Start a v2 session for the owner in a setup test.

| Test | Steps | Asserts |
|---|---|---|
| `v2ShouldSeeV4Row` | with the session cookie `GET /api/v2/configuration/{$userId}` | 200; some `data[].key === $published`; that item's `value` decodes to an object with keys `name, published, description, body, key` and `body === $body` |
| `v2ShouldServeV4RowAsFile` | anonymous `GET /api/v2/configuration/{$userId}/{$published}.json` | 200; body `=== $body` |
| `v4ShouldSeeV2Row` | with the cookie `POST /api/v2/configuration/{$userId}` `{name:"From v2", published:true, description:"", body:"{\"x\": 1}"}`; take `data.key`; then token `GET /api/v4/configurations/{key}` and anonymous `GET base/{key}.json` | v4 object has `name:"From v2"`, `published:true`; `.json` body `=== "{\"x\": 1}"` |
| `v4PatchShouldKeepV2Body` | token `PATCH /api/v4/configurations/{key}` `{description:"edited in v4"}`; then cookie `GET /api/v2/configuration/{$userId}/{key}` | v2 `value` has `description:"edited in v4"`, `body === "{\"x\": 1}"`, `key` unchanged |
| `v2RowOwnerStaysNull` | token `GET /api/v4/keyvalue/{$published}` | `owner` is null (v4-created row is stored like a v2 row) |

Delete the v2-created key in the cleanup test.

- [ ] **Step 2: Run** `… codecept run api ConfigurationV4ApiCest`. Expected: PASS. A failure here is a compatibility defect in Task 1's stored shape — fix it there, not by loosening the test.

- [ ] **Step 3: Check the OpenAPI document.** Build it the way `swagger.php` is served in the dev container (find the route with `grep -rn "swagger" public/index.php app/api/v4 | head`), fetch it from `http://localhost:8080`, and confirm it contains operation ids `getConfiguration`, `postConfiguration`, `patchConfiguration`, `deleteConfiguration`, `getPublicConfiguration` and `components.schemas.Configuration`.
Expected: all six names present. Record the command used in the task report.

- [ ] **Step 4: Run the neighbours** to confirm nothing else moved, one command each: `run api ConfigurationManagementCest`, `run api KeyvalueV4ApiCest`, `run unit Route2MatchTest`. Expected: PASS.

- [ ] **Step 5: Update the spec** status to `implemented` and add a "Deviations" section listing anything built differently (or "none").

- [ ] **Step 6: Write the downstream note** in the task report (not a file): new operations and URLs for GC2 App, SDK, MCP and website docs; the new Vidi URL form `…/api/v4/configurations/database/{database}/{key}.json`; and that `body` must be valid JSON object/array on v4 writes.

- [ ] **Step 7: Commit** — `test(configuration): cover v2/v4 interchange` and `docs: mark configurations v4 spec implemented`
