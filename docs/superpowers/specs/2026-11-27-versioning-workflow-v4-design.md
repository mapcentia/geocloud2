# Versioning and workflow in v4 — design

Date: 2026-11-27. Branch: `feat/versioning-workflow-v4`. Scope chosen by the
user: everything (switches, roles, the work list and the approval step).

## Intent

Versioning and workflow only exist in the legacy session controllers
(`/controllers/table/versions`, `/controllers/table/workflow`,
`/controllers/workflow`, layer roles). A v4 client can edit a versioned or
workflow table through the Feature API and WFS-T, but cannot switch either on
or off, give roles, see what is waiting or approve it.

## How it works (unchanged)

- **Versioning** is five `gc2_version_*` columns on the table. With them, an
  edit keeps the previous state as an ended version (a row with
  `gc2_version_end_date`).
- **Workflow** is `gc2_status` (1 drafted, 2 reviewed, 3 published) and
  `gc2_workflow` (hstore of signatures) on a versioned table. A sub-user's
  role on the table (author, reviewer, publisher) decides the status their
  edits get; users without a role read published features only.
- Every edit is logged in `settings.workflow`.

## Design

### Switches on the table resource

`GET /api/v4/schemas/{schema}/tables/{table}` returns `versioning` and
`workflow` (booleans). `PATCH` takes them; database owner only
(`403 SUPER_USER_ONLY`), tables only (`409 NOT_A_TABLE`).

- Workflow needs versioning: `workflow: true` without it is
  `409 VERSIONING_REQUIRED` (both may be enabled in one request), and
  `versioning: false` while workflow stays is `409 WORKFLOW_ENABLED`. The
  request is checked as a whole before anything changes.
- **Disabling versioning deletes the ended versions**, then drops the columns.
  The legacy endpoint only drops the columns, which leaves every old version
  as a live duplicate row; it is left as it is. The API documents that the
  history is lost.
- **Disabling workflow** (new) drops the two columns and the table's entries in
  `settings.workflow`. Every row is an ordinary row afterwards.
- Enabling workflow marks existing rows as published (as the legacy endpoint).

### Roles

`GET`/`PATCH /api/v4/schemas/{schema}/tables/{table}/workflow-roles`.
Items `{subuser, role}`, role one of `none`, `author`, `reviewer`,
`publisher`. `PATCH` takes one or a list and merges; owner only. Any
authenticated user of the database may read them. Stored in the existing
`roles` column, on every settings row of the table.

### Items and approval

`GET`/`PATCH /api/v4/schemas/{schema}/tables/{table}/workflow-items/[gid]`.

- An item is the latest logged step of a feature: `gid`, `version_gid`,
  `status`, `status_name`, `operation`, `user`, `author`, `reviewer`,
  `publisher`, `created`.
- `GET` without id lists what the caller can still move forward (status below
  the caller's level; deleted features excluded); `?all=true` lists all. With
  id(s): that item, or `404 WORKFLOW_ITEM_NOT_FOUND`.
- `PATCH` with id(s) and no body is the approval: it signs with the caller's
  role and sets that role's status, keeps the previous state as an ended
  version and logs the step. All or nothing for a list. `404
  FEATURE_NOT_FOUND`, `409 WORKFLOW_STATUS_CONFLICT` (already at or past the
  caller's status), `409 VERSION_ENDED`, `409 WORKFLOW_NOT_ENABLED`.
- Access is by role, not by schema ownership: the owner (acting as publisher)
  and sub-users with a role; others get `403 NO_WORKFLOW_ROLE`. These routes
  therefore do not go through `initiate()`.

### Decisions that differ from the legacy GUI

- The owner can approve, as publisher. Legacy refused a user without a role.
- Approving a feature that is already at the caller's status is a conflict.
  Legacy made another version with the same status.
- "Waiting for me" is by status level. Legacy listed what the user had not
  signed.

### hstore

The template database has the `hstore` extension in schema `settings`, which
is not on the search path, so the type, its functions and its operators are
not found by name. That breaks not only enabling workflow but the whole
runtime (WFS-T and reads use `hstore` unqualified). The type is kept; the
migration relocates the extension: `ALTER EXTENSION hstore SET SCHEMA public`.
Columns and data are untouched. Until a database is migrated, enabling
workflow answers `501 HSTORE_NOT_AVAILABLE`.

On the dev server only `template_geocloud` has been relocated (by hand, for
the tests); `mydb` is left for the migration run.

## Not covered

- Rejecting or sending a feature back a step (the legacy GUI has no such step).
- A schema-wide work list: the list is per table.
- Deletes under workflow are listed with `all=true` but cannot be approved.
