# Settings History Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add full change history (INSERT/UPDATE/DELETE) for `settings.key_value` and `settings.geometry_columns_join` into two separate mirrored history tables, populated by database triggers.

**Architecture:** Append idempotent DDL to the migration list in `app/migration/Sql.php`. Two history tables are created as `LIKE`-mirrors of their source tables plus four audit columns. A single generic PL/pgSQL function `settings.history_trigger()`, attached to both source tables via `AFTER` row-level triggers, serializes the affected row with `to_jsonb()`, merges in audit metadata, and re-materializes it into the matching history table with `jsonb_populate_record()`. This auto-adapts to future columns: adding a column to a source table only requires adding the same column to its history table — the function never changes.

**Tech Stack:** PHP 8.4 (migration runner), PostgreSQL/PostGIS (PL/pgSQL triggers, JSONB functions). The migration runner is `app/migration/run.php`, which executes each statement in `Sql::get()` in its own transaction and silently swallows errors — so every statement must be re-runnable.

## Global Constraints

- All new objects live in the `settings` schema of every per-customer GC2 database.
- Every appended statement MUST be idempotent / re-run-safe (the runner swallows errors, so a non-idempotent statement that "succeeds once then errors" is acceptable, but prefer explicit `IF NOT EXISTS` / `CREATE OR REPLACE` / `DROP ... IF EXISTS` so re-runs stay clean).
- New statements are appended to `Sql::get()` **before** the `include 'Views1.php';` line (currently `app/migration/Sql.php:278`).
- Source row stored per operation: INSERT → `NEW`, UPDATE → `NEW`, DELETE → `OLD`. (Confirmed in the design spec.)
- Audit columns, in this exact order and naming, on every history table: `history_id BIGSERIAL`, `history_operation CHAR(1)`, `history_db_user TEXT`, `history_timestamp TIMESTAMPTZ DEFAULT now()`.
- The generic function name is exactly `settings.history_trigger()`. Trigger names are `<source_table>_history_tr`.
- Local verification uses the `postgres` container (`docker exec postgres psql -U mydb -d <db>`); `martinhoghdk` is a small DB that has both source tables. Rendering the actual emitted SQL uses the `docker-gc2core-1` container where the repo is mounted at `/var/www/geocloud2`.

---

## Task 1: key_value history (table, audit columns, shared trigger function, trigger)

**Files:**
- Modify: `app/migration/Sql.php` (insert before `include 'Views1.php';`, currently line 278)

**Interfaces:**
- Consumes: existing `settings.key_value` table.
- Produces:
  - Table `settings.key_value_history` (mirror of `settings.key_value` + audit columns).
  - Function `settings.history_trigger() RETURNS trigger` — generic, used by Task 2 as well.
  - Trigger `key_value_history_tr` on `settings.key_value`.

- [ ] **Step 1: Write the failing verification (history table must not exist yet)**

Run:
```bash
docker exec postgres psql -U mydb -d martinhoghdk -c "SELECT to_regclass('settings.key_value_history');"
```
Expected: a single row with an empty/`NULL` value (table does not exist yet). This confirms the starting state.

- [ ] **Step 2: Append the key_value history statements to `Sql.php`**

Insert the following block immediately before the `include 'Views1.php';` line (currently `app/migration/Sql.php:278`):

```php
        // --- History tracking: settings.key_value ---
        $sqls[] = "CREATE TABLE settings.key_value_history (LIKE settings.key_value)";
        $sqls[] = "ALTER TABLE settings.key_value_history ADD COLUMN IF NOT EXISTS history_id BIGSERIAL";
        $sqls[] = "ALTER TABLE settings.key_value_history ADD COLUMN IF NOT EXISTS history_operation CHAR(1)";
        $sqls[] = "ALTER TABLE settings.key_value_history ADD COLUMN IF NOT EXISTS history_db_user TEXT";
        $sqls[] = "ALTER TABLE settings.key_value_history ADD COLUMN IF NOT EXISTS history_timestamp TIMESTAMPTZ DEFAULT now()";
        $sqls[] = <<<'SQL'
CREATE OR REPLACE FUNCTION settings.history_trigger() RETURNS trigger
LANGUAGE plpgsql AS $FN$
DECLARE
    hist_table text := TG_TABLE_NAME || '_history';
    rec        record;
    seq        text;
    payload    jsonb;
BEGIN
    rec := CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    seq := pg_get_serial_sequence('settings.' || hist_table, 'history_id');
    payload := to_jsonb(rec) || jsonb_build_object(
        'history_id',        nextval(seq),
        'history_operation', left(TG_OP, 1),
        'history_db_user',   current_user,
        'history_timestamp', now()
    );
    EXECUTE format(
        'INSERT INTO settings.%I SELECT (jsonb_populate_record(NULL::settings.%I, $1)).*',
        hist_table, hist_table
    ) USING payload;
    RETURN NULL;
END;
$FN$
SQL;
        $sqls[] = "DROP TRIGGER IF EXISTS key_value_history_tr ON settings.key_value";
        $sqls[] = "CREATE TRIGGER key_value_history_tr AFTER INSERT OR UPDATE OR DELETE ON settings.key_value FOR EACH ROW EXECUTE FUNCTION settings.history_trigger()";
```

> **Why nowdoc (`<<<'SQL'`):** the function body contains `$FN$`, `$1`, and single quotes. A normal double-quoted PHP string would interpolate `$FN`/`$1` and break the SQL; nowdoc passes the body through verbatim. The closing `SQL;` MUST be at column 0.

- [ ] **Step 3: Verify the PHP file still parses**

Run:
```bash
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -l Sql.php
```
Expected: `No syntax errors detected in Sql.php`.

- [ ] **Step 4: Render the emitted SQL and run it end-to-end against the test DB (rolled back)**

This proves the actual strings emitted by `Sql.php` produce valid SQL and that the trigger logs I/U/D correctly. Render only the history statements, wrap them in a transaction, exercise the table, and roll back:

```bash
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -r '
require "Sql.php";
foreach (app\migration\Sql::get() as $s) { if (stripos($s,"history") !== false) echo trim($s).";\n"; }
' 2>/dev/null > /tmp/hist_kv.sql

cat >> /tmp/hist_kv.sql <<'EOF'
INSERT INTO settings.key_value (key, value) VALUES ('hist_test', '{"a":1}');
UPDATE settings.key_value SET value = '{"a":2}' WHERE key = 'hist_test';
DELETE FROM settings.key_value WHERE key = 'hist_test';
SELECT history_id, history_operation, history_db_user, key, value
FROM settings.key_value_history WHERE key = 'hist_test' ORDER BY history_id;
EOF

# Prepend BEGIN; and append ROLLBACK; so the shared DB is untouched
{ echo 'BEGIN;'; cat /tmp/hist_kv.sql; echo 'ROLLBACK;'; } > /tmp/hist_kv_tx.sql
docker cp /tmp/hist_kv_tx.sql postgres:/tmp/hist_kv_tx.sql
docker exec postgres psql -U mydb -d martinhoghdk -v ON_ERROR_STOP=1 -f /tmp/hist_kv_tx.sql
```

Expected output includes (and ends with `ROLLBACK`):
```
 history_id | history_operation | history_db_user |    key    |  value
------------+-------------------+-----------------+-----------+----------
          1 | I                 | mydb            | hist_test | {"a": 1}
          2 | U                 | mydb            | hist_test | {"a": 2}
          3 | D                 | mydb            | hist_test | {"a": 2}
(3 rows)
```

If any statement errors (output is not clean), fix the `Sql.php` block and re-run before committing.

- [ ] **Step 5: Commit**

```bash
git add app/migration/Sql.php
git commit -m "feat: add change history for settings.key_value

Adds settings.key_value_history (LIKE-mirror + audit columns), the
generic settings.history_trigger() function, and an AFTER row trigger
logging INSERT/UPDATE/DELETE.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: geometry_columns_join history (table, audit columns, trigger)

**Files:**
- Modify: `app/migration/Sql.php` (append immediately after the Task 1 block, still before `include 'Views1.php';`)

**Interfaces:**
- Consumes: existing `settings.geometry_columns_join` table; the `settings.history_trigger()` function created in Task 1.
- Produces:
  - Table `settings.geometry_columns_join_history` (mirror + audit columns).
  - Trigger `geometry_columns_join_history_tr` on `settings.geometry_columns_join`.

- [ ] **Step 1: Write the failing verification (history table must not exist yet)**

Run:
```bash
docker exec postgres psql -U mydb -d martinhoghdk -c "SELECT to_regclass('settings.geometry_columns_join_history');"
```
Expected: empty/`NULL` value (does not exist yet).

- [ ] **Step 2: Append the geometry_columns_join history statements to `Sql.php`**

Insert immediately after the Task 1 block (still before `include 'Views1.php';`):

```php
        // --- History tracking: settings.geometry_columns_join ---
        $sqls[] = "CREATE TABLE settings.geometry_columns_join_history (LIKE settings.geometry_columns_join)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join_history ADD COLUMN IF NOT EXISTS history_id BIGSERIAL";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join_history ADD COLUMN IF NOT EXISTS history_operation CHAR(1)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join_history ADD COLUMN IF NOT EXISTS history_db_user TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join_history ADD COLUMN IF NOT EXISTS history_timestamp TIMESTAMPTZ DEFAULT now()";
        $sqls[] = "DROP TRIGGER IF EXISTS geometry_columns_join_history_tr ON settings.geometry_columns_join";
        $sqls[] = "CREATE TRIGGER geometry_columns_join_history_tr AFTER INSERT OR UPDATE OR DELETE ON settings.geometry_columns_join FOR EACH ROW EXECUTE FUNCTION settings.history_trigger()";
```

- [ ] **Step 3: Verify the PHP file still parses**

Run:
```bash
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -l Sql.php
```
Expected: `No syntax errors detected in Sql.php`.

- [ ] **Step 4: Render and run end-to-end against the test DB (rolled back)**

This time render ALL history statements (Task 1 + Task 2) so the shared function is created before the gcj trigger, then exercise the gcj table:

```bash
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -r '
require "Sql.php";
foreach (app\migration\Sql::get() as $s) { if (stripos($s,"history") !== false) echo trim($s).";\n"; }
' 2>/dev/null > /tmp/hist_all.sql

cat >> /tmp/hist_all.sql <<'EOF'
INSERT INTO settings.geometry_columns_join (_key_, f_table_title, meta) VALUES ('hist.test.geom', 'orig title', '{"x":1}');
UPDATE settings.geometry_columns_join SET f_table_title = 'new title', meta = '{"x":2}' WHERE _key_ = 'hist.test.geom';
DELETE FROM settings.geometry_columns_join WHERE _key_ = 'hist.test.geom';
SELECT history_id, history_operation, _key_, f_table_title, meta, uuid IS NOT NULL AS has_uuid
FROM settings.geometry_columns_join_history WHERE _key_ = 'hist.test.geom' ORDER BY history_id;
EOF

{ echo 'BEGIN;'; cat /tmp/hist_all.sql; echo 'ROLLBACK;'; } > /tmp/hist_all_tx.sql
docker cp /tmp/hist_all_tx.sql postgres:/tmp/hist_all_tx.sql
docker exec postgres psql -U mydb -d martinhoghdk -v ON_ERROR_STOP=1 -f /tmp/hist_all_tx.sql
```

Expected output includes (and ends with `ROLLBACK`):
```
 history_id | history_operation |     _key_      | f_table_title |   meta   | has_uuid
------------+-------------------+----------------+---------------+----------+----------
          1 | I                 | hist.test.geom | orig title    | {"x": 1} | t
          2 | U                 | hist.test.geom | new title     | {"x": 2} | t
          3 | D                 | hist.test.geom | new title     | {"x": 2} | t
(3 rows)
```

The `has_uuid = t` rows confirm the `uuid NOT NULL` column round-trips through the JSONB serialization.

- [ ] **Step 5: Commit**

```bash
git add app/migration/Sql.php
git commit -m "feat: add change history for settings.geometry_columns_join

Adds settings.geometry_columns_join_history (LIKE-mirror + audit
columns) and an AFTER row trigger reusing settings.history_trigger().

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Idempotency check, graceful-degradation check, and CHANGELOG

**Files:**
- Modify: `CHANGELOG.md` (repo root — add a note under the current/unreleased section, matching the existing entry style)

**Interfaces:**
- Consumes: the full history SQL emitted by `Sql.php` (Tasks 1 & 2).
- Produces: a CHANGELOG entry. No schema changes.

- [ ] **Step 1: Verify idempotency — run the emitted history SQL twice in one transaction**

Re-running the migration must not error destructively. Render the history statements and run the whole block twice, then roll back:

```bash
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -r '
require "Sql.php";
foreach (app\migration\Sql::get() as $s) { if (stripos($s,"history") !== false) echo trim($s).";\n"; }
' 2>/dev/null > /tmp/hist_idem.sql

{ echo 'BEGIN;'; cat /tmp/hist_idem.sql; cat /tmp/hist_idem.sql; echo 'ROLLBACK;'; } > /tmp/hist_idem_tx.sql
docker cp /tmp/hist_idem_tx.sql postgres:/tmp/hist_idem_tx.sql
docker exec postgres psql -U mydb -d martinhoghdk -f /tmp/hist_idem_tx.sql 2>&1 | grep -iE 'error' || echo "NO ERRORS"
```

Expected: `NO ERRORS`. (The runner swallows errors in production, but a clean second run confirms `IF NOT EXISTS` / `CREATE OR REPLACE` / `DROP ... IF EXISTS` cover every statement. The second `CREATE TABLE ... (LIKE ...)` is the one statement that legitimately errors with "already exists" — that is expected and harmless; if `grep` catches only that line, treat it as a pass. Any OTHER error is a real failure.)

> Note: the only non-`IF NOT EXISTS` statement is `CREATE TABLE ... (LIKE ...)`. On a real re-run the runner catches its "already exists" error and prints `-`; that is by design. Do not add `IF NOT EXISTS` to the `CREATE TABLE` unless the team prefers it — it is harmless either way.

- [ ] **Step 2: Verify graceful degradation when a new source column is missing from the history table**

Adding a column to a source table without adding it to the history table must NOT break logging (the new column's value is simply dropped). Confirm in a rolled-back transaction:

```bash
cat > /tmp/hist_degrade.sql <<'EOF'
BEGIN;
EOF
docker exec -w /var/www/geocloud2/app/migration docker-gc2core-1 php -r '
require "Sql.php";
foreach (app\migration\Sql::get() as $s) { if (stripos($s,"history") !== false) echo trim($s).";\n"; }
' 2>/dev/null >> /tmp/hist_degrade.sql
cat >> /tmp/hist_degrade.sql <<'EOF'
ALTER TABLE settings.key_value ADD COLUMN brand_new_col text;
INSERT INTO settings.key_value (key, value, brand_new_col) VALUES ('degrade_test', '{"a":1}', 'ignored');
SELECT history_operation, key, value FROM settings.key_value_history WHERE key = 'degrade_test';
ROLLBACK;
EOF
docker cp /tmp/hist_degrade.sql postgres:/tmp/hist_degrade.sql
docker exec postgres psql -U mydb -d martinhoghdk -v ON_ERROR_STOP=1 -f /tmp/hist_degrade.sql
```

Expected: the INSERT succeeds and one history row (`I`, `degrade_test`, `{"a": 1}`) is returned — logging continued despite the unknown `brand_new_col`. Ends with `ROLLBACK`.

- [ ] **Step 3: Add a CHANGELOG entry**

`CHANGELOG.md` follows Keep-a-Changelog/CalVer: the newest version section is at the top (currently `## [2026.6.3] - 2026-18-6`), with entries grouped under `### Fixed` / `### Added`. That top section currently has only a `### Fixed` group. Add an `### Added` group to it (directly under the `## [2026.6.3]` heading, before `### Fixed`) containing exactly this bullet:

```markdown
### Added
- Add change history for `settings.key_value` and `settings.geometry_columns_join`. Each table now has a mirrored `*_history` table populated by an `AFTER` trigger (`settings.history_trigger()`) that records every INSERT/UPDATE/DELETE with the operation, DB user, and timestamp.
```

If by the time you implement this a newer version section has been added at the top, put the `### Added` entry under that newest section instead.

- [ ] **Step 4: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: note settings history tracking in CHANGELOG

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Notes for the implementer

- **Querying history later:**
  ```sql
  SELECT history_id, history_operation, history_db_user, history_timestamp, value
  FROM   settings.key_value_history WHERE key = 'someKey' ORDER BY history_id;
  ```
- **Production rollout** happens when `app/migration/run.php` runs (it applies `Sql::get()` to every customer DB). No separate deploy step is needed beyond merging this change.
- **No backfill:** existing rows are not seeded as initial `I` snapshots (deliberate, per spec). If wanted later, add one `INSERT INTO settings.<x>_history SELECT *, nextval(...), 'I', current_user, now() FROM settings.<x>` per table — but mind the audit-column count.
- **Adding a future column** to a source table: add the identical column to its `*_history` table (one `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`) and it flows automatically; the trigger function is never touched.
