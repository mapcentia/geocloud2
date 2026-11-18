# Snapshot STAC catalog: incremental update on publish, full rebuild as a job

Date: 2026-11-18

## Problem

`SnapshotWorker` rewrote the whole static STAC catalog of a database after
every publish: one storage write per `item.json`, plus `collection.json` and
`latest.json` per relation and `catalog.json`. The cost of one snapshot
therefore grew with the number of published snapshots.

Observed on a database with a large catalog (2026-11-18): the export of a
relation finished in 1–24 s, but the catalog rewrite after it took about
7.5 minutes. The worker claims two rows per run, so a pair took about
16 minutes, throughput was 7–8 snapshots per hour, and the queue grew faster
than it drained.

## Decision

1. **A publish writes only the documents it can have changed** — four writes,
   independent of catalog size:
   - `schema=S/relation=R/_gc2_snapshot_date=D/item.json`
   - `schema=S/relation=R/collection.json`
   - `schema=S/relation=R/latest.json`
   - `catalog.json` (a first snapshot of a relation adds a child link)

   Items link only to root, parent and collection, never to sibling items, so
   the relation's older items stay correct. The documents are still built by
   `StacCatalogWriter::build()` from all published rows (one query, in
   memory) and then filtered by path, so the incremental and the full path
   produce byte-identical documents. `catalog.json` is still written last.

2. **The full rewrite is a queued job with an API**, for repairing a catalog
   that has drifted and for bringing it up to a newer document layout:

   | | |
   |---|---|
   | `POST /api/v4/snapshots/catalog/rebuilds` | No body. `202` with `{id, status: "pending", _links.self}`. `409 CATALOG_REBUILD_IN_PROGRESS` while one is pending or running, `501 SNAPSHOT_NOT_CONFIGURED` without storage. |
   | `GET /api/v4/snapshots/catalog/rebuilds` | Newest 50, bare array. |
   | `GET /api/v4/snapshots/catalog/rebuilds/{id[,id]}` | One object or an array. `404 CATALOG_REBUILD_NOT_FOUND`. |

   Resource fields: `id`, `status` (`pending/running/succeeded/failed`),
   `documents`, `failed_documents`, `error`, `username`, `created`, `started`,
   `finished`. Super-user only.

   Queue table `settings.snapshot_catalog_rebuilds` (migration in
   `app/migration/Sql.php`). `snapshot_worker.php` claims one pending rebuild
   per database per run, after that run's snapshots. A rebuild in which any
   document could not be written ends `failed` with the counts. A `running`
   row older than `Snapshot::STALE_RUNNING_INTERVAL` is reclaimed.

## Deviations from the STAC catalog design

- The earlier design rebuilt in full on every publish so that the catalog
  healed itself. It no longer does: documents of *other* relations are only
  corrected by an explicit rebuild.
- Consequences to be aware of:
  - A changed layer title or abstract reaches `collection.json` at the
    relation's next snapshot, and `catalog.json` at any next publish.
  - After an upgrade that changes the document layout, queue one rebuild per
    database.
  - Databases whose catalog predates this change are already complete; no
    rebuild is needed for the fix itself.

## Known limits

- A full rebuild still does one sequential storage write per document and
  holds the worker's `flock` while it runs, so snapshots of every database on
  that host wait for it. Concurrent writes would shorten it; not done here.
- Cross-host writers are still last-writer-wins on `catalog.json` and
  `collection.json`.

## Tests

- `SnapshotWorkerTest`: a publish leaves another relation's documents
  untouched and `catalog.json` lists every relation; a queued rebuild restores
  every document and records the counts.
- `SnapshotCatalogRebuildV4ApiCest`: the HTTP contract.
