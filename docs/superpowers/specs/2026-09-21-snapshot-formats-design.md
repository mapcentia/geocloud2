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

Scheduler jobs: `snapshot: true` enqueues with `SnapshotFormat::defaults()`.
A per-job `snapshot_formats` field is a follow-up, not part of this change.

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

## 9. Follow-ups (not in scope)

- Per-job `snapshot_formats` on scheduler jobs.
- Further formats (GeoJSON, GeoPackage, CSV): registry entries; GeoPackage
  would need `requiresGeometry: false` and a media type
  `application/geopackage+sqlite3`.
