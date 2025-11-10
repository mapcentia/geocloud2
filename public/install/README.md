# GC2 Install System

This document explains how to use the Centia.io installer located in `public/install/`. It helps you:

- Verify the environment and list available PostgreSQL databases.
- Install required PostGIS extensions and the Centia.io `settings` schema into a selected database.
  - The settings schema is the only required schema for Centia.io to use the database.
- Run post-install SQL scripts defined by `app/migration/Sql::get()`.
- Create the global `mapcentia` user database (if missing) and run its migrations.
- Elevate PostgreSQL credentials when necessary (insufficient privileges or connection failures).
- Create the owner user for a newly prepared database.

All installer pages are accessible via your browser.


## Contents
- Overview
- Prerequisites
- Configuration and credentials
- Pages and workflow
  - 1) Index (overview): `index.php`
  - 2) Prepare a database for GC2: `prepare.php?db=...`
  - 3) Create the global user database: `userdatabase.php`
- Handled error codes and elevation prompts
- Typical usage scenarios
- Troubleshooting
- Security notes
- FAQ


## Overview
The install system lets you set up GC2 on an existing PostgreSQL/PostGIS cluster. It performs the following:

- Checks that your server environment is ready (PHP, MapScript, writable directories).
- For each non-system database, detects whether PostGIS and the `settings` schema are present.
- Allows you to initialize databases by creating PostGIS extensions and the settings schema.
- Runs a set of post-install SQL scripts needed by Centia.io. The first and last scripts are critical and must succeed (re-run is supported).
- After a successful prepare, prompts you to create the owner user for that database.
- Ensures the global `mapcentia` database exists and is migrated (stores Centia.io users and related metadata).


## Prerequisites
- PostgreSQL reachable from the Centia.io web server.
- PostGIS extension available in the cluster (extensions can be created by a privileged user).
- The Centia.io http application deployed.
- Web server user can write to:
  - `app/tmp`

The index page (`/install/index.php`) shows green/red checks for these items.


## Configuration and credentials
The installer uses connection parameters from environment variables (preferred) or 
`app/conf/Connection.php` as a fallback. Environment variables:

- `POSTGRES_HOST` (e.g., `postgres`)
- `POSTGRES_DB` (default: `postgres`)
- `POSTGRES_USER` (e.g., `centia`)
- `POSTGRES_PORT` (default: `5432`)
- `POSTGRES_PASSWORD`
- `POSTGRES_PGBOUNCER` (default: `false`)

If the configured credentials lack privileges to create extensions, schemas, or databases, 
the installer will show a form where you can enter elevated PostgreSQL credentials 
(typically a superuser or a role with the required rights). 
Your inputs are used only for the current operation and page reload.


## Pages and workflow
### 1) Index (overview): `public/install/index.php`
Open in your browser: `/install`

What it does:
- Displays PHP mode (mod_apache vs CGI/FastCGI).
- Checks write access to `app/wms/mapfiles` and `app/tmp`.
- Checks if MapScript is installed.
- Lists PostgreSQL databases and, for each non-system DB, indicates:
  - PostGIS: OK / Missing
  - GC2 settings schema: OK / Missing
- If `mapcentia` database is missing, shows a warning with a button to create it (`userdatabase.php`).
- For databases missing requirements, shows an “Install” action that links to `prepare.php?db=<name>`.

System databases that are ignored by the prepare action include: `template0`, `template1`, `postgres`, `postgis_template`, `mapcentia`, `gc2scheduler`, `rdsadmin`.


### 2) Prepare a database for GC2: `public/install/prepare.php?db=<yourdb>`
Use this page to initialize a specific database for GC2.

Steps performed:
1) Connect to the selected database using the configured credentials. If connection fails, an elevation form is displayed to retry with different credentials.
2) Create PostGIS extensions (uses `CREATE EXTENSION postgis_raster CASCADE`).
   - If insufficient privileges (SQLSTATE `42501`), you will be prompted for elevated credentials.
3) Create the GC2 settings schema by running the SQL from `public/install/sql/createSettings.sql`.
   - Duplicate schema (SQLSTATE `42P06`) is treated as already installed and not a fatal error.
   - Insufficient privileges (SQLSTATE `42501`) prompts for elevated credentials.
4) Run post-install SQL scripts defined by `app/migration/Sql::get()`. For each script the page shows a badge:
   - OK badges are green; the first and last scripts are highlighted in blue on success.
   - SKIP badges are grey (or red for the first/last script when they fail).
   - The first and last scripts are critical. If either fails, the page shows a red alert and a “Re-run scripts” button to retry (reloads the page). It may take a couple of re-runs.
5) On overall success (first and last scripts are OK), a green success message appears and you are prompted to create the owner user for this database:
   - Provide an email and password.
   - The user’s `name` is set to the database name.
   - This creates the owner (super) user entry within Centia.io.

You can always return to the overview with the “Back to overview” button.


### 3) Create the global user database: `public/install/userdatabase.php`
This page ensures the global `mapcentia` database exists and applies migrations.

Steps performed:
1) Connect to the cluster (default database).
   - If connection fails, an elevation form is shown.
2) Create `mapcentia` if missing (using `public/install/sql/createUserDatabase.sql`).
   - Duplicate database (SQLSTATE `42P04`) is treated as already existing.
   - Insufficient privileges (SQLSTATE `42501`) triggers the elevation prompt.
3) Connect to `mapcentia`.
   - Connection failure (SQLSTATE `08006`) triggers the elevation prompt.
4) Ensure the `users` table exists (using `public/install/sql/createUserTable.sql`).
5) Run GC2 migrations for the `mapcentia` database (`app/migration/Sql::mapcentia()`).
6) Finish with a success message and a link back to the overview.

Note: For `userdatabase.php` there is no special first/last script requirement.


## Handled error codes and elevation prompts
The installer inspects exception codes/messages and reacts to specific SQLSTATEs:
- `42501` insufficient_privilege: Shows an elevation form to provide higher-privileged PostgreSQL credentials.
- `42P06` duplicate_schema: Treated as a non-fatal “already exists” condition during schema creation.
- `42P04` duplicate_database: Treated as a non-fatal “already exists” condition when creating `mapcentia`.
- `08006` connection_failure: When trying to connect to `mapcentia`, prompts for credentials to retry.


## Typical usage scenarios
- Fresh setup:
  1) Visit `/install/index.php` and verify environment checks.
  2) If prompted, click “Create database” to create `mapcentia` and run its migrations.
  3) For each data database you want to enable for Centia.io, click “Install” to open `prepare.php?db=<name>` and follow the steps.
  4) After a successful prepare, create the owner user when prompted.

- Missing `settings` schema in an existing PostGIS database:
  1) From the index, click “Install” for the target DB.
  2) If PostGIS extensions or privileges are missing, provide elevated credentials when prompted.
  3) Re-run the post-install scripts if the page instructs you to.
  4) Create the owner user.


## Troubleshooting
- I get insufficient privileges (SQLSTATE 42501):
  - Provide a PostgreSQL superuser or a role that can create extensions, schemas, and (for `userdatabase.php`) databases.

- The first/last post-install scripts fail in `prepare.php`:
  - Use the “Re-run scripts” button. It can take a couple of re-runs depending on ordering or dependencies.
  - Check PostgreSQL logs for the exact failing SQL.

- Connection to `mapcentia` fails (SQLSTATE 08006):
  - Ensure the DB exists and is network-accessible. Provide elevated credentials if required.

- Directories are not writable:
  - Adjust file system permissions so the web server user can write to `app/wms/mapfiles` and `app/tmp`.

- MapScript not installed:
  - Install MapScript to enable related features. The index page merely reports the status; GC2 core may still run without it depending on features used.


## Security notes
- Only provide elevated (superuser) credentials when necessary and in a secure environment.
- The installer uses provided credentials for the current page action; do not reuse privileged credentials for routine app operations.
- Once installation is done, consider restricting access to the `/install/` directory or removing it from publicly accessible locations.


## FAQ
- Where do I configure default connection parameters?
  - Via environment variables (preferred) or by setting values in `app/conf/Connection.php`.

- Which databases are considered system databases and skipped for prepare actions?
  - `template0`, `template1`, `postgres`, `postgis_template` and certain GC2 internals like `mapcentia`, `gc2scheduler`, `rdsadmin`.

- Can I run the installer multiple times?
  - Yes. It is idempotent with regard to key operations (duplicate schema/database cases are handled) and provides re-run options where needed.

- Does the installer support Docker?
  - If you run GC2 via Docker, ensure the Postgres container exposes the required environment variables (see `docker/conn.env` and `docker/docker-compose.yml`) and that the web container can reach it.


## File map
- `public/install/index.php` — Overview and checks, per-database status, and links to actions.
- `public/install/prepare.php` — Prepare a specific database: create extensions, GC2 schema, run post-install scripts, and create owner user.
- `public/install/userdatabase.php` — Create and migrate the global `mapcentia` database.
- `public/install/sql/createSettings.sql` — SQL for creating the GC2 settings schema.
- `public/install/sql/createUserDatabase.sql` — SQL for creating the `mapcentia` database (used by `userdatabase.php`).
- `public/install/sql/createUserTable.sql` — SQL for creating the base `users` table in `mapcentia`.