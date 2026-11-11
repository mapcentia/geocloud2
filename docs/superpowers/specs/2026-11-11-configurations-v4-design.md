# v4 Configurations API — design

Date: 2026-11-11
Status: implemented on branch `feat/configurations-v4` (see Deviations)

## Intent

Port `/api/v2/configuration` to v4 so the new GC2 App (`/app`) can create and
edit Vidi configurations with its OAuth token, while Vidi keeps fetching
published configurations anonymously as `.json`.

The old Dashboard stays in service on v2. A configuration created in the GC2
App must show up and be editable in the Dashboard, and the other way round.

## Decisions made by the owner

- v4 reads and writes the **same storage, same shape** as v2. No migration.
- v2 is not changed.
- Writes (create, update, delete) are for the **database owner only**.
  Sub-users are rejected.
- Anonymous callers can fetch **one published configuration** and can **list
  the published ones**.
- Vidi's use is: fetch one published configuration as a raw `.json` file.
- v4 requires `body` to be valid JSON.

Everything under "Design" below that is not in this list is the author's call
and is marked *(call)*.

## Goals

- GC2 App can list, read, create, update and delete configurations through v4.
- Vidi can switch to a v4 URL by changing the URL only.
- Dashboard (v2) and GC2 App (v4) are interchangeable on the same rows.
- Endpoints follow the v4 rules in `AGENTS.md` §3–§4 and appear in OpenAPI.

## Non-goals

- Changing, deprecating or removing v2.
- Changing the Dashboard or Vidi. (Vidi only needs a new URL, set by its users.)
- Sub-user access to configuration management.
- An owner column or per-user ownership of configurations.
- Parsing, validating or restructuring the Vidi configuration inside `body`.
- Rate limiting of anonymous calls (same as the other token-less v4 routes).

## Anti-goals

- A configuration written by v4 that the Dashboard cannot list, open or save.
- An anonymous caller reading an unpublished configuration, or any
  `settings.key_value` row that is not a configuration.
- Reformatting `body` on a round trip.

## Current state (v2)

`app/api/v2/Configuration.php`, route `api/v2/configuration/[userId]/[configurationId]`,
session-based.

- Storage: `settings.key_value`, one row per configuration.
  - `key`: `configuration_<ascii name>_<uniqid>` — built as
    `'configuration_' . Keyvalue::toAscii($name, NULL, '_') . '_'` passed as the
    prefix to `uniqid($prefix, TRUE)`, with `.` removed from the result.
  - `value`: a JSON object `{name, published, description, body, key}`.
    `published` is a boolean, `body` is a **string** holding JSON text, `key`
    repeats the row key.
  - `owner` is NULL (legacy row in the v4 keyvalue model: readable by every
    user of the database through `/api/v4/keyvalue`).
- Anonymous read of a published configuration; `<key>.json` returns the raw
  `body` as an attachment.
- There is no stored owner; "owner" in v2 is whoever's name is in the URL.

## Design

### Two controllers *(call)*

Scope is per controller, so management and anonymous read are two classes that
share one model.

| Route | Scope | Verbs |
|---|---|---|
| `api/v4/configurations/[key]` | `SUPER_USER_ONLY` | GET, POST, PATCH, DELETE, HEAD, OPTIONS |
| `api/v4/configurations/database/{database}/[key]` | `PUBLIC` | GET, HEAD, OPTIONS |

The public route takes the database from the path, like the other token-less
v4 routes, and threads its own `Connection` for that database. A token sent to
the public route is ignored: it always behaves anonymously *(call)*.

### Resource

```json
{
  "key": "configuration_my_map_673211a0b1c2d3",
  "name": "My map",
  "description": "…",
  "published": true,
  "body": "{\"brandName\": \"…\"}"
}
```

- `body` stays a string *(call)*, as v2 stores it. v4 stores and returns it
  byte for byte. (The Dashboard itself re-serialises `body` with
  `JSON.stringify` on every save, so formatting only survives while a
  configuration is edited through v4.)
- `published` is a real boolean in and out.
- The row's `id`, `owner` and `public` columns are not exposed *(call)*.

### Management route (token, database owner)

- `GET /api/v4/configurations` — bare array of all configurations, published
  or not.
- `GET /api/v4/configurations/{key[,key…]}` — one object for one key, array for
  a list. Any missing key → `404 CONFIGURATION_NOT_FOUND`.
- `POST /api/v4/configurations` — one object or an array of objects. Required:
  `name` (non-empty string), `published` (bool), `description` (string, may be
  empty), `body` (string). `key` is not accepted; the server generates it
  as v2 does, then strips every character outside `[A-Za-z0-9_]` *(call)*.
  v2's `toAscii` passes an e-mail-shaped name through unchanged, which would
  put `@`, `+` or `-` in the key; v4 does not produce such keys. `201` with `Location: /api/v4/configurations/<key1,key2>`.
- `PATCH /api/v4/configurations/{key}` — any subset of the four fields; the
  stored JSON is read, merged and written back, `key` kept. A single key only
  *(call)*. `303` + `Location`. Renaming does not change the key (as in v2).
- `DELETE /api/v4/configurations/{key[,key…]}` — every key is checked to exist
  before any is deleted. `204`.
- Sub-user token → `403 SUPER_USER_ONLY` (from the scope).
- `body` must be a string holding **valid JSON** (owner's decision), on POST
  and on PATCH when present; otherwise `400 INVALID_REQUEST`. The decoded value
  must be an object or an array *(call)*, because the Dashboard runs an
  unguarded `JSON.parse` on open and treats the result as an object. An empty
  object or array is accepted *(call)*; the Dashboard can open it but will not
  save it until it has content. GC2 App therefore cannot store syntactically
  broken drafts through the API.

### Public route (no token)

- `GET /api/v4/configurations/database/{database}` — bare array of published
  configurations, same object shape.
- `GET /api/v4/configurations/database/{database}/{key}` — the object, if
  published.
- `GET /api/v4/configurations/database/{database}/{key}.json` — the raw `body`
  string as the response body, `Content-Type: application/json`,
  `Content-Disposition: attachment; filename=<key>.json`, matching v2. Sent
  through a response object with explicit headers, not `header()`/`echo`/`exit`.
- Unpublished and unknown keys both answer `404 CONFIGURATION_NOT_FOUND`
  *(call; v2 answers 403 for unpublished)*, so keys cannot be probed.
- Unknown database → `404` *(call)*, not a 500.
- A single key only on the public route; no comma lists *(call)*.
- CORS: an `options_index()` stub; no new request or response headers are
  needed beyond the global ones.

### Key handling

- A key must match `^configuration_[A-Za-z0-9_@+\-]+$` *(call)*, checked
  before it reaches SQL; otherwise `400 INVALID_REQUEST`. The pattern is wider
  than what v4 generates so that Dashboard-made rows with an e-mail-shaped
  name stay readable, patchable and deletable by key. Rows whose key falls
  outside even this pattern are left out of v4 lists. The `.json` suffix is stripped first
  and is only meaningful on the public route.
- Both routes only ever touch rows whose key starts with `configuration_` —
  in the list query and in single-key reads, updates and deletes. v2 does not
  enforce this on single-key reads; v4 must.
- A row whose value is not a JSON object with the expected fields is skipped
  in lists and is `404` on a direct read.

### Storage compatibility

- v4 writes rows the way v2 does: `value` = `{name, published, description,
  body, key}`, `owner` NULL, `public` left at its default *(call)*. This keeps
  a row indistinguishable from a Dashboard-made one.
- Model: a `Configuration` model (extends `Model`, constructor takes a
  `Connection`) owning the prefix, key generation, row filtering and the
  JSON merge. It may reuse `Keyvalue` internally or query `settings.key_value`
  directly with bound parameters — builder's choice.
- No schema change, nothing in `app/migration/Sql.php`.

### Known gap, deliberately left open

Because rows have no owner, sub-users can read unpublished configurations
through `/api/v4/keyvalue` today. Closing it would require v2 to write rows
differently, which is out of scope. Not made worse by this work.

## Worker safety

Per `AGENTS.md` §4: both controllers pass their `Connection` to the model, no
`Database::setDb()`, no statics, headers only on response objects.

## OpenAPI

- `#[OA\Schema(schema: 'Configuration')]` for the resource.
- Operations: `getConfiguration`, `postConfiguration`, `patchConfiguration`,
  `deleteConfiguration` on the management route; `getPublicConfiguration` on
  the public route *(names are a call)*.
- GET response and POST body documented as `oneOf: [Configuration, array of
  Configuration]`.

## Tests

- `ConfigurationV4ApiCest`, provisioning its own owner and one sub-user and
  cleaning up after itself:
  - CRUD as owner; list and comma-list shapes; POST of an array.
  - Sub-user gets 403 on every management verb.
  - POST and PATCH with a `body` that is not JSON, or is a JSON scalar, are 400
    and store nothing.
  - Anonymous: list shows only published; published single read; `.json`
    returns the raw body with the attachment headers; unpublished and unknown
    are 404; a non-`configuration_` key is rejected; unknown database is 404.
  - Interchange: a row created through v4 is returned by
    `GET /api/v2/configuration/{user}` and vice versa; a v4 PATCH leaves a
    v2-created `body` byte-identical.
- Unit test for key validation and the JSON merge if they end up as pure
  functions.

## Downstream

New endpoints, nothing existing changes. Tell: GC2 App (management API),
SDK and MCP generators (new operations), website docs, and Vidi users (new
URL form for the published `.json`).

## Left to the builder

- Whether the model wraps `Keyvalue` or issues its own SQL.
- How `.json` is detected given `Route2`'s segment matching.
- Which response class carries the raw `.json` body.
- Exact OpenAPI wording and examples.

## Deviations

Recorded during implementation (plan: `docs/superpowers/plans/2026-11-11-configurations-v4.md`).

- **No token on the management route is 400, not 401.** The v4 front
  controller rejects a missing token for every token route before a controller
  runs. Not changed here.
- **A verb the public route does not offer is 406 `NOT_ACCEPTABLE`, not 405.**
  That is what `Route2` answers for every v4 controller.
- **No `options_index()` stub was added.** `Route2` answers a real preflight
  (one carrying `Access-Control-Request-Method`) itself; `AbstractApi` already
  has the stub.
- **Missing or unknown fields are 400 `INPUT_VALIDATION_ERROR`**, the
  platform's code for `Assert\Collection` failures. `INVALID_REQUEST` is used
  for an invalid key and an invalid `body`.
- **The `database` path segment must match `^[A-Za-z0-9_\-]+$`**, otherwise
  `404 DATABASE_NOT_FOUND`.
- **Stored key order is not `{name, published, description, body, key}`.** The
  column is `jsonb`, which orders keys itself, for v2-written rows too.
- **Only rows with `owner IS NULL` are configurations.** The final review
  found that a sub-user could write a `configuration_*` row through
  `/api/v4/keyvalue` and have it served as a published configuration. v2 and
  the v4 configurations API never set an owner, the v4 keyvalue API always
  does, so the filter separates them. Such rows are still listed by v2. This
  overrides "the row's owner column is not used" under Storage compatibility.
- **`null` is rejected for every field, and the request body must be an
  object** (POST: or a non-empty list of objects; PATCH: one non-empty
  object), otherwise 400.
- **A database that exists but has no `settings.key_value`, or refuses
  connections, also answers `404 DATABASE_NOT_FOUND`.**
- **Keys are read with `rawurldecode`**, so a literal `+` in a Dashboard-made
  key addresses the row.
- **No Sphinx page.** `docs/pages` has no v4 API reference to add to.

### Known, not fixed

- Two concurrent PATCHes on one key can lose an update.
- A name longer than about 215 characters gives a key over `VARCHAR(256)` and a 500.
- A whitespace-only name is accepted.
- HEAD on the public route always answers 204.
- The OpenAPI PATCH body refers to `Configuration`, whose `required` lists all four fields.
