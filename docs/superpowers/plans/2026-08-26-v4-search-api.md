# v4 Search API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the used part of the v2 Elasticsearch API to a v4 "Search" surface — (re)build an OpenSearch index from a table/view and search it — with a per-database `analysis` block and the OpenSearch analyzer bug fixed.

**Architecture:** Two new v4 controllers (`Search` per-table for query+lifecycle, `SearchSettings` at database level for the per-db `analysis`), a new Guzzle-based `app\opensearch\Client`, and a small settings composer. The table→mapping logic (`Elasticsearch::createMapFromTable`) and the bulk indexer (`Sql_to_es`) are reused unchanged. The legacy v2 controllers and the old curl `app\models\Elasticsearch` model are left in place.

**Tech Stack:** PHP 8.4, GuzzleHttp\Client, OpenSearch, PostgreSQL, Codeception (unit + api suites).

**Spec:** `docs/superpowers/specs/2026-08-26-v4-search-api-design.md`

## Global Constraints

- **Runtime PHP is 8.4** (container `docker-dev-1`). The repo uses 8.4-only syntax (`new X()->method()`). Lint every changed PHP file with `docker exec docker-dev-1 php -l /var/www/geocloud2/<path>` (ignore the JIT warning). The host CLI is 8.3 and will falsely report parse errors — do not use it.
- **Tests run in the container** `docker-gc2core-1`, working dir `/var/www/geocloud2/app`: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run <suite> <TestName>'`. `error_log` output goes to `docker logs docker-gc2core-1`.
- **File header** on every new PHP file (copy verbatim, only the year/author line style already used across the repo):
  ```php
  <?php
  /**
   * @author     Martin Høgh <mh@mapcentia.com>
   * @copyright  2013-2026 MapCentia ApS
   * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
   */
  ```
- **Index name** is always `{db}_{schema}_{table}` where `db` is `$this->route->jwt['data']['database']`.
- **Auth:** search = `AbstractApi::initiate(schema, relation)` (schema/relation existence + sub-user schema access). Rebuild/drop/settings = owner/superuser only: `$this->route->jwt['data']['superUser'] === true`, else throw `GC2Exception("...", 403, null, "NOT_OWNER")`.
- **`esHost`** comes from `App::$param['esHost']`; port defaults to `9200` when absent (same normalization as `app\models\Sql_to_es::runSql`).
- **Do not touch** `app/api/v2/Elasticsearch.php`, `app/api/v1/Elasticsearch.php`, or `app/models/Elasticsearch.php` (legacy). The v2 controller's inline default settings become dead code once the shared JSON is correct; leaving it is intentional and lower-risk.

---

## File Structure

- Create `app/opensearch/Client.php` — Guzzle OpenSearch client: `indexExists`, `createIndex`, `deleteIndex`, `search`. Throws `OpenSearchException` on non-2xx.
- Create `app/opensearch/OpenSearchException.php` — carries HTTP status + parsed error body.
- Create `app/opensearch/SettingsComposer.php` — composes index settings from the static default + optional per-db `analysis`.
- Modify `app/conf/elasticsearch_settings.json` — fix `edge_ngram` + working `max_ngram_diff`.
- Modify `app/models/Setting.php` — add `getSearchAnalysis()` / `updateSearchAnalysis()`.
- Create `app/api/v4/controllers/SearchSettings.php` — `GET`/`PUT` `api/v4/search/settings`.
- Create `app/api/v4/controllers/Search.php` — `GET`/`POST`/`PUT`/`DELETE` `api/v4/schemas/{schema}/tables/{table}/search`.
- Create `app/tests/unit/SearchSettingsComposerTest.php`, `app/tests/unit/OpenSearchClientTest.php`.
- Create `app/tests/api/SearchV4ApiCest.php`.

---

## Task 1: Fix and validate the default OpenSearch settings JSON

**Files:**
- Modify: `app/conf/elasticsearch_settings.json`
- Test: `app/tests/unit/SearchSettingsComposerTest.php` (created here, extended in Task 3)

**Interfaces:**
- Produces: a valid `elasticsearch_settings.json` whose `settings.analysis.filter.substring.type === "edge_ngram"` and whose ngram range is within `settings.max_ngram_diff`.

- [ ] **Step 1: Write the failing test**

Create `app/tests/unit/SearchSettingsComposerTest.php`:

```php
<?php
use PHPUnit\Framework\TestCase;

class SearchSettingsComposerTest extends TestCase
{
    public function testDefaultSettingsJsonIsOpenSearchCompatible(): void
    {
        $path = __DIR__ . '/../../conf/elasticsearch_settings.json';
        $json = json_decode(file_get_contents($path), true);
        $this->assertIsArray($json, 'settings JSON must parse');

        $filter = $json['settings']['analysis']['filter']['substring'];
        $this->assertSame('edge_ngram', $filter['type'], 'camelCase edgeNGram was removed in ES7/OpenSearch');

        $range = $filter['max_gram'] - $filter['min_gram'];
        $this->assertLessThanOrEqual(
            $json['settings']['max_ngram_diff'],
            $range,
            'ngram range must not exceed index.max_ngram_diff'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit SearchSettingsComposerTest'`
Expected: FAIL — `edgeNGram` !== `edge_ngram` (and range 254 > 20).

- [ ] **Step 3: Fix the JSON**

Replace `app/conf/elasticsearch_settings.json` with:

```json
{
  "settings": {
    "number_of_shards": 5,
    "number_of_replicas": 0,
    "max_ngram_diff": 19,
    "analysis": {
      "analyzer": {
        "auto_complete_search_analyzer": {
          "type": "custom",
          "tokenizer": "whitespace",
          "filter": ["lowercase", "asciifolding"]
        },
        "auto_complete_analyzer": {
          "type": "custom",
          "tokenizer": "whitespace",
          "filter": ["lowercase", "asciifolding", "substring"]
        }
      },
      "filter": {
        "substring": {
          "type": "edge_ngram",
          "min_gram": 1,
          "max_gram": 20
        }
      }
    }
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit SearchSettingsComposerTest'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/conf/elasticsearch_settings.json app/tests/unit/SearchSettingsComposerTest.php
git commit -m "fix(search): correct OpenSearch analyzer default (edge_ngram + max_ngram_diff)"
```

---

## Task 2: OpenSearch client

**Files:**
- Create: `app/opensearch/OpenSearchException.php`
- Create: `app/opensearch/Client.php`
- Test: `app/tests/unit/OpenSearchClientTest.php`

**Interfaces:**
- Produces:
  - `app\opensearch\OpenSearchException extends \Exception` with `public function __construct(string $message, int $status, ?array $body = null)` and `public function getStatus(): int`, `public function getBody(): ?array`.
  - `app\opensearch\Client`:
    - `__construct(?string $host = null, ?\GuzzleHttp\Client $http = null)`
    - `indexExists(string $index): bool`
    - `createIndex(string $index, array $body): array` — `$body` is `['settings'=>..., 'mappings'=>...]`; throws `OpenSearchException` on non-2xx.
    - `deleteIndex(string $index): void` — ignores 404.
    - `search(string $index, string $query, bool $isBody): array` — returns decoded OpenSearch response; throws `OpenSearchException` on non-2xx.

- [ ] **Step 1: Write the failing test**

Create `app/tests/unit/OpenSearchClientTest.php` (uses Guzzle's MockHandler — no live cluster):

```php
<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use app\opensearch\Client;
use app\opensearch\OpenSearchException;

class OpenSearchClientTest extends TestCase
{
    private function clientWith(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $guzzle = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);
        return new Client('http://os:9200', $guzzle);
    }

    public function testIndexExistsTrueOn200(): void
    {
        $c = $this->clientWith([new Response(200)]);
        $this->assertTrue($c->indexExists('db_s_t'));
    }

    public function testIndexExistsFalseOn404(): void
    {
        $c = $this->clientWith([new Response(404)]);
        $this->assertFalse($c->indexExists('db_s_t'));
    }

    public function testCreateIndexThrowsOnError(): void
    {
        $c = $this->clientWith([new Response(400, [], json_encode(['error' => ['reason' => 'bad analyzer']]))]);
        $this->expectException(OpenSearchException::class);
        $c->createIndex('db_s_t', ['settings' => []]);
    }

    public function testSearchReturnsDecodedBody(): void
    {
        $c = $this->clientWith([new Response(200, [], json_encode(['hits' => ['total' => ['value' => 3]]]))]);
        $res = $c->search('db_s_t', '{"query":{"match_all":{}}}', true);
        $this->assertSame(3, $res['hits']['total']['value']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit OpenSearchClientTest'`
Expected: FAIL — `Class "app\opensearch\Client" not found`.

- [ ] **Step 3: Write `OpenSearchException`**

Create `app/opensearch/OpenSearchException.php` (with the standard header):

```php
namespace app\opensearch;

use Exception;

class OpenSearchException extends Exception
{
    public function __construct(string $message, private readonly int $status, private readonly ?array $body = null)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }
}
```

- [ ] **Step 4: Write `Client`**

Create `app/opensearch/Client.php` (with the standard header):

```php
namespace app\opensearch;

use app\conf\App;
use GuzzleHttp\Client as GuzzleClient;

class Client
{
    private string $host;
    private GuzzleClient $http;

    public function __construct(?string $host = null, ?GuzzleClient $http = null)
    {
        $raw = $host ?? (App::$param['esHost'] ?: 'http://127.0.0.1');
        $split = explode(':', $raw);
        $port = !empty($split[2]) ? $split[2] : '9200';
        $this->host = $split[0] . ':' . $split[1] . ':' . $port;
        $this->http = $http ?? new GuzzleClient([
            'timeout' => 60.0,
            'http_errors' => false,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    public function indexExists(string $index): bool
    {
        $res = $this->http->head("$this->host/$index");
        return $res->getStatusCode() === 200;
    }

    public function createIndex(string $index, array $body): array
    {
        $res = $this->http->put("$this->host/$index", ['body' => json_encode($body)]);
        return $this->decodeOrThrow($res, "Could not create index '$index'");
    }

    public function deleteIndex(string $index): void
    {
        $res = $this->http->delete("$this->host/$index");
        $code = $res->getStatusCode();
        if ($code !== 200 && $code !== 404) {
            $this->decodeOrThrow($res, "Could not delete index '$index'");
        }
    }

    public function search(string $index, string $query, bool $isBody): array
    {
        $url = "$this->host/$index/_search";
        if ($isBody) {
            $res = $this->http->post($url, ['body' => $query]);
        } else {
            $res = $this->http->get($url . ($query !== '' ? "?$query" : ''));
        }
        return $this->decodeOrThrow($res, "Search failed on index '$index'");
    }

    private function decodeOrThrow(\Psr\Http\Message\ResponseInterface $res, string $context): array
    {
        $body = json_decode((string)$res->getBody(), true);
        $code = $res->getStatusCode();
        if ($code < 200 || $code >= 300) {
            throw new OpenSearchException($context, $code, is_array($body) ? $body : null);
        }
        return is_array($body) ? $body : [];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit OpenSearchClientTest'`
Expected: PASS. Then lint: `docker exec docker-dev-1 php -l /var/www/geocloud2/app/opensearch/Client.php` and `.../OpenSearchException.php`.

- [ ] **Step 6: Commit**

```bash
git add app/opensearch/Client.php app/opensearch/OpenSearchException.php app/tests/unit/OpenSearchClientTest.php
git commit -m "feat(search): add opensearch Guzzle client"
```

---

## Task 3: Settings composer + Setting model helpers

**Files:**
- Create: `app/opensearch/SettingsComposer.php`
- Modify: `app/models/Setting.php` (add two methods after `getArray()`, ~line 76)
- Test: `app/tests/unit/SearchSettingsComposerTest.php` (extend from Task 1)

**Interfaces:**
- Consumes: `app/conf/elasticsearch_settings.json` (Task 1).
- Produces:
  - `app\opensearch\SettingsComposer::compose(?array $perDbAnalysis): array` — returns `['settings' => [...]]`; when `$perDbAnalysis` is non-null it replaces `settings.analysis`.
  - `app\opensearch\SettingsComposer::defaultAnalysis(): array` — the default `settings.analysis` block.
  - `app\models\Setting::getSearchAnalysis(): ?array`
  - `app\models\Setting::updateSearchAnalysis(array $analysis): void`

- [ ] **Step 1: Write the failing test**

Add to `app/tests/unit/SearchSettingsComposerTest.php`:

```php
    public function testComposeUsesDefaultWhenNull(): void
    {
        $settings = \app\opensearch\SettingsComposer::compose(null);
        $this->assertSame('edge_ngram', $settings['settings']['analysis']['filter']['substring']['type']);
        $this->assertSame(5, $settings['settings']['number_of_shards']);
    }

    public function testComposeReplacesAnalysisWhenProvided(): void
    {
        $custom = ['analyzer' => ['x' => ['type' => 'custom', 'tokenizer' => 'standard']], 'filter' => []];
        $settings = \app\opensearch\SettingsComposer::compose($custom);
        $this->assertSame($custom, $settings['settings']['analysis'], 'per-db analysis replaces default');
        $this->assertSame(5, $settings['settings']['number_of_shards'], 'other settings stay from default');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit SearchSettingsComposerTest'`
Expected: FAIL — `Class "app\opensearch\SettingsComposer" not found`.

- [ ] **Step 3: Write `SettingsComposer`**

Create `app/opensearch/SettingsComposer.php` (with header):

```php
namespace app\opensearch;

use app\conf\App;

class SettingsComposer
{
    public static function compose(?array $perDbAnalysis): array
    {
        $default = json_decode(file_get_contents(App::$param['path'] . '/app/conf/elasticsearch_settings.json'), true);
        if ($perDbAnalysis !== null) {
            $default['settings']['analysis'] = $perDbAnalysis;
        }
        return $default;
    }

    public static function defaultAnalysis(): array
    {
        return self::compose(null)['settings']['analysis'];
    }
}
```

Note: unit tests run with `App::$param['path']` set by the Codeception bootstrap. If `path` is unset in the unit context, the test bootstrap already loads `app/conf/App.php`; verify by running the test. If `path` is genuinely absent, the composer test can pass an explicit default — but do NOT add a constructor param just for tests; prefer the real config.

- [ ] **Step 4: Add Setting helpers**

In `app/models/Setting.php`, after `getArray()` (line 76), add:

```php
    /**
     * Per-database OpenSearch analysis block, stored under `search.analysis`
     * in settings.viewer. Null when the database has no custom analysis.
     */
    public function getSearchAnalysis(): ?array
    {
        $arr = $this->getArray();
        if (!isset($arr->search->analysis)) {
            return null;
        }
        return json_decode(json_encode($arr->search->analysis), true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updateSearchAnalysis(array $analysis): void
    {
        $arr = $this->getArray();
        if (!isset($arr->search)) {
            $arr->search = new stdClass();
        }
        $arr->search->analysis = $analysis;
        if (App::$param["encryptSettings"]) {
            $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
            $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($arr) . "', dearmor('$pubKey'))";
        } else {
            $sql = "UPDATE settings.viewer SET viewer='" . json_encode($arr) . "'";
        }
        $this->execQuery($sql, "PDO", "transaction");
        $this->clearCacheOnSchemaChanges();
    }
```

(`stdClass`, `App`, and `InvalidArgumentException` are already imported in `Setting.php`; confirm with the file's `use` block and add any that are missing.)

- [ ] **Step 5: Run test to verify it passes**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run unit SearchSettingsComposerTest'`
Expected: PASS. Lint `app/models/Setting.php` and `app/opensearch/SettingsComposer.php`.

- [ ] **Step 6: Commit**

```bash
git add app/opensearch/SettingsComposer.php app/models/Setting.php app/tests/unit/SearchSettingsComposerTest.php
git commit -m "feat(search): settings composer + per-db analysis in settings.viewer"
```

---

## Task 4: `SearchSettings` controller (per-db analysis)

**Files:**
- Create: `app/api/v4/controllers/SearchSettings.php`
- Test: `app/tests/api/SearchV4ApiCest.php` (created here; extended in Tasks 5–6)

**Interfaces:**
- Consumes: `Setting::getSearchAnalysis/updateSearchAnalysis` (Task 3), `SettingsComposer::defaultAnalysis()` (Task 3).
- Produces: routes `GET`/`PUT` `api/v4/search/settings`. Body shape `{"analysis": { ... }}`.

- [ ] **Step 1: Write the controller**

Create `app/api/v4/controllers/SearchSettings.php` (with header). Model the constructor/attributes on `app/api/v4/controllers/Keyvalue.php`:

```php
namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Setting;
use app\opensearch\SettingsComposer;
use OpenApi\Attributes as OA;

#[AcceptableMethods(['GET', 'PUT', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/search/settings', scope: Scope::SUB_USER_ALLOWED)]
class SearchSettings extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'search settings';
    }

    private function assertOwner(): void
    {
        if (empty($this->route->jwt['data']['superUser'])) {
            throw new GC2Exception("Only the database owner can manage search settings", 403, null, "NOT_OWNER");
        }
    }

    public function validate(): void
    {
    }

    #[OA\Get(path: '/api/v4/search/settings', operationId: 'getSearchSettings', description: "Get the per-database OpenSearch analysis block (the default when none is set).", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'The analysis block')]
    public function get_index(): Response
    {
        $this->assertOwner();
        $analysis = (new Setting($this->connection))->getSearchAnalysis() ?? SettingsComposer::defaultAnalysis();
        return $this->getResponse(['analysis' => $analysis]);
    }

    #[OA\Put(path: '/api/v4/search/settings', operationId: 'putSearchSettings', description: "Set the per-database OpenSearch analysis block. Applies to indexes built after this call.", tags: ['Search'])]
    #[AcceptableContentTypes(['application/json'])]
    #[OA\Response(response: 200, description: 'Saved')]
    public function put_index(): Response
    {
        $this->assertOwner();
        $body = json_decode((string)Input::getBody(), true);
        if (!is_array($body) || !isset($body['analysis']) || !is_array($body['analysis'])) {
            throw new GC2Exception("Body must be a JSON object with an 'analysis' object", 400, null, "INVALID_DATA");
        }
        (new Setting($this->connection))->updateSearchAnalysis($body['analysis']);
        return $this->getResponse(['analysis' => $body['analysis']]);
    }

    public function post_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function delete_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function put_index_deprecated(): Response
    {
        return $this->put_index();
    }
}
```

Note: confirm the exact set of abstract methods `AbstractApi`/`ApiInterface` requires by checking `app/api/v4/controllers/Keyvalue.php` (it implements the same interface); implement exactly those, throwing `METHOD_NOT_ALLOWED` where the resource does not support them. Remove the `put_index_deprecated` stub if the interface does not need it.

- [ ] **Step 2: Write the failing API test (owner sets and reads analysis)**

Create `app/tests/api/SearchV4ApiCest.php`. Copy the user/token/schema bootstrap from `app/tests/api/FeatureV4ApiCest.php::shouldPrepareUserSchemaAndLayer` (same user creation → `/api/v4/oauth` password grant → `Authorization: Bearer` → create schema → create a `poi` table with `gid serial`, `name varchar`, and a geometry column). Then:

```php
    public function shouldSetAndGetPerDbAnalysis(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $analysis = [
            'analyzer' => ['t' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase']]],
            'filter' => new stdClass(),
        ];
        $I->sendPUT('/api/v4/search/settings', json_encode(['analysis' => $analysis]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('/api/v4/search/settings');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['analysis' => ['analyzer' => ['t' => ['tokenizer' => 'standard']]]]);
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest'`
Expected: FAIL — 404/route not found before the controller exists (or an assertion failure).

- [ ] **Step 4: Make it pass**

The controller from Step 1 should satisfy the test. Lint `app/api/v4/controllers/SearchSettings.php`. If the route is not matched, verify the v4 dispatcher auto-discovers controllers by the `#[Controller]` attribute (as with `Keyvalue`) — no manual registration should be needed.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest:shouldSetAndGetPerDbAnalysis'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/SearchSettings.php app/tests/api/SearchV4ApiCest.php
git commit -m "feat(search): SearchSettings v4 controller for per-db analysis"
```

---

## Task 5: `Search` controller — query (GET/POST)

**Files:**
- Create: `app/api/v4/controllers/Search.php`
- Test: `app/tests/api/SearchV4ApiCest.php` (extend)

**Interfaces:**
- Consumes: `app\opensearch\Client` (Task 2), `AbstractApi::initiate` (existing).
- Produces: routes `GET`/`POST` `api/v4/schemas/{schema}/tables/{table}/search`; index name `{db}_{schema}_{table}`. `PUT`/`DELETE` are added in Task 6 (leave `AcceptableMethods` including them now, but implement `put_index`/`delete_index` as `METHOD_NOT_ALLOWED` stubs here so the class is loadable; Task 6 replaces them).

- [ ] **Step 1: Write the controller (query methods + stubs)**

Create `app/api/v4/controllers/Search.php` (with header):

```php
namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\opensearch\Client;
use app\opensearch\OpenSearchException;
use OpenApi\Attributes as OA;

#[AcceptableMethods(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/search', scope: Scope::SUB_USER_ALLOWED)]
class Search extends AbstractApi
{
    private string $schemaName;
    private string $tableName;

    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'search';
    }

    private function initParams(): void
    {
        $this->schemaName = (string)$this->route->getParam('schema');
        $this->tableName = (string)$this->route->getParam('table');
    }

    private function indexName(): string
    {
        return $this->route->jwt['data']['database'] . "_" . $this->schemaName . "_" . $this->tableName;
    }

    private function assertOwner(): void
    {
        if (empty($this->route->jwt['data']['superUser'])) {
            throw new GC2Exception("Only the database owner can build or drop a search index", 403, null, "NOT_OWNER");
        }
    }

    private function mapOpenSearchException(OpenSearchException $e, string $code): GC2Exception
    {
        $reason = $e->getBody()['error']['reason'] ?? $e->getMessage();
        return new GC2Exception($reason, $e->getStatus() ?: 400, $e, $code);
    }

    public function validate(): void
    {
        $this->initParams();
        // Read auth + schema/relation existence (sub-user schema access enforced here).
        $this->initiate(schema: $this->schemaName, relation: $this->tableName);
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'search', description: "Search the table's OpenSearch index. Query string or body is passed to OpenSearch _search unaltered.", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'OpenSearch response')]
    public function get_index(): Response
    {
        $query = Input::getBody() ?: (Input::getQueryString() ?: '');
        return $this->runSearch($query, (bool)Input::getBody());
    }

    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'searchPost', description: "Search with an OpenSearch query DSL body.", tags: ['Search'])]
    #[AcceptableContentTypes(['application/json'])]
    #[OA\Response(response: 200, description: 'OpenSearch response')]
    public function post_index(): Response
    {
        return $this->runSearch((string)Input::getBody(), true);
    }

    private function runSearch(string $query, bool $isBody): Response
    {
        $client = new Client();
        try {
            $result = $client->search($this->indexName(), $query, $isBody);
        } catch (OpenSearchException $e) {
            if ($e->getStatus() === 404) {
                throw new GC2Exception("No search index for this relation. Build it first with PUT.", 404, $e, "INDEX_NOT_FOUND");
            }
            throw $this->mapOpenSearchException($e, "SEARCH_ERROR");
        }
        return $this->getResponse($result);
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Not implemented", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function delete_index(): Response
    {
        throw new GC2Exception("Not implemented", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
```

Note: `getResponse(array $data)` JSON-encodes `$data`; the OpenSearch response is already an array, so it round-trips fine. Confirm `Input::getQueryString()` returns the raw query string (as used in v2 `get_search`).

- [ ] **Step 2: Write the failing API test (search before build → 404 INDEX_NOT_FOUND)**

Add to `SearchV4ApiCest.php`:

```php
    public function shouldReturn404WhenNoIndex(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search?q=*');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['code' => 'INDEX_NOT_FOUND']);
    }
```

(Confirm the error-body key the v4 error renderer uses — check an existing FeatureV4ApiCest 404 assertion; adjust `['code' => ...]` to the actual shape, e.g. `errorCode`/`code`.)

- [ ] **Step 3: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest:shouldReturn404WhenNoIndex'`
Expected: FAIL — route/controller not present yet.

- [ ] **Step 4: Make it pass**

Controller from Step 1 satisfies it (search against a non-existent index → OpenSearch 404 → `INDEX_NOT_FOUND`). Requires OpenSearch reachable from `docker-gc2core-1` at `App::$param['esHost']`. Lint `app/api/v4/controllers/Search.php`.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest:shouldReturn404WhenNoIndex'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/controllers/Search.php app/tests/api/SearchV4ApiCest.php
git commit -m "feat(search): Search v4 controller query (GET/POST) + index-not-found mapping"
```

---

## Task 6: `Search` controller — rebuild (PUT) and drop (DELETE)

**Files:**
- Modify: `app/api/v4/controllers/Search.php` (replace the `put_index`/`delete_index` stubs from Task 5)
- Test: `app/tests/api/SearchV4ApiCest.php` (extend)

**Interfaces:**
- Consumes: `Client` (Task 2), `SettingsComposer::compose` + `Setting::getSearchAnalysis` (Task 3), `app\models\Elasticsearch::createMapFromTable` (existing), `app\models\Sql_to_es::runSql($q, $schema, $rel, $priKey, $db)` returning `['success'=>bool, 'errors'=>bool, 'errors_in'=>array, 'num_of_bulks'=>int, 'message'=>string]` (existing).
- Produces: `PUT` (build) and `DELETE` (drop) on the search route; both owner-only.

- [ ] **Step 1: Write the failing API test (owner builds, then searches, then drops)**

Add to `SearchV4ApiCest.php` (assumes the `poi` table has a couple of rows — insert them via the v4 Feature API or a v2 insert as `FeatureV4ApiCest` does; reuse that helper):

```php
    public function shouldBuildSearchAndDropIndex(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');

        // Seed one feature so the index has a document.
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/features',
            json_encode(['type' => 'Feature', 'properties' => ['name' => 'Findme'],
                'geometry' => ['type' => 'Point', 'coordinates' => [9.5, 55.7]]]));

        // Build
        $I->sendPUT('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['index' => $this->userId . '_' . $this->schemaName . '_poi']);

        // OpenSearch is near-real-time; refresh by waiting or the API can force it. Poll search.
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search',
            json_encode(['query' => ['match' => ['properties.name' => 'Findme']]]));
        $I->seeResponseCodeIs(HttpCode::OK);

        // Drop
        $I->sendDELETE('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function shouldForbidSubUserBuild(ApiTester $I)
    {
        // A sub-user token (create one in bootstrap) must get 403 NOT_OWNER on PUT.
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subUserToken);
        $I->sendPUT('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['code' => 'NOT_OWNER']);
    }
```

Add sub-user creation to the bootstrap: create a sub-user under the database and obtain a token via the password grant, following the sub-user pattern already used in the repo's api tests (see any `*V4ApiCest` that exercises sub-user privileges). If a near-real-time refresh flake appears on the match query, add `?refresh=wait_for` handling in the build step (call `POST {index}/_refresh` via the client at the end of build) rather than sleeping in the test.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest:shouldBuildSearchAndDropIndex'`
Expected: FAIL — `put_index` still returns 405.

- [ ] **Step 3: Implement `put_index` and `delete_index`**

Replace the two stub methods in `app/api/v4/controllers/Search.php`:

```php
    #[OA\Put(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'buildSearchIndex', description: "(Re)build the OpenSearch index from the table/view. Owner/superuser only.", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'Index built')]
    public function put_index(): Response
    {
        $this->assertOwner();
        $db = $this->route->jwt['data']['database'];
        $index = $this->indexName();
        $fullTable = "$this->schemaName.$this->tableName";

        // Compose settings (default + optional per-db analysis) and mapping from the table.
        $analysis = new \app\models\Setting($this->connection)->getSearchAnalysis();
        $body = \app\opensearch\SettingsComposer::compose($analysis);
        $body['mappings'] = new \app\models\Elasticsearch()->createMapFromTable($fullTable);

        $client = new Client();
        try {
            if ($client->indexExists($index)) {
                $client->deleteIndex($index);
            }
            $client->createIndex($index, $body);
        } catch (OpenSearchException $e) {
            throw $this->mapOpenSearchException($e, "INDEX_BUILD_ERROR");
        }

        // Bulk index rows through the reused Sql_to_es indexer.
        $priObj = $this->table[0]->getPrimeryKey($fullTable);
        $priKey = $priObj['attname'] ?? null;
        if (!$priKey) {
            throw new GC2Exception("The relation has no primary key", 400, null, "INDEX_BUILD_ERROR");
        }
        $api = new \app\models\Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $res = $api->runSql("SELECT * FROM \"$this->schemaName\".\"$this->tableName\"", $this->schemaName, $this->tableName, $priKey, $db);
        if (empty($res['success'])) {
            throw new GC2Exception($res['message'] ?? "Bulk indexing failed", 400, null, "INDEX_BUILD_ERROR");
        }

        return $this->getResponse([
            'index' => $index,
            'message' => $res['message'] ?? null,
            'errors' => $res['errors'] ?? false,
            'errors_in' => $res['errors_in'] ?? [],
        ]);
    }

    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'dropSearchIndex', description: "Drop the OpenSearch index. Owner/superuser only.", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'Index dropped')]
    public function delete_index(): Response
    {
        $this->assertOwner();
        $client = new Client();
        try {
            $client->deleteIndex($this->indexName());
        } catch (OpenSearchException $e) {
            throw $this->mapOpenSearchException($e, "INDEX_BUILD_ERROR");
        }
        return $this->getResponse(['index' => $this->indexName(), 'dropped' => true]);
    }
```

Notes for the implementer:
- `$this->table[0]` is set by `initiate()` (a `TableModel` for the qualified relation); it exposes `getPrimeryKey()` (inherited from `Model`). If `getPrimeryKey` is not reachable on `$this->table[0]` in this context, use a fresh `new \app\models\Model($this->connection)` and call `getPrimeryKey($fullTable)` — mirror whichever the Feature controller uses (`$ctx->model()->getPrimeryKey(...)`).
- `Sql_to_es` uses the global DB connection and posts to `{esHost}/_bulk` itself; it derives the same `{db}_{schema}_{table}` index name. For token requests the dispatcher has already selected the JWT database on the global connection (same assumption v2 relied on). If the bulk step indexes into the wrong DB context during the api test, set the DB explicitly before the call the way the surrounding v4 code does, and note it — do not refactor `Sql_to_es`.

- [ ] **Step 4: Run tests to verify they pass**

Run:
```
docker exec docker-gc2core-1 bash -lc 'cd /var/www/geocloud2/app && vendor/bin/codecept run api SearchV4ApiCest'
```
Expected: PASS (all methods). Lint `app/api/v4/controllers/Search.php`.

- [ ] **Step 5: Commit**

```bash
git add app/api/v4/controllers/Search.php app/tests/api/SearchV4ApiCest.php
git commit -m "feat(search): Search v4 index rebuild (PUT) and drop (DELETE)"
```

---

## Self-Review notes (for the implementer)

- **Spec coverage:** Task 1 = analyzer bug fix + shared default; Task 2 = client; Task 3 = settings composition + per-db storage; Task 4 = `SearchSettings` (per-db analysis GET/PUT); Task 5 = search read-auth + query proxy; Task 6 = rebuild/drop owner-only. CDC, meta, upsert, per-doc delete, mapping endpoints are explicitly out of scope (spec §Non-goals) — no tasks.
- **Error codes** used: `INDEX_NOT_FOUND`, `SEARCH_ERROR`, `INDEX_BUILD_ERROR`, `NOT_OWNER`, `INVALID_DATA`; relation/schema existence come from `initiate()` (`TABLE_NOT_FOUND`/`SCHEMA_NOT_FOUND`) — the spec's `RELATION_NOT_FOUND` is realized as `initiate()`'s `TABLE_NOT_FOUND` for consistency with the other v4 APIs. `OPENSEARCH_OFFLINE` surfaces as a Guzzle connect exception → let it map to a 500/SEARCH_ERROR; add an explicit catch only if the api test shows a raw stack trace.
- **Type consistency:** `Client::createIndex(string,array):array`, `search(string,string,bool):array`, `SettingsComposer::compose(?array):array`, `Setting::getSearchAnalysis():?array` — used identically in Tasks 4–6.
- **Verify-before-claim:** every task ends by running its test in `docker-gc2core-1` and linting changed files in `docker-dev-1`. Do not mark a task done on a green lint alone.
