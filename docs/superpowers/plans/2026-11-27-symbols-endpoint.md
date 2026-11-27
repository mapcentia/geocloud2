# Symbols Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Serve the built-in MapServer symbols (name, type, points) at `GET /api/v4/symbols/[name]`, from the same definitions the mapfile is generated from.

**Architecture:** A stateless `app\inc\MapSymbols` holds the 14 definitions as a constant and renders them as mapfile text; `Mapfile::renderSymbols()` delegates to it. A new v4 controller `Symbol` returns the same definitions as JSON.

**Tech Stack:** PHP 8.4, the v4 `AbstractApi`/`#[Controller]` framework, swagger-php attributes, Codeception (unit + api).

**Spec:** `docs/superpowers/specs/2026-11-27-symbols-endpoint-design.md`

## Global Constraints

- The symbols MapServer receives must not change: same 14 names, order, type, filled flag, points, anchor point.
- Order: triangle, circle, square, star, hatch1, dashed1, arrow, arrow2, continue, dashed-line-short, dashed-line-long, dash-dot, dash-dot-dot, dot-dot.
- Lists are bare JSON arrays; `type` is lowercase `vector`/`ellipse`; `filled` is a real boolean; `anchorpoint` key is absent when the symbol has none.
- No database access, no statics holding request state, no `Database::setDb()`.
- Work on branch `feat/symbols-v4` in the main checkout. Do not commit unless the user asks; never push.
- Tests run in the dev container: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run <suite> <Name>`.

## Review Focus

- A real mapfile still parses: after the refactor, regenerate one schema mapfile and render a map with a `circle` and an `arrow` style (manual check, Task 1 step 5).
- Number formatting in the rendered block: floats must not be emitted in exponent or locale form (`0.375`, not `3.75E-1` or `0,375`) — pinned by the unit test's exact-line assertions in Task 1.
- JSON number types: `0` and `1` stay integers, `0.375` a float; `filled` is `true`/`false`, not `1`/`0` — pinned in the Cest (Task 2).
- A name list with a duplicate (`/symbols/star,star`) returns two entries and does not 500 — Cest, Task 2.
- A trailing comma or empty segment (`/symbols/star,`) is `400 INVALID_REQUEST`, not a 404 for the name `""` — Cest, Task 2.

---

### Task 1: `MapSymbols` as the single source

**Files:**
- Create: `app/inc/MapSymbols.php`
- Modify: `app/models/Mapfile.php:239-405` (`renderSymbols()` body)
- Test: `app/tests/unit/MapSymbolsTest.php`

**Interfaces:**
- Produces: `app\inc\MapSymbols::all(): array` — list of `['name' => string, 'type' => 'vector'|'ellipse', 'filled' => bool, 'points' => list<array{0: int|float, 1: int|float}>, 'anchorpoint' => array{0: int|float, 1: int|float}]` with `anchorpoint` present only on `arrow` and `arrow2`.
- Produces: `app\inc\MapSymbols::render(): string` — one `SYMBOL` block per definition.

- [x] **Step 1: Write the failing unit test** `MapSymbolsTest` with:
  - `testNamesInMapfileOrder`: `array_column(MapSymbols::all(), 'name')` equals the 14 names in the Global Constraints order.
  - `testDefinitionShape`: every entry has `type` in `['vector','ellipse']`, `filled` is bool, every point is a 2-element list of int|float; only `arrow` and `arrow2` have `anchorpoint`, both `[0, 0.5]`; `hatch1` is the only `filled === false`.
  - `testExactGeometry`: `star` points equal `[[0,.375],[.35,.375],[.5,0],[.65,.375],[1,.375],[.75,.625],[.875,1],[.5,.75],[.125,1],[.25,.625]]`; `dash-dot` equals `[[20,6],[2,6]]`; `hatch1` equals `[[0,1],[1,0]]`; `arrow` equals `[[0,0.4],[3,0.4],[3,0],[5,0.8],[3,1.6],[3,1.2],[0,1.2],[0,0.4]]`; `circle`, `dashed1`, `continue`, `dash-dot-dot`, `dot-dot` equal `[[1,1]]`; `dashed-line-short` `[[10,1]]`; `dashed-line-long` `[[10,10]]`; `triangle` `[[0,1],[.5,0],[1,1],[0,1]]`; `square` `[[0,1],[0,0],[1,0],[1,1],[0,1]]`; `arrow2` `[[0,0.8],[1,0.4],[0,0],[0,0.8]]`.
  - `testRenderMatchesDefinitions`: `MapSymbols::render()` contains exactly 14 occurrences of `SYMBOL\n`; contains `NAME "star"\nTYPE VECTOR\nFILLED TRUE\nPOINTS\n0 0.375\n0.35 0.375\n`; contains `NAME "hatch1"\nTYPE VECTOR\nFILLED FALSE\nPOINTS\n0 1\n1 0\nEND\nEND\n`; contains `ANCHORPOINT 0 0.5\n` exactly twice; contains `NAME "dash-dot"\nTYPE ELLIPSE\nFILLED TRUE\nPOINTS\n20 6\n2 6\nEND\nEND\n`.
  - `testMapfileDelegates`: `(new ReflectionMethod(\app\models\Mapfile::class, 'renderSymbols'))` invoked on an instance created with `newInstanceWithoutConstructor()` returns `MapSymbols::render()`.
- [x] **Step 2: Run** `codecept run unit MapSymbolsTest` — expected: FAIL, class `app\inc\MapSymbols` not found.
- [x] **Step 3: Implement `MapSymbols`** as a `final class` with `private const SYMBOLS` (values from `Mapfile.php:246-403`), `all()` returning it, and `render()` emitting per symbol: `SYMBOL\nNAME "<name>"\nTYPE <UPPER type>\nFILLED <TRUE|FALSE>\nPOINTS\n<x y per line>\nEND\n[ANCHORPOINT x y\n]END\n\n`. Format numbers with a plain cast to string after `json_encode`-style normalisation so `0.375` renders as `0.375` and `1` as `1` (no locale, no exponent).
- [x] **Step 4: Replace the heredoc in `Mapfile::renderSymbols()`** with `return MapSymbols::render();`. Run `codecept run unit MapSymbolsTest` and `codecept run unit MapfileRenderTest` — expected: PASS.
- [x] **Step 5: Manual mapfile check.** Regenerate a schema mapfile in the dev container (see memory note "Regenerate mapfiles from CLI"), then `docker exec docker-dev-1 map2img -m <mapfile> -o /tmp/s.png` (or a WMS GetMap on `localhost:8080`) — expected: no MapServer symbol/parse error.

### Task 2: `Symbol` controller, OpenAPI, Cest

**Files:**
- Create: `app/api/v4/controllers/Symbol.php`
- Modify: `app/api/v4/controllers/Style.php:39` (the `symbol` property description)
- Test: `app/tests/api/SymbolV4ApiCest.php`

**Interfaces:**
- Consumes: `MapSymbols::all()` from Task 1.
- Produces: `GET /api/v4/symbols/[name]` as specified in the spec.

- [x] **Step 1: Write the Cest** `SymbolV4ApiCest`, modelled on `app/tests/api/MetaConfigV4ApiCest.php` (own super-user + sub-user, tokens via `/api/v4/oauth` password grant, `client_id: gc2-cli`; same cleanup as that Cest). Tests, in order:
  - `shouldPrepareUsersAndTokens`
  - `shouldListAllSymbolsAsABareArray`: 200; decoded body is a list of 14; names in the Global Constraints order; raw body contains `"filled":true` and `"filled":false`; `arrow` has `anchorpoint` `[0,0.5]`; `circle` has no `anchorpoint` key.
  - `shouldReturnOneSymbolAsAnObject`: `/api/v4/symbols/star` → 200, object with `name` `star`, `type` `vector`, 10 points.
  - `shouldReturnAListInRequestedOrder`: `/api/v4/symbols/star,arrow` → array, names `['star','arrow']`; `/api/v4/symbols/star,star` → array of 2.
  - `shouldReturn404ForAnUnknownName`: `/api/v4/symbols/star,nope` → 404, `errorCode` `SYMBOL_NOT_FOUND`.
  - `shouldReturn400ForAMalformedName`: `/api/v4/symbols/star,` and `/api/v4/symbols/st%20ar` → 400, `errorCode` `INVALID_REQUEST`.
  - `shouldAllowASubUser`: sub-user token → 200, 14 entries.
  - `shouldRejectAMissingToken`: no Authorization header → not 200.
  - `shouldRejectPost`: `POST /api/v4/symbols` → 406 `NOT_ACCEPTABLE` (framework behaviour, as `meta-config`).
  - cleanup test last.
- [x] **Step 2: Run** `codecept run api SymbolV4ApiCest` — expected: FAIL on the first GET (no such route).
- [x] **Step 3: Implement `Symbol`** following `MetaConfig.php`: `#[AcceptableMethods(['GET', 'OPTIONS'])]`, `#[Controller(route: 'api/v4/symbols/[name]', scope: Scope::SUB_USER_ALLOWED)]`, constructor `(public readonly Route2 $route, Connection $connection)` with `$this->resource = 'symbols'`. `get_index()`: no `name` param → `getResponse(MapSymbols::all())`; otherwise split on `,`, look each up by name, throw `GC2Exception("Symbol not found: <name>", 404, null, 'SYMBOL_NOT_FOUND')` on a miss, return `getResponse($rows, single: count($rows) === 1)`. `validate()`: when `name` is present and does not match `^[A-Za-z0-9_\-]+(,[A-Za-z0-9_\-]+)*$` throw `GC2Exception(..., 400, null, 'INVALID_REQUEST')`. Empty stubs for post/put/patch/delete as in `MetaConfig`.
- [x] **Step 4: Annotate.** `#[OA\Schema(schema: 'Symbol', required: ['name','type','filled','points'], …)]` with the field table from the spec; `#[OA\Get(path: '/api/v4/symbols/{name}', operationId: 'getSymbol', tags: ['Symbols'], …)]` with optional path parameter `name` (comma separated list), 200 `oneOf: [ref Symbol, array of ref Symbol]`, 400, 404. Rewrite the `Style.symbol` description: the 14 names, "listed with geometry by GET /api/v4/symbols", an `http(s)` image URL or a `[column]` reference also accepted; drop the SYMBOLSET sentence.
- [x] **Step 5: Run** `codecept run api SymbolV4ApiCest`, then `codecept run api MetaConfigV4ApiCest` and `codecept run unit MapSymbolsTest` — expected: PASS. Fetch the OpenAPI document from the dev container and confirm it contains `getSymbol` and the `Symbol` schema.
- [x] **Step 6: Tell the consumer.** Message the gc2-app session that the endpoint runs on `http://localhost:8080`, with the final shape.
