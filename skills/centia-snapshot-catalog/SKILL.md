---
name: centia-snapshot-catalog
description: Analyse GC2/Centia Parquet snapshots with DuckDB by walking the STAC catalog.json in the snapshot store — find datasets, decide whether a dataset has geometry (and in which CRS), read one snapshot, the newest snapshot or the whole history over Hive partitions, and run spatial and non-spatial SQL on them.
---

# Centia snapshot catalog + DuckDB

A GC2 snapshot store is a static STAC 1.1.0 catalog over (Geo)Parquet files.
Everything an agent needs to plan an analysis is in three small JSON files;
the data itself is read directly with DuckDB over HTTP or S3.

Example root: `https://gc2-parquet.s3.eu-west-1.amazonaws.com/centia-io/dk/catalog.json`
(`{base}` below is the directory that holds `catalog.json`).

## 1. Layout

```
{base}/catalog.json                                   Catalog: one "child" link per dataset
{base}/schema=S/relation=R/collection.json            Collection: title, extent, CRS, one "item" link per snapshot date
{base}/schema=S/relation=R/latest.json                Pointer to the newest snapshot (newer stores only)
{base}/schema=S/relation=R/_gc2_snapshot_date=D/item.json          Item: one snapshot
{base}/schema=S/relation=R/_gc2_snapshot_date=D/data-<uuid>.parquet   the data (GeoParquet when spatial)
{base}/schema=S/relation=R/_gc2_snapshot_date=D/data-<uuid>.fgb       optional FlatGeobuf
{base}/schema=S/relation=R/_gc2_snapshot_date=D/metadata-<uuid>.json  column schema, row count, crs, bbox, formats
```

- A dataset is `schema.relation` (a PostGIS table or view). Every href is
  relative to the file it appears in.
- `_gc2_snapshot_date=D` is a Hive partition key: reading several dates at
  once with `hive_partitioning = true` adds a `_gc2_snapshot_date` column.
- Only JSON files live outside the partitions, so `*.parquet` globs are safe.

## 2. Setup

```sql
INSTALL httpfs; LOAD httpfs;
INSTALL spatial; LOAD spatial;          -- GEOMETRY type, ST_* functions, GeoParquet awareness
-- Public bucket, anonymous access (needed for globs; HTTP URLs cannot glob):
CREATE SECRET pub (TYPE s3, REGION 'eu-west-1', KEY_ID '', SECRET '');
```

Single files work with plain `https://…` URLs. Wildcards need the
`s3://bucket/prefix/…` form (the bucket is the first host label of the
HTTPS URL: `s3://gc2-parquet/centia-io/dk/…`).

## 3. Find datasets

Read the STAC JSON with DuckDB instead of guessing paths:

```sql
-- every dataset in the store
SELECT l.title AS dataset, l.href
FROM (SELECT unnest(links) AS l FROM read_json('{base}/catalog.json'))
WHERE l.rel = 'child';

-- one dataset: extent, CRS, snapshot dates
SELECT id, title, description, keywords,
       extent.spatial.bbox[1]              AS bbox_wgs84,
       extent.temporal.interval[1]         AS first_last_snapshot,
       summaries                           -- {"gc2:schema_version": [...], "proj:code": ["EPSG:25832"]}
FROM read_json('{base}/schema=S/relation=R/collection.json');

SELECT l.href FROM (SELECT unnest(links) AS l FROM read_json('{base}/schema=S/relation=R/collection.json'))
WHERE l.rel = 'item' ORDER BY l.href DESC;          -- newest first is how they are listed
```

The item tells you what one snapshot holds:

```sql
SELECT id, bbox, geometry.type AS footprint,
       properties."gc2:row_count"    AS rows,
       properties."proj:code"        AS crs,
       properties.datetime           AS snapshot_date,
       assets.data.href, assets.data.title,          -- "GeoParquet" or "Parquet"
       assets.flatgeobuf.href                        -- NULL when not produced
FROM read_json('{base}/schema=S/relation=R/_gc2_snapshot_date=D/item.json');
```

Title, description and keywords come from the layer metadata in GC2; when a
layer has no title the collection is titled `schema.relation`.

## 4. Does the dataset have geometry?

Check in this order; stop at the first definitive answer.

1. **Item** (`item.json`): `bbox` and a Polygon `geometry` present → spatial;
   `geometry: null` and no `bbox` → non-spatial. `assets.data.title` is
   `GeoParquet` for spatial data and `Parquet` otherwise. `properties."proj:code"`
   gives the CRS (`EPSG:25832` etc.). Items published before September 2026 may
   lack `proj:code`; the collection `extent.spatial.bbox` of `[-180,-90,180,90]`
   then means "unknown or non-spatial", not "world-wide data".
2. **metadata-<uuid>.json** (next to the data): `crs` (`"EPSG:25832"` or null),
   `bbox`, and `schema[]` where a column with `data_type` starting with
   `geometry(` or `geography(` is the geometry column.
3. **The Parquet file itself** (definitive, one HTTP range request):

```sql
-- GeoParquet metadata: present only for spatial files
SELECT json_extract_string(decode(value), '$.primary_column')                       AS geom_column,
       json_extract_string(decode(value), '$.columns.the_geom.crs.id.code')          AS epsg,
       json_extract_string(decode(value), '$.columns.the_geom.geometry_types')       AS geometry_types
FROM parquet_kv_metadata('{file}.parquet') WHERE key = 'geo';      -- 0 rows => non-spatial

-- or simply look at the column type DuckDB infers (spatial extension loaded)
SELECT column_name, column_type FROM (DESCRIBE SELECT * FROM read_parquet('{file}.parquet'))
WHERE column_type LIKE 'GEOMETRY%';                                -- e.g. GEOMETRY('EPSG:25832')
```

GC2 names the geometry column `the_geom` in imported tables; other tables
may use another name, so read `primary_column` rather than assuming.

## 5. Read the data

```sql
-- one snapshot (HTTP is fine for single files)
SELECT count(*) FROM read_parquet('{base}/schema=S/relation=R/_gc2_snapshot_date=D/data-<uuid>.parquet');

-- the newest snapshot through the GC2 API (fixed URL, needs a Bearer token unless the layer is public)
CREATE SECRET api (TYPE http, BEARER_TOKEN '<token>');
SELECT * FROM read_parquet('https://<gc2-host>/api/v4/schemas/S/relations/R/snapshots/latest/data') LIMIT 10;

-- the newest snapshot from the store: follow latest.json (newer stores) or the first item link
SELECT item, assets.data.href FROM read_json('{base}/schema=S/relation=R/latest.json');

-- the whole history, one row per snapshot date (s3:// + secret from §2)
SELECT _gc2_snapshot_date, count(*)
FROM read_parquet('s3://gc2-parquet/centia-io/dk/schema=S/relation=R/_gc2_snapshot_date=*/data-*.parquet',
                  hive_partitioning = true)
GROUP BY 1 ORDER BY 1;
```

Rules of thumb:

- Select only the columns you need; Parquet is columnar and the files are
  read over the network. `properties."gc2:row_count"` tells you the size
  before you read.
- Prefer a dated URL when an analysis must be reproducible; `latest` moves
  after every publish.
- Schema drift across dates: compare `gc2:schema_version` (a hash of
  column names and types) between items before `UNION`ing them; use
  `union_by_name = true` in `read_parquet` when columns differ.
- Timestamps are stored as UTC `TIMESTAMP WITH TIME ZONE`; `Time` and binary
  columns were exported as strings.

## 6. Spatial analysis

With the spatial extension the geometry column arrives as
`GEOMETRY('EPSG:xxxx')`, so `ST_*` functions work directly:

```sql
SELECT ST_GeometryType(the_geom) AS type, count(*) FROM read_parquet('{file}') GROUP BY 1;

-- area per category in the native CRS (planar units of the CRS, metres for EPSG:25832)
SELECT temanavn, sum(ST_Area(the_geom)) / 1e6 AS km2 FROM read_parquet('{file}') GROUP BY 1 ORDER BY 2 DESC;

-- reproject to WGS84 for a map / bbox filter; source and target CRS from proj:code / the geo metadata
SELECT ST_Transform(the_geom, 'EPSG:25832', 'EPSG:4326', always_xy := true) AS geom_wgs84 FROM read_parquet('{file}');

-- spatial join between two datasets in the same CRS
SELECT b.*, k.navn AS kommune
FROM read_parquet('{buildings}') b
JOIN read_parquet('{municipalities}') k ON ST_Intersects(b.the_geom, k.the_geom);
```

Join datasets in different CRSs only after `ST_Transform`-ing one of them.
`ST_Area`/`ST_Length` are planar: use a projected CRS (EPSG:25832 for
Denmark), never EPSG:4326, for metric results.

Non-spatial datasets (no `geo` metadata) are plain tables: join them to a
spatial one on a key column (`id`, `bfe`, `kommunekode`, …) to map them.

## 7. FlatGeobuf assets

`assets.flatgeobuf` (when present) is the same rows as a `.fgb` file for web
maps and GIS clients. In DuckDB use `ST_Read('{file}.fgb')`; for analysis
prefer the Parquet asset, which is faster to scan and carries the same CRS.

## 8. Pitfalls

- Plain HTTPS URLs cannot glob; use `s3://` with an anonymous secret for
  `_gc2_snapshot_date=*` reads, or list the item hrefs from the collection.
- `parquet_kv_metadata` values are BLOBs: wrap them in `decode()` before
  `json_extract_string`.
- Do not create files or directories beside the partitions in the store
  (the `latest.json`/`collection.json` JSON files are the only exceptions);
  Hive readers treat every directory under `relation=R/` as a partition.
- The store lists every published relation of the database; access to the
  files is whatever the bucket allows, while the GC2 API (`/api/v4/schemas/
  {schema}/relations/{relation}/snapshots/…`) enforces privileges and
  geofence rules. Use the API URLs when the data is not public.
