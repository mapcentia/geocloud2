# v4 Search API (port of v2 Elasticsearch to OpenSearch)

**Date:** 2026-08-26
**Branch:** dev/multiple_styles
**Status:** Approved design

## Problem

The v2 Elasticsearch API (`app/api/v2/Elasticsearch.php`, ~733 lines) targets an old Elasticsearch cluster over a mix of Guzzle and raw curl, backed by the 2013–2018 `app\models\Elasticsearch` model. The site has moved to **OpenSearch**, and index creation no longer works.

Root cause of the breakage is the analyzer configuration, which is duplicated in two places — `app/conf/elasticsearch_settings.json` and an inline default in the v2 controller (`app/api/v2/Elasticsearch.php:73-118`):

- The substring filter uses `"type": "edgeNGram"` (camelCase). That name was **removed in Elasticsearch 7.0**; OpenSearch (a fork of ES 7.10) requires `edge_ngram`. Index creation fails.
- `min_gram: 1, max_gram: 255` gives a range of 254, which exceeds `index.max_ngram_diff`. The JSON file sets it to `20` (still far too low), and the inline default omits it entirely (defaulting to 1). So even with `edge_ngram`, the analyzer fails to build.

Beyond the analyzer, the v2 plumbing is fragile against OpenSearch: raw curl with no auth/TLS header support, and per-document delete hitting `/{index}/{id}` instead of the ES7+/OpenSearch form `/{index}/_doc/{id}`.

## Goals

- Port the **used** part of v2 to a clean v4 surface, renamed **Search**: (re)build an OpenSearch index from a table/view, and search in it.
- Make the OpenSearch `analysis` block definable **per database**, stored in the `settings` schema. The remaining general settings stay static in `app/conf`.
- Fix the analyzer bug so OpenSearch index creation works.

## Non-goals (out of scope for this work)

- **CDC / "river":** the Postgres `NOTIFY`/`LISTEN` trigger mechanism that keeps the index in sync. Deferred to a later phase; index freshness is achieved by re-running a rebuild.
- Per-document upsert/delete endpoints, and the meta index (v2 `put_upsert`, `delete_delete`, `get_meta`).
- A mapping-editing or mapping-preview endpoint (v2 `get_map`).
- Rewriting bulk indexing (`Sql_to_es`) — reused as-is.
- The v2 controllers stay in place as legacy.

## Decisions made

- **Approach A:** new v4 controllers + a new clean OpenSearch client; reuse the existing table→mapping logic and the `Sql_to_es` bulk indexer behind a clean interface. Legacy v2 and the old curl model are left untouched.
- **Two controllers:** a per-table `Search` (query + index lifecycle) and a database-level `SearchSettings` (per-db `analysis`), because `analysis` is a per-database concern, not per-table.
- **Index identity is unchanged from v2:** `{db}_{schema}_{table}`, for continuity with existing indices/tooling.
- **Settings split:** `number_of_shards`, `number_of_replicas`, `max_ngram_diff` and the default `analysis` stay static in `app/conf/elasticsearch_settings.json`. Only the `analysis` block is overridable per database. A per-db `analysis`, when present, **replaces** the default `analysis` block; everything else always comes from the static default.
- **Per-db storage:** a top-level `search.analysis` key in the existing per-database `settings.viewer` JSON blob (via the `Setting` model). No migration.
- **Auth — search:** read access on the underlying relation, using the same relation-privilege model as the rest of v4 (sub-user privileges apply; owner/superuser always). "If you can read the table, you can read its index."
- **Auth — rebuild / drop / settings:** owner/superuser only (`parentUser` / JWT `superUser`). Sub-users are refused regardless of write access.
- **Mapping:** consumed at rebuild, not exposed. Per-column ES config keeps living where it does today (the `elasticsearch` JSON blob on the layer in `geometry_columns`) and is edited through the existing layer/column surfaces.
- **Bug fix is shared:** v2 reads the same `elasticsearch_settings.json`, so correcting the default file also fixes v2's index creation.

## 1. Resources and routes

Two new v4 controllers in `app/api/v4/controllers/`. Scope: `Scope::SUB_USER_ALLOWED` (per-method auth is enforced in the controller, see §3).

### `Search` — `api/v4/schemas/{schema}/tables/{table}/search`

One resource = "this table's search index."

| Method | Action | Auth |
|---|---|---|
| `GET` | Search — query string (or body) passed unaltered to OpenSearch `_search` | Read on relation |
| `POST` | Search with a body (OpenSearch query DSL) | Read on relation |
| `PUT` | (Re)build the index from the table/view | Owner/superuser |
| `DELETE` | Drop the index | Owner/superuser |

The route follows the Feature/Table pattern (`api/v4/schemas/{schema}/tables/{table}/...`). `{schema}`/`{table}` resolve the relation; the index name is derived as `{db}_{schema}_{table}`.

### `SearchSettings` — `api/v4/search/settings`

Database-level configuration of the per-db `analysis` block.

| Method | Action | Auth |
|---|---|---|
| `GET` | Return the effective per-db `analysis` (the stored one, or the static default when none is set) | Owner/superuser |
| `PUT` | Set the per-db `analysis` block | Owner/superuser |

## 2. Settings composition

### Static default — `app/conf/elasticsearch_settings.json`

The single source of the default settings. The duplicated inline default in the controller is removed. Corrected so it actually loads on OpenSearch:

```json
{
  "settings": {
    "number_of_shards": 5,
    "number_of_replicas": 0,
    "max_ngram_diff": 19,
    "analysis": {
      "analyzer": {
        "auto_complete_search_analyzer": {
          "type": "custom",
          "tokenizer": "whitespace",
          "filter": ["lowercase", "asciifolding"]
        },
        "auto_complete_analyzer": {
          "type": "custom",
          "tokenizer": "whitespace",
          "filter": ["lowercase", "asciifolding", "substring"]
        }
      },
      "filter": {
        "substring": {
          "type": "edge_ngram",
          "min_gram": 1,
          "max_gram": 20
        }
      }
    }
  }
}
```

Changes from today: `edgeNGram` → `edge_ngram`; `max_gram` 255 → 20 and `max_ngram_diff` 20 → 19 so the range (19) is within the limit. Rationale: 255-length edge-ngrams bloat the index for no practical autocomplete benefit; a per-db override can raise it where genuinely needed. The filename is kept (not renamed to `search_settings.json`) so v2 keeps reading it and operators need no config migration.

### Per-database `analysis` — `settings.viewer`

Stored under a top-level `search` key in the per-database `settings.viewer` JSON blob:

```json
{
  "search": {
    "analysis": { "analyzer": { ... }, "filter": { ... } }
  }
}
```

The `Setting` model gains two helpers following the existing `getArray()` / update pattern:

- `getSearchAnalysis(): ?array` — returns the stored `search.analysis`, or `null`.
- `updateSearchAnalysis(array $analysis): void` — writes `search.analysis` back into `settings.viewer`.

### Composition at rebuild

```
settings = decode(elasticsearch_settings.json)
perDb = Setting.getSearchAnalysis()
if perDb !== null:
    settings.settings.analysis = perDb   # whole-block replace
createIndex(index, settings)
```

`SearchSettings GET` returns `perDb ?? default.settings.analysis`; `PUT` validates the body is a JSON object and stores it via `updateSearchAnalysis`.

## 3. Authorization

- **Search (GET/POST):** read authorization on `{schema}.{table}`, mirroring the read branch of the other v4 relation APIs. Owner/superuser always pass; sub-users pass per their relation read privilege. The check must work for any relation (not only spatial layers).
- **Rebuild / drop (PUT/DELETE) and all of `SearchSettings`:** owner/superuser only — the controller rejects sub-users with `403 NOT_OWNER` before doing any work. Determined from the JWT identity (`superUser` / `parentUser`), as in `buildContext()` on the Feature controller.

## 4. Index build flow (`PUT .../search`)

Synchronous:

1. Owner/superuser check.
2. Verify the relation (table or view) exists → resolve index name `{db}_{schema}_{table}`.
3. Compose settings (§2).
4. Delete the index if it exists (idempotent rebuild).
5. Create the index with the composed settings.
6. Build the mapping from the relation (`Layer::getElasticsearchMapping` → `Elasticsearch::createMapFromTable`) and PUT it to the index.
7. Bulk-index rows via `Sql_to_es` (`SELECT * FROM {relation}`).
8. Return a summary: index name, number of documents indexed.

Large tables block the request (synchronous). Acceptable for now; asynchronous indexing belongs with the deferred CDC phase.

## 5. Search flow (`GET/POST .../search`)

1. Read authorization on the relation.
2. Resolve index name.
3. Send the query to OpenSearch `_search` via the new client: a GET query string is forwarded as-is; a GET/POST body (OpenSearch query DSL) is forwarded unaltered.
4. Return the OpenSearch response JSON unchanged.

## 6. Components and files

**New**

- `app/api/v4/controllers/Search.php` — GET/POST/PUT/DELETE per §1.
- `app/api/v4/controllers/SearchSettings.php` — GET/PUT per-db `analysis`.
- `app/opensearch/Client.php` — a clean Guzzle-based client: `indexExists`, `createIndex`, `deleteIndex`, `putMapping`, `search`. Reads `esHost` as today, with a hook for an auth/TLS header for the future. This replaces raw-curl usage in the new path.

**Reused unchanged**

- `app\models\Elasticsearch::createMapFromTable` / `mapPg2EsType` — table → OpenSearch mapping body.
- `app\models\Layer::getElasticsearchMapping` — per-column ES config (the `elasticsearch` blob on the layer) merged with default PG→ES type inference.
- `app\models\Sql_to_es` — bulk indexing.

**Modified**

- `app/conf/elasticsearch_settings.json` — corrected, consolidated default (§2).
- `app\models\Setting` — `getSearchAnalysis()` / `updateSearchAnalysis()`.

## 7. Error handling

OpenSearch and relation errors map to `GC2Exception` with stable codes:

| Code | HTTP | When |
|---|---|---|
| `OPENSEARCH_OFFLINE` | 503 | Cluster not reachable |
| `RELATION_NOT_FOUND` | 404 | The `{schema}.{table}` relation does not exist |
| `INDEX_NOT_FOUND` | 404 | Search/drop on a table with no built index |
| `INDEX_BUILD_ERROR` | 400 | OpenSearch rejected the settings/mapping, or bulk indexing failed |
| `SEARCH_ERROR` | 400 | OpenSearch rejected the query |
| `NOT_OWNER` | 403 | Sub-user attempted rebuild/drop/settings |
| `INVALID_DATA` | 400 | `SearchSettings PUT` body is not a JSON object |

## 8. Testing strategy

- **Unit:** settings composition (default vs per-db override replace-semantics), index-name derivation, and the corrected default JSON being valid and containing `edge_ngram` with a range within `max_ngram_diff`.
- **Client:** `app\opensearch\Client` against a stubbed/local OpenSearch — create/exists/delete/putMapping/search happy paths and error mapping.
- **API (Codeception):** owner rebuild → search returns hits; sub-user with read privilege can search but gets `403 NOT_OWNER` on PUT/DELETE; sub-user without read privilege is denied search; `SearchSettings PUT` then a rebuild reflects the per-db analyzer. Runs inside `docker-gc2core-1` per the repo's test conventions.

## 9. Future (deferred)

- CDC/"river": Postgres `NOTIFY`/`LISTEN` trigger + a listener that streams changes into the index; asynchronous rebuild for large tables.
- Per-document upsert/delete, meta index, mapping preview/edit endpoints — if a need re-emerges.
- OpenSearch authentication/TLS credentials in `App.php`, consumed via the client's auth-header hook.
