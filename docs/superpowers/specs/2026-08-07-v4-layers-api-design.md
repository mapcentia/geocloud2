# v4 Layers API with classes/styles/labels sub-resources

**Date:** 2026-08-07
**Branch:** dev/multiple_styles
**Status:** Approved design

## Problem

Layer configuration (the *Settings* property grid in the admin GUI) and class styling can only be managed through the legacy session-based controllers (`/controllers/tile`, `/controllers/classification`). There is no v4 REST surface for provisioning a layer's properties and its classes — including the new `styles[]`/`labels[]` arrays introduced by the dynamic symbols/labels feature. Styles and labels are currently only addressable by array position, which shifts when entries are deleted.

## Decisions made

- **Routes:** `api/v4/layers/{layer}` with sub-resources `classes/{class}`, `classes/{class}/styles/{style}` and `classes/{class}/labels/{label}`, following the existing Schema → Table → Column controller pattern.
- **Layer identifier:** the full `_key_` — `schema.table.geom_column` (e.g. `my_schema.my_table.the_geom`).
- **POST `/api/v4/layers/` configures an existing layer** (the row in `settings.geometry_columns_join` is auto-created with the table); it never creates tables. 404 when the layer row does not exist.
- **Layer properties = the whole `def` JSON** (all 23 keys of the Tile model schema), not just the GUI's Settings-tab subset.
- **Fixed ids:** server-generated 8-char hex ids on class, style and label objects; assigned lazily and persisted on first v4 access.
- **Storage is unchanged:** classes stay as JSON in the `class` column, properties stay in the `def` column. Only the API surface is new.

## 1. Resources and routes

Four new v4 controllers in `app/api/v4/controllers/`:

| Controller | Route | Methods |
|---|---|---|
| `Layer` | `api/v4/layers/[layer]` | GET, POST, PATCH |
| `LayerClass` | `api/v4/layers/{layer}/classes/[class]` | GET, POST, PATCH, DELETE |
| `Style` | `api/v4/layers/{layer}/classes/{class}/styles/[style]` | GET, POST, PATCH, DELETE |
| `Label` | `api/v4/layers/{layer}/classes/{class}/labels/[label]` | GET, POST, PATCH, DELETE |

- No DELETE on the layer itself — its existence follows the table/geometry column.
- Scope: `Scope::SUB_USER_ALLOWED`. Authorization follows the Table API: the controller splits the layer key on `.`, and `initiate(schema: ...)` enforces schema-level access (sub-users reach only their own schema/public) and 404s on missing schema/table.
- Existence checks beyond `initiate`: the layer row must exist in `settings.geometry_columns_join` (404 `LAYER_NOT_FOUND`), and addressed class/style/label ids must exist (404 `CLASS_NOT_FOUND` / `STYLE_NOT_FOUND` / `LABEL_NOT_FOUND`).
- Comma-separated id lists are supported where the other v4 APIs support them (GET, DELETE). PATCH addresses a single resource.

## 2. Resource shapes

### Layer

```json
{
  "name": "my_schema.my_table.the_geom",
  "properties": {
    "theme_column": "", "label_column": "", "opacity": "", "label_max_scale": "",
    "label_min_scale": "", "cluster": "", "meta_tiles": "", "meta_size": "",
    "meta_buffer": "", "ttl": "", "auto_expire": "", "maxscaledenom": "",
    "minscaledenom": "", "symbolscaledenom": "", "geotype": "", "offsite": "",
    "format": "", "lock": false, "layers": "", "bands": "", "cache": "",
    "s3_tile_set": "", "label_no_clip": false, "polyline_no_clip": false
  },
  "classes": [ { "...": "see Class" } ]
}
```

- `properties` mirrors the `def` JSON exactly — the schema (key list) lives in the `Tile` model and is reused, not duplicated.
- `null` values are converted to `""` on output (existing convention).

### Class

```json
{
  "id": "a1b2c3d4",
  "sortid": 10,
  "name": "My class",
  "expression": "[type]='road'",
  "class_minscaledenom": "", "class_maxscaledenom": "",
  "leader": false, "leader_gridstep": "", "leader_maxdistance": "", "leader_color": "",
  "styles": [
    { "id": "e5f6a7b8", "sortid": 10, "name": "Fill", "color": "#008000", "...": "STYLE_KEYS" }
  ],
  "labels": [
    { "id": "c9d0e1f2", "sortid": 10, "name": "Road name", "on": true, "text": "[name]", "...": "LABEL_KEYS" }
  ]
}
```

Style/label property vocabularies are `Classification::STYLE_KEYS` / `Classification::LABEL_KEYS` plus `id`, `sortid`, `name` (and `on` for labels). Classes are always returned in the normalized (new) format: legacy flat classes pass through `Classification::normalizeClass()` before serving.

## 3. Fixed ids

- Format: 8 hex chars from `bin2hex(random_bytes(4))`.
- New helper `Classification::ensureIds(array $classes): array` — static, idempotent. Walks classes and their `styles`/`labels`, adds an `id` where missing, never touches existing ids. Input is normalized (`normalizeClass`) first.
- **Lazy persistence:** every v4 read or write runs `ensureIds`; if any id was added during a GET, the updated class JSON is written back with one UPDATE so ids are stable from the first API access onward.
- **GUI compatibility:** the admin editor round-trips unknown keys (property-grid backing arrays keep untouched properties; `Classification::update()` key-merges), so ids survive GUI edits. Classes created by the wizard or the GUI without ids get them on the next v4 access. `id` is never rendered into the mapfile.
- Ids are unique within their scope (classes within a layer; styles/labels within a class). On POST/`ensureIds`, regenerate on the (unlikely) collision.
- Client-supplied `id` on POST is rejected by validation (400) — ids are server-assigned only.

## 4. Semantics

### Layer

- **GET `/api/v4/layers`**: all layers in the schemas the caller can access, full resources. `namesOnly=true` returns only the keys (mirrors the Schema controller).
- **GET `/api/v4/layers/{layer}`**: single full resource.
- **POST `/api/v4/layers/`**: body is a full resource (`name` required) or an array of them. Sets `properties` (key-merge into `def` via the Tile model) and, when `classes` is present, **replaces the class array wholesale** (declarative provisioning). Server assigns all ids. 201 + Location.
- **PATCH `/api/v4/layers/{layer}`**: body `{ "properties": { ... } }` — key-merge on `def` (the Tile model's existing update semantics). `classes` is not allowed in a layer PATCH; use the sub-resources.

### Classes

- **GET**: collection or by id.
- **POST**: create one class or an array of classes; nested `styles`/`labels` allowed and get ids assigned. New classes append to the array.
- **PATCH `{id}`**: key-merge on the class object. `styles`/`labels` are rejected in the PATCH body (400) — they change through their own routes.
- **DELETE `{id}`**: removes the class.

### Styles / Labels

- **GET**: collection (sorted by `sortid` for display parity is *not* applied — the raw array order is returned; `sortid` is data) or by id.
- **POST**: appends entries; server assigns `id`. When `sortid` is omitted it defaults to highest existing + 10 (same rule as the GUI's Add button).
- **PATCH `{id}`**: key-merge (e.g. `{"sortid": 5}` to reorder).
- **DELETE `{id}`**: removes the entry.

### Side effects

- Every mutating call (POST/PATCH/DELETE on any of the four resources) regenerates the database's WMS and WFS mapfiles via `\app\models\Mapfile::writeMapfile(generateWms()/generateWfs())` — the API equivalent of the GUI's `writeFiles()`. Tile-cache invalidation is **not** triggered automatically (matches GUI behavior, where cache clearing is a separate action).
- All array mutations on one request run against a single read-modify-write of the `class` JSON inside a transaction (`withTransaction`), consistent with the other v4 controllers.

## 5. Validation

Each controller implements `getAssert()` (Symfony constraints), following the Column pattern:

- **Layer POST:** `name` required (string, matching `schema.table.geom`); `properties` optional object whose keys must be in the Tile schema; `classes` optional array of class asserts. PATCH: `properties` only.
- **Class:** `name` required on POST, optional on PATCH; known base keys (`sortid` int, `expression` string, scale denominators, `leader*`); `styles`/`labels` arrays of the respective asserts (POST only); unknown keys rejected (400).
- **Style:** keys restricted to `STYLE_KEYS` + `sortid` (int) + `name` (string).
- **Label:** keys restricted to `LABEL_KEYS` + `sortid` (int) + `name` (string) + `on` (bool).
- `id` in any POST body → 400. POST to a resource id → 406 (`postWithResource`), PATCH/DELETE on a collection → 400/406, all per the existing conventions.
- OpenAPI attributes (`#[OA\...]`) on all endpoints, consistent with the other v4 controllers, so the swagger doc picks them up.

## 6. Model changes

- `app/models/Classification.php`: add `ensureIds()`; add id-based lookup/mutation helpers used by the new controllers (`getClassById`, and array-level insert/merge/delete that operate on the stored JSON and return the affected ids). Existing positional methods and the legacy controllers are untouched.
- `app/models/Tile.php`: expose the `def` key list (constant) so the Layer controller can validate `properties` without duplicating the schema. `update()` reused as-is.
- No database schema changes. No changes to how classes/def are stored.

## 7. Testing

Codeception inside `docker-gc2core-1`:

- **Unit:** `ensureIds` (idempotency, legacy input via `normalizeClass`, only-missing ids added, uniqueness); sortid default on append.
- **API suite (new Cest):** full CRUD walk for layers → classes → styles → labels: create layer config with nested classes/styles/labels, GET single/collection, PATCH properties and sortid reorder, DELETE entries, id stability across requests. Error cases: unknown layer key (404), unknown class/style/label id (404), client-supplied id (400), `classes` in layer PATCH (400), `styles` in class PATCH (400), unknown property key (400), POST to resource id (406), sub-user on foreign schema (403).
- Manual verification: provision a layer via the API, confirm the GUI shows the classes/styles/labels and the rendered WMS reflects them.
