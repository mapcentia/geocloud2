# Snapshot output formats Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Snapshots can be produced in several formats (Parquet + FlatGeobuf now, more later) chosen per request with a server default, served per format by the read API and listed as STAC assets.

**Architecture:** One registry class (`SnapshotFormat`) describes every format; the job API validates against it, the worker loops over the requested formats (skipping those that need geometry on non-spatial relations), the row and metadata.json record per-format results, the read API serves `/data/{format}`, and the STAC writer emits one asset per produced format.

**Tech Stack:** PHP 8.4, PostgreSQL/PostGIS, ogr2ogr (GDAL, Parquet + FlatGeobuf drivers present in the image), Flysystem storage, Codeception.

**Spec:** `docs/superpowers/specs/2026-09-21-snapshot-formats-design.md` — the spec is the authority; every exact value (ids, media types, error codes, JSON shapes) is there.

## Global Constraints

- Format ids: `parquet`, `flatgeobuf`. Media types `application/vnd.apache.parquet`, `application/flatgeobuf`. Files `data-<uuid>.parquet` / `data-<uuid>.fgb`.
- `formats` request field: optional, non-empty, unique, known ids, else 400 `INVALID_REQUEST`. Default `App::$param['snapshot']['formats'] ?? ['parquet']`.
- Skipped formats are recorded `{format, status: "skipped", reason}`; the snapshot fails only when nothing was produced.
- `/data` keeps serving the Parquet. New `/data/{format}`: 400 unknown id, 404 `NO_SNAPSHOT_ERROR` when not produced.
- Never commit `app/conf/App.php`; never stage `docker/docker-compose.yml`. Tests run inside `docker-dev-1`, one suite per command. Follow `AGENTS.md`.

---

### Task 1: Format registry and config

**Files:** Create `app/inc/snapshot/SnapshotFormat.php`, `app/tests/unit/SnapshotFormatTest.php`; Modify `docker/conf/gc2/App.php` (snapshot block, add `"formats" => ["parquet"]` with the comment from spec §6).

**Produces:** `SnapshotFormat::ids()`, `get(string)`, `has(string)`, `fromExtension(string $fileName)`, `defaults()`, instance readonly props `id, driver, extension, mediaType, requiresGeometry, stacAssetKey, ogrArgs`, methods `fileName(string $uuid)`, `stacTitle(bool $spatial)` (spec §1 table).

- [ ] Write `SnapshotFormatTest` (ids, get/has, unknown id throws `InvalidArgumentException`, `fromExtension('data-x.fgb')` → flatgeobuf and `.json` → null, `fileName`, `defaults()` = `['parquet']` when `App::$param['snapshot']['formats']` unset and throws `RuntimeException` on an unknown configured id — set/restore `App::$param` in the test).
- [ ] Run it, see it fail; implement the class; run green.
- [ ] Commit `feat(snapshot): format registry (parquet, flatgeobuf)`.

### Task 2: Model and migration

**Files:** Modify `app/models/Snapshot.php`, `app/migration/Sql.php`, `app/tests/unit/SnapshotModelTest.php`.

**Produces:** `create(string $schema, string $relation, ?int $srs, string $username, array $formats): string` (stores `formats` JSONB as the id list); `publish(..., array $files, ?array $bbox = null, array $formats = [])` stores the results array; `static presentFormats(array $row): array` → spec §2 representation (requested → `status: requested`; published → stored results; row without `formats` → derived from `files` via `SnapshotFormat::fromExtension`, `status: produced`). `get()`/`list()`/`listPublished()`/`getPublished()`/`listAllPublished()` return `formats` decoded.

- [ ] Migration: `ALTER TABLE settings.snapshots ADD COLUMN IF NOT EXISTS formats JSONB` next to the `bbox` line; apply to the dev DBs the way Task "STAC" did (`php migration/run.php` in the container).
- [ ] Tests first: create stores formats; publish stores results; presentFormats for the three cases. Then implement; green.
- [ ] Update the single production caller of `create()` (`app/api/v4/controllers/Snapshot.php:218`) to pass `SnapshotFormat::defaults()` for now (Task 4 wires the request field) and `app/scripts/get.php:~1618` (scheduler `--snapshot`) to pass `SnapshotFormat::defaults()`. Keep `SnapshotV4ApiCest` green.
- [ ] Commit `feat(snapshot): formats column, per-format results on publish`.

### Task 3: Worker multi-format export

**Files:** Modify `app/inc/SnapshotWorker.php`, `app/tests/unit/SnapshotWorkerTest.php`.

**Consumes:** Task 1 registry, Task 2 `publish(..., formats)`.

- [ ] Tests first (spec §8 worker cases): two-format request on the spatial table → two data files in the store (`data-<uuid>.parquet`, `data-<uuid>.fgb`), row `formats` has two `produced` entries with sizes > 0 and media types, metadata.json has the same `formats`, `ogrinfo -so <fgb>` reports the layer; flatgeobuf skipped with reason `relation has no geometry column` for the geometry-less relation while parquet is produced; only `['flatgeobuf']` on the geometry-less relation → row `failed` with the "no requested format could be produced" error; no `<uuid>.*` temp files left.
- [ ] Implement: `runOne` reads the row's `formats` (fallback `SnapshotFormat::defaults()`), computes columns/crs/bbox/hasGeometry once, loops per spec §3, `export(schema, relation, srs, SnapshotFormat $format, string $tmpFile)` builds the ogr2ogr line from the registry (`-f {driver}` + `ogrArgs` + `-t_srs` + `-preserve_fid`), uploads via `$format->fileName($uuid)`, writes `formats` into metadata.json and passes results to `publish()`; `finally` removes every temp file.
- [ ] Green; commit `feat(snapshot): export every requested format, skip unsupported with reason`.

### Task 4: Job API

**Files:** Modify `app/api/v4/controllers/Snapshot.php`, `app/tests/api/SnapshotV4ApiCest.php`.

- [ ] Cest first: POST `formats: ["parquet","flatgeobuf"]` → 202 and `GET /snapshots/{id}` lists both with `status: requested`; `formats: ["geojson"]` → 400 `INVALID_REQUEST`; `formats: []` → 400; `formats: ["parquet","parquet"]` → 400; only `["flatgeobuf"]` on a table without geometry (create `nogeom` table in the fixture) → 400 with the spec §2 message; omitted `formats` → row shows the server default.
- [ ] Implement: `getAssert()` gains `formats` (`Optional`, `Type('array')`, `Count(min 1)`, `All([Type('string'), Choice(SnapshotFormat::ids())])`, `Unique`); `post_index` resolves `$formats = $r['formats'] ?? SnapshotFormat::defaults()`, checks the non-spatial rule with `Model::getGeometryColumns`-style lookup (reuse what `SnapshotWorker::geometryColumn` does — extract a small shared helper if needed), passes to `create()`; `present()` adds `'formats' => Snapshot::presentFormats($row)`; OpenAPI: `formats` on `SnapshotRequest` (array of string, enum = ids) and on `Snapshot` (array of objects per spec §2).
- [ ] Green; commit `feat(snapshot): formats on POST /api/v4/snapshots`.

### Task 5: Read API `/data/{format}`

**Files:** Modify `app/api/v4/controllers/RelationSnapshot.php`, `app/tests/api/RelationSnapshotV4ApiCest.php`.

- [ ] Cest first (spec §8): the fixture snapshot requests both formats; `/data` → Parquet with `application/vnd.apache.parquet`; `GET`+`HEAD /data/flatgeobuf` → `application/flatgeobuf`, `Content-Length`, `Accept-Ranges: bytes`, a `Range: bytes=0-7` → 206 with the FlatGeobuf magic bytes `66 47 42 03 66 47 42 00` (first 8 bytes); `/data/geojson` → 400; `/data/flatgeobuf` on a snapshot that skipped it → 404 `NO_SNAPSHOT_ERROR`; metadata has `formats[*].href`; `_links.data` present.
- [ ] Implement: `dataFile()` → `dataFileFor(?string $format)`: null → parquet if produced, else the only produced file, else 409 `MULTI_FILE_SNAPSHOT` listing `/data/{format}` hrefs; format given → validate with `SnapshotFormat::has` (400) and pick the produced entry (404). `get_data`/`head_data` read `$this->route->getParam('file')` as the format (the `[file]` segment). Remove the `PARQUET` constant; media type from the registry (`/files/{name}` via `fromExtension` else `application/octet-stream`). `present()` adds `formats` with `href` for produced entries. `validate()`: for `data` the `[file]` segment must match `^[a-z0-9]+$`. OpenAPI: new operation `getRelationSnapshotDataFormat` with `format` enum parameter; `RelationSnapshot` schema gains `formats`.
- [ ] Green (note: this Cest needs S3 egress from the container; if DNS is still broken, run it with `storage: local` in a throwaway App.php copy is NOT allowed — instead report the environment state). Commit `feat(snapshot): serve a chosen format via /data/{format}`.

### Task 6: STAC assets per format

**Files:** Modify `app/inc/snapshot/StacCatalogWriter.php`, `app/tests/unit/StacCatalogWriterTest.php`, spec `2026-09-16-snapshot-storage-and-read-api-design.md` (STAC section: assets per format).

- [ ] Tests first: row with `formats` produced parquet+flatgeobuf → assets `data` (parquet type, title GeoParquet) and `flatgeobuf` (`application/flatgeobuf`, title FlatGeobuf, roles data); skipped flatgeobuf → no asset; row without `formats` → `data` asset from `files` as today.
- [ ] Implement via `SnapshotFormat` (`stacAssetKey`, `mediaType`, `stacTitle`). Green; commit `feat(snapshot): STAC asset per produced format`.

### Task 7: Docs, changelog, verification

- [ ] CHANGELOG `[Unreleased]/Added`: multi-format snapshots (formats field, default, `/data/{format}`, FlatGeobuf, STAC assets, skip rule).
- [ ] Run: unit `SnapshotFormatTest`, `SnapshotModelTest`, `SnapshotWorkerTest`, `StacCatalogWriterTest`, `LocalSnapshotStorageTest`; api `SnapshotV4ApiCest`, `RelationSnapshotV4ApiCest`; `php -l` on touched files; generate OpenAPI with `vendor/bin/openapi` and check the new operation/properties; grep for `PARQUET` leftovers and `'.parquet'` string checks outside the registry.
- [ ] Commit `docs: changelog for multi-format snapshots`.
