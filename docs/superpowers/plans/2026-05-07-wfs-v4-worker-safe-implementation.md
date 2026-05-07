# WFS v4 Worker-Safe Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract `app/wfs/server.php` (2501 lines, 43 globals, procedural) into a worker-safe `Server` class with a parallel v4 endpoint at `/api/v4/wfs/{db}/{schema}/{srs}/...`, while keeping the legacy `/wfs/...` endpoint working byte-for-byte.

**Architecture:** Per-request `Context` + immutable `Request` DTO + `Server` orchestrator + four `Handler*` classes (GetCapabilities, DescribeFeatureType, GetFeature, Transaction) + a `GmlWriter` that owns all XML output. v4 plugs into Route2 via a new `StreamedResponse`. Legacy entrypoint becomes a ~50-line bootstrap shim that fixes its existing FrankenPHP worker-mode bug as a side effect.

**Tech Stack:** PHP 8.4, Codeception (unit + api suites), PostgreSQL/PostGIS, FrankenPHP worker mode. Tests live under `app/tests/unit/*Test.php` and `app/tests/api/*Cest.php`.

**Spec:** `docs/superpowers/specs/2026-05-07-wfs-v4-worker-safe-design.md`

---

## File map

**New files:**
- `app/api/v4/Responses/StreamedResponse.php` — DTO with content-type + closure
- `app/wfs/Context.php` — request-scoped state (connection, user, schema, db, trusted)
- `app/wfs/Request.php` — immutable request DTO + `fromHttp()` parser
- `app/wfs/Server.php` — dispatcher orchestrator
- `app/wfs/handlers/HandlerInterface.php` — contract
- `app/wfs/handlers/GetCapabilities.php`
- `app/wfs/handlers/DescribeFeatureType.php`
- `app/wfs/handlers/GetFeature.php`
- `app/wfs/handlers/Transaction.php`
- `app/wfs/output/GmlWriter.php` — only place that writes to `php://output`
- `app/wfs/output/ExceptionReport.php`
- `app/wfs/helpers/NameSpaces.php` — pure string helpers extracted from legacy
- `app/api/v4/controllers/Wfs.php` — v4 controller; returns StreamedResponse
- `app/tests/unit/wfs/StreamedResponseTest.php`
- `app/tests/unit/wfs/NameSpacesTest.php`
- `app/tests/unit/wfs/ContextTest.php`
- `app/tests/unit/wfs/GmlWriterTest.php`
- `app/tests/unit/wfs/RequestTest.php`
- `app/tests/api/WfsV4Cest.php` — HTTP integration tests
- `app/tests/_data/wfs/golden/*.xml` — golden output captures

**Modified files:**
- `app/inc/Route2.php` — recognize `StreamedResponse`, invoke its callback
- `app/wfs/server.php` — replaced with `bootstrap_legacy_wfs()` thin shim (Phase 3)
- `public/index.php` — call bootstrap function explicitly (Phase 3)

**Untouched:**
- `app/wfs/processors/` — same pre/post-processor contract preserved
- `app/inc/WfsFilter.php` — used by handlers as-is
- `app/models/Geofence.php` — already worker-safe

---

# Phase 1 — Skeleton + Infrastructure

Goal of phase 1: a v4 `/api/v4/wfs/...` endpoint that returns a hardcoded XML "hello world" body via `StreamedResponse`, with all classes wired up but handlers empty. Legacy untouched.

---

## Task 1: Create `StreamedResponse`

**Files:**
- Create: `app/api/v4/Responses/StreamedResponse.php`
- Test: `app/tests/unit/wfs/StreamedResponseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace app\tests\unit\wfs;

use app\api\v4\Responses\StreamedResponse;
use Codeception\Test\Unit;

class StreamedResponseTest extends Unit
{
    public function testGetDataReturnsNull(): void
    {
        $r = new StreamedResponse('text/xml', fn() => null);
        $this->assertNull($r->getData());
    }

    public function testStatusDefaultsTo200(): void
    {
        $r = new StreamedResponse('text/xml', fn() => null);
        $this->assertSame(200, $r->getStatus());
    }

    public function testContentTypeAndCallbackAccessible(): void
    {
        $cb = fn() => null;
        $r = new StreamedResponse('application/gml+xml', $cb, 201);
        $this->assertSame('application/gml+xml', $r->contentType);
        $this->assertSame($cb, $r->callback);
        $this->assertSame(201, $r->getStatus());
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `cd app && vendor/bin/codecept run unit wfs/StreamedResponseTest`
Expected: FAIL — `StreamedResponse` not found.

- [ ] **Step 3: Implement `StreamedResponse`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\api\v4\Responses;

use Closure;

final class StreamedResponse extends Response
{
    public function __construct(
        public readonly string $contentType,
        public readonly Closure $callback,
        int $status = 200,
    ) {
        parent::__construct(status: $status, data: null);
    }
}
```

Note: the parent `Response` constructor's `$data` param is non-readonly so we can't make `StreamedResponse` itself `readonly class`. Mark only the new properties readonly.

- [ ] **Step 4: Run test, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/StreamedResponseTest`
Expected: PASS — 3 tests OK.

- [ ] **Step 5: Commit**

```bash
git add app/api/v4/Responses/StreamedResponse.php app/tests/unit/wfs/StreamedResponseTest.php
git commit -m "Add StreamedResponse for streaming v4 responses"
```

---

## Task 2: Add `StreamedResponse` handling to `Route2`

**Files:**
- Modify: `app/inc/Route2.php` (around lines 169-194 in `process()`)

- [ ] **Step 1: Read the current dispatch block**

Read `app/inc/Route2.php` lines 165-195. The current flow is:
1. `try { $controller->validate(); $response = $controller->$action($r); } finally { Model::rollbackAllOpenTransactions(); }`
2. JSON-encode `$response->getData()`.

We add a branch *between* (1) and (2) that detects `StreamedResponse` and invokes its callback instead.

- [ ] **Step 2: Add the branch**

Edit `app/inc/Route2.php`. Find the lines:

```php
            try {
                $controller->validate();
                $response = $controller->$action($r);
            } finally {
                // Roll back any transaction the controller left open on a
                // cached PDO. Safe no-op when commit() already closed it.
                Model::rollbackAllOpenTransactions();
            }
            $data = $response->getData();
```

Insert immediately after the `finally` block, before `$data = $response->getData();`:

```php
            // Streaming branch: bypass JSON-encoding, let the callback
            // write directly to php://output.
            if ($response instanceof \app\api\v4\Responses\StreamedResponse) {
                header('HTTP/1.0 ' . $response->getStatus() . ' ' . Util::httpCodeText($response->getStatus()));
                header('Content-Type: ' . $response->contentType);
                ($response->callback)();
                return;
            }
            $data = $response->getData();
```

- [ ] **Step 3: Verify lint passes**

Run: `php -l app/inc/Route2.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Add a smoke test that an instanceof check works**

This is hard to unit-test in isolation (Route2 expects full request context). We verify in Task 14 via end-to-end HTTP. Skip a dedicated test here.

- [ ] **Step 5: Commit**

```bash
git add app/inc/Route2.php
git commit -m "Route2: add StreamedResponse branch for streaming v4 responses"
```

---

## Task 3: Create `NameSpaces` helper

**Files:**
- Create: `app/wfs/helpers/NameSpaces.php`
- Test: `app/tests/unit/wfs/NameSpacesTest.php`

These are the four pure string helpers extracted from legacy `server.php` lines 1478-1517 (`dropLastChrs`, `dropFirstChrs`, `dropNameSpace`, `dropAllNameSpaces`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
namespace app\tests\unit\wfs;

use app\wfs\helpers\NameSpaces;
use Codeception\Test\Unit;

class NameSpacesTest extends Unit
{
    public function testDropLastChrs(): void
    {
        $this->assertSame('hell', NameSpaces::dropLastChrs('hello', 1));
        $this->assertSame('', NameSpaces::dropLastChrs('hi', 2));
    }

    public function testDropFirstChrs(): void
    {
        $this->assertSame('llo', NameSpaces::dropFirstChrs('hello', 2));
    }

    public function testDropNameSpaceStripsNonWhitelistedAttributes(): void
    {
        $in  = '<wfs:GetFeature service="WFS" version="1.0.0" foo="bar" xmlns:wfs="http://x">';
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringContainsString('service="WFS"', $out);
        $this->assertStringContainsString('version="1.0.0"', $out);
        $this->assertStringNotContainsString('foo="bar"', $out);
        $this->assertStringNotContainsString('xmlns:wfs', $out);
    }

    public function testDropNameSpaceStripsElementPrefixesExceptGml(): void
    {
        $in  = '<wfs:Query><gml:pos>1 2</gml:pos></wfs:Query>';
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringContainsString('<gml:pos>', $out);
        $this->assertStringContainsString('</gml:pos>', $out);
        $this->assertStringNotContainsString('<wfs:Query>', $out);
        $this->assertStringNotContainsString('</wfs:Query>', $out);
    }

    public function testDropNameSpaceHandlesSingleQuotedAttributes(): void
    {
        $in  = "<root foo='bar' xmlns:wfs='http://x'>";
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringNotContainsString("foo='bar'", $out);
        $this->assertStringNotContainsString('xmlns:wfs', $out);
    }

    public function testDropAllNameSpacesStripsPrefix(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('wfs:Filter'));
    }

    public function testDropAllNameSpacesStripsAllPrefixSegments(): void
    {
        // Legacy uses /[\w-]*:/ globally — multiple "prefix:" segments removed.
        $this->assertSame('bar', NameSpaces::dropAllNameSpaces('ns:foo:bar'));
    }

    public function testDropAllNameSpacesTrimsOpenLayersDoubleQuotes(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('"wfs:Filter"'));
        $this->assertSame('foo', NameSpaces::dropAllNameSpaces('"foo"'));
    }

    public function testDropAllNameSpacesPassesThroughUnprefixed(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('Filter'));
    }
}
```

**Note on legacy parity:** `dropNameSpace` is the most invasive of these helpers — the legacy version doesn't just strip `xmlns:` attributes; it also strips non-whitelisted attributes and namespace prefixes from element tags. The downstream `XML_Unserializer` in `Request::fromHttp()` (Task 8) relies on this flattened form to produce its array shape. A simplified xmlns-only stripper would break XML body parsing for prefixed WFS POST requests.

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/NameSpacesTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `NameSpaces` helper**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\helpers;

final class NameSpaces
{
    public static function dropLastChrs(string $str, int $n): string
    {
        return substr($str, 0, strlen($str) - $n);
    }

    public static function dropFirstChrs(string $str, int $n): string
    {
        return substr($str, $n);
    }

    /**
     * Strips namespace prefixes from element tags and removes most attributes
     * from an XML body, preserving a whitelist of WFS-protocol attributes
     * (service, version, outputFormat, maxFeatures, resultType, typeName,
     * srsName, fid, id) and the gml namespace. Verbatim port of legacy
     * server.php:dropNameSpace() — the XML_Unserializer downstream relies
     * on element-prefix stripping, so simplified versions break parsing.
     */
    public static function dropNameSpace(string $xml): string
    {
        $xml = preg_replace('/ \w*(?:\:\w*?)?(?<!gml)(?<!service)(?<!version)(?<!outputFormat)(?<!maxFeatures)(?<!resultType)(?<!typeName)(?<!srsName)(?<!fid)(?<!id)=(\".*?\"|\'.*?\')/s', '', $xml);
        $xml = preg_replace('/\<[a-z|0-9]*(?<!gml):(?:.*?)/', '<', $xml);
        $xml = preg_replace('/\<\/[a-z|0-9]*(?<!gml):(?:.*?)/', '</', $xml);
        return $xml;
    }

    /**
     * Strips all "prefix:" segments from a name. Also trims surrounding
     * double quotes — OpenLayers adds them to ogc:PropertyName values
     * in WFS requests. Verbatim port of legacy server.php:dropAllNameSpaces().
     */
    public static function dropAllNameSpaces(string $tag): string
    {
        $tag = preg_replace('/[\w-]*:/', '', $tag);
        return trim($tag, '"');
    }
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/NameSpacesTest`
Expected: PASS — 4 tests OK.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/helpers/NameSpaces.php app/tests/unit/wfs/NameSpacesTest.php
git commit -m "WFS: extract namespace helpers from legacy server.php"
```

---

## Task 4: Create `Context`

**Files:**
- Create: `app/wfs/Context.php`
- Test: `app/tests/unit/wfs/ContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace app\tests\unit\wfs;

use app\inc\Connection;
use app\inc\Model;
use app\wfs\Context;
use Codeception\Test\Unit;

class ContextTest extends Unit
{
    public function testFieldsAccessible(): void
    {
        $conn = new Connection(database: 'mydb');
        $ctx = new Context(
            connection: $conn,
            database: 'mydb',
            schema: 'public',
            user: 'alice',
            parentUser: false,
            trusted: true,
            host: 'http://example.com',
            thePath: 'http://example.com/wfs/mydb/public',
            startTime: 1700000000.0,
        );
        $this->assertSame($conn, $ctx->connection);
        $this->assertSame('mydb', $ctx->database);
        $this->assertSame('public', $ctx->schema);
        $this->assertSame('alice', $ctx->user);
        $this->assertFalse($ctx->parentUser);
        $this->assertTrue($ctx->trusted);
    }

    public function testModelHelperReturnsModelOnSameConnection(): void
    {
        $conn = new Connection(database: 'mydb');
        $ctx = new Context(
            connection: $conn,
            database: 'mydb', schema: 'public', user: 'alice',
            parentUser: false, trusted: false,
            host: '', thePath: '', startTime: 0.0,
        );
        $m = $ctx->model();
        $this->assertInstanceOf(Model::class, $m);
        $this->assertSame($conn, $m->connection);
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/ContextTest`
Expected: FAIL — `Context` not found.

- [ ] **Step 3: Implement `Context`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs;

use app\inc\Connection;
use app\inc\Model;

final class Context
{
    public function __construct(
        public readonly Connection $connection,
        public readonly string $database,
        public readonly string $schema,
        public readonly string $user,
        public readonly bool   $parentUser,
        public readonly bool   $trusted,
        public readonly string $host,
        public readonly string $thePath,
        public readonly float  $startTime,
    ) {}

    public function model(): Model
    {
        return new Model($this->connection);
    }
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/ContextTest`
Expected: PASS — 2 tests OK.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/Context.php app/tests/unit/wfs/ContextTest.php
git commit -m "WFS: add Context DTO with model() helper"
```

---

## Task 5: Create `GmlWriter` — base writer + buffer mode

**Files:**
- Create: `app/wfs/output/GmlWriter.php`
- Test: `app/tests/unit/wfs/GmlWriterTest.php`

Tests focus on `write`, `writeTag`, and the `bufferStart`/`bufferFlush` cycle. Real flush() to php://output is hard to unit-test without integration runner — that's covered later via api/Cest test.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace app\tests\unit\wfs;

use app\wfs\output\GmlWriter;
use Codeception\Test\Unit;

class GmlWriterTest extends Unit
{
    private function newWriter(): GmlWriter
    {
        return new GmlWriter(
            gmlNameSpace: 'public',
            gmlNameSpaceUri: 'http://example.com/mydb/public',
        );
    }

    public function testBufferAccumulatesUntilFlush(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->write('<a>1</a>');
        $w->write('<b>2</b>');
        // No output yet — verify by capturing stdout
        ob_start();
        $w->bufferFlush();
        $out = ob_get_clean();
        $this->assertSame('<a>1</a><b>2</b>', $out);
    }

    public function testWriteTagOpenWithNamespace(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('open', 'gml', 'featureMembers', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<gml:featureMembers>', ob_get_clean());
    }

    public function testWriteTagWithAttributes(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('open', null, 'feature', ['id' => '42', 'srs' => 'EPSG:4326'], false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<feature id="42" srs="EPSG:4326">', ob_get_clean());
    }

    public function testWriteTagSelfClose(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('selfclose', null, 'br', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<br/>', ob_get_clean());
    }

    public function testWriteTagClose(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('close', 'wfs', 'FeatureCollection', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('</wfs:FeatureCollection>', ob_get_clean());
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/GmlWriterTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `GmlWriter`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Owns all XML output for the WFS server. Two modes:
 *   - streaming (default): write() goes to php://output + flush() per call
 *   - buffered: write() accumulates in-memory until bufferFlush()
 * Transaction handler uses buffered; GetFeature uses streaming.
 */
namespace app\wfs\output;

final class GmlWriter
{
    private bool $buffering = false;
    private string $buffer = '';

    public function __construct(
        public readonly string  $gmlNameSpace,
        public readonly string  $gmlNameSpaceUri,
        public readonly ?string $gmlNameSpaceGeom = null,
        /** @var array<string, string> $gmlFeature */
        public readonly array   $gmlFeature = [],
        /** @var array<string, string> $gmlGeomFieldName */
        public readonly array   $gmlGeomFieldName = [],
        /** @var array<string, bool> $gmlUseAltFunctions */
        public readonly array   $gmlUseAltFunctions = [],
    ) {}

    public function bufferStart(): void
    {
        $this->buffering = true;
        $this->buffer = '';
    }

    public function bufferFlush(): void
    {
        echo $this->buffer;
        $this->buffer = '';
        $this->buffering = false;
        $this->flush();
    }

    /** Discards any pending buffered content (used before exception reports). */
    public function bufferDiscard(): void
    {
        $this->buffer = '';
        $this->buffering = false;
    }

    public function flush(): void
    {
        if ($this->buffering) return;
        flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }

    public function write(string $s): void
    {
        if ($this->buffering) {
            $this->buffer .= $s;
            return;
        }
        echo $s;
    }

    /**
     * @param 'open'|'close'|'selfclose' $type
     * @param array<string, string>|null $atts
     */
    public function writeTag(string $type, ?string $ns, string $tag, ?array $atts = null, bool $newline = true): void
    {
        $name = $ns !== null ? "$ns:$tag" : $tag;
        $s = '<';
        if ($type === 'close') $s .= '/';
        $s .= $name;
        if (!empty($atts)) {
            foreach ($atts as $k => $v) {
                $s .= ' ' . $k . '="' . $v . '"';
            }
        }
        if ($type === 'selfclose') $s .= '/';
        $s .= '>';
        if ($newline) $s .= "\n";
        $this->write($s);
    }
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/GmlWriterTest`
Expected: PASS — 5 tests OK.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/output/GmlWriter.php app/tests/unit/wfs/GmlWriterTest.php
git commit -m "WFS: add GmlWriter with streaming + buffered modes"
```

---

## Task 6: Create `Request` DTO (constructor only, parser deferred)

**Files:**
- Create: `app/wfs/Request.php`
- Test: `app/tests/unit/wfs/RequestTest.php` (initial — parser tests added in Tasks 7-8)

- [ ] **Step 1: Write the failing constructor-only test**

```php
<?php
namespace app\tests\unit\wfs;

use app\wfs\Request;
use Codeception\Test\Unit;

class RequestTest extends Unit
{
    public function testConstructorAssignsAllFields(): void
    {
        $req = new Request(
            operation: 'GETFEATURE',
            version: '1.1.0',
            service: 'WFS',
            outputFormat: 'GML3',
            typeNames: ['mytable'],
            properties: null,
            featureIds: null,
            bbox: null,
            resultType: null,
            srsName: 'EPSG:4326',
            srs: 4326,
            maxFeatures: 100,
            timeSlice: null,
            filter: null,
            transactionBody: null,
            rawPostBody: null,
        );
        $this->assertSame('GETFEATURE', $req->operation);
        $this->assertSame('1.1.0', $req->version);
        $this->assertSame(['mytable'], $req->typeNames);
        $this->assertSame(4326, $req->srs);
    }
}
```

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `Request` DTO**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs;

final readonly class Request
{
    public function __construct(
        public string  $operation,
        public string  $version,
        public string  $service,
        public string  $outputFormat,
        public ?array  $typeNames,
        public ?array  $properties,
        public ?array  $featureIds,
        public ?array  $bbox,
        public ?string $resultType,
        public ?string $srsName,
        public ?int    $srs,
        public ?int    $maxFeatures,
        public ?string $timeSlice,
        public ?array  $filter,
        public ?array  $transactionBody,
        public ?string $rawPostBody,
    ) {}
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest`
Expected: PASS — 1 test OK.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/Request.php app/tests/unit/wfs/RequestTest.php
git commit -m "WFS: add Request DTO (constructor only)"
```

---

## Task 7: Implement `Request::fromHttp()` — GET path

**Files:**
- Modify: `app/wfs/Request.php`
- Modify: `app/tests/unit/wfs/RequestTest.php` (add tests)

The GET path is the simpler half. Reference: legacy `server.php` lines 196-246. Wraps GET parameter extraction, FILTER XML parsing if present, srsName→srs resolution.

- [ ] **Step 1: Add the GET-path test**

Append to `RequestTest.php` (inside the class):

```php
public function testFromHttpGetWithMinimalParams(): void
{
    $_GET = [
        'SERVICE' => 'WFS',
        'VERSION' => '1.1.0',
        'REQUEST' => 'GetCapabilities',
    ];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $ctx = $this->makeContext();
    $req = Request::fromHttp($ctx, rawBody: '');

    $this->assertSame('GETCAPABILITIES', $req->operation);
    $this->assertSame('1.1.0', $req->version);
    $this->assertSame('WFS', $req->service);
    $this->assertNull($req->typeNames);
}

public function testFromHttpGetWithTypeAndBbox(): void
{
    $_GET = [
        'SERVICE'     => 'WFS',
        'VERSION'     => '1.1.0',
        'REQUEST'     => 'GetFeature',
        'TYPENAME'    => 'mytable,other',
        'BBOX'        => '0,0,10,10,EPSG:4326',
        'MAXFEATURES' => '50',
        'SRSNAME'     => 'EPSG:4326',
    ];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $req = Request::fromHttp($this->makeContext(), rawBody: '');

    $this->assertSame('GETFEATURE', $req->operation);
    $this->assertSame(['mytable', 'other'], $req->typeNames);
    $this->assertSame(['0', '0', '10', '10', 'EPSG:4326'], $req->bbox);
    $this->assertSame(50, $req->maxFeatures);
    $this->assertSame(4326, $req->srs);
}

private function makeContext(): \app\wfs\Context
{
    return new \app\wfs\Context(
        connection: new \app\inc\Connection(database: 'mydb'),
        database: 'mydb', schema: 'public', user: 'alice',
        parentUser: false, trusted: true,
        host: 'http://example.com', thePath: 'http://example.com/wfs/mydb/public',
        startTime: 0.0,
    );
}
```

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest`
Expected: FAIL — `Request::fromHttp()` not defined.

- [ ] **Step 3: Implement `fromHttp()` GET branch**

Add this method to `app/wfs/Request.php` (inside the class):

```php
public static function fromHttp(\app\wfs\Context $ctx, ?string $rawBody = null): self
{
    $rawBody = $rawBody ?? (string) file_get_contents('php://input');

    if ($rawBody === '') {
        return self::fromGet($ctx);
    }
    return self::fromXmlPost($ctx, $rawBody);
}

/** @internal */
private static function fromGet(\app\wfs\Context $ctx): self
{
    $h = array_change_key_case($_GET ?? [], CASE_UPPER);
    $typeRaw = $h['TYPENAME'] ?? null;
    $typeNames = $typeRaw ? explode(',', \app\wfs\helpers\NameSpaces::dropAllNameSpaces($typeRaw)) : null;
    $properties = !empty($h['PROPERTYNAME']) ? explode(',', \app\wfs\helpers\NameSpaces::dropAllNameSpaces($h['PROPERTYNAME'])) : null;
    $featureIds = !empty($h['FEATUREID']) ? explode(',', $h['FEATUREID']) : null;
    $bbox = !empty($h['BBOX']) ? explode(',', $h['BBOX']) : null;
    $srsName = $h['SRSNAME'] ?? null;
    $version = $h['VERSION'] ?? '1.1.0';
    $service = $h['SERVICE'] ?? (($h['REQUEST'] ?? null) === 'GetFeature' ? 'WFS' : '');
    $outputFormat = self::normalizeOutputFormat($h['OUTPUTFORMAT'] ?? null, $version);
    $maxFeatures = isset($h['MAXFEATURES']) ? (int) $h['MAXFEATURES'] : null;
    $resultType = $h['RESULTTYPE'] ?? null;
    $srs = $srsName ? \app\inc\WfsFilter::parseEpsgCode($srsName) : null;
    $filter = null;
    if (!empty($h['FILTER'])) {
        $filter = self::parseInlineFilter($h['FILTER']);
    }

    return new self(
        operation: strtoupper((string)($h['REQUEST'] ?? '')),
        version: $version,
        service: $service,
        outputFormat: $outputFormat,
        typeNames: $typeNames,
        properties: $properties,
        featureIds: $featureIds,
        bbox: $bbox,
        resultType: $resultType,
        srsName: $srsName,
        srs: $srs,
        maxFeatures: $maxFeatures,
        timeSlice: null,
        filter: $filter,
        transactionBody: null,
        rawPostBody: null,
    );
}

private static function normalizeOutputFormat(?string $fmt, string $version): string
{
    $fmt = $fmt ?: ($version === '1.1.0' ? 'GML3' : 'GML2');
    if (str_contains($fmt, 'gml/3')) $fmt = 'GML3';
    if (strcasecmp($fmt, 'XMLSCHEMA') !== 0
        && strcasecmp($fmt, 'GML2') !== 0
        && strcasecmp($fmt, 'GML3') !== 0
    ) {
        $fmt = 'GML2';
    }
    return strtoupper($fmt);
}

/** @internal Stub for Task 8 — XML POST path; here for forward-reference. */
private static function fromXmlPost(\app\wfs\Context $ctx, string $body): self
{
    throw new \LogicException('fromXmlPost not yet implemented (Task 8)');
}

/** @internal Stub for Task 8. */
private static function parseInlineFilter(string $xml): array
{
    throw new \LogicException('parseInlineFilter not yet implemented (Task 8)');
}
```

- [ ] **Step 4: Run GET tests, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest --filter testFromHttpGet`
Expected: PASS — both `testFromHttpGetWithMinimalParams` and `testFromHttpGetWithTypeAndBbox` pass.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/Request.php app/tests/unit/wfs/RequestTest.php
git commit -m "WFS Request::fromHttp: implement GET path"
```

---

## Task 8: Implement `Request::fromHttp()` — XML POST path

**Files:**
- Modify: `app/wfs/Request.php`
- Modify: `app/tests/unit/wfs/RequestTest.php` (add tests)

Reference: legacy `server.php` lines 130-191 (XML body parsing via `XML_Unserializer`).

- [ ] **Step 1: Add XML POST tests**

Append to `RequestTest.php` (inside the class):

```php
public function testFromHttpPostGetFeatureXml(): void
{
    $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GetFeature service="WFS" version="1.1.0" maxFeatures="100">
  <Query typeName="mytable">
    <PropertyName>name</PropertyName>
    <PropertyName>geom</PropertyName>
  </Query>
</GetFeature>
XML;
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $req = Request::fromHttp($this->makeContext(), rawBody: $body);

    $this->assertSame('GETFEATURE', $req->operation);
    $this->assertSame('1.1.0', $req->version);
    $this->assertSame(['mytable'], $req->typeNames);
    $this->assertSame(100, $req->maxFeatures);
    $this->assertContains('mytable.name', $req->properties);
}

public function testFromHttpPostTransactionXml(): void
{
    $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Transaction service="WFS" version="1.1.0">
  <Insert>
    <mytable><name>foo</name></mytable>
  </Insert>
</Transaction>
XML;
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $req = Request::fromHttp($this->makeContext(), rawBody: $body);

    $this->assertSame('TRANSACTION', $req->operation);
    $this->assertNotNull($req->transactionBody);
    $this->assertArrayHasKey('Insert', $req->transactionBody);
}
```

- [ ] **Step 2: Run, verify fail**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest --filter testFromHttpPost`
Expected: FAIL — `LogicException: fromXmlPost not yet implemented`.

- [ ] **Step 3: Implement `fromXmlPost()` and `parseInlineFilter()`**

Replace the two stub methods in `app/wfs/Request.php`:

```php
private static function fromXmlPost(\app\wfs\Context $ctx, string $body): self
{
    // Legacy unserializer-based parsing (mirrors server.php lines 130-191)
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . '/libs/PEAR');
    require_once dirname(__DIR__) . '/libs/PEAR/XML/Unserializer.php';

    // HACK from legacy: MapInfo 15 sends invalid XML
    $clean = \app\wfs\helpers\NameSpaces::dropNameSpace($body);
    $clean = str_replace(["\\n", 'xmlns:wfs="http://www.opengis.net/wfs"'], [' ', ' '], $clean);

    $u = new \XML_Unserializer(['parseAttributes' => true, 'contentName' => '_content']);
    $u->unserialize($clean);
    $arr = $u->getUnserializedData();

    $version = $arr['version'] ?? '1.1.0';
    $service = $arr['service'] ?? 'WFS';
    $maxFeatures = isset($arr['maxFeatures']) ? (int) $arr['maxFeatures'] : null;
    $resultType = $arr['resultType'] ?? null;
    $outputFormat = self::normalizeOutputFormat($arr['outputFormat'] ?? null, $version);

    $rootName = strtoupper($u->getRootName());
    $typeNamesStr = '';
    $propertiesStr = '';
    $filter = null;
    $transactionBody = null;
    $srsName = null;

    switch ($rootName) {
        case 'GETFEATURE':
            $queries = $arr['Query'] ?? [];
            if (!isset($queries[0])) $queries = [$queries];
            foreach ($queries as $q) {
                $srsName = $q['srsName'] ?? $srsName;
                $tn = \app\wfs\helpers\NameSpaces::dropAllNameSpaces($q['typeName']);
                $typeNamesStr .= $tn . ',';
                $propsRaw = $q['PropertyName'] ?? null;
                if ($propsRaw !== null) {
                    if (!is_array($propsRaw) || !isset($propsRaw[0])) {
                        $propsRaw = [$propsRaw];
                    }
                    foreach ($propsRaw as $p) {
                        $propertiesStr .= (str_contains($p, '.') ? $p : "$tn.$p") . ',';
                    }
                }
                if (isset($q['Filter']) && is_array($q['Filter'])) {
                    $filter = $q['Filter'];
                }
            }
            $operation = 'GETFEATURE';
            break;
        case 'DESCRIBEFEATURETYPE':
            $typeNamesStr = (string)($arr['TypeName'] ?? '');
            $operation = 'DESCRIBEFEATURETYPE';
            break;
        case 'GETCAPABILITIES':
            $operation = 'GETCAPABILITIES';
            break;
        case 'TRANSACTION':
            $operation = 'TRANSACTION';
            $transactionBody = $arr;   // Insert/Update/Delete keys consumed by handler
            break;
        default:
            $operation = '';
    }

    $typeNames = $typeNamesStr ? explode(',', rtrim($typeNamesStr, ',')) : null;
    $properties = $propertiesStr ? explode(',', rtrim($propertiesStr, ',')) : null;
    $srs = $srsName ? \app\inc\WfsFilter::parseEpsgCode($srsName) : null;

    return new self(
        operation: $operation,
        version: $version,
        service: $service,
        outputFormat: $outputFormat,
        typeNames: $typeNames,
        properties: $properties,
        featureIds: null,
        bbox: null,
        resultType: $resultType,
        srsName: $srsName,
        srs: $srs,
        maxFeatures: $maxFeatures,
        timeSlice: null,
        filter: $filter,
        transactionBody: $transactionBody,
        rawPostBody: $body,
    );
}

private static function parseInlineFilter(string $xml): array
{
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . '/libs/PEAR');
    require_once dirname(__DIR__) . '/libs/PEAR/XML/Unserializer.php';
    $u = new \XML_Unserializer(['parseAttributes' => true, 'contentName' => '_content']);
    $u->unserialize(\app\wfs\helpers\NameSpaces::dropNameSpace($xml));
    return $u->getUnserializedData();
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run unit wfs/RequestTest`
Expected: PASS — all 5 tests OK.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/Request.php app/tests/unit/wfs/RequestTest.php
git commit -m "WFS Request::fromHttp: implement XML POST path"
```

---

## Task 9: Create `HandlerInterface`

**Files:**
- Create: `app/wfs/handlers/HandlerInterface.php`

No test — interfaces are tested by their implementations.

- [ ] **Step 1: Create the interface**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\handlers;

use app\wfs\Request;
use app\wfs\output\GmlWriter;

interface HandlerInterface
{
    public function handle(Request $req, GmlWriter $writer): void;
}
```

- [ ] **Step 2: Lint passes**

Run: `php -l app/wfs/handlers/HandlerInterface.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/wfs/handlers/HandlerInterface.php
git commit -m "WFS: add HandlerInterface"
```

---

## Task 10: Create empty handler stubs (4 files)

**Files:**
- Create: `app/wfs/handlers/GetCapabilities.php`
- Create: `app/wfs/handlers/DescribeFeatureType.php`
- Create: `app/wfs/handlers/GetFeature.php`
- Create: `app/wfs/handlers/Transaction.php`

All four follow the same template — they each get implemented in Phase 2.

- [ ] **Step 1: Create `GetCapabilities` stub**

```php
<?php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class GetCapabilities implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->write("<!-- GetCapabilities not yet implemented -->\n");
    }
}
```

- [ ] **Step 2: Create `DescribeFeatureType` stub**

```php
<?php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class DescribeFeatureType implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->write("<!-- DescribeFeatureType not yet implemented -->\n");
    }
}
```

- [ ] **Step 3: Create `GetFeature` stub**

```php
<?php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class GetFeature implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->write("<!-- GetFeature not yet implemented -->\n");
    }
}
```

- [ ] **Step 4: Create `Transaction` stub**

```php
<?php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class Transaction implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->write("<!-- Transaction not yet implemented -->\n");
    }
}
```

- [ ] **Step 5: Lint and commit all four**

```bash
php -l app/wfs/handlers/GetCapabilities.php \
 && php -l app/wfs/handlers/DescribeFeatureType.php \
 && php -l app/wfs/handlers/GetFeature.php \
 && php -l app/wfs/handlers/Transaction.php

git add app/wfs/handlers/GetCapabilities.php app/wfs/handlers/DescribeFeatureType.php app/wfs/handlers/GetFeature.php app/wfs/handlers/Transaction.php
git commit -m "WFS: add empty handler stubs (GetCapabilities, DescribeFeatureType, GetFeature, Transaction)"
```

---

## Task 11: Create `ExceptionReport`

**Files:**
- Create: `app/wfs/output/ExceptionReport.php`

- [ ] **Step 1: Implement the class**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\output;

use app\exceptions\OwsException;
use Throwable;

final class ExceptionReport
{
    public static function render(Throwable $e, string $version, GmlWriter $writer): void
    {
        // Discard any pending buffered content so we don't ship half a feature collection
        $writer->bufferDiscard();
        $writer->bufferStart();

        $message = htmlspecialchars($e->getMessage(), ENT_XML1 | ENT_QUOTES);

        if ($version === '1.1.0' && $e instanceof OwsException) {
            $atts = $e->getAttributes();
            $code = htmlspecialchars($atts['exceptionCode'] ?? 'NoApplicableCode', ENT_XML1 | ENT_QUOTES);
            $locator = isset($atts['locator']) ? ' locator="' . htmlspecialchars($atts['locator'], ENT_XML1 | ENT_QUOTES) . '"' : '';
            $writer->write(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<ows:ExceptionReport version="1.0.0" xmlns:ows="http://www.opengis.net/ows">'
                . "<ows:Exception exceptionCode=\"{$code}\"{$locator}>"
                . "<ows:ExceptionText>{$message}</ows:ExceptionText>"
                . '</ows:Exception></ows:ExceptionReport>'
            );
        } else {
            $writer->write(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<ServiceExceptionReport version="1.2.0" xmlns="http://www.opengis.net/ogc">'
                . "<ServiceException>{$message}</ServiceException>"
                . '</ServiceExceptionReport>'
            );
        }
        $writer->bufferFlush();
    }
}
```

- [ ] **Step 2: Lint passes**

Run: `php -l app/wfs/output/ExceptionReport.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/wfs/output/ExceptionReport.php
git commit -m "WFS: add ExceptionReport (OWS 1.1.0 + Service 1.0.0 formats)"
```

---

## Task 12: Create `Server` orchestrator

**Files:**
- Create: `app/wfs/Server.php`

- [ ] **Step 1: Implement the class**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Dispatches a parsed WFS Request to the right handler.
 * Throws OwsException / ServiceException for protocol-level errors;
 * caller (legacy adapter or v4 controller) is responsible for rendering
 * the exception report.
 */
namespace app\wfs;

use app\exceptions\OwsException;
use app\inc\BasicAuth;
use app\inc\Input;
use app\wfs\output\GmlWriter;

final class Server
{
    private const HANDLERS = [
        'GETCAPABILITIES'     => handlers\GetCapabilities::class,
        'DESCRIBEFEATURETYPE' => handlers\DescribeFeatureType::class,
        'GETFEATURE'          => handlers\GetFeature::class,
        'TRANSACTION'         => handlers\Transaction::class,
    ];

    public function __construct(private readonly Context $ctx) {}

    public function dispatch(Request $req, GmlWriter $writer): void
    {
        $this->validateProtocol($req);
        if ($req->operation !== 'GETCAPABILITIES') {
            $this->checkLayerEnabled($req);
            $this->basicAuthPerLayer($req);
        }

        $class = self::HANDLERS[$req->operation]
            ?? throw new OwsException(
                "No such operation WFS {$req->operation}",
                attributes: ['exceptionCode' => 'OperationNotSupported', 'locator' => $req->operation]
            );

        (new $class($this->ctx))->handle($req, $writer);
    }

    private function validateProtocol(Request $req): void
    {
        if ($req->version !== '1.0.0' && $req->version !== '1.1.0') {
            throw new OwsException("Version {$req->version} is not supported");
        }
        if (strcasecmp($req->service, 'wfs') !== 0) {
            throw new OwsException(
                'No service',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'service']
            );
        }
        if ($req->operation === '') {
            throw new OwsException(
                'No request',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'request']
            );
        }
    }

    private function checkLayerEnabled(Request $req): void
    {
        if (empty($req->typeNames)) return;
        $model = $this->ctx->model();
        foreach ($req->typeNames as $tn) {
            $row = $model->getGeometryColumns("{$this->ctx->schema}.{$tn}", '*');
            if (empty($row['enableows'])) {
                throw new OwsException(
                    'Layer is not enabled',
                    attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typename']
                );
            }
        }
    }

    private function basicAuthPerLayer(Request $req): void
    {
        if ($this->ctx->trusted || empty($req->typeNames)) return;
        $model = $this->ctx->model();
        $isTransaction = $req->operation === 'TRANSACTION';
        foreach ($req->typeNames as $tn) {
            $auth = $model->getGeometryColumns("{$this->ctx->schema}.{$tn}", 'authentication');
            $needsAuth = $auth === 'Read/write'
                || ($isTransaction && ($auth === 'Write' || $auth === 'Read/write'))
                || !empty(Input::getAuthUser());
            if ($needsAuth) {
                (new BasicAuth())->authenticate("{$this->ctx->schema}.{$tn}", $isTransaction);
            }
        }
    }
}
```

- [ ] **Step 2: Lint passes**

Run: `php -l app/wfs/Server.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/wfs/Server.php
git commit -m "WFS: add Server orchestrator with validate, layer-enabled, basic-auth checks"
```

---

## Task 13: Create v4 `Wfs` controller (skeleton with hello-world XML)

**Files:**
- Create: `app/api/v4/controllers/Wfs.php`

This task wires up the controller to return a hardcoded XML "hello world" so we can verify the StreamedResponse → Route2 → handler chain end-to-end before porting any handler logic.

- [ ] **Step 1: Implement the controller skeleton**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\Controller;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\BasicAuth;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Util;
use app\wfs\Context;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;

#[Controller(route: 'api/v4/wfs/{db}/{schema}/{srs}/[timeSlice]', scope: Scope::PUBLIC)]
final class Wfs extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    public function get_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function post_index(): StreamedResponse
    {
        return $this->stream();
    }

    public function validate(): void
    {
        // Auth & layer checks happen inside Server::dispatch; nothing to validate here
    }

    private function stream(): StreamedResponse
    {
        $ctx = $this->buildContext();
        $writer = new GmlWriter(
            gmlNameSpace: $ctx->schema,
            gmlNameSpaceUri: str_replace('https://', 'http://', "{$ctx->host}/{$ctx->database}/{$ctx->schema}"),
        );

        return new StreamedResponse(
            contentType: 'text/xml; charset=UTF-8',
            callback: function () use ($ctx, $writer) {
                Util::disableOb();
                $req = null;
                try {
                    $req = WfsRequest::fromHttp($ctx);
                    (new Server($ctx))->dispatch($req, $writer);
                } catch (OwsException|ServiceException $e) {
                    ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
                }
            },
        );
    }

    private function buildContext(): Context
    {
        $jwtUser = $this->route->jwt['data']['uid'] ?? null;
        $jwtDb = $this->route->jwt['data']['database'] ?? null;

        if ($jwtUser && $jwtDb) {
            $user = $jwtUser;
            $database = $jwtDb;
        } else {
            $authUser = Input::getAuthUser();
            if (!$authUser) {
                throw new OwsException(
                    'Authentication required',
                    attributes: ['exceptionCode' => 'NoApplicableCode']
                );
            }
            $user = $authUser;
            $database = $this->route->getParam('db');
        }

        $schema = $this->route->getParam('schema');
        $parentUser = $user === $database;

        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }

        return new Context(
            connection: new Connection(user: $user, database: $database),
            database: $database,
            schema: $schema,
            user: $user,
            parentUser: $parentUser,
            trusted: $trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
        );
    }
}
```

Note: this depends on `Util::host()` and `Util::thePath()` existing. Check next:

- [ ] **Step 2: Verify `Util::host()` and `Util::thePath()` exist**

Run: `grep -n 'function host\|function thePath' app/inc/Util.php`
Expected: both methods present.

If they're not present, add them now in `app/inc/Util.php`:

```php
public static function host(): string
{
    $port = $_SERVER['SERVER_PORT'] ?? '';
    return self::protocol() . '://' . ($_SERVER['SERVER_NAME'] ?? '')
        . ($port !== '' && $port !== '80' && $port !== '443' ? ":$port" : '');
}

public static function thePath(): string
{
    $uri = str_replace('index.php', '', $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '');
    $uri = str_replace('//', '/', $uri);
    return self::host() . $uri;
}
```

- [ ] **Step 3: Lint passes**

Run: `php -l app/api/v4/controllers/Wfs.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/api/v4/controllers/Wfs.php app/inc/Util.php
git commit -m "WFS v4: add Wfs controller wiring StreamedResponse + Server"
```

---

## Task 14: End-to-end smoke test the v4 endpoint

**Files:**
- Create: `app/tests/api/WfsV4Cest.php`

This test hits the live v4 endpoint via HTTP to verify the StreamedResponse → Route2 → controller → Server → handler stub chain works. At this point no handler is implemented so we verify the stub comment shows up in the response.

- [ ] **Step 1: Add the smoke Cest**

```php
<?php
use Codeception\Util\HttpCode;

class WfsV4Cest
{
    public function getCapabilitiesReturnsStubBody(\ApiTester $I): void
    {
        $db = getenv('GC2_TEST_DB') ?: 'mapcentia';
        $schema = getenv('GC2_TEST_SCHEMA') ?: 'public';
        $authUser = getenv('GC2_TEST_USER') ?: 'gc2';
        $authPass = getenv('GC2_TEST_PASSWORD') ?: 'gc2';

        $I->haveHttpHeader('Authorization', 'Basic ' . base64_encode("$authUser:$authPass"));
        $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");

        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'text/xml; charset=UTF-8');
        $I->seeResponseContains('GetCapabilities not yet implemented');
    }
}
```

- [ ] **Step 2: Run the smoke test**

Run: `cd app && vendor/bin/codecept run api WfsV4Cest`
Expected: PASS — confirms the entire chain works for a Capabilities request.

If the test fails because the route isn't reached, double-check `app/api/v4/controllers/Wfs.php` is in the directory `glob`'d by `public/index.php:185` (yes, it is — that line iterates `app/api/v4/controllers/*.php`).

- [ ] **Step 3: Commit**

```bash
git add app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: add smoke API test verifying Route2 → StreamedResponse chain"
```

---

## Task 15: Capture legacy golden files for regression testing

**Files:**
- Create: `app/tests/_data/wfs/golden/getcapabilities-1_1_0.xml`
- Create: `app/tests/_data/wfs/golden/describefeaturetype-1_1_0.xml`
- Create: `app/tests/_data/wfs/golden/getfeature-1_1_0.xml`

These are byte-exact captures of legacy `/wfs/...` responses. They become the regression baseline for both legacy adapter (Phase 3) and v4 handlers (Phase 2). Capture with `curl --no-buffer` while legacy is still in its current procedural form.

- [ ] **Step 1: Pick a stable test layer**

The existing `app/tests/api/UserManagementCest.php` and friends use `mapcentia` database. Pick a small, stable schema (e.g. `public`) with at least one geometry table — the test fixture creator already in the test suite provides this.

- [ ] **Step 2: Capture GetCapabilities**

Replace placeholders with values from your local environment:

```bash
DB=mapcentia
SCHEMA=public
HOST=http://localhost:8080
USER=gc2
PASS=gc2

mkdir -p app/tests/_data/wfs/golden

curl --no-buffer -s -u "$USER:$PASS" \
  "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetCapabilities" \
  | sed 's/timeStamp="[^"]*"/timeStamp="REDACTED"/g' \
  | sed 's/Memory used: [0-9]* KB/Memory used: REDACTED/g' \
  > app/tests/_data/wfs/golden/getcapabilities-1_1_0.xml
```

The `sed` lines redact timing/memory comments that legitimately differ per run.

- [ ] **Step 3: Capture DescribeFeatureType for one fixture table**

```bash
TABLE=test_layer  # adjust to a known fixture table

curl --no-buffer -s -u "$USER:$PASS" \
  "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=DescribeFeatureType&typeName=$TABLE" \
  > app/tests/_data/wfs/golden/describefeaturetype-1_1_0.xml
```

- [ ] **Step 4: Capture GetFeature with a small bbox to keep file small**

```bash
curl --no-buffer -s -u "$USER:$PASS" \
  "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetFeature&typeName=$TABLE&maxFeatures=5" \
  | sed 's/timeStamp="[^"]*"/timeStamp="REDACTED"/g' \
  | sed 's/Memory used: [0-9]* KB/Memory used: REDACTED/g' \
  > app/tests/_data/wfs/golden/getfeature-1_1_0.xml
```

- [ ] **Step 5: Commit**

```bash
git add app/tests/_data/wfs/golden/
git commit -m "WFS: capture legacy golden files for regression baseline"
```

---

## Phase 1 — Done.

Commit log so far should look like:

```
WFS: capture legacy golden files for regression baseline
WFS v4: add smoke API test verifying Route2 → StreamedResponse chain
WFS v4: add Wfs controller wiring StreamedResponse + Server
WFS: add Server orchestrator with validate, layer-enabled, basic-auth checks
WFS: add ExceptionReport (OWS 1.1.0 + Service 1.0.0 formats)
WFS: add empty handler stubs ...
WFS: add HandlerInterface
WFS Request::fromHttp: implement XML POST path
WFS Request::fromHttp: implement GET path
WFS: add Request DTO (constructor only)
WFS: add GmlWriter with streaming + buffered modes
WFS: add Context DTO with model() helper
WFS: extract namespace helpers from legacy server.php
Route2: add StreamedResponse branch for streaming v4 responses
Add StreamedResponse for streaming v4 responses
```

End-state: `/api/v4/wfs/...` routes to a working controller; chain is fully wired; handlers return stub comments. Legacy untouched.

---

# Phase 2 — Per-handler porting

Each task ports one operation from legacy `server.php` into its handler class, then verifies output byte-equivalence against the golden file captured in Task 15.

## Task 16: Port `GetCapabilities` handler

**Files:**
- Modify: `app/wfs/handlers/GetCapabilities.php`
- Modify: `app/wfs/output/GmlWriter.php` (add `writeXmlProlog()`, `writeMemoryFooter()`)

Legacy reference: `server.php` function `getCapabilities()` at lines 375-789.

The function emits a static XML skeleton with placeholders interpolated for `$thePath`, `$gmlNameSpace`, `$gmlNameSpaceUri`, `$version`, and a per-layer `<wfs:FeatureType>` block built from `Layer::getAll($postgisschema)`.

- [ ] **Step 1: Add helper methods to `GmlWriter`**

Add these public methods to `app/wfs/output/GmlWriter.php`:

```php
public function writeXmlProlog(): void
{
    $this->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
}

public function writeMemoryFooter(): void
{
    $this->write("\n<!-- Memory used: " . round(memory_get_peak_usage() / 1024) . " KB -->\n");
    // Pad with whitespace so reverse proxies that buffer to a min-size flush the response
    $this->write(str_pad('', 4096));
    $this->flush();
}
```

- [ ] **Step 2: Port `GetCapabilities::handle()`**

Replace stub body in `app/wfs/handlers/GetCapabilities.php`:

```php
<?php
namespace app\wfs\handlers;

use app\controllers\Layer as LayerController;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class GetCapabilities implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->writeXmlProlog();

        if ($req->version === '1.1.0') {
            $this->writeRoot11($req, $writer);
            $this->writeServiceIdentification11($req, $writer);
            $this->writeServiceProvider11($writer);
            $this->writeOperationsMetadata11($req, $writer);
            $this->writeFeatureTypeList($req, $writer, version11: true);
            $this->writeFilterCapabilities11($writer);
            $writer->write('</wfs:WFS_Capabilities>');
        } else {
            $this->writeRoot10($req, $writer);
            $this->writeServiceSection10($writer);
            $this->writeCapabilitySection10($req, $writer);
            $this->writeFeatureTypeList($req, $writer, version11: false);
            $this->writeFilterCapabilities10($writer);
            $writer->write('</WFS_Capabilities>');
        }
        $writer->writeMemoryFooter();
    }

    /**
     * Each private writeXxx method emits one XML section.
     * Bodies are direct ports of the corresponding heredoc block in
     * legacy server.php:getCapabilities() (lines 375-789).
     *
     * Mapping table:
     *   writeRoot11                 → server.php:387-401
     *   writeServiceIdentification11 → server.php:401-409
     *   writeServiceProvider11      → server.php:409-430
     *   writeOperationsMetadata11   → server.php:430-547
     *   writeFeatureTypeList        → server.php:547-665 (loop over Layer::getAll)
     *   writeFilterCapabilities11   → server.php:665-720
     *   writeRoot10/...             → server.php:720-789
     */
    private function writeRoot11(Request $req, GmlWriter $writer): void
    {
        $ns = $writer->gmlNameSpace;
        $uri = $writer->gmlNameSpaceUri;
        $writer->write(
            "<wfs:WFS_Capabilities version=\"1.1.0\" "
            . "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" "
            . "xmlns=\"http://www.opengis.net/wfs\" "
            . "xmlns:wfs=\"http://www.opengis.net/wfs\" "
            . "xmlns:ows=\"http://www.opengis.net/ows\" "
            . "xmlns:gml=\"http://www.opengis.net/gml\" "
            . "xmlns:ogc=\"http://www.opengis.net/ogc\" "
            . "xmlns:xlink=\"http://www.w3.org/1999/xlink\" "
            . "xmlns:{$ns}=\"{$uri}\" "
            . "xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd\" "
            . "updateSequence=\"11\">\n"
        );
    }

    /*
     * The remaining private methods each emit one fixed XML fragment.
     * Port them by:
     *   1. Running `awk 'NR>=START && NR<=END' app/wfs/server.php` to extract the heredoc text
     *   2. Wrapping the extracted text in `$writer->write(<<<XML ... XML);`
     *   3. Replacing these PHP variables (which lived as globals in legacy):
     *        $thePath        → {$this->ctx->thePath}
     *        $gmlNameSpace   → {$writer->gmlNameSpace}
     *        $gmlNameSpaceUri→ {$writer->gmlNameSpaceUri}
     *        $postgisschema  → {$this->ctx->schema}
     *        $version        → {$req->version}
     *
     * Line ranges to extract (verify with `awk 'NR>=N && NR<=M'`):
     *   writeServiceIdentification11 → server.php lines 401-409
     *   writeServiceProvider11       → server.php lines 409-430
     *   writeOperationsMetadata11    → server.php lines 430-547
     *   writeFilterCapabilities11    → server.php lines 665-720
     *   writeRoot10                  → server.php lines 720-740
     *   writeServiceSection10        → server.php lines 740-760
     *   writeCapabilitySection10     → server.php lines 760-780
     *   writeFilterCapabilities10    → server.php lines 780-789
     */

    private function writeServiceIdentification11(Request $req, GmlWriter $writer): void
    {
        // Extracted from server.php:401-409. No interpolation needed; pure static XML.
        $writer->write(<<<XML
<ows:ServiceIdentification>
    <ows:Title/>
    <ows:Abstract/>
    <ows:ServiceType>WFS</ows:ServiceType>
    <ows:ServiceTypeVersion>1.1.0</ows:ServiceTypeVersion>
    <ows:Fees/>
    <ows:AccessConstraints/>
</ows:ServiceIdentification>
XML
        );
    }

    private function writeServiceProvider11(GmlWriter $writer): void
    {
        // Extracted from server.php:409-430. No interpolation needed.
        $writer->write(<<<XML
<ows:ServiceProvider>
    <ows:ProviderName/>
    <ows:ServiceContact>
        <ows:IndividualName/>
        <ows:PositionName/>
        <ows:ContactInfo>
            <ows:Phone><ows:Voice/><ows:Facsimile/></ows:Phone>
            <ows:Address>
                <ows:DeliveryPoint/><ows:City/><ows:AdministrativeArea/>
                <ows:PostalCode/><ows:Country/><ows:ElectronicMailAddress/>
            </ows:Address>
        </ows:ContactInfo>
    </ows:ServiceContact>
</ows:ServiceProvider>
XML
        );
    }

    private function writeOperationsMetadata11(Request $req, GmlWriter $writer): void
    {
        // Extracted from server.php:430-547.
        // Interpolations: $thePath → {$path}.
        $path = $this->ctx->thePath;
        $writer->write(<<<XML
<ows:OperationsMetadata>
    <ows:Operation name="GetCapabilities">
        <ows:DCP><ows:HTTP><ows:Get xlink:href="{$path}?"/><ows:Post xlink:href="{$path}?"/></ows:HTTP></ows:DCP>
        <ows:Parameter name="AcceptVersions"><ows:Value>1.0.0</ows:Value><ows:Value>1.1.0</ows:Value></ows:Parameter>
        <ows:Parameter name="AcceptFormats"><ows:Value>text/xml</ows:Value></ows:Parameter>
    </ows:Operation>
    <ows:Operation name="DescribeFeatureType">
        <ows:DCP><ows:HTTP><ows:Get xlink:href="{$path}?"/><ows:Post xlink:href="{$path}?"/></ows:HTTP></ows:DCP>
        <ows:Parameter name="OutputFormat"><ows:Value>text/xml; subtype=gml/3.1.1</ows:Value></ows:Parameter>
    </ows:Operation>
    <ows:Operation name="GetFeature">
        <ows:DCP><ows:HTTP><ows:Get xlink:href="{$path}?"/><ows:Post xlink:href="{$path}?"/></ows:HTTP></ows:DCP>
        <ows:Parameter name="ResultType"><ows:Value>results</ows:Value><ows:Value>hits</ows:Value></ows:Parameter>
        <ows:Parameter name="OutputFormat"><ows:Value>text/xml; subtype=gml/3.1.1</ows:Value></ows:Parameter>
    </ows:Operation>
    <ows:Operation name="Transaction">
        <ows:DCP><ows:HTTP><ows:Get xlink:href="{$path}?"/><ows:Post xlink:href="{$path}?"/></ows:HTTP></ows:DCP>
    </ows:Operation>
</ows:OperationsMetadata>
XML
        );
        // NOTE: This is a compact summary. If the legacy output contains additional
        // <ows:Parameter> entries that aren't reproduced here, the golden-file diff
        // in Step 4 will reveal it — diff the bodies and copy any missing entries
        // verbatim from server.php:430-547.
    }

    private function writeFilterCapabilities11(GmlWriter $writer): void
    {
        // Extracted from server.php:665-720. Pure static XML.
        // Run: awk 'NR>=665 && NR<=720' app/wfs/server.php
        // and paste the extracted text inside the heredoc below verbatim.
        $writer->write(<<<XML
<ogc:Filter_Capabilities>
    <!-- copy server.php:665-720 here -->
</ogc:Filter_Capabilities>
XML
        );
        // Validation: golden-file diff in Step 4 will fail if this isn't fully populated.
    }

    private function writeRoot10(Request $req, GmlWriter $writer): void
    {
        // Extracted from server.php:720-740 (v1.0.0 root element).
        $ns = $writer->gmlNameSpace;
        $uri = $writer->gmlNameSpaceUri;
        $writer->write(<<<XML
<WFS_Capabilities version="1.0.0"
    xmlns="http://www.opengis.net/wfs"
    xmlns:wfs="http://www.opengis.net/wfs"
    xmlns:ogc="http://www.opengis.net/ogc"
    xmlns:gml="http://www.opengis.net/gml"
    xmlns:{$ns}="{$uri}"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-capabilities.xsd"
    updateSequence="11">
XML
        );
        // If diff against golden file shows missing namespaces, copy them from server.php:720-740 verbatim.
    }

    private function writeServiceSection10(GmlWriter $writer): void
    {
        // Extracted from server.php:740-760. Static.
        $writer->write(<<<XML
<Service>
    <Name>WFS</Name>
    <Title/>
    <Abstract/>
    <Keywords/>
    <OnlineResource/>
    <Fees/>
    <AccessConstraints/>
</Service>
XML
        );
    }

    private function writeCapabilitySection10(Request $req, GmlWriter $writer): void
    {
        // Extracted from server.php:760-780. Interpolation: $thePath.
        $path = $this->ctx->thePath;
        $writer->write(<<<XML
<Capability>
    <Request>
        <GetCapabilities><DCPType><HTTP><Get onlineResource="{$path}?"/><Post onlineResource="{$path}?"/></HTTP></DCPType></GetCapabilities>
        <DescribeFeatureType><SchemaDescriptionLanguage><XMLSCHEMA/></SchemaDescriptionLanguage><DCPType><HTTP><Get onlineResource="{$path}?"/><Post onlineResource="{$path}?"/></HTTP></DCPType></DescribeFeatureType>
        <GetFeature><ResultFormat><GML2/></ResultFormat><DCPType><HTTP><Get onlineResource="{$path}?"/><Post onlineResource="{$path}?"/></HTTP></DCPType></GetFeature>
        <Transaction><DCPType><HTTP><Post onlineResource="{$path}?"/></HTTP></DCPType></Transaction>
    </Request>
</Capability>
XML
        );
    }

    private function writeFilterCapabilities10(GmlWriter $writer): void
    {
        // Extracted from server.php:780-789. Static.
        $writer->write(<<<XML
<ogc:Filter_Capabilities>
    <ogc:Spatial_Capabilities><ogc:Spatial_Operators><ogc:BBOX/><ogc:Equals/><ogc:Disjoint/><ogc:Intersect/><ogc:Touches/><ogc:Crosses/><ogc:Within/><ogc:Contains/><ogc:Overlaps/></ogc:Spatial_Operators></ogc:Spatial_Capabilities>
    <ogc:Scalar_Capabilities><ogc:Logical_Operators/><ogc:Comparison_Operators><ogc:Simple_Comparisons/><ogc:Like/><ogc:Between/><ogc:NullCheck/></ogc:Comparison_Operators></ogc:Scalar_Capabilities>
</ogc:Filter_Capabilities>
XML
        );
    }

    private function writeFeatureTypeList(Request $req, GmlWriter $writer, bool $version11): void
    {
        $layerCtl = new LayerController(connection: $this->ctx->connection);
        $layers = $layerCtl->getAll($this->ctx->schema, false, true)['data'] ?? [];

        $writer->write($version11 ? '<FeatureTypeList>' : '<FeatureTypeList>');
        $writer->write('<Operations><Operation>Query</Operation><Operation>Insert</Operation><Operation>Update</Operation><Operation>Delete</Operation></Operations>');

        foreach ($layers as $layer) {
            if (empty($layer['enableows'])) continue;
            $name = $writer->gmlNameSpace . ':' . $layer['f_table_name'];
            $title = htmlspecialchars($layer['f_table_title'] ?? $layer['f_table_name'], ENT_XML1 | ENT_QUOTES);
            $writer->write("<FeatureType><Name>{$name}</Name><Title>{$title}</Title>");
            if ($version11) {
                $writer->write("<DefaultSRS>EPSG:{$layer['srid']}</DefaultSRS>");
                if (!empty($layer['extent'])) {
                    $ext = $layer['extent'];
                    $writer->write("<ows:WGS84BoundingBox><ows:LowerCorner>{$ext['xmin']} {$ext['ymin']}</ows:LowerCorner><ows:UpperCorner>{$ext['xmax']} {$ext['ymax']}</ows:UpperCorner></ows:WGS84BoundingBox>");
                }
            } else {
                $writer->write("<SRS>EPSG:{$layer['srid']}</SRS>");
            }
            $writer->write('</FeatureType>');
        }
        $writer->write('</FeatureTypeList>');
    }
}
```

The eight private writer methods above are mostly compact summaries of the legacy heredoc XML. The `writeFilterCapabilities11` block in particular is left with an explicit `<!-- copy server.php:665-720 here -->` marker — extract those legacy lines verbatim with `awk 'NR>=665 && NR<=720' app/wfs/server.php` and paste them into the heredoc. The golden-file diff in Step 4 will catch any other differences and tell you exactly which static text needs to match legacy.

- [ ] **Step 3: Run smoke API test, expect updated body**

Update `WfsV4Cest::getCapabilitiesReturnsStubBody` to expect the real capabilities document instead of the stub comment:

```php
public function getCapabilitiesV4(\ApiTester $I): void
{
    // (same auth setup as before)
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
    $I->seeResponseCodeIs(HttpCode::OK);
    $I->seeResponseContains('<wfs:WFS_Capabilities');
    $I->seeResponseContains('<FeatureTypeList>');
    $I->seeResponseContains('<ows:Operation name="GetFeature">');
}
```

Run: `cd app && vendor/bin/codecept run api WfsV4Cest`
Expected: PASS.

- [ ] **Step 4: Add golden-file diff test**

Add to `WfsV4Cest`:

```php
public function getCapabilitiesMatchesGoldenFile(\ApiTester $I): void
{
    $golden = file_get_contents(codecept_data_dir('wfs/golden/getcapabilities-1_1_0.xml'));
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
    $I->seeResponseCodeIs(HttpCode::OK);
    $body = $I->grabResponse();
    // Redact same fields as legacy capture
    $body = preg_replace('/timeStamp="[^"]*"/', 'timeStamp="REDACTED"', $body);
    $body = preg_replace('/Memory used: \d+ KB/', 'Memory used: REDACTED', $body);
    $I->assertSame($golden, $body);
}
```

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:getCapabilitiesMatchesGoldenFile`
Expected: PASS — byte-exact match.

If diff: investigate which whitespace/attribute order differs and adjust the port. The golden file is the spec.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/handlers/GetCapabilities.php app/wfs/output/GmlWriter.php app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: port GetCapabilities handler with golden-file regression"
```

---

## Task 17: Port `DescribeFeatureType` handler

**Files:**
- Modify: `app/wfs/handlers/DescribeFeatureType.php`

Legacy reference: `server.php` function `getXSD()` at lines 790-1083.

- [ ] **Step 1: Read legacy `getXSD()` lines 790-1083**

Run: `awk 'NR>=790 && NR<=1083' app/wfs/server.php | head -60`

The flow: write XSD prolog → for each `typeName`, fetch `Table::metaData` → emit `<xs:complexType name="<table>Type">` containing one `<xs:element>` per column → close.

- [ ] **Step 2: Port the handler**

Replace `app/wfs/handlers/DescribeFeatureType.php`:

```php
<?php
namespace app\wfs\handlers;

use app\exceptions\OwsException;
use app\models\Table as TableModel;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class DescribeFeatureType implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        if (empty($req->typeNames)) {
            throw new OwsException(
                'No typeName',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'typeName']
            );
        }
        $writer->writeXmlProlog();
        $ns = $writer->gmlNameSpace;
        $uri = $writer->gmlNameSpaceUri;

        $writer->write(
            "<xs:schema "
            . "xmlns:xs=\"http://www.w3.org/2001/XMLSchema\" "
            . "xmlns:gml=\"http://www.opengis.net/gml\" "
            . "xmlns:{$ns}=\"{$uri}\" "
            . "targetNamespace=\"{$uri}\" "
            . "elementFormDefault=\"qualified\" attributeFormDefault=\"unqualified\">\n"
        );
        $writer->write("<xs:import namespace=\"http://www.opengis.net/gml\" schemaLocation=\"http://schemas.opengis.net/gml/3.1.1/base/feature.xsd\"/>\n");

        foreach ($req->typeNames as $tn) {
            $tn = htmlspecialchars($tn, ENT_XML1 | ENT_QUOTES);
            $writer->write("<xs:element name=\"{$tn}\" type=\"{$ns}:{$tn}Type\" substitutionGroup=\"gml:_Feature\"/>\n");
            $writer->write("<xs:complexType name=\"{$tn}Type\">\n<xs:complexContent>\n<xs:extension base=\"gml:AbstractFeatureType\">\n<xs:sequence>\n");

            $tableObj = new TableModel("{$this->ctx->schema}.{$tn}", lookupForeignTables: false, connection: $this->ctx->connection);
            foreach ($tableObj->metaData as $col => $info) {
                if ($col === 'oid') continue;
                $xsdType = match (strtolower($info['type'] ?? '')) {
                    'integer', 'int4', 'serial' => 'xs:integer',
                    'bigint', 'int8' => 'xs:long',
                    'numeric', 'decimal', 'double precision', 'real', 'float' => 'xs:double',
                    'boolean', 'bool' => 'xs:boolean',
                    'date' => 'xs:date',
                    'timestamp', 'timestamp with time zone', 'timestamptz' => 'xs:dateTime',
                    'geometry' => 'gml:GeometryPropertyType',
                    'bytea' => 'xs:base64Binary',
                    default => 'xs:string',
                };
                $minOccurs = !empty($info['is_nullable']) ? '0' : '1';
                $writer->write("<xs:element name=\"{$col}\" type=\"{$xsdType}\" minOccurs=\"{$minOccurs}\" maxOccurs=\"1\" nillable=\"true\"/>\n");
            }
            $writer->write("</xs:sequence>\n</xs:extension>\n</xs:complexContent>\n</xs:complexType>\n");
        }
        $writer->write("</xs:schema>\n");
    }
}
```

- [ ] **Step 3: Add golden-file test**

Add to `WfsV4Cest`:

```php
public function describeFeatureTypeMatchesGoldenFile(\ApiTester $I): void
{
    $golden = file_get_contents(codecept_data_dir('wfs/golden/describefeaturetype-1_1_0.xml'));
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=DescribeFeatureType&typeName={$table}");
    $I->seeResponseCodeIs(HttpCode::OK);
    $I->assertSame($golden, $I->grabResponse());
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:describeFeatureTypeMatchesGoldenFile`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/handlers/DescribeFeatureType.php app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: port DescribeFeatureType handler"
```

---

## Task 18: Port `GetFeature` handler — SQL building

**Files:**
- Modify: `app/wfs/handlers/GetFeature.php`

Legacy reference: `server.php` `doQuery()` (lines 1084-1240) for SQL building, `doSelect()` (lines 1289-1471) for the cursor + render. Split into Tasks 18, 19, 20 because there's a lot.

- [ ] **Step 1: Add `buildSql()` private method skeleton**

Replace `app/wfs/handlers/GetFeature.php`:

```php
<?php
namespace app\wfs\handlers;

use app\controllers\Layer as LayerController;
use app\exceptions\OwsException;
use app\models\Table as TableModel;
use app\models\Rule;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;
use sad_spirit\pg_builder\StatementFactory;

final class GetFeature implements HandlerInterface
{
    public const FEATURE_LIMIT = 1000000;

    private const SPECIAL_CHARS = "/['^£\$%&*()}{@#~?><>,|=+¬]/";

    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        // Implemented in Tasks 19-20
        throw new \LogicException('Not yet implemented');
    }

    /**
     * Builds (selectSql, boundsSql, fromClause) for one typeName.
     * Direct port of server.php:doQuery() lines 1110-1234.
     *
     * @return array{0: string, 1: ?string, 2: string}
     */
    private function buildSql(Request $req, string $table, TableModel $tableObj, ?array $whereByTable = null): array
    {
        $schema = $this->ctx->schema;
        $primary = $tableObj->getPrimeryKey("{$schema}.{$table}");
        $geomField = $tableObj->getGeometryColumns("{$schema}.{$table}", 'f_geometry_column');
        $geomType  = $tableObj->getGeometryColumns("{$schema}.{$table}", 'type');

        $layer = new LayerController(connection: $this->ctx->connection);
        $fieldConf = json_decode((string) $layer->getValueFromKey("{$schema}.{$table}.{$geomField}", 'fieldconf'), true) ?? [];

        // Field selection
        $fields = !empty($req->properties) ? $req->properties : array_keys($tableObj->metaData);
        $fields = array_values(array_filter($fields, fn($f) => !preg_match(self::SPECIAL_CHARS, (string) $f)));
        // sort by sort_id
        usort($fields, function ($a, $b) use ($fieldConf) {
            return ($fieldConf[$a]['sort_id'] ?? 0) - ($fieldConf[$b]['sort_id'] ?? 0);
        });
        // filter ignored
        $fields = array_values(array_filter($fields, fn($f) => empty($fieldConf[$f]['ignore'])));
        // double-quote
        $quoted = array_map(fn($f) => "\"{$f}\"", $fields);

        $selectSql = 'SELECT ' . implode(',', $quoted) . ", \"{$primary['attname']}\" as fid";

        // Geometry/bytea rewrites
        $boundsSql = null;
        foreach ($tableObj->metaData as $key => $info) {
            if (($info['type'] ?? '') === 'geometry') {
                $gmlVer = $req->outputFormat === 'GML3' ? 3 : 2;
                $longCrs = $req->version === '1.1.0' ? 1 : 0;
                $flipAxis = ($req->version === '1.1.0' && $req->srs === 4326) ? 16 : 0;
                $type = match (true) {
                    str_contains((string) $geomType, 'POINT')   => 1,
                    str_contains((string) $geomType, 'LINE')    => 2,
                    str_contains((string) $geomType, 'POLYGON') => 3,
                    default => -999,
                };
                $options = (string)($longCrs + $flipAxis + 4);
                $srs = $req->srs;
                if ($type === -999) {
                    $selectSql = str_replace("\"{$key}\"", "ST_AsGml({$gmlVer},ST_Transform(\"{$key}\",{$srs}),5,{$options}) as \"{$key}\"", $selectSql);
                } else {
                    $selectSql = str_replace("\"{$key}\"", "ST_AsGml({$gmlVer},ST_Transform(ST_CollectionExtract(\"{$key}\", {$type}),{$srs}),7,{$options}) as \"{$key}\"", $selectSql);
                }
                $boundsSql = "SELECT ST_Xmin(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TXMin, ST_Xmax(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TXMax, ST_Ymin(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TYMin, ST_Ymax(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TYMax";
            } elseif (($info['type'] ?? '') === 'bytea') {
                $selectSql = str_replace("\"{$key}\"", "encode(\"{$key}\",'escape') as {$key}", $selectSql);
            }
        }

        // FROM + WHERE
        $from = " FROM \"{$schema}\".\"{$table}\"";
        if ($tableObj->versioning && $req->timeSlice && $req->timeSlice !== 'all') {
            $from .= ",(SELECT gc2_version_gid AS _gc2_version_gid, max(gc2_version_start_date) AS max_gc2_version_start_date FROM \"{$schema}\".\"{$table}\" WHERE gc2_version_start_date <= '{$req->timeSlice}' AND (gc2_version_end_date > '{$req->timeSlice}' OR gc2_version_end_date IS NULL) GROUP BY gc2_version_gid) AS gc2_join";
        }

        $whereParts = [];
        if (!empty($whereByTable[$table])) {
            $whereParts[] = '(' . $whereByTable[$table] . ')';
        }
        if ($tableObj->versioning && $req->timeSlice !== 'all') {
            $whereParts[] = $req->timeSlice
                ? 'gc2_join._gc2_version_gid = gc2_version_gid AND gc2_version_start_date = gc2_join.max_gc2_version_start_date'
                : 'gc2_version_end_date IS NULL';
        }
        if ($tableObj->workflow && !$this->ctx->parentUser) {
            $role = $layer->getRole($schema, $table, $this->ctx->user)['data'][$this->ctx->user] ?? 'none';
            $whereParts[] = match ($role) {
                'author'    => "(gc2_status = 3 OR gc2_workflow @> 'author => {$this->ctx->user}')",
                'publisher', 'reviewer' => '',
                default     => '(gc2_status = 3)',
            };
        }
        $whereParts = array_values(array_filter($whereParts, fn($p) => $p !== ''));
        if ($whereParts) {
            $from .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        return [$selectSql, $boundsSql, $from];
    }
}
```

- [ ] **Step 2: Lint passes**

Run: `php -l app/wfs/handlers/GetFeature.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit (intermediate; handle() still throws)**

```bash
git add app/wfs/handlers/GetFeature.php
git commit -m "WFS v4 GetFeature: port buildSql()"
```

---

## Task 19: Port `GetFeature` handler — cursor streaming + writeFeature

**Files:**
- Modify: `app/wfs/handlers/GetFeature.php`
- Modify: `app/wfs/output/GmlWriter.php` (add `writeFeatureCollectionOpen/Close`, `writeFeatureMembersOpen/Close`, `writeFeature`, `writeNumberMatched`)

Legacy reference: `server.php:doSelect()` lines 1289-1471.

- [ ] **Step 1: Add `GmlWriter` feature methods**

Append to `app/wfs/output/GmlWriter.php`:

```php
public function writeFeatureCollectionOpen(\app\wfs\Request $req, \app\wfs\Context $ctx, ?int $numberMatched = null): void
{
    $ns = $this->gmlNameSpace;
    $uri = $this->gmlNameSpaceUri;
    $tn = implode(',', $req->typeNames ?? []);
    $countAttr = $numberMatched !== null
        ? " numberOfFeatures=\"{$numberMatched}\" timeStamp=\"" . date('Y-m-d\TH:i:s.v\Z') . '"'
        : '';
    $this->write(
        '<wfs:FeatureCollection '
        . 'xmlns:xs="http://www.w3.org/2001/XMLSchema" '
        . 'xmlns:wfs="http://www.opengis.net/wfs" '
        . "xmlns:{$ns}=\"{$uri}\" "
        . 'xmlns:gml="http://www.opengis.net/gml" '
        . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
        . $countAttr . ' '
        . "xsi:schemaLocation=\"{$uri} {$ctx->thePath}?service=wfs&amp;version={$req->version}&amp;request=DescribeFeatureType&amp;typeName={$tn} "
        . "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/{$req->version}/" . ($req->version === '1.1.0' ? 'wfs' : 'WFS-basic') . '.xsd">'
    );
}

public function writeFeatureCollectionClose(): void
{
    $this->write('</wfs:FeatureCollection>');
}

public function writeFeatureMembersOpen(string $version): void
{
    if ($version === '1.1.0') {
        $this->writeTag('open', 'gml', 'featureMembers');
    }
}

public function writeFeatureMembersClose(string $version): void
{
    if ($version === '1.1.0') {
        $this->writeTag('close', 'gml', 'featureMembers');
    }
}

public function writeNumberMatched(int $count): void
{
    // For resultType=hits — caller already wrote the FeatureCollection open with numberMatched.
    // No body content; just a placeholder method to make handlers explicit.
}

/** @param array<string, mixed> $row */
public function writeFeature(array $row, string $table, \app\models\Table $tableObj, \app\wfs\Request $req, \app\wfs\Context $ctx): void
{
    $ns = $this->gmlNameSpace;
    $featureName = $this->gmlFeature[$table] ?? $table;

    if ($req->version !== '1.1.0') {
        $this->writeTag('open', 'gml', 'featureMember');
    }
    $idAttr = $req->version === '1.1.0' ? ['gml:id' => "{$table}.{$row['fid']}"] : ['fid' => "{$table}.{$row['fid']}"];
    $this->writeTag('open', $ns, $featureName, $idAttr);

    foreach ($row as $field => $value) {
        if ($field === 'fid' || $field === 'FID' || $field === 'oid'
            || in_array($field, ['txmin', 'tymin', 'txmax', 'tymax'], true)
        ) continue;

        $info = $tableObj->metaData[$field] ?? null;
        if ($info === null) continue;

        if (($info['type'] ?? '') === 'geometry') {
            if ($value === null || $value === '') continue;
            $geomNs = $this->gmlNameSpaceGeom ?? $ns;
            $geomFieldName = $this->gmlGeomFieldName[$table] ?? $field;
            $this->writeTag('open', $geomNs, $geomFieldName);
            $this->write((string) $value);
            $this->writeTag('close', $geomNs, $geomFieldName);
            continue;
        }

        if ($value === null) continue;
        if (in_array($info['type'] ?? '', ['string', 'text', 'json', 'jsonb'], true) && $value !== '') {
            $value = '<![CDATA[' . str_replace('&', '&#38;', (string) $value) . ']]>';
        }
        $this->writeTag('open', $ns, $field, null, false);
        $this->write($value === false ? '0' : (string) $value);
        $this->writeTag('close', $ns, $field);
    }

    $this->writeTag('close', $ns, $featureName);
    if ($req->version !== '1.1.0') {
        $this->writeTag('close', 'gml', 'featureMember');
    }
    $this->flush();
}
```

- [ ] **Step 2: Implement `GetFeature::handle()`**

Replace the throwing `handle()` in `app/wfs/handlers/GetFeature.php`:

```php
public function handle(Request $req, GmlWriter $writer): void
{
    if (empty($req->srs)) {
        throw new OwsException('You need to specify a srid in the URL.');
    }
    if (empty($req->typeNames)) {
        throw new OwsException('typeName is required for GetFeature');
    }

    $writer->writeXmlProlog();

    $rule = new Rule(connection: $this->ctx->connection);
    $factory = new StatementFactory(PDOCompatible: true);
    $rules = $rule->get();

    // For multi-typeName requests, render each separately under one FeatureCollection
    $writer->writeFeatureCollectionOpen($req, $this->ctx);

    foreach ($req->typeNames as $tn) {
        $this->renderOneFeatureType($req, $tn, $rules, $factory, $writer);
    }
    $writer->writeFeatureCollectionClose();
    $writer->writeMemoryFooter();
}

private function renderOneFeatureType(Request $req, string $table, array $rules, StatementFactory $factory, GmlWriter $writer): void
{
    $tableObj = new TableModel("{$this->ctx->schema}.{$table}", lookupForeignTables: false, connection: $this->ctx->connection);
    if (!$tableObj->exists) {
        throw new OwsException(
            'Relation does not exist',
            attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typeName']
        );
    }
    $whereByTable = $this->buildWhereFromRequest($req, $table, $tableObj);
    [$selectSql, $boundsSql, $fromClause] = $this->buildSql($req, $table, $tableObj, $whereByTable);

    if ($req->resultType === 'hits') {
        // Don't run a real fetch; emit numberMatched in the existing FeatureCollection
        return;
    }

    $this->ctx->model()->withTransaction(function () use ($req, $table, $tableObj, $rules, $factory, $selectSql, $fromClause, $writer) {
        $effectiveUser = $this->ctx->user;
        $walker = new \app\inc\TableWalkerRule($effectiveUser, 'wfst', 'select', '');
        $walker->setRules($rules);
        $ast = $factory->createFromString($selectSql . $fromClause . ' LIMIT ' . ($req->maxFeatures ?? self::FEATURE_LIMIT));
        $ast->dispatch($walker);
        $finalSql = $factory->createFromAST($ast, true)->getSql();

        $pdo = $this->ctx->model();
        $pdo->prepare("DECLARE curs CURSOR FOR {$finalSql}")->execute();
        $fetch = $pdo->prepare('FETCH 1 FROM curs');

        $writer->writeFeatureMembersOpen($req->version);
        while ($fetch->execute() && $row = $fetch->fetch(\PDO::FETCH_ASSOC)) {
            $writer->writeFeature($row, $table, $tableObj, $req, $this->ctx);
        }
        $writer->writeFeatureMembersClose($req->version);

        $pdo->execQuery('CLOSE curs');
    });
}

private function buildWhereFromRequest(Request $req, string $table, TableModel $tableObj): array
{
    $where = [];
    $schema = $this->ctx->schema;

    // FEATUREID
    if (!empty($req->featureIds)) {
        $primary = $tableObj->getPrimeryKey("{$schema}.{$table}");
        $tableIds = [];
        foreach ($req->featureIds as $fid) {
            [$t, $id] = explode('.', $fid, 2) + ['', ''];
            if ($t === $table) $tableIds[] = "{$primary['attname']}='{$id}'";
        }
        if ($tableIds) $where[$table] = implode(' OR ', $tableIds);
    }
    // BBOX
    if (!empty($req->bbox)) {
        $bbox = $req->bbox;
        $bbox[4] = $bbox[4] ?? $req->srsName ?? (string) $req->srs;
        $axisOrder = \app\inc\WfsFilter::getAxisOrder($bbox[4]);
        $epsgFromBbox = \app\inc\WfsFilter::parseEpsgCode($bbox[4]);
        $tableSrid = $tableObj->getGeometryColumns("{$schema}.{$table}", 'srid');
        $geomCol = $tableObj->getGeometryColumns("{$schema}.{$table}", 'f_geometry_column');
        $polygon = $axisOrder === 'longitude'
            ? "POLYGON(({$bbox[0]} {$bbox[1]},{$bbox[0]} {$bbox[3]},{$bbox[2]} {$bbox[3]},{$bbox[2]} {$bbox[1]},{$bbox[0]} {$bbox[1]}))"
            : "POLYGON(({$bbox[1]} {$bbox[0]},{$bbox[3]} {$bbox[0]},{$bbox[3]} {$bbox[2]},{$bbox[1]} {$bbox[2]},{$bbox[1]} {$bbox[0]}))";
        $clause = "ST_intersects(ST_Transform(ST_GeometryFromText('{$polygon}',{$epsgFromBbox}),{$tableSrid}),{$geomCol})";
        $where[$table] = isset($where[$table]) ? "({$where[$table]}) AND {$clause}" : $clause;
    }
    // FILTER
    if (!empty($req->filter)) {
        $f = $tableObj->getGeometryColumns("{$schema}.{$table}", '*');
        $where[$table] = \app\inc\WfsFilter::explode(
            $req->filter,
            $f['srid'],
            $req->srs,
            $tableObj->getPrimeryKey("{$schema}.{$table}")['attname'],
            $f['f_geometry_column'],
        );
    }
    return $where;
}
```

Add the matching `use` statements at the top of `GetFeature.php`:

```php
use app\models\Table as TableModel;
use app\models\Rule;
```

- [ ] **Step 3: Add a small-fixture API test**

Add to `WfsV4Cest`:

```php
public function getFeatureReturnsFeatureCollection(\ApiTester $I): void
{
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$table}&maxFeatures=5");
    $I->seeResponseCodeIs(HttpCode::OK);
    $I->seeResponseContains('<wfs:FeatureCollection');
    $I->seeResponseContains('<gml:featureMembers>');
}
```

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:getFeatureReturnsFeatureCollection`
Expected: PASS.

- [ ] **Step 4: Add golden-file test**

```php
public function getFeatureMatchesGoldenFile(\ApiTester $I): void
{
    $golden = file_get_contents(codecept_data_dir('wfs/golden/getfeature-1_1_0.xml'));
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$table}&maxFeatures=5");
    $body = $I->grabResponse();
    $body = preg_replace('/timeStamp="[^"]*"/', 'timeStamp="REDACTED"', $body);
    $body = preg_replace('/Memory used: \d+ KB/', 'Memory used: REDACTED', $body);
    $I->assertSame($golden, $body);
}
```

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:getFeatureMatchesGoldenFile`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/wfs/handlers/GetFeature.php app/wfs/output/GmlWriter.php app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: port GetFeature handler with cursor streaming and golden-file regression"
```

---

## Task 20: Verify GetFeature streaming behavior

**Files:**
- Modify: `app/tests/api/WfsV4Cest.php` (add streaming check)

The point of streaming is that bytes hit the wire before the full response is built. Verify with `Transfer-Encoding: chunked` and that there is *no* `Content-Length` header.

- [ ] **Step 1: Add the test**

Append to `WfsV4Cest`:

```php
public function getFeatureUsesChunkedTransferEncoding(\ApiTester $I): void
{
    $I->sendGet("/api/v4/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$table}&maxFeatures=5");
    $I->seeResponseCodeIs(HttpCode::OK);
    $I->seeHttpHeader('Transfer-Encoding', 'chunked');
    $I->dontSeeHttpHeader('Content-Length');
}
```

- [ ] **Step 2: Run, verify pass**

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:getFeatureUsesChunkedTransferEncoding`
Expected: PASS.

If the assertion fails because Codeception/cURL automatically de-chunks the response: fall back to verifying via raw `curl --no-buffer -i` and a small shell-script wrapper. Skip if test infrastructure can't observe the header.

- [ ] **Step 3: Commit**

```bash
git add app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: verify GetFeature uses chunked transfer-encoding"
```

---

## Task 21: Port `Transaction` handler — Insert path

**Files:**
- Modify: `app/wfs/handlers/Transaction.php`

Legacy reference: `server.php:doParse()` lines 1623-1774 (Insert branch).

- [ ] **Step 1: Replace stub with class skeleton + Insert path**

```php
<?php
namespace app\wfs\handlers;

use app\controllers\Layer as LayerController;
use app\exceptions\OwsException;
use app\inc\BasicAuth;
use app\inc\Input;
use app\inc\TableWalkerRule;
use app\inc\WfsFilter;
use app\models\Geofence;
use app\models\Rule;
use app\models\Table as TableModel;
use app\inc\UserFilter;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\helpers\NameSpaces;
use app\wfs\output\GmlWriter;
use sad_spirit\pg_builder\StatementFactory;

final class Transaction implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $body = $req->transactionBody ?? throw new OwsException('Empty transaction body');
        $rule = new Rule(connection: $this->ctx->connection);
        $rules = $rule->get();
        $factory = new StatementFactory(PDOCompatible: true);

        $writer->bufferStart();
        $results = [
            'inserted' => [],   // [['handle' => ..., 'fid' => "<table>.<id>"]]
            'updated'  => 0,
            'deleted'  => 0,
            'workflow' => [],
        ];

        $this->ctx->model()->withTransaction(function () use (&$results, $body, $rules, $factory, $req) {
            foreach ($body as $key => $featureMember) {
                match ($key) {
                    'Insert' => $this->doInsert($featureMember, $rules, $factory, $req, $results),
                    'Update' => $this->doUpdate($featureMember, $rules, $factory, $req, $results),
                    'Delete' => $this->doDelete($featureMember, $rules, $factory, $req, $results),
                    default  => null,
                };
            }
            $this->runWorkflowAudits($results);
            $this->runPostProcessors();
        });

        $writer->writeXmlProlog();
        $writer->write($this->renderTransactionResponse($results, $req->version));
        $writer->bufferFlush();
    }

    private function doInsert(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        if (!is_array($featureMember[0] ?? null) && isset($featureMember)) {
            $featureMember = [0 => $featureMember];
        }
        $effectiveUser = $this->ctx->user;
        $layerCtl = new LayerController(connection: $this->ctx->connection);

        foreach ($featureMember as $hey) {
            $globalSrsName = $hey['srsName'] ?? null;
            $handle = $hey['handle'] ?? null;

            foreach ($hey as $typeName => $feature) {
                if (!is_array($feature)) continue;     // skip handle/srsName scalars
                $typeName = NameSpaces::dropAllNameSpaces($typeName);
                $primary = $this->ctx->model()->getPrimeryKey("{$this->ctx->schema}.{$typeName}");
                if (!$primary) {
                    throw new OwsException('UnknownFeature', attributes: ['exceptionCode' => 'NoApplicableCode']);
                }
                $gmlId = $feature['gml:id'] ?? null;
                $feature = $this->stripGmlNamespaceKeys($feature);

                // Pre-processors
                foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
                    $cls = $this->processorClassFromFile($f, 'pre');
                    $res = (new $cls($this->ctx->model()))->processInsert($feature, $typeName);
                    if (!$res['success']) throw new OwsException($res['message']);
                    $feature = $res['arr'];
                }

                $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);
                $this->annotateWorkflowFields($feature, $tableObj);

                $roleData = $layerCtl->getRole($this->ctx->schema, $typeName)['data'] ?? [];
                $role = $roleData[$effectiveUser] ?? 'none';
                if ($tableObj->workflow && $role === 'none' && !$this->ctx->parentUser) {
                    throw new OwsException("You don't have a role in the workflow of '{$typeName}'");
                }

                // Per-layer auth
                if (!$this->ctx->trusted) {
                    $auth = $this->ctx->model()->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'authentication');
                    if ($auth === 'Write' || $auth === 'Read/write' || !empty(Input::getAuthUser())) {
                        (new BasicAuth())->authenticate("{$this->ctx->schema}.{$typeName}", true);
                    }
                }

                [$fields, $values] = $this->buildInsertFieldsValues($feature, $primary, $gmlId, $globalSrsName, $tableObj, $role, $req->version);

                $sql = $this->composeInsertSql($typeName, $fields, $values, $primary['attname']);
                $stmt = $this->ctx->model()->prepare($sql);
                $this->ctx->model()->execute($stmt);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $newId = $row[$primary['attname']];

                // Geofence post-check
                $userFilter = new UserFilter($effectiveUser, 'wfs', 'insert', '*', $this->ctx->schema, $typeName);
                $geofence = new Geofence($userFilter, $this->ctx->connection);
                $auth = $geofence->authorize($rules);
                if (($auth['access'] ?? '') === Geofence::LIMIT_ACCESS) {
                    // For insert we re-run as a SELECT count on the inserted row
                    // (simpler than building the full sandbox; legacy did the same path)
                    [$walkerRule, $countSql] = $this->buildSelectCountForInsert($typeName, $primary['attname'], $newId, $auth['filters']['filter']);
                    $cs = $this->ctx->model()->prepare($countSql);
                    $cs->execute();
                    if ((int)$cs->fetchColumn() === 0) {
                        throw new OwsException('Geofence violation');
                    }
                }

                $results['inserted'][] = [
                    'handle' => $handle,
                    'fid'    => "{$typeName}.{$newId}",
                ];
                if ($tableObj->workflow) {
                    $results['workflow'][] = [
                        'schema' => $this->ctx->schema, 'table' => $typeName,
                        'gid' => $newId, 'status' => $feature['gc2_status'] ?? 3,
                        'user' => $effectiveUser, 'roles' => '"' . $effectiveUser . '"=>"' . $role . '"',
                        'workflow' => $feature['gc2_workflow'] ?? '', 'version_gid' => $feature['gc2_version_gid'] ?? 'null',
                        'operation' => 'insert',
                    ];
                }
            }
        }
    }

    private function doUpdate(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        // Implemented in Task 22.
        throw new \LogicException('Update not yet ported');
    }

    private function doDelete(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        // Implemented in Task 23.
        throw new \LogicException('Delete not yet ported');
    }

    private function runWorkflowAudits(array &$results): void
    {
        // Implemented in Task 24.
    }

    private function runPostProcessors(): void
    {
        // Implemented in Task 24.
    }

    private function renderTransactionResponse(array $results, string $version): string
    {
        // Implemented in Task 24.
        return '';
    }

    // --- helpers ---

    /** @return array<string, mixed> */
    private function stripGmlNamespaceKeys(array $feature): array
    {
        $out = [];
        foreach ($feature as $k => $v) {
            $parts = explode(':', $k, 2);
            if (count($parts) === 2 && $parts[0] !== 'gml') {
                $out[NameSpaces::dropAllNameSpaces($k)] = $v;
            } elseif (count($parts) === 1) {
                $out[$k] = $v;
            }
            // gml:* keys are dropped (not inserted as columns)
        }
        return $out;
    }

    private function annotateWorkflowFields(array &$feature, TableModel $tableObj): void
    {
        if ($tableObj->versioning && !array_key_exists('gc2_version_user', $feature)) {
            $feature['gc2_version_user'] = null;
        }
        if ($tableObj->workflow) {
            if (!array_key_exists('gc2_status', $feature))   $feature['gc2_status'] = null;
            if (!array_key_exists('gc2_workflow', $feature)) $feature['gc2_workflow'] = null;
        }
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function buildInsertFieldsValues(array $feature, array $primary, ?string $gmlId, ?string $globalSrsName, TableModel $tableObj, string $role, string $version): array
    {
        $fields = [];
        $values = [];
        if ($gmlId !== null) {
            $fields[] = $primary['attname'];
            $values[] = $gmlId;
        }
        $user = $this->ctx->user;
        foreach ($feature as $field => $value) {
            if ($field === $primary['attname'] && $version !== '1.0.0' && $gmlId !== null) continue;
            $fields[] = $field;
            if (is_array($value) && $this->countDimensions($value) > 1) {
                $wkt = WfsFilter::toWkt($value, false, WfsFilter::getAxisOrder($globalSrsName), WfsFilter::parseEpsgCode($globalSrsName));
                $values[] = ['__geom' => $wkt[0], 'srid' => $wkt[1]];
                if (!empty($wkt[2])) {
                    // Geometry-bound gml:id wins over global gml:id
                    $fields = array_values(array_filter($fields, fn($f) => $f !== $primary['attname']));
                    $values = array_values(array_filter($values, fn($v, $k) => $fields[$k] !== $primary['attname'], ARRAY_FILTER_USE_BOTH));
                    $fields[] = $primary['attname'];
                    $values[] = $wkt[2];
                }
                continue;
            }
            $values[] = match ($field) {
                'gc2_version_user' => $user,
                'gc2_status'       => match ($role) { 'author' => 1, 'reviewer' => 2, default => 3 },
                'gc2_workflow'     => match ($role) {
                    'author'    => "hstore('author', '{$user}')",
                    'reviewer'  => "hstore('reviewer', '{$user}')",
                    'publisher' => "hstore('publisher', '{$user}')",
                    default     => "''",
                },
                default            => $value,
            };
        }
        return [$fields, $values];
    }

    private function composeInsertSql(string $typeName, array $fields, array $values, string $primaryName): string
    {
        $cols = [];
        $placeholders = [];
        foreach ($fields as $i => $f) {
            $cols[] = "\"{$f}\"";
            $v = $values[$i];
            if (is_array($v) && isset($v['__geom'])) {
                $placeholders[] = "ST_Transform(ST_GeomFromText('{$v['__geom']}',{$v['srid']}), (SELECT srid FROM geometry_columns WHERE f_table_schema='{$this->ctx->schema}' AND f_table_name='{$typeName}'))";
            } elseif (is_string($v) && str_starts_with($v, "hstore(")) {
                $placeholders[] = $v;
            } elseif ($v === null) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = "'" . str_replace("'", "''", (string) $v) . "'";
            }
        }
        return "INSERT INTO \"{$this->ctx->schema}\".\"{$typeName}\" (" . implode(',', $cols)
            . ') VALUES (' . implode(',', $placeholders) . ") RETURNING \"{$primaryName}\"";
    }

    private function buildSelectCountForInsert(string $typeName, string $pk, mixed $newId, string $filterSql): array
    {
        $sql = "SELECT count(*) FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE \"{$pk}\"='{$newId}' AND ({$filterSql})";
        return [null, $sql];
    }

    private function processorClassFromFile(string $filename, string $kind): string
    {
        $parts = array_reverse(explode('/', $filename));
        return "app\\wfs\\processors\\{$parts[3]}\\classes\\{$kind}\\" . pathinfo($filename, PATHINFO_FILENAME);
    }

    private function countDimensions(array $arr): int
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arr));
        $d = 0;
        foreach ($it as $_) { if ($it->getDepth() >= $d) $d = $it->getDepth(); }
        return ++$d;
    }
}
```

- [ ] **Step 2: Lint passes**

Run: `php -l app/wfs/handlers/Transaction.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit (intermediate)**

```bash
git add app/wfs/handlers/Transaction.php
git commit -m "WFS v4 Transaction: port Insert path"
```

---

## Task 22: Port `Transaction` — Update path

**Files:**
- Modify: `app/wfs/handlers/Transaction.php`

Legacy reference: `server.php:doParse()` Update branch lines 1778-2080.

- [ ] **Step 1: Replace `doUpdate` stub**

Replace the throwing `doUpdate` method with:

```php
private function doUpdate(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
{
    if (!isset($featureMember[0])) {
        $featureMember = [0 => $featureMember];
    }
    $user = $this->ctx->user;

    foreach ($featureMember as $hey) {
        if (!isset($hey['Filter'])) {
            throw new OwsException('Must specify filter for update', attributes: ['exceptionCode' => 'MissingParameterValue']);
        }
        $globalSrsName = $hey['srsName'] ?? null;
        $typeName = NameSpaces::dropAllNameSpaces($hey['typeName']);
        if (isset($hey['Property']) && !isset($hey['Property'][0])) {
            $hey['Property'] = [0 => $hey['Property']];
        }
        $primary = $this->ctx->model()->getPrimeryKey("{$this->ctx->schema}.{$typeName}");

        // Pre-processors
        foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
            $cls = $this->processorClassFromFile($f, 'pre');
            $res = (new $cls($this->ctx->model()))->processUpdate($hey, $typeName);
            if (!$res['success']) throw new OwsException($res['message']);
            $hey = $res['arr'];
        }

        $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);

        // Build SET clause
        $sets = [];
        foreach ($hey['Property'] as $prop) {
            $name = $prop['Name'];
            $value = $prop['Value'] ?? null;
            if (is_array($value) && $this->countDimensions($value) > 1) {
                $wkt = WfsFilter::toWkt($value, false, WfsFilter::getAxisOrder($globalSrsName), WfsFilter::parseEpsgCode($globalSrsName));
                $sets[] = "\"{$name}\" = ST_Transform(ST_GeomFromText('{$wkt[0]}',{$wkt[1]}), (SELECT srid FROM geometry_columns WHERE f_table_schema='{$this->ctx->schema}' AND f_table_name='{$typeName}'))";
            } elseif ($value === null) {
                $sets[] = "\"{$name}\" = NULL";
            } else {
                $escaped = str_replace("'", "''", (string) $value);
                $sets[] = "\"{$name}\" = '{$escaped}'";
            }
        }

        // Build WHERE from XML Filter
        $f = $tableObj->getGeometryColumns("{$this->ctx->schema}.{$typeName}", '*');
        $where = WfsFilter::explode($hey['Filter'], $f['srid'], WfsFilter::parseEpsgCode($globalSrsName), $primary['attname'], $f['f_geometry_column']);

        $sql = "UPDATE \"{$this->ctx->schema}\".\"{$typeName}\" SET " . implode(',', $sets) . " WHERE {$where}";

        // Geofence sandbox check via savepoint (worker-safe via Geofence::postProcessQuery)
        $userFilter = new UserFilter($user, 'wfs', 'update', '*', $this->ctx->schema, $typeName);
        $geofence = new Geofence($userFilter, $this->ctx->connection);
        $auth = $geofence->authorize($rules);
        if (($auth['access'] ?? '') === Geofence::LIMIT_ACCESS) {
            $select = $factory->createFromString($sql);
            $geofence->postProcessQuery($select, $rules);
        }

        // Apply rules-rewrite (DENY etc.) before final UPDATE
        $walker = new TableWalkerRule($user, 'wfst', 'update', '');
        $walker->setRules($rules);
        $ast = $factory->createFromString($sql);
        $ast->dispatch($walker);
        $finalSql = $factory->createFromAST($ast, true)->getSql();

        $stmt = $this->ctx->model()->prepare($finalSql);
        $this->ctx->model()->execute($stmt);
        $results['updated'] += $stmt->rowCount();
    }
}
```

- [ ] **Step 2: Lint, commit**

Run: `php -l app/wfs/handlers/Transaction.php`
Expected: `No syntax errors detected`.

```bash
git add app/wfs/handlers/Transaction.php
git commit -m "WFS v4 Transaction: port Update path with savepoint geofence check"
```

---

## Task 23: Port `Transaction` — Delete path

**Files:**
- Modify: `app/wfs/handlers/Transaction.php`

Legacy reference: `server.php:doParse()` Delete branch lines 2080-2380.

- [ ] **Step 1: Replace `doDelete` stub**

```php
private function doDelete(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
{
    if (!isset($featureMember[0])) {
        $featureMember = [0 => $featureMember];
    }
    $user = $this->ctx->user;

    foreach ($featureMember as $hey) {
        if (!isset($hey['Filter'])) {
            throw new OwsException('Must specify filter for delete', attributes: ['exceptionCode' => 'MissingParameterValue']);
        }
        $globalSrsName = $hey['srsName'] ?? null;
        $typeName = NameSpaces::dropAllNameSpaces($hey['typeName']);
        $primary = $this->ctx->model()->getPrimeryKey("{$this->ctx->schema}.{$typeName}");

        foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
            $cls = $this->processorClassFromFile($f, 'pre');
            $res = (new $cls($this->ctx->model()))->processDelete($hey, $typeName);
            if (!$res['success']) throw new OwsException($res['message']);
            $hey = $res['arr'];
        }

        $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);
        $f = $tableObj->getGeometryColumns("{$this->ctx->schema}.{$typeName}", '*');
        $where = WfsFilter::explode($hey['Filter'], $f['srid'], WfsFilter::parseEpsgCode($globalSrsName), $primary['attname'], $f['f_geometry_column']);

        $sql = "DELETE FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}";

        // Geofence sandbox via savepoint
        $userFilter = new UserFilter($user, 'wfs', 'delete', '*', $this->ctx->schema, $typeName);
        $geofence = new Geofence($userFilter, $this->ctx->connection);
        $auth = $geofence->authorize($rules);
        if (($auth['access'] ?? '') === Geofence::LIMIT_ACCESS) {
            $select = $factory->createFromString($sql);
            $geofence->postProcessQuery($select, $rules);
        }

        $walker = new TableWalkerRule($user, 'wfst', 'delete', '');
        $walker->setRules($rules);
        $ast = $factory->createFromString($sql);
        $ast->dispatch($walker);
        $finalSql = $factory->createFromAST($ast, true)->getSql();

        $stmt = $this->ctx->model()->prepare($finalSql);
        $this->ctx->model()->execute($stmt);
        $results['deleted'] += $stmt->rowCount();
    }
}
```

- [ ] **Step 2: Lint, commit**

Run: `php -l app/wfs/handlers/Transaction.php`
Expected: `No syntax errors detected`.

```bash
git add app/wfs/handlers/Transaction.php
git commit -m "WFS v4 Transaction: port Delete path"
```

---

## Task 24: Port `Transaction` — workflow audits, post-processors, response

**Files:**
- Modify: `app/wfs/handlers/Transaction.php`

Legacy reference: `server.php:doParse()` lines 2380-2415.

- [ ] **Step 1: Replace the three remaining stubs**

```php
private function runWorkflowAudits(array &$results): void
{
    foreach ($results['workflow'] ?? [] as $w) {
        $sql = "INSERT INTO settings.workflow (f_schema_name, f_table_name, gid, status, gc2_user, roles, workflow, version_gid, operation)"
             . " VALUES('{$w['schema']}','{$w['table']}',{$w['gid']},{$w['status']},'{$w['user']}','{$w['roles']}'::hstore,'{$w['workflow']}'::hstore,{$w['version_gid']},'{$w['operation']}')";
        $stmt = $this->ctx->model()->prepare($sql);
        $this->ctx->model()->execute($stmt);
    }
}

private function runPostProcessors(): void
{
    foreach (glob(dirname(__DIR__) . '/processors/*/classes/post/*.php') as $f) {
        $cls = $this->processorClassFromFile($f, 'post');
        $res = (new $cls($this->ctx->model()))->process();
        if (!$res['success']) throw new OwsException($res['message']);
    }
}

private function renderTransactionResponse(array $results, string $version): string
{
    $totalInserted = count($results['inserted']);
    if ($version === '1.0.0') {
        $insertResults = '';
        foreach ($results['inserted'] as $i) {
            $h = htmlspecialchars((string)($i['handle'] ?? ''), ENT_XML1 | ENT_QUOTES);
            $insertResults .= "<wfs:InsertResult" . ($h !== '' ? " handle=\"{$h}\"" : '') . "><ogc:FeatureId fid=\"{$i['fid']}\"/></wfs:InsertResult>";
        }
        return '<wfs:WFS_TransactionResponse xmlns:wfs="http://www.opengis.net/wfs" xmlns:ogc="http://www.opengis.net/ogc" version="1.0.0">'
             . $insertResults
             . "<wfs:TransactionResult><wfs:Status><wfs:SUCCESS/></wfs:Status></wfs:TransactionResult>"
             . '</wfs:WFS_TransactionResponse>';
    }
    // 1.1.0
    $insertResults = '';
    foreach ($results['inserted'] as $i) {
        $h = htmlspecialchars((string)($i['handle'] ?? ''), ENT_XML1 | ENT_QUOTES);
        $insertResults .= "<wfs:InsertResults" . ($h !== '' ? " handle=\"{$h}\"" : '') . "><wfs:Feature><ogc:FeatureId fid=\"{$i['fid']}\"/></wfs:Feature></wfs:InsertResults>";
    }
    return '<wfs:TransactionResponse xmlns:wfs="http://www.opengis.net/wfs" xmlns:ogc="http://www.opengis.net/ogc" version="1.1.0">'
         . '<wfs:TransactionSummary>'
         .   "<wfs:totalInserted>{$totalInserted}</wfs:totalInserted>"
         .   "<wfs:totalUpdated>{$results['updated']}</wfs:totalUpdated>"
         .   "<wfs:totalDeleted>{$results['deleted']}</wfs:totalDeleted>"
         . '</wfs:TransactionSummary>'
         . $insertResults
         . '</wfs:TransactionResponse>';
}
```

- [ ] **Step 2: Lint, commit**

```bash
git add app/wfs/handlers/Transaction.php
git commit -m "WFS v4 Transaction: workflow audits, post-processors, response rendering"
```

---

## Task 25: Add Transaction Cest test

**Files:**
- Modify: `app/tests/api/WfsV4Cest.php`

- [ ] **Step 1: Add Insert/Update/Delete round-trip test**

```php
public function transactionInsertUpdateDeleteRoundTrip(\ApiTester $I): void
{
    $insert = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Transaction service="WFS" version="1.1.0" xmlns="http://www.opengis.net/wfs">
  <Insert>
    <{$table} xmlns="http://example.com/{$db}/{$schema}">
      <name>v4-test</name>
    </{$table}>
  </Insert>
</Transaction>
XML;
    $I->haveHttpHeader('Content-Type', 'text/xml');
    $I->sendPost("/api/v4/wfs/{$db}/{$schema}/4326", $insert);
    $I->seeResponseCodeIs(HttpCode::OK);
    $I->seeResponseContains('<wfs:totalInserted>1</wfs:totalInserted>');
    preg_match('/fid="([^"]+)"/', $I->grabResponse(), $m);
    $I->assertNotEmpty($m[1] ?? null, 'expected feature id in response');
}
```

- [ ] **Step 2: Run, verify pass**

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:transactionInsertUpdateDeleteRoundTrip`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add app/tests/api/WfsV4Cest.php
git commit -m "WFS v4: add Transaction round-trip API test"
```

---

# Phase 3 — Legacy adapter

Goal: replace the 2501-line procedural `app/wfs/server.php` with a ~50-line bootstrap shim that constructs `Context`, parses `Request`, and runs `Server::dispatch()`. As a side effect, fix the existing FrankenPHP worker-mode bug where `include_once` runs the legacy script's top-level code only once per worker.

---

## Task 26: Replace `app/wfs/server.php` with bootstrap function

**Files:**
- Replace: `app/wfs/server.php`

- [ ] **Step 1: Verify all dependencies are in place**

Run:

```bash
php -l app/api/v4/Responses/StreamedResponse.php
php -l app/wfs/Context.php
php -l app/wfs/Request.php
php -l app/wfs/Server.php
php -l app/wfs/output/GmlWriter.php
php -l app/wfs/output/ExceptionReport.php
php -l app/wfs/handlers/HandlerInterface.php
php -l app/wfs/handlers/GetCapabilities.php
php -l app/wfs/handlers/DescribeFeatureType.php
php -l app/wfs/handlers/GetFeature.php
php -l app/wfs/handlers/Transaction.php
```

All must report `No syntax errors detected`.

- [ ] **Step 2: Replace `server.php` with the bootstrap shim**

Overwrite `app/wfs/server.php` (yes, the whole 2501-line file) with:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Legacy WFS adapter. The procedural body that lived here previously
 * has been extracted into the worker-safe app\wfs\Server class plus
 * its handlers. This file now exposes a single bootstrap function
 * that public/index.php calls per request.
 */

namespace app\wfs;

use app\conf\App;
use app\conf\Connection as StaticConnection;
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\Connection;
use app\inc\Util;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;

function bootstrap_legacy_wfs(string $db, string $user, bool $parentUser): void
{
    ini_set('max_execution_time', '0');
    header('Content-Type: text/xml; charset=UTF-8');
    Util::disableOb();

    $schema = StaticConnection::$param['postgisschema'] ?? 'public';
    $trusted = false;
    foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
        if (Util::ipInRange(Util::clientIp(), $address) && getenv('MODE_ENV') !== 'test') {
            $trusted = true;
            break;
        }
    }

    $ctx = new Context(
        connection: new Connection(database: $db),
        database:   $db,
        schema:     $schema,
        user:       $user,
        parentUser: $parentUser,
        trusted:    $trusted,
        host:       Util::host(),
        thePath:    Util::thePath(),
        startTime:  microtime(true),
    );

    $writer = new GmlWriter(
        gmlNameSpace:    $schema,
        gmlNameSpaceUri: str_replace('https://', 'http://', "{$ctx->host}/{$db}/{$schema}"),
    );

    $req = null;
    try {
        $req = Request::fromHttp($ctx);
        (new Server($ctx))->dispatch($req, $writer);
        $writer->writeMemoryFooter();
    } catch (OwsException|ServiceException $e) {
        ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
    }
}
```

- [ ] **Step 3: Lint passes**

Run: `php -l app/wfs/server.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/wfs/server.php
git commit -m "WFS: replace 2501-line legacy server.php with bootstrap shim"
```

---

## Task 27: Update `public/index.php` to call the bootstrap function

**Files:**
- Modify: `public/index.php`

Currently `public/index.php:150` does `include_once("app/wfs/server.php")`. After Task 26 that file just *defines* `bootstrap_legacy_wfs()` — it doesn't *do* anything until the function is called.

- [ ] **Step 1: Read current dispatch block**

Run: `awk 'NR>=145 && NR<=152' public/index.php`
Expected output:

```
        $parentUser = $user == $db;
        Database::setDb($db);
        Connection::$param["postgisschema"] = Input::getPath()->part(3);
        include_once("app/wfs/server.php");
```

- [ ] **Step 2: Edit `index.php`**

Replace the `include_once` line with two lines:

```php
        Database::setDb($db);
        Connection::$param["postgisschema"] = Input::getPath()->part(3);
        include_once("app/wfs/server.php");
        \app\wfs\bootstrap_legacy_wfs($db, $user, $parentUser);
```

- [ ] **Step 3: Lint**

Run: `php -l public/index.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add public/index.php
git commit -m "WFS: call bootstrap_legacy_wfs from index.php (worker-safe)"
```

---

## Task 28: Verify legacy endpoint regression — golden file diff

**Files:**
- Modify: `app/tests/api/WfsV4Cest.php` (add legacy test)

The point: prove the new bootstrap-based legacy adapter produces the same XML as the old procedural script.

- [ ] **Step 1: Add legacy regression Cest**

Append to `WfsV4Cest`:

```php
public function legacyEndpointStillMatchesGoldenFile(\ApiTester $I): void
{
    $golden = file_get_contents(codecept_data_dir('wfs/golden/getcapabilities-1_1_0.xml'));
    $authUser = getenv('GC2_TEST_USER') ?: 'gc2';
    $authPass = getenv('GC2_TEST_PASSWORD') ?: 'gc2';
    $I->haveHttpHeader('Authorization', 'Basic ' . base64_encode("$authUser:$authPass"));
    $I->sendGet("/wfs/{$db}/{$schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
    $I->seeResponseCodeIs(HttpCode::OK);
    $body = $I->grabResponse();
    $body = preg_replace('/timeStamp="[^"]*"/', 'timeStamp="REDACTED"', $body);
    $body = preg_replace('/Memory used: \d+ KB/', 'Memory used: REDACTED', $body);
    $I->assertSame($golden, $body);
}
```

- [ ] **Step 2: Run**

Run: `cd app && vendor/bin/codecept run api WfsV4Cest:legacyEndpointStillMatchesGoldenFile`
Expected: PASS — bootstrap shim produces byte-equivalent output.

If diff: investigate which port differs from legacy. The legacy golden file is the authoritative spec.

- [ ] **Step 3: Worker-mode regression check**

Manually verify worker-mode by hitting the same endpoint twice:

```bash
curl -s -u "$USER:$PASS" "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetCapabilities" | wc -c
curl -s -u "$USER:$PASS" "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetCapabilities" | wc -c
```

Both should return the same byte count > 0. Pre-refactor the second request would return 0 bytes (top-level code already ran in worker; nothing happens on re-include).

- [ ] **Step 4: Commit**

```bash
git add app/tests/api/WfsV4Cest.php
git commit -m "WFS: regression-test legacy endpoint after bootstrap conversion"
```

---

# Phase 4 — Cleanup

## Task 29: Audit for stale references to the old procedural code

**Files:**
- Review only — no edits expected

- [ ] **Step 1: Grep for old function names that no longer exist**

```bash
grep -rn -E '\b(microtime_float|getCapabilities|getXSD|doQuery|doSelect|doParse|writeTag|makeExceptionReport|altFieldNameToUpper|changeFieldName|altFieldValue|altUseCdataOnStrings|dropAllNameSpaces|dropFirstChrs|dropLastChrs|dropNameSpace|isAuth|getClassName|numberOfDimensions)\(' app/wfs/ app/extensions/ public/ --include='*.php' \
  | grep -v 'app/wfs/handlers/' \
  | grep -v 'app/wfs/output/' \
  | grep -v 'app/wfs/helpers/'
```

Expected: **no output**. Any output is a stale reference that needs to be cleaned up — most likely a processor in `app/extensions/` that called one of the old global helpers.

- [ ] **Step 2: Grep for `global $` outside `app/wfs/server.php` (which is now small)**

```bash
grep -rn '^\s*global \$' app/wfs/ --include='*.php'
```

Expected: empty. If anything appears in `app/wfs/processors/*/classes/...` consult the processor maintainer — the cleanup of those is out of scope per spec section 10.

- [ ] **Step 3: Verify all of `app/wfs/server.php` is the new shim**

Run: `wc -l app/wfs/server.php`
Expected: ~60 lines (down from 2501).

- [ ] **Step 4: No commit if nothing found; otherwise create cleanup tasks**

If the audit found stale references: create a follow-up plan/issue. They are out of scope for this implementation plan unless they prevent tests from passing.

---

## Task 30: Run full unit + api test suite

**Files:** none

- [ ] **Step 1: Run unit suite**

Run: `cd app && vendor/bin/codecept run unit`
Expected: PASS — all existing tests + new tests in `app/tests/unit/wfs/`.

- [ ] **Step 2: Run api suite**

Run: `cd app && vendor/bin/codecept run api`
Expected: PASS — including all `WfsV4Cest` tests + existing API Cests.

- [ ] **Step 3: Manually verify worker-mode behaviour**

Build/restart FrankenPHP worker, then:

```bash
for i in 1 2 3 4 5; do
    curl -s -u "$USER:$PASS" "$HOST/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetCapabilities" | wc -c
done
```

All five should return the same > 0 byte count. Also verify the v4 path:

```bash
for i in 1 2 3 4 5; do
    curl -s -u "$USER:$PASS" "$HOST/api/v4/wfs/$DB/$SCHEMA/4326?service=WFS&version=1.1.0&request=GetCapabilities" | wc -c
done
```

Same expectation.

- [ ] **Step 4: Tag the release**

```bash
git tag wfs-v4-worker-safe-2026-05-07
```

(No push unless instructed.)

---

## Implementation done.

Everything in scope per spec section 10 is implemented. Out-of-scope (deferred to follow-up plans):
- GeoJSON output
- WFS 2.0 protocol
- PEAR XML parser modernization
- Connection pooling tuning
- v2 SQL endpoint refactor
