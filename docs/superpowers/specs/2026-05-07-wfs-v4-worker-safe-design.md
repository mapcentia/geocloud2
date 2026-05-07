# WFS v4 (worker-safe) and legacy refactor — Design

**Date:** 2026-05-07
**Author:** Martin Høgh (with Claude)
**Status:** Approved (awaiting implementation plan)

## 1. Goal and constraints

Refactor `app/wfs/server.php` so that:

- A new WFS v4 endpoint is available under `/api/v4/wfs/{db}/{schema}/{srs}/...` and is fully **worker-safe** (no globals, no static state across requests, deterministic transaction handling).
- The existing legacy WFS endpoint at `/wfs/{db}/{schema}/{srs}/...` continues to function. Its current `include_once`-based execution model is also broken under FrankenPHP worker mode (top-level code only runs once per worker), so the refactor must fix that as a side effect.
- WFS XML output must keep its current **streaming/flushed** behaviour — large GetFeature responses must not be buffered in full.
- Existing pre/post-processor plugins under `app/wfs/processors/*/classes/{pre,post}/*.php` keep working unchanged. Same instantiation contract: `new $cls($model)`.
- WFS protocol surface stays identical: 1.0.0/1.1.0, GML2/GML3, GetCapabilities/DescribeFeatureType/GetFeature/Transaction. No new features (GeoJSON, WFS 2.0) in this iteration.
- **Output byte-equivalence**: legacy adapter responses must match the existing `server.php` output byte-for-byte where reasonable (modulo timestamps, memory comments, and the `Connection: close` header which is dropped). v4 may diverge in headers but must match the body byte-for-byte against legacy for the same request.

## 2. Architecture and file layout

```
app/
├─ wfs/
│  ├─ server.php                     ← legacy adapter (thin shim, ~50 lines)
│  ├─ processors/                    ← unchanged, used by both legacy and v4
│  ├─ Server.php                     ← orchestrator: parse request, dispatch
│  ├─ Request.php                    ← immutable DTO (all ex-globals consolidated)
│  ├─ Context.php                    ← request-scoped state: connection, user, schema, db, trusted
│  ├─ handlers/
│  │  ├─ HandlerInterface.php
│  │  ├─ GetCapabilities.php
│  │  ├─ DescribeFeatureType.php
│  │  ├─ GetFeature.php              ← cursor-streaming
│  │  └─ Transaction.php             ← Insert/Update/Delete (private methods)
│  ├─ output/
│  │  ├─ GmlWriter.php               ← writeTag, flush, namespaces, buffer-mode
│  │  └─ ExceptionReport.php         ← OWS/Service exception XML rendering
│  └─ helpers/
│     └─ NameSpaces.php              ← dropNameSpace, dropAllNameSpaces, dropFirst/Last
└─ api/v4/
   └─ controllers/
      └─ Wfs.php                     ← v4 controller (Route2-dispatched, returns StreamedResponse)

app/api/v4/Responses/
└─ StreamedResponse.php              ← new: callable + content-type
app/inc/
└─ Route2.php                        ← +5 lines: recognize StreamedResponse, invoke callback
```

### 2.1 Data flow

**Legacy** (`/wfs/{db}/{schema}/{srs}/...`):

```
public/index.php (sets $db, $user, $parentUser, schema)
  → include app/wfs/server.php (thin adapter, defines bootstrap_legacy_wfs())
  → bootstrap_legacy_wfs($db, $user, $parentUser)
  → builds Context from index.php-set globals + Connection::$param
  → builds Request via Request::fromHttp($ctx)
  → new Server($ctx)->dispatch($req, $gmlWriter)
  → handler streams XML via GmlWriter (echo + flush per feature)
```

**v4** (`/api/v4/wfs/{db}/{schema}/{srs}/...`):

```
public/index.php → Route2 → matches Wfs controller route
  → JWT auth attempted (Route2.process); falls through to controller for Basic
  → Wfs::get_index() / post_index()
    → buildContext() (JWT or Basic + path params)
    → Request::fromHttp($ctx)
    → returns new StreamedResponse(contentType, callback)
  → Route2 sees StreamedResponse, sets Content-Type, invokes callback
  → callback: new Server($ctx)->dispatch($req, $writer)
  → handler streams XML
```

### 2.2 Component responsibilities

| Component | Responsibility | Scope |
|---|---|---|
| `Server` | Validate request, route to handler. No XML written here. | Per request |
| `Request` | Immutable DTO with all parsed parameters (typename, srs, version, filter, bbox, …). No logic. | Per request |
| `Context` | Connection + user + parentUser + schema + db + trusted. Constructor-injected into Server and handlers. | Per request |
| `Handler*` | Implements one WFS operation flow. Calls Connection (via Context) and GmlWriter. | Per request |
| `GmlWriter` | Encapsulates all XML streaming (`echo`+`flush`+`ob_flush`), `writeTag` helpers, namespace handling, buffer-mode for Transaction. **Only place that writes to `php://output`.** | Per request |
| `StreamedResponse` | DTO with `callable` + `contentType`. Route2 sees the type, calls callback, no JSON encoding. | Per request |

### 2.3 Worker safety per component

- **No `static` fields** on Server/Request/Context/handlers. Each request gets fresh instances.
- **No `global`** — everything goes via constructor DI or method arguments.
- **Database connection** comes from the v4 `Connection` object (same pattern as other v4 controllers). Legacy adapter constructs a `Connection` from `$user`/`$db` already set by `index.php` — no touching of `Connection::$param` static.
- **Transactions** wrapped in `Model::withTransaction()` in `Transaction` handler so that errors mid-flight roll back. `GetFeature`'s cursor loop is also wrapped in `withTransaction` so the cursor always closes and the transaction never leaks.
- **Pre/post-processors** loaded per request via `glob()` loop (as today), but instantiated in handler scope (not stored in statics).
- **Streaming flush** uses `flush()` + `ob_flush()` on regular output buffer; FrankenPHP handles chunked transfer-encoding correctly as long as `Content-Length` is not set.

## 3. Server, Request, Context API

### 3.1 `Server`

```php
namespace app\wfs;

final class Server
{
    private const HANDLERS = [
        'GETCAPABILITIES'     => handlers\GetCapabilities::class,
        'DESCRIBEFEATURETYPE' => handlers\DescribeFeatureType::class,
        'GETFEATURE'          => handlers\GetFeature::class,
        'TRANSACTION'         => handlers\Transaction::class,
    ];

    public function __construct(private readonly Context $ctx) {}

    public function dispatch(Request $req, output\GmlWriter $writer): void
    {
        $this->validateProtocol($req);
        $this->checkLayerEnabled($req);          // for non-Capabilities
        $this->basicAuth($req);                  // if !trusted

        $class = self::HANDLERS[strtoupper($req->operation)]
            ?? throw new OwsException(
                "No such operation WFS {$req->operation}",
                attributes: ['exceptionCode' => 'OperationNotSupported', 'locator' => $req->operation]
            );

        (new $class($this->ctx))->handle($req, $writer);
    }
}
```

### 3.2 `Request`

```php
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

    public static function fromHttp(Context $ctx): self { /* parser factory */ }
}
```

`Request::fromHttp(Context $ctx)` encapsulates the entire 200-line `HTTP_FORM_VARS`/XML-unserializer logic from the top of current `server.php`. **Single point of GET/POST/XML parsing.**

### 3.3 `Context`

```php
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

    public function model(): Model { return new Model($this->connection); }
}
```

### 3.4 Mapping from old globals

| Old global | New location |
|---|---|
| `$postgisObject`, `$layerObj`, `$geometryColumnsObj` | Local in each handler, instantiated per request from `$ctx->connection` |
| `$user`, `$parentUser`, `$db` | `Context::$user`, `$parentUser`, `$database` |
| `$postgisschema` | `Context::$schema` |
| `$trusted` | `Context::$trusted` |
| `$host`, `$thePath`, `$server`, `$startTime` | `Context::$host`, `$thePath`, `$startTime` |
| `$tables`, `$properties`, `$fields`, `$wheres`, `$bbox`, `$resultType`, `$srsName`, `$version`, `$service`, `$maxFeatures`, `$outputFormat`, `$srs`, `$timeSlice`, `$featureids`, `$HTTP_FORM_VARS` | `Request` fields, set once in `Request::fromHttp` |
| `$BBox`, `$fullSql`, `$arr`, `$tableObj`, `$fieldConfArr`, `$gen`, `$level`, `$depth`, `$currentTable`, `$currentTag`, `$from`, `$filters` | Local in handler/loop bodies (were never request-state, just loop-state) |
| `$gmlNameSpace`, `$gmlNameSpaceUri`, `$gmlNameSpaceGeom`, `$gmlFeature`, `$gmlGeomFieldName`, `$gmlUseAltFunctions`, `$defaultBoundedBox` | `GmlWriter` properties |
| `$specialChars`, `$logPath`, `$unserializer`, `$unserializer_options`, `$lf`, `$cacheDir`, `$logFile`, `$transaction`, `$rowIdsChanged`, `$ODEUMhostName` | Constants or local variables in `Request` parsing |

**Net result: zero globals remaining. Every request is fully isolated.**

## 4. Handlers

All handlers implement the same interface and are instantiated per request:

```php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

interface HandlerInterface
{
    public function handle(Request $req, GmlWriter $writer): void;
}
```

Constructor convention: `__construct(Context $ctx)`. `Server::dispatch()` instantiates as `new $class($this->ctx)`.

### 4.1 `GetCapabilities`

- **Input**: none — delivers full schema layer catalogue.
- **Flow**: write XML prolog + `<wfs:WFS_Capabilities>` root → static service identification/provider/operations metadata → query `Layer::getAll($ctx->schema)` (one query, no cursor — relatively few layers) → for each layer write `<wfs:FeatureType>` (name, title, defaultSRS, BoundingBox via `getGeometryColumns(...)['extent']`) → write `<ogc:Filter_Capabilities>` (static, version-dependent) → close root.
- **DB**: one query per layer for extent (existing pattern). Optimization out of scope.
- **Worker safety**: no transaction opened; reads only.

### 4.2 `DescribeFeatureType`

- **Input**: `$req->typeNames` (may be comma-separated multiple).
- **Flow**: write XSD prolog + namespace imports → for each typeName fetch metadata via `Table::getMetaData(...)` and generate `<xs:complexType>` with one `<xs:element>` per column → geometry columns get specific `gml:` type per `geomType` → close schema element.
- **DB**: one read per requested layer.
- **Worker safety**: reads only.

### 4.3 `GetFeature` (streaming-critical)

```php
public function handle(Request $req, GmlWriter $writer): void
{
    if (!$req->srs) {
        throw new OwsException('You need to specify a srid in the URL.');
    }

    $writer->writeXmlProlog();
    $writer->writeFeatureCollectionOpen($req, $this->ctx);

    $rule = new Rule(connection: $this->ctx->connection);
    $factory = new StatementFactory(PDOCompatible: true);
    $rules = $rule->get();

    foreach ($req->typeNames as $table) {
        $this->renderOneFeatureType($req, $table, $rules, $factory, $writer);
    }

    $writer->writeFeatureCollectionClose();
}

private function renderOneFeatureType(...): void
{
    $tableObj = new Table("{$this->ctx->schema}.$table", connection: $this->ctx->connection);
    if (!$tableObj->exists) {
        throw new OwsException(
            'Relation does not exist',
            attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typeName']
        );
    }

    [$selectSql, $boundsSql, $fromClause] = $this->buildSql($req, $table, $tableObj);

    $countAst = $factory->createFromString("SELECT COUNT(*) {$fromClause} LIMIT " . self::FEATURE_LIMIT);
    $countAst->dispatch(new TableWalkerRule($this->effectiveUser($req), 'wfst', 'select', ''));
    $countSql = $factory->createFromAST($countAst, true)->getSql();

    if ($req->resultType === 'hits') {
        // count + writeNumberMatched, return
    }

    $this->ctx->model()->withTransaction(function () use (...) {
        // DECLARE CURSOR; FETCH 1; loop; CLOSE
        // writer streams each row via writeFeature() with flush() per call
    });
}
```

**Why `withTransaction` here?** The cursor `curs` is transaction-scoped in PostgreSQL. If the fetch loop throws halfway (client disconnects, a row fails, a `flush()` fails), the cursor must close and the transaction must roll back. `withTransaction` handles that automatically.

**Streaming detail**: `writer->writeFeature(...)` calls `flush()` + `ob_flush()` after each feature so the client receives bytes incrementally. Critical: no code between `writeFeatureCollectionOpen` and `writeFeatureCollectionClose` may buffer more than ~1 feature.

### 4.4 `Transaction`

Single class with private methods (not sub-classes — they share transaction scope, rules, and processor loading):

```php
final class Transaction implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $body = $req->transactionBody ?? throw new OwsException('Empty transaction body');

        $rule = new Rule(connection: $this->ctx->connection);
        $rules = $rule->get();
        $factory = new StatementFactory(PDOCompatible: true);

        $writer->bufferStart();   // Transaction returns one XML response, not streaming
        $results = ['inserted' => [], 'updated' => 0, 'deleted' => 0, 'handles' => []];

        $this->ctx->model()->withTransaction(function () use (&$results, $body, $rules, $factory, $req, $writer) {
            foreach ($body as $key => $featureMember) {
                match ($key) {
                    'Insert' => $this->doInsert($featureMember, $rules, $factory, $req, $results, $writer),
                    'Update' => $this->doUpdate($featureMember, $rules, $factory, $req, $results),
                    'Delete' => $this->doDelete($featureMember, $rules, $factory, $req, $results),
                    default  => null,
                };
            }
            $this->runWorkflowAudits($results);
            $this->runPostProcessors($results);
        });

        $writer->writeTransactionResponse($results, $req->version);
        $writer->bufferFlush();
    }

    private function doInsert(...): void { /* ~200 lines from legacy */ }
    private function doUpdate(...): void { /* ~300 lines */ }
    private function doDelete(...): void { /* ~150 lines */ }

    private function runWorkflowAudits(array &$results): void { /* settings.workflow inserts */ }
    private function runPostProcessors(array $results): void { /* glob + new $class($model)->process() */ }
}
```

**Geofence integration**: each write operation calls `$geofence->postProcessQuery(...)` which is already worker-safe (uses savepoint via `withRollback`). With the outer `withTransaction` this becomes:

```
BEGIN
  SAVEPOINT sp_xxx
    -- run user UPDATE in temp foo
    -- count rows matching geofence filter
  ROLLBACK TO SAVEPOINT sp_xxx
  RELEASE SAVEPOINT sp_xxx
  -- now run actual UPDATE
COMMIT
```

Worker-safe by construction as long as Geofence receives `$ctx->connection`.

**Pre-processors** loaded inside `doInsert`/`doUpdate`/`doDelete` via the same `glob()` pattern as legacy, instantiated with `$this->ctx->model()`.

**Worker safety in Transaction**: entire block is one `withTransaction` — errors roll back everything including savepoints and pre-processor work. Post-processors run **inside** the transaction before commit so they can fail and roll back. Matches legacy semantics.

## 5. Output, streaming, and Route2 integration

### 5.1 `GmlWriter`

```php
namespace app\wfs\output;

final class GmlWriter
{
    private bool $buffering = false;
    private string $buffer = '';

    public function __construct(
        public readonly string $gmlNameSpace,
        public readonly string $gmlNameSpaceUri,
        public readonly ?string $gmlNameSpaceGeom = null,
        public readonly array $gmlFeature = [],
        public readonly array $gmlGeomFieldName = [],
        public readonly array $gmlUseAltFunctions = [],
    ) {}

    public function bufferStart(): void { $this->buffering = true; $this->buffer = ''; }
    public function bufferFlush(): void { echo $this->buffer; $this->buffer = ''; $this->buffering = false; flush(); }

    public function flush(): void
    {
        if ($this->buffering) return;
        flush();
        if (ob_get_level() > 0) ob_flush();
    }

    public function write(string $s): void
    {
        if ($this->buffering) { $this->buffer .= $s; return; }
        echo $s;
    }

    public function writeTag(string $type, ?string $ns, string $tag, ?array $atts = null, bool $newline = true): void
    {
        // (same logic as legacy writeTag, writes via $this->write())
    }

    // High-level helpers used by handlers:
    public function writeXmlProlog(): void;
    public function writeFeatureCollectionOpen(Request $req, Context $ctx): void;
    public function writeFeatureCollectionClose(): void;
    public function writeFeatureMembersOpen(string $version): void;
    public function writeFeatureMembersClose(string $version): void;
    public function writeFeature(array $row, string $table, Table $tableObj, Request $req, Context $ctx): void;
    public function writeNumberMatched(int $count): void;
    public function writeTransactionResponse(array $results, string $version): void;
    public function writeMemoryFooter(): void;
}
```

Design choices:

1. **`buffering` mode in same class** instead of two classes — Transaction response and GetFeature response share 90% of tag writing. A flag keeps the API small.
2. **Constructor configuration** instead of globals. Constructed by legacy adapter / v4 controller from `Connection::$param["postgisschema"]` + `$ctx->host`/`$db`/`$schema`.
3. **No `static` state**.
4. **`ob_get_level() > 0` guard** before `ob_flush()` because `Util::disableOb()` turns OB off.

### 5.2 `StreamedResponse`

```php
namespace app\api\v4\Responses;

final readonly class StreamedResponse extends Response
{
    public function __construct(
        public string $contentType,
        public \Closure $callback,
        public int $status = 200,
    ) {
        parent::__construct(data: null);
    }
}
```

`getData()` returns `null` so Route2's existing branch doesn't try to JSON-encode anything.

The controller builds its own `GmlWriter` and closes over it — Route2 never needs to know about GmlWriter.

### 5.3 Route2 changes

Five lines added to the existing `dispatch` flow:

```php
try {
    $controller->validate();
    $response = $controller->$action($r);
} finally {
    Model::rollbackAllOpenTransactions();
}

if ($response instanceof StreamedResponse) {
    header('HTTP/1.0 ' . $response->status . ' ' . Util::httpCodeText($response->status));
    header('Content-Type: ' . $response->contentType);
    ($response->callback)();
    return;
}

// Existing JSON branch unchanged below
```

### 5.4 Headers and output buffering

Legacy `server.php` sets headers + disables OB at file top. In worker mode this only runs once. Solution: **headers are sent inside the StreamedResponse branch / inside `bootstrap_legacy_wfs()`**.

- `Util::disableOb()` is called inside the bootstrap function (legacy) and inside the StreamedResponse branch in Route2 (v4).
- `Connection: close` header is dropped. It was a legacy workaround for clients that didn't handle missing `Content-Length`. FrankenPHP/HTTP/1.1 chunked transfer-encoding handles this. Re-add if compatibility issues surface.
- `Content-Length` is **not** set (we don't know it). PHP/FrankenPHP automatically uses chunked encoding when missing.

### 5.5 Buffering vs streaming per operation

| Operation | Mode | Why |
|---|---|---|
| GetCapabilities | streaming | Output is large; clients expect quick start |
| DescribeFeatureType | streaming | Small but no reason to buffer |
| GetFeature | **streaming per feature** | Datasets can be huge; cursor is row-by-row |
| Transaction | **buffered** | Atomic response expected; rollback shouldn't leak partial XML; response is short |

Transaction calls `$writer->bufferStart()` before the transaction and `$writer->bufferFlush()` after commit. If a throw happens before commit, `bufferFlush()` is never called → no partial output → exception report renders cleanly.

### 5.6 ExceptionReport

```php
namespace app\wfs\output;

final class ExceptionReport
{
    public static function render(\Throwable $e, string $version, GmlWriter $writer): void
    {
        $writer->bufferStart();   // discard any pending buffer
        if ($version === '1.1.0' && $e instanceof OwsException) {
            $writer->write(self::renderOws($e));
        } else {
            $writer->write(self::renderService($e));
        }
        $writer->bufferFlush();
    }
}
```

If a throw happens mid-stream (after some features already sent), we render an `<ows:ExceptionReport>` element after the last closed feature — same behaviour as legacy.

## 6. Auth, legacy adapter, error handling

### 6.1 v4 auth flow

`buildContext()` only establishes **who** the user is. Per-layer authorization (`BasicAuth::authenticate("{$schema}.{$typeName}", ...)`) happens later inside `Server::dispatch()` once `Request::fromHttp()` has parsed the `TYPENAME`. Path parameters come from the Route2 route map (matched by the `Wfs` controller's `#[Controller(route: ...)]` attribute), not from `Input::getPath()->part(N)`.

```php
private function buildContext(): Context
{
    // 1. Try Bearer JWT (already validated by Route2 if present)
    $user = $this->route->jwt['data']['uid'] ?? null;
    $database = $this->route->jwt['data']['database'] ?? null;

    // 2. Fallback to HTTP Basic (no per-layer check yet; deferred to Server)
    if (!$user || !$database) {
        $authUser = Input::getAuthUser();
        if (!$authUser) {
            throw new OwsException(
                'Authentication required',
                attributes: ['exceptionCode' => 'NoApplicableCode']
            );
        }
        $database = $this->route->getParam('db');
        $user = $authUser;
    }

    $schema = $this->route->getParam('schema');
    $parentUser = $user === $database;

    return new Context(
        connection: new Connection(user: $user, database: $database),
        database: $database,
        schema: $schema,
        user: $user,
        parentUser: $parentUser,
        trusted: $this->isTrustedIp(),
        host: Util::host(),
        thePath: Util::thePath(),
        startTime: microtime(true),
    );
}
```

The `Wfs` controller is `#[Controller(route: 'api/v4/wfs/{db}/{schema}/{srs}/[timeSlice]', scope: Scope::PUBLIC)]` so Route2 doesn't reject anonymous requests; controller validates auth itself (Basic or JWT) and then `Server::dispatch()` runs the per-layer `BasicAuth::authenticate()` check (already defined as `$this->basicAuth($req)` in section 3.1).

`App::$param['trustedAddresses']` semantics preserved.

### 6.2 Legacy adapter

`app/wfs/server.php` reduced to ~50 lines:

```php
<?php
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

    $schema  = StaticConnection::$param['postgisschema'];
    $trusted = false;
    foreach (App::$param['trustedAddresses'] as $address) {
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

`public/index.php` change (one line):

```php
include_once 'app/wfs/server.php';
\app\wfs\bootstrap_legacy_wfs($db, $user, $parentUser);
```

**This fixes the worker-mode bug in legacy as a side effect** — the bootstrap function runs every request, not just first per worker.

### 6.3 Error handling layers

| Layer | What's caught | Action |
|---|---|---|
| `Server::dispatch()` | nothing | propagates |
| `Handler*::handle()` | nothing | throws `OwsException`/`ServiceException` for protocol errors |
| `withTransaction` blocks | `\Throwable` | automatic rollback, rethrow |
| Legacy adapter top-level try | `OwsException`, `ServiceException` | `ExceptionReport::render($e, $version, $writer)` |
| v4 StreamedResponse callback | same | same |
| Route2 finally | open transactions via `Model::rollbackAllOpenTransactions()` | safety net |
| Worker loop finally | remaining transactions + `DISCARD ALL` | safety net |

## 7. Testing strategy

### 7.1 Unit tests (PHPUnit)

| File | Coverage |
|---|---|
| `Tests/wfs/RequestTest.php` | `Request::fromHttp()` parses GET with various parameters; XML POST body for GetFeature/Transaction; FILTER XML; FEATUREID format; case-insensitive parameter names |
| `Tests/wfs/ContextTest.php` | Constructor arg distribution; `model()` helper returns Model bound to correct Connection |
| `Tests/wfs/output/GmlWriterTest.php` | `writeTag` with/without ns, atts, indenting; `bufferStart`/`bufferFlush` cycle; nested tags |
| `Tests/wfs/handlers/GetCapabilitiesTest.php` | XML output validates against WFS 1.1.0 XSD |
| `Tests/wfs/handlers/GetFeatureTest.php` | Mock cursor returning rows, verify streaming output structure; bbox/filter/maxFeatures handling; rules-rewrite invokes |
| `Tests/wfs/handlers/TransactionTest.php` | Insert/Update/Delete sub-flows; geofence LIMIT block scenarios; pre/post-processor invocations; rollback on mid-transaction error |

### 7.2 Integration tests

- Generate fixtures via the same `Database::createSchema()` used elsewhere
- Run full HTTP flow through v4 controller with real DB cursor
- Verify streaming via `curl` + sanity-check chunked encoding (`Transfer-Encoding: chunked`)
- WFS conformance: subset of GeoTools/QGIS validation suites against both endpoints

### 7.3 Regression tests for legacy

- Capture existing WFS responses from production endpoint (golden files)
- Run same requests against legacy adapter post-refactor — diff should be empty (or only timestamp/memory comments)

## 8. Rollout plan

1. **Phase 1 — Skeleton** (no legacy changes):
   - Add `StreamedResponse` + Route2 5-line change
   - Create `Context`, `Request`, `Server`, `HandlerInterface` (empty stubs)
   - Create `GmlWriter` with `writeTag` + buffer mode (port from legacy)
   - Create empty v4 controller stub returning hardcoded XML "hello world" via StreamedResponse
   - Test: route works end-to-end, content-type set correctly, chunked encoding observable

2. **Phase 2 — Per-handler porting**:
   - Port `GetCapabilities` (easiest, mostly static XML)
   - Port `DescribeFeatureType`
   - Port `GetFeature` (hardest — cursor + rules-rewrite + geofence)
   - Port `Transaction` (most complex)
   - For each: keep golden file diff = empty against legacy

3. **Phase 3 — Legacy adapter**:
   - Replace `app/wfs/server.php` content with ~50-line thin shim
   - Wrap in `bootstrap_legacy_wfs()` function
   - Update `public/index.php` 1-line change
   - Verify regression tests still green

4. **Phase 4 — Cleanup**:
   - Remove dead code (the 12 helper functions above/below if no longer used)
   - Drop PEAR XML imports if all parsing migrated to SimpleXMLElement (or keep — no obligation)

Each phase committed separately for easy rollback.

## 9. Risks and mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| Subtle regression in `writeTag` formatting (whitespace, newline) breaks existing clients | Medium | Golden file test, byte-exact diff against legacy output |
| GML2 vs GML3 differences not preserved correctly in shared handler | Medium | Test both versions explicitly in handler tests |
| Streaming flush doesn't work in FrankenPHP worker mode (output buffered by reverse proxy) | Low | Test with `curl --no-buffer` against actual worker; verify chunked encoding |
| `bootstrap_legacy_wfs()` breaks if `index.php` calls with unexpected globals | Low | Explicit function signature catches the problem at first call |
| Pre/post-processors rely on globals we removed | Medium | Audit `app/wfs/processors/` and `app/extensions/*` for `global $...` references; document new contract |
| Geofence's new `connection` parameter breaks if caller not updated | Low | Already fixed in earlier rettelse for v4 path; legacy path gets it via `$ctx->connection` |
| WFS clients that don't follow HTTP/1.1 chunked encoding | Low | Keep `Connection: close` as fallback if specific clients fail |

## 10. Out of scope for this iteration

- GeoJSON output (deferred; would extend `WriterInterface` later)
- WFS 2.0 protocol support (deferred; significant schema changes)
- Connection pooling tuning beyond what `Model::$PdoConnections` already provides
- Modernizing the PEAR XML parser dependency (works, low priority)
- `app/api/v2/Sql.php` and other legacy WFS-adjacent endpoints
