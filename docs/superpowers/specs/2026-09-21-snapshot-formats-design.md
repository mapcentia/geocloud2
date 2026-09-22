# Snapshot output formats (Parquet + FlatGeobuf, extensible) — design

- **Date:** 2026-09-21
- **Status:** approved in chat, spec for implementation
- **Builds on:** `2026-09-15-snapshot-api-design.md`, `2026-09-16-snapshot-storage-and-read-api-design.md` (incl. the STAC catalog section)

## Goal

A snapshot can be produced in one or more output formats. FlatGeobuf is added
next to (Geo)Parquet, and adding a further format later is one registry entry
plus tests: no other code knows the list of formats.

Decisions taken during brainstorming:

1. Formats are chosen **per request** (`formats` on `POST /api/v4/snapshots`),
   with a **server default** `snapshot.formats` in `App.php` (default
   `["parquet"]`). Scheduler jobs with `snapshot: true` use the server default.
2. The read API gets **`/snapshots/{date}/data/{format}`**; `/data` keeps
   serving the Parquet (backward compatible).
3. A format that cannot be produced for a relation (FlatGeobuf needs a geometry
   column) is **skipped with a recorded reason**; the snapshot fails only when
   no requested format could be produced.

## 1. Format registry — `app/inc/snapshot/SnapshotFormat.php`

A `final class SnapshotFormat` with a static registry keyed by format id. One
entry per format; everything else reads the registry.

| Field | parquet | flatgeobuf |
|---|---|---|
| `id` | `parquet` | `flatgeobuf` |
| `driver` (ogr2ogr `-f`) | `Parquet` | `FlatGeobuf` |
| `extension` | `parquet` | `fgb` |
| `mediaType` | `application/vnd.apache.parquet` | `application/flatgeobuf` |
| `requiresGeometry` | `false` | `true` |
| `stacAssetKey` | `data` | `flatgeobuf` |
| `stacTitle` | `GeoParquet` / `Parquet` (spatial or not) | `FlatGeobuf` |
| `ogrArgs` | `-mapFieldType Time=String,Binary=String` | `-mapFieldType Time=String,Binary=String` |

API:

```php
SnapshotFormat::ids(): array                       // ['parquet', 'flatgeobuf']
SnapshotFormat::get(string $id): SnapshotFormat    // InvalidArgumentException on unknown id
SnapshotFormat::has(string $id): bool
SnapshotFormat::fromExtension(string $file): ?SnapshotFormat   // by file name, for old rows
SnapshotFormat::defaults(): array                  // App::$param['snapshot']['formats'] ?? ['parquet'], validated
$format->fileName(string $uuid): string            // "data-<uuid>.<extension>"
```

`defaults()` throws `RuntimeException` on an unknown id in `App.php` so a
misconfiguration is loud at the first request/worker run.

## 2. Job API — `POST /api/v4/snapshots`

Request object gains `formats?: string[]`:

- optional; when absent the server default applies;
- non-empty, unique, every id in `SnapshotFormat::ids()`; otherwise 400
  `INVALID_REQUEST` (message names the unknown id and the known ids);
- arrays of requests: the rule applies per element (all-or-nothing as today);
- when the relation is known to be non-spatial (no geometry column) and every
  requested format `requiresGeometry`, 400 `INVALID_REQUEST`
  ("relation has no geometry column; formats [...] cannot be produced").

The row stores the requested list in a new column
`settings.snapshots.formats JSONB` (migration:
`ALTER TABLE settings.snapshots ADD COLUMN IF NOT EXISTS formats JSONB`).

`Snapshot::create(string $schema, string $relation, ?int $srs, string $username, array $formats)`.

### Row / API representation

The snapshot object (`GET /api/v4/snapshots[/{id}]` and the relation read API)
gains:

```json
"formats": [
  {"format": "parquet",    "status": "produced", "file": "data-<uuid>.parquet", "size_bytes": 123456, "media_type": "application/vnd.apache.parquet"},
  {"format": "flatgeobuf", "status": "skipped",  "reason": "relation has no geometry column"}
]
```

Before publish (pending/running) `formats` is the requested list rendered as
`{"format": id, "status": "requested"}`. `files` is unchanged (every file incl.
metadata). Old rows without the column: `formats` derived from `files`
(`SnapshotFormat::fromExtension`), `status: produced`.

`metadata-<uuid>.json` gets the same `formats` array. Its existing `files`
array is unchanged.

### Scheduler jobs

A job with `snapshot: true` enqueues with `SnapshotFormat::defaults()` unless
the job names its own formats. `jobs.snapshot_formats JSONB` (`NULL` = the
server default) holds them, written through `snapshot_formats` on
`POST`/`PATCH /api/v4/scheduler/jobs` with the same rule as `formats` here
(null, or a non-empty unique list of known ids, else 400 `INVALID_REQUEST`);
`Job::buildGetCmd()` passes a non-null list to `get.php` as
`--snapshotFormats <base64 of the JSON list>`. The non-spatial rule stays in
the worker: a geometry-only list on a non-spatial relation yields a failed
snapshot with the worker's reason, not a refusal at job level (nothing knows
at write time what the relation will look like after the next import).

## 3. Worker — `SnapshotWorker::runOne`

```
columns, schemaVersion, crs, bbox, hasGeometry  (once)
for each requested format (request order):
    if format.requiresGeometry and !hasGeometry:
        results[] = {format, status: skipped, reason: "relation has no geometry column"}; continue
    export(format) → tmp "<uuid>.<ext>"; upload as format.fileName(uuid); written[] = …
    results[] = {format, status: produced, file, size_bytes, media_type}
if no result has status produced: throw RuntimeException("no requested format could be produced: …")
```

`export(string $schema, string $relation, ?int $srs, SnapshotFormat $format, string $tmpFile)`
builds the ogr2ogr line from the registry (`-f {driver}`, `ogrArgs`,
`-t_srs` when `srs` set, `-preserve_fid`, the `-sql SELECT * FROM …`).
`-nln {relation}` names the layer inside the file after the relation for every
format: with a `-sql` source GDAL would otherwise call it `sql_statement`,
which a FlatGeobuf reader shows to the user (Parquet has no layer name). An
ogr2ogr failure for one format fails the snapshot (it is an error, not a
"cannot be produced" case). Temp files of every format are removed in the
`finally`.

`Snapshot::publish(..., array $files, ?array $bbox = null, array $formats = [])`
writes `formats` (the results array) on the row; `files` keeps every
file `{name, size_bytes}`.

The STAC rebuild is unchanged in trigger and structure.

## 4. Read API — `RelationSnapshot`

Route stays `…/snapshots/[date]/(action)/[file]`; for the `data` action the
`[file]` segment is a format id.

| Request | Behaviour |
|---|---|
| `GET/HEAD /data` | the `parquet` file when produced; else the only produced file; else 409 `MULTI_FILE_SNAPSHOT` whose message lists `/data/{format}` for every produced format |
| `GET/HEAD /data/{format}` | the produced file of that format; 400 `INVALID_REQUEST` for an unknown id; 404 `NO_SNAPSHOT_ERROR` when the format was skipped or not requested |
| `GET/HEAD /files/{name}` | unchanged |
| `OPTIONS` | `options_data` already covers `/data/{format}` |

Serving uses the existing `serve()` (proxy or presigned redirect, Range, ETag)
with the format's `mediaType` as `Content-Type` (today `PARQUET` is hard
coded; the constant goes away in favour of the registry; `/files/{name}`
picks the media type via `fromExtension`, else `application/octet-stream`).

The metadata object (`GET /snapshots/{date}` and the list) adds
`formats` as in §2 with an `href` per produced format
(`{base}/{date}/data/{format}`), and `_links.data` stays the Parquet
(absent when no Parquet was produced).

OpenAPI: `formats` property on `SnapshotRequest`, `Snapshot`,
`RelationSnapshot`; new operation `getRelationSnapshotDataFormat` for
`/data/{format}` with a `format` path parameter (`enum` = `SnapshotFormat::ids()`).

## 5. STAC

`item.json` assets: one asset per **produced** format, keyed by
`stacAssetKey` (`data` for Parquet, `flatgeobuf` for FlatGeobuf), with
`href: ./<file>`, `type: mediaType`, `roles: ["data"]`, `title: stacTitle`.
Skipped formats get no asset. `metadata` asset unchanged. The writer takes the
row's `formats` (falls back to `files` + `fromExtension` for old rows).

## 6. Configuration

`docker/conf/gc2/App.php` `snapshot` block gains:

```php
// Output formats produced when a request does not name any: ids from
// SnapshotFormat (parquet, flatgeobuf).
"formats" => ["parquet"],
```

## 7. Compatibility

- `/data` still returns the Parquet; `files` unchanged; old rows render
  `formats` from their files. No client change is required.
- Downstream (SDK, MCP server, website docs, centia-app) are told about
  `formats` on POST/GET, `/data/{format}` and the media types.

## 8. Testing

Unit:
- `SnapshotFormatTest`: registry ids, `get` unknown throws, `fromExtension`,
  `fileName`, `defaults()` reads App.php and rejects unknown ids.
- `SnapshotWorkerTest`: parquet + flatgeobuf both produced for the spatial
  table (two data files, `formats` on the row and in metadata.json, sizes >
  0, `.fgb` readable by `ogrinfo`); flatgeobuf skipped with reason for the
  geometry-less relation; only-flatgeobuf on a geometry-less relation fails
  the row; temp files cleaned up.
- `StacCatalogWriterTest`: item has `data` and `flatgeobuf` assets with the
  right types; skipped format has no asset; old row without `formats` still
  gets the `data` asset.
- `SnapshotModelTest`: `create` stores `formats`; `publish` stores results;
  presenter derives `formats` for a row without the column.

API:
- `SnapshotV4ApiCest`: POST with `formats: ["parquet","flatgeobuf"]` → 202 and
  the row lists both as requested; unknown format → 400; `formats: []` → 400;
  only `["flatgeobuf"]` on a non-spatial table → 400.
- `RelationSnapshotV4ApiCest`: after a two-format snapshot, `/data` is the
  Parquet, `/data/flatgeobuf` answers with `application/flatgeobuf`, HEAD +
  Range work on it, `/data/geojson` → 400, metadata `formats` carries both
  hrefs.

## 8a. Deviations recorded during implementation (Tasks 1-3)

- `ogrArgs` is a list of already split arguments
  (`['-mapFieldType', 'Time=String,Binary=String']`) rather than one string, so
  the worker escapes each element instead of interpolating a pre-joined blob
  into the shell. Same resulting command line.
- `SnapshotFormat::defaults()` treats a configured empty list as "nothing
  configured" and returns `["parquet"]`; a request-level `formats: []` is still
  a 400 (§2).
- `Snapshot::publish()` derives the per-format results from its `$files` list
  when a caller passes none, so a published row never renders its *requested*
  list as if it were the outcome. Only the legacy single-format callers hit it.
- `Snapshot::presentFormats()` normalises key order and casts `size_bytes`,
  because jsonb does not preserve the key order the worker wrote.
- The worker treats a spatial column in `geography_columns` as geometry for the
  skip rule (ogr2ogr exports geography fine); the footprint query casts such a
  column to geometry. `metadata.json`'s `crs` still comes from
  `geometry_columns` only, so a geography relation reports `crs: null` while
  carrying a bbox — as before this change.
- A row whose stored `formats` names only ids the registry does not know fails
  with "no known output format requested: …" instead of falling back to the
  server default. The fallback applies only to a row with no list at all (one
  queued before the column existed).

## 8b. Deviations recorded during implementation (Tasks 4-6)

**Job API (§2)**

- An unknown format id is rejected in `validate()` *before* the
  `Assert\Collection` runs, so the answer is `400 INVALID_REQUEST` with the
  message the spec asks for ("Unknown snapshot format 'x'; known formats are
  …"). `Assert\Choice` stays in the collection as a second line of defence.
  The other shape errors — an empty list, duplicates, a non-string element —
  are Symfony violations and answer `400 INPUT_VALIDATION_ERROR`, which is how
  every other v4 body error reads.
- The non-spatial refusal names the relation:
  `Relation <schema>.<relation> has no geometry column; formats [flatgeobuf]
  cannot be produced`. Without the relation an array request would not say
  which element was refused.
- `SnapshotFormat::IDS` (a constant with the same ids as the registry) was added
  because a PHP attribute argument must be a constant expression and cannot call
  `ids()`; the OpenAPI enums read it and a unit test asserts
  `ids() === IDS`.
- `SnapshotWorker::spatialColumn()` moved to `Snapshot::spatialColumn()` (the
  model), so the job API's refusal and the worker's skip answer from one query.

**Read API (§4)**

- A `[file]` segment on `/data` that is not shaped like a format id (uppercase,
  a hyphen, …) answers `400 INVALID_REQUEST` rather than the previous
  `404 NO_SNAPSHOT_ERROR`: a caller gets one answer for "that is not a format
  id" whether it is misspelled or miscased.
- The `409 MULTI_FILE_SNAPSHOT` message lists `/data/{format}` paths rather than
  full hrefs (GC2Exception carries no data payload). It is unreachable with the
  two formats that exist today — two produced files always include the Parquet —
  and is therefore not covered by a test.
- `formats` is rendered by the shared presenter, so it appears on the list
  entries as well as on the single snapshot, `href` and all.
- The FlatGeobuf signature is asserted as its first **seven** bytes
  (`66 67 62 03 66 67 62`); the eighth is the format's minor version and is
  `0x01` with the GDAL in the current image, not the `0x00` of the spec text.

**STAC (§5)**

- `StacCatalogWriter` decodes the row's `formats` itself instead of calling
  `Snapshot::presentFormats()`, keeping the writer pure (rows in, documents out,
  no model class) as it already was for `files`, `bbox` and `relation_schema`.
- A row whose `formats` still holds the *requested* ids (strings, never written
  by `publish()`) is described by its `files`, like a row from before the column.

## 9. Follow-ups (not in scope)

- Further formats (GeoJSON, GeoPackage, CSV): registry entries; GeoPackage
  would need `requiresGeometry: false` and a media type
  `application/geopackage+sqlite3`.
