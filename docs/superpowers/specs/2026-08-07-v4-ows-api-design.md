# v4 OWS API (worker-safe WMS proxy) — Design

**Date:** 2026-08-07
**Branch:** dev/multiple_styles
**Status:** Approved design

## 1. Goal and constraints

Create a new OWS endpoint under `api/v4/ows/schema/{schema}` that is a v4 version of the
existing `/ows` (and `/wms`) endpoint served by `app/controllers/Wms.php`:

- **Full parity** with the legacy endpoint: WMS, WFS proxying (GET and POST) and
  UTFGRID/MVT (`format=json|mvt`) against the MapServer/QGIS Server backends, including
  the `filters` parameter, `labels=false`, geofence rules, versioning filters and
  external WMS-source passthrough.
- **Fully worker-safe (FrankenPHP)**: no globals, no static request state, no `exit()`,
  no direct `header()`/`echo` outside the streamed-response callback, deterministic
  cleanup. The precedent is the WFS v4 refactor
  (`docs/superpowers/specs/2026-05-07-wfs-v4-worker-safe-design.md`).
- **The legacy endpoint stays untouched.** `/ows/{db}/{schema}` and `/wms/{db}/{schema}`
  keep running through `app/controllers/Wms.php` exactly as today. Zero changes to that
  file or its routing.
- **Token-based**: database, user and user group come from the Bearer JWT, exactly like
  the v4 WFS controller (`app/api/v4/controllers/Wfs.php`). Anonymous and HTTP-Basic
  clients keep using the legacy endpoint.
- **No php-mapscript**: the two `mapObj` uses in the legacy controller (QGS-path lookup
  and WMS-source lookup) are replaced by direct reads of
  `settings.geometry_columns_join.wmssource` — the mapfile `CONNECTION` for such layers
  is written verbatim from that column (see `Mapfile.php` `$row['wmssource']`), so the
  DB value is authoritative.

## 2. Route, auth, and authorization

```php
#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/ows/schema/{schema}', scope: Scope::SUB_USER_ALLOWED)]
final class Ows extends AbstractApi
```

- GET serves WMS/WFS/UTFGRID query-string requests; POST serves WFS XML requests.
  PUT/PATCH/DELETE are stubbed to satisfy `ApiInterface` (rejected upstream by
  `AcceptableMethods`), mirroring the Wfs controller.
- Identity: `uid`/`database`/`userGroup` from `$this->route->jwt['data']`. Requests
  without a valid JWT never reach the controller (Route2 rejects non-PUBLIC routes).
  A note in the OpenAPI description points anonymous/basic clients to the legacy
  endpoint.
- **Per-layer authorization** with the JWT identity, matching the legacy semantics of
  `Controller::basicHttpAuthLayer()`: the layer's `authentication` level and, for
  sub-users, the layer privileges decide access. The check is skipped when the client
  IP is inside `App::$param['trustedAddresses']` (same rule as legacy; the
  `getenv('MODE_ENV') !== 'test'` guard from the Wfs controller is copied so tests can
  exercise auth).
- **Geofence rules**: the `setFilterFromRules()` logic is ported as-is — `UserFilter`
  with request `"ows"`/`"select"`, `Geofence::authorize()` per layer; `deny` throws,
  `limit` adds the rule filter; layers with a `gc2_version_gid` column get
  `gc2_version_end_date IS NULL` appended. All model objects are constructed with the
  request's `Connection` (no statics).

## 3. Architecture and file layout

The legacy controller is left untouched; the v4 path gets its own module in the style
of `app/wfs/`:

```
app/
├─ ows/                                (new namespace app\ows)
│  ├─ Context.php        ← request-scoped: Connection, database, schema, user,
│  │                       userGroup, trusted, host
│  ├─ Request.php        ← immutable DTO + fromHttp() parser (single parse point)
│  ├─ SourceResolver.php ← DB lookups replacing mapObj
│  ├─ MapfilePatcher.php ← tmp-file creation + filter/label patching in PHP
│  └─ Proxy.php          ← backend URL construction + streaming curl execution
└─ api/v4/controllers/
   └─ Ows.php            ← controller; returns StreamedResponse
```

### 3.1 `Context`

Same shape as `app\wfs\Context` (constructor-injected, readonly): `connection`,
`database`, `schema`, `user`, `userGroup`, `parentUser`, `trusted`, `host`. Built by
`Ows::buildContext()` from the JWT exactly like `Wfs::buildContext()`.

### 3.2 `Request` (immutable DTO)

```php
final readonly class Request
{
    public function __construct(
        public string  $method,        // 'GET' | 'POST'
        public string  $service,       // 'wms' | 'wfs' | 'utfgrid'
        public array   $layers,        // namespace-stripped layer names (schema.table)
        public array   $filters,       // decoded+parenthesized from the filters param
        public bool    $disableLabels, // labels=false
        public string  $queryString,   // original query string (for backend passthrough)
        public array   $query,         // parsed query, keys upper-cased
        public ?string $rawPostBody,   // WFS XML for POST
    ) {}

    public static function fromHttp(): self;
}
```

`fromHttp()` consolidates the parsing currently spread through the legacy constructor
and `get()`:

- Service detection: `SERVICE` param (case-insensitive); `FORMAT=json|mvt` ⇒
  `utfgrid`; POST XML root `service` attribute for WFS.
- Layer names from `LAYERS`/`LAYER`/`TYPENAME`/`TYPENAMES` (GET) or the `wfs:Query`
  `typeName`/`typeNames` attributes (POST XML via `SimpleXMLElement`). Namespace
  prefixes (`ns:layer`) are stripped once, here — handlers never re-strip.
- `filters` param: base64url-decoded JSON, each filter wrapped in parentheses
  (`array_map(fn($i) => "($i)", ...)`), same as legacy.
- POST with zero parsed layers throws (legacy: "Could not get the typeName from the
  requests").
- All input is read through `Input::` helpers — no direct superglobal access.

### 3.3 `SourceResolver` (replaces mapObj)

Reads `wmssource` from `settings.geometry_columns_join` for the request's layers
(single query, keyed by `_key_` prefix match on `schema.table.%`):

- `qgsFilePath(): ?string` — if any requested layer's `wmssource` contains a
  `map=<path>.qgs` query parameter, return that path (first hit), else null.
  Mirrors `getQGSFilePath()`.
- `wmsSource(): ?array` — only when exactly one layer is requested: if that layer's
  `wmssource` starts with `http`, return `parse_url()` parts with the query keys
  upper-cased. Mirrors `getWmsSource()` including the single-layer restriction.

No mapfile is opened; php-mapscript is not loaded.

### 3.4 `MapfilePatcher` (replaces shell sed)

Pure string manipulation on the mapfile/QGS content — read file, patch in PHP, write a
tmp copy. **No `shell_exec`** (this also removes the legacy shell-injection surface
where filter text was interpolated into `sed` commands).

- `patchMapfile(string $path, array $filters, bool $disableLabels, array $layers): string`
  - Filter: replace the `/*FILTER_<schema>.<table>*/` marker with
    `WHERE <combined filter>` per layer (legacy sed `s;/\*FILTER_x.y\*/;WHERE ...;g`).
  - Labels: remove every block from `#START_LABEL<n>_<schema>.<table>` to
    `#END_LABEL<n>_<schema>.<table>` for each layer (regex with `[0-9]*`, covering old
    LABEL1/LABEL2 markers), matching the legacy sed range delete.
- `patchQgs(string $path, array $filters, bool $disableLabels, array $layers, Model $model): string`
  - Per layer: build the `WHERE` (filters + `gc2_version_end_date IS NULL` when the
    versioning column exists), XML-escape it with the legacy `xmlEscape()` mapping,
    and replace `sql=…<` on the `table="schema"."table"` datasource lines.
  - Labels: `labelsEnabled="1"` → `labelsEnabled="0"`.
- Tmp files are written to `App::$param['path'] . 'app/tmp/'` with unique names and the
  patcher returns the path; the caller deletes them in `finally` after the proxy
  completes (**fixes the legacy tmp-file leak** for the v4 path).
- The legacy rule "filters/rules + multiple layers + QGS backend ⇒ error" is preserved
  verbatim.

### 3.5 `Proxy`

Builds the backend URL with the same decision matrix as legacy `get()`/`post()`:

| Situation | Backend URL |
|---|---|
| Filters/labels + QGS (single layer) | `http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=<tmp .qgs>&<queryString>` |
| Filters/labels + MapServer | `http://127.0.0.1/cgi-bin/mapserv.fcgi?map=<tmp .map>&<queryString>` |
| No filters + QGS (service ≠ utfgrid) | `qgis_mapserv.fcgi?map=<qgs>&<queryString>` |
| No filters + external WMS source (1 layer) | source URL with merged query (see below) |
| Otherwise | `mapserv.fcgi?map=<static mapfile>&<queryString>` |
| POST (WFS XML) | `mapserv.fcgi?map=<static or tmp mapfile>`, body forwarded |

- Mapfile name: `<db>_<schema>_wfs.map` for `wfs`/`utfgrid`, else `<db>_<schema>_wms.map`
  (identical `match` to legacy).
- WMS-source passthrough: merge request query over the source query; `BBOX`, `WIDTH`,
  `HEIGHT` always from the request; `SRS`/`CRS` chosen by the source's WMS `VERSION`;
  `REQUEST=GetMap` forced; credentials from the source URL — byte-identical logic to
  legacy lines 308–334.
- **Streaming execution**: curl with `CURLOPT_WRITEFUNCTION` (echo + flush per chunk —
  GetMap images are never buffered whole) and `CURLOPT_HEADERFUNCTION` forwarding
  upstream headers with the legacy filtering (rewrite
  `application/vnd.ogc.se_xml`/`text/xml; charset=UTF-8` to `text/xml`, drop
  `Content-Encoding` and chunked markers). `X-Powered-By: GC2 WMS` and
  `Cache-Control: no-store` are emitted for parity. No `CURLOPT_RETURNTRANSFER`, no
  `exit()`.

### 3.6 Controller

```php
public function get_index(): StreamedResponse { return $this->stream(); }
public function post_index(): StreamedResponse { return $this->stream(); }

private function stream(): StreamedResponse
{
    $ctx = $this->buildContext();          // JWT → Context (as Wfs)
    $req = OwsRequest::fromHttp();
    $this->authorizeLayers($ctx, $req);    // per-layer auth (skipped when trusted)
    $filters = $this->applyRules($ctx, $req); // geofence + versioning + client filters
    return new StreamedResponse(
        contentType: 'application/octet-stream', // placeholder; proxy overrides from upstream
        callback: function () use ($ctx, $req, $filters) {
            Util::disableOb();
            $tmp = null;
            try {
                [$url, $tmp] = new Proxy($ctx)->resolve($req, $filters);
                new Proxy($ctx)->run($url, $req);
            } catch (Throwable $e) {
                ServiceExceptionReport::render($e); // only if nothing sent yet
            } finally {
                if ($tmp) @unlink($tmp);
            }
        },
    );
}
```

(Exact decomposition of `resolve`/`run` is an implementation detail; the contract is:
URL + optional tmp file resolved first, streaming after, tmp deleted in `finally`.)

## 4. Worker-safety checklist (deltas from legacy)

| Legacy behavior | v4 behavior |
|---|---|
| `exit()` after echo | `StreamedResponse` callback returns normally |
| `header()`/`echo` throughout | Only inside the streamed callback |
| `$_GET`, `$_SERVER[...]` direct reads | `Request::fromHttp()` via `Input::` helpers, single parse point |
| `Session::start()` + session user | JWT identity only |
| `Database::setDb()` / `Connection::$param` statics | Per-request `Connection` in `Context` |
| `shell_exec('sed ...')` with interpolated filter text | PHP string/regex patching (no shell, no injection surface) |
| Tmp mapfiles leaked in `app/tmp/` | Deleted in `finally` |
| Whole response buffered (`RETURNTRANSFER`) | Chunk-streamed (`WRITEFUNCTION` + flush) |
| `mapObj` (php-mapscript) | `SourceResolver` DB lookups |

No `static` properties anywhere in `app/ows/`; every object is per-request.

## 5. Rate limiting

`public/index.php` applies `RateLimiter::consumeForJwt(...)` (default 120/min) to every
`api/v4` request before routing. Tile/GetMap clients exceed that trivially. Change:
when the path starts with `api/v4/ows`, consume against a separate configurable limit
`App::$param['apiV4']['owsRateLimitPerMinute']` (default **1200**) instead of the
standard one. This is a small guarded branch at the existing call site; all other v4
routes keep the current behavior.

## 6. Error handling

- Errors thrown **before streaming starts** (auth, unknown layer, rule deny, QGS+filters
  +multi-layer, unparsable POST) render an OGC ServiceException XML report
  (`<ServiceExceptionReport>`, HTTP 200 with `text/xml` — WMS clients expect this),
  reusing/porting the message shapes of the legacy `ServiceException` path.
- Errors after the first proxied byte cannot change the response; they are logged via
  `error_log` and the stream ends.
- `Model::rollbackAllOpenTransactions()` in Route2's `finally` remains the safety net
  (the OWS path opens no transactions itself).

## 7. Testing

Run inside `docker-gc2core-1` (Codeception):

- **Unit** (`app/tests/unit/ows/`):
  - `RequestTest`: service detection (WMS/WFS/UTFGRID incl. `FORMAT=json|mvt`), layer
    parsing from GET params and POST XML (with and without namespace prefixes),
    filters base64url-decode + parenthesization, POST-without-layers error.
  - `SourceResolverTest`: QGS path extraction and WMS-source parsing from `wmssource`
    fixture values; single-layer restriction for WMS source.
  - `MapfilePatcherTest`: filter marker replacement, numbered-label block removal, QGS
    `sql=`/`labelsEnabled` patching — pure string tests against fixture files, no DB.
- **API** (`app/tests/api/OwsApiCest.php`): self-contained user/token/schema/table
  setup (LayerApiCest pattern) plus:
  - GetCapabilities and GetMap against `api/v4/ows/schema/{schema}` with Bearer token —
    response **parity-compared with legacy** `/ows/{db}/{schema}` for the same request:
    status and Content-Type always; XML bodies byte-for-byte; image bodies
    byte-for-byte first, falling back to size/format assertions if MapServer output
    proves non-deterministic across runs.
  - WFS GetFeature via GET and via POST XML through the proxy, parity-compared.
  - `filters` and `labels=false` requests succeed and differ from the unfiltered
    response.
  - Error cases: missing token (400/401 from Route2), unknown layer, geofence deny.
  - Verify no `app/tmp/*.map`/`*.qgs` files remain after filtered requests.
- **Manual**: QGIS/browser against the FrankenPHP container (port 8080/8081) with a
  token; confirm streamed GetMap (chunked transfer), and legacy endpoint unaffected.

## 8. Out of scope

- Refactoring `app/controllers/Wms.php` onto the new module (legacy stays byte-for-byte
  as is; can be revisited later like the WFS legacy adapter was).
- Caching/tile-cache integration changes.
- New protocol features beyond legacy parity.
