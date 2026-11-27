# Symbols endpoint (v4) — design

Date: 2026-11-27. Branch: `feat/symbols-v4`. Size: a small change.

## Intent

GC2 App needs the names of the MapServer symbols a style's `symbol` field can
take, with enough geometry to draw a small preview of each, for the Symbol
field in Map > Style. Today the 14 symbols exist only as a hard-coded text
block in `Mapfile::renderSymbols()`; nothing exposes them, and the two hand-kept
copies of the list (the old admin GUI and the OpenAPI prose on `Style.symbol`)
have both drifted: they lack `star` and `continue`.

## Goals

- One read-only v4 endpoint that lists the built-in symbols with type and points.
- One source of truth: the mapfile block and the endpoint are generated from the
  same definitions, so the list cannot drift from what MapServer is given.
- Visible to the SDK and MCP generators (OpenAPI annotated).

## Non-goals

- Validating `Style.symbol` against the list. It stays a free string: the
  mapfile renderer also accepts an `http…` image URL and a `[column]` binding.
- Custom or per-database symbols, SYMBOLSET files, image symbols.
- The SDK method and the app UI (other repositories).
- Changing the old admin GUI's hard-coded list.

## Anti-goal

The symbols MapServer receives must not change: same 14 names, same order,
same type, filled flag, points and anchor point as today.

## Approaches

1. **One definition, two renderers (chosen).** The definitions live in one PHP
   constant; the mapfile text and the JSON are both produced from it. Cannot
   drift. The generated mapfile text differs from today's in spacing and
   keyword case only.
2. A second list next to the existing mapfile text. No change to mapfile
   output, but it is exactly the duplication that already lost `star` and
   `continue` twice. Rejected.
3. Parse the existing heredoc at request time. Keeps the text byte-identical
   but adds a mapfile parser for a static list. Rejected.

Chosen by the user: "an endpoint in the backend, with drawings"; design
approved 2026-11-27.

## Design

**Source.** `app\inc\MapSymbols` (new, stateless, no connection):

- `MapSymbols::all(): array` — the 14 definitions in mapfile order.
- `MapSymbols::render(): string` — the MapServer `SYMBOL … END` blocks.
- `Mapfile::renderSymbols()` returns `MapSymbols::render()`.

Order: triangle, circle, square, star, hatch1, dashed1, arrow, arrow2,
continue, dashed-line-short, dashed-line-long, dash-dot, dash-dot-dot, dot-dot.

**Route.** `api/v4/symbols/[name]`, `Scope::SUB_USER_ALLOWED`, methods `GET`
and `OPTIONS` (inherited stub); `HEAD` is not offered. Other verbs are refused
with `406 NOT_ACCEPTABLE`, which is what the v4 framework answers for a verb
outside `#[AcceptableMethods]` (same as `meta-config`; deviation from the
first draft, which said 405). No
database access; the threaded `Connection` is passed to the parent only.

**Responses.**

- `GET /api/v4/symbols` → `200`, bare JSON array of all symbols, mapfile order.
- `GET /api/v4/symbols/arrow` → `200`, one bare object.
- `GET /api/v4/symbols/arrow,star` → `200`, array, in the order requested.
- Name segment must match `^[A-Za-z0-9_\-]+(,[A-Za-z0-9_\-]+)*$`, else
  `400 INVALID_REQUEST`. Any unknown name → `404 SYMBOL_NOT_FOUND` (nothing
  partial).

**Resource** (`#[OA\Schema(schema: 'Symbol')]`):

| Field | Type | Notes |
|---|---|---|
| `name` | string | Exactly the value `Style.symbol` takes. |
| `type` | `"vector"` \| `"ellipse"` | Lowercase. |
| `filled` | boolean | `false` only for `hatch1`. |
| `points` | array of `[x, y]` numbers | Mapfile order. `dash-dot` is `[[20,6],[2,6]]`, `hatch1` `[[0,1],[1,0]]`. |
| `anchorpoint` | `[x, y]` | Only on `arrow` and `arrow2` (`[0, 0.5]`); the key is absent otherwise. |

Example: `{"name":"arrow","type":"vector","filled":true,"points":[[0,0.4],[3,0.4],[3,0],[5,0.8],[3,1.6],[3,1.2],[0,1.2],[0,0.4]],"anchorpoint":[0,0.5]}`

**OpenAPI.** `operationId: getSymbol`, tag `Symbols`, response
`oneOf: [Symbol, array of Symbol]`. The `Style.symbol` description is corrected:
all 14 names, pointing at this endpoint, mentioning URL and `[column]` values,
and without the claim about a SYMBOLSET (the mapfile has none).

**Tests.** Unit: the 14 names in order, each definition's shape, and that the
rendered block defines the same names/types/points (including `ANCHORPOINT`).
Api Cest: own super-user and sub-user; bare array of 14; single; list; 404;
400; sub-user allowed; no token refused (400); preflight; POST is 406.

Verified 2026-11-27: MapServer in the dev container (`/opt/bin/map2img`) renders
a test map using all 14 generated symbols without error.

## Notes for consumers

- Preview caveat: `dashed1`, `continue`, `dash-dot-dot` and `dot-dot` are the
  same ellipse as `circle`; the dash look of line symbols comes from the
  style's `pattern`/`gap`, not the symbol.
- An older server answers an unknown v4 route with a non-200 (400 without a
  token); clients treat any non-200 as "no list".
- Downstream to tell when merged: GC2 App (consumer), SDK and MCP server (new
  `getSymbol` operation), website docs.
