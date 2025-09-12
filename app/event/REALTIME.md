# GC2 Real‑Time Event System (app/event)

This document explains how to run, integrate with, and extend the GC2 real‑time system located in `app/event`. It targets developers who want to consume database change events and/or issue live SQL queries over WebSocket.


## Overview

The real‑time system exposes a lightweight HTTP/WebSocket server built on Amp that provides:

- A WebSocket endpoint for authenticated clients: `/broadcast`
  - Accepts a JWT token and (for sub‑users) a list of relations they need access to.
  - Receives SQL text messages from the client and returns the query result as JSON.
  - Broadcasts batched Postgres notifications to all connected clients subscribed to the same database.
- A simple health endpoint: `/ping` → `pong`.

In the background, a Postgres listener consumes NOTIFY events from each GC2 database, batches them, optionally enriches them with full rows, and forwards the payload to connected WebSocket clients.


## Architecture

Key components:

- `app/event/main.php`
  - Starts an Amp HTTP server on port 80 (inside the container).
  - Routes:
    - `GET /broadcast` (WebSocket) handled by `WsBroadcast`.
    - `GET /ping` health check.
  - Loads `app/event/pglistner.php`, which starts the Postgres listener and batching logic.

- `app/event/sockets/WsBroadcast.php`
  - Validates the client token via `ValidateTokenTask`.
  - For sub‑users, enforces relation access via `AuthTask` (requires `rel` query param).
  - Attaches client properties (db, uid) and keeps the socket open.
  - On incoming text messages (SQL), runs the query asynchronously with `RunQueryTask` and replies with JSON.
  - Exposes a `gateway` used by the listener to push notifications.

- `app/event/pglistner.php`
  - Discovers GC2 databases (`DatabaseTask`), then for each DB:
    - Connects to Postgres.
    - LISTENs on channel `_gc2_notify_transaction`.
    - Batches payloads per DB using size/time thresholds and periodically flushes.
    - On flush, enriches payloads with `PreparePayloadTask` and broadcasts to all clients connected to that DB.

- `app/event/tasks/*.php`
  - `ValidateTokenTask`: Validates JWT, yields parsed claims like `database`, `uid`, `superUser`.
  - `AuthTask`: For sub‑users, checks access to a relation (`schema.table`).
  - `RunQueryTask`: Executes arbitrary SQL against the user DB and returns JSON (types converted).
  - `DatabaseTask`: Returns a filtered list of GC2 databases to monitor.
  - `PreparePayloadTask`: Groups/expands batched NOTIFY events and pulls full rows for INSERT/UPDATE keys.

- Container tooling
  - `app/event/Dockerfile`: PHP 8.3 ZTS + Amp stack, dev watcher, etc.
  - `app/event/dev-entrypoint.sh`: Hot‑reload during development using watchexec.
  - `docker/docker-compose.yml`: Defines the `event` service exposing port 8088 on the host.


## Running the service

The recommended way is via Docker Compose along with the rest of GC2:

1. Ensure you’re in the repo root and start services:
   - `docker-compose` file lives in `docker/` of this repository; many setups use a parent compose repository too. If you use this repo’s compose:
   - Run: `docker compose -f docker/docker-compose.yml up --build`

2. Relevant ports (host → container):
   - Event service (this service): `8088 → 80`
   - GC2 Admin: `8080 → 80`

3. Health check:
   - `curl http://localhost:8088/ping` should return `pong`.

The event service also relies on PostGIS being up (see the `postgis` service in compose). The listener will continuously retry connecting to each database if they’re not yet available.


## Authentication and connection

The WebSocket endpoint is:

- ws://localhost:8088/broadcast?token=...&rel=...

Query parameters:

- `token` (required): A GC2 JWT. It must include at least `database` and user identity claims.
- `rel` (required for sub‑users only): Comma‑separated list of relations the client needs to access (e.g., `public.parcels,public.addresses`). Superusers don’t need `rel`.

Connection flow:

1. Server validates the token in a worker (`ValidateTokenTask`).
2. If `superUser` is false, server enforces per‑relation access for each entry in `rel` using `AuthTask`. Missing or unauthorized rels result in an error message and socket close.
3. On success, the client is registered with properties `{ db, uid, joinedAt }`.

Errors are returned as JSON and the socket is closed. Examples:

- Missing token:
  `{ "type": "error", "error": "missing_token", "message": "Missing token" }`
- Invalid token:
  `{ "type": "error", "error": "invalid_token", "message": "..." }`
- Sub‑user missing rel:
  `{ "type": "error", "error": "missing_rel", "message": "Sub-users must specify a rel parameter" }`
- Unauthorized rel:
  `{ "type": "error", "error": "not_allowed", "message": "Not allowed to access this resource: <rel>" }`


## Sending SQL over WebSocket

Once connected, the client can send a text message containing a SQL statement. The server executes it with `RunQueryTask` against the authenticated user’s database and replies with a JSON object similar to what the GC2 SQL API returns.

Example (client → server):

- Message: `SELECT 1 AS ok`  (plain text)

Example response (server → client):

```json
{
  "success": true,
  "data": [{ "ok": 1 }],
  "total_rows": 1,
  "format": "json"
}
```

Notes:
- Types are converted (`convert_types = true`).
- Errors from the SQL execution are returned with fields like `message`, `file`, `line`.


## Receiving database change events

The service listens to Postgres NOTIFY events on channel `_gc2_notify_transaction` for each discovered DB. Notifications are batched per DB and delivered to all connected clients of that DB.

Batching defaults (see `pglistner.php`):

- `batchSize = 10` notifications or
- `timeThreshold = 2` seconds (whichever comes first),
- checked every `timerFrequency = 1` second.

When a batch is flushed for DB `mydb`, a message is sent to each client connected to `mydb`:

```json
{
  "type": "batch",
  "db": "mydb",
  "batch": {
    "mydb": {
      "schema.table": {
        "INSERT": [["key","value"]],
        "UPDATE": [["key","value"]],
        "DELETE": [["key","value"]],
        "full_data": [ { "col1": "val", "col2": 123 } ]
      },
      "another.schema.table": {}
    }
  }
}
```

Event payload format and enrichment:

- Raw NOTIFY `payload` is expected as a comma‑separated list: `OP,SCHEMA,TABLE,KEY,VALUE`.
- `PreparePayloadTask` groups notifications by `schema.table` and operation, and for `INSERT`/`UPDATE` collects unique KEY values to fetch full rows:
  - Builds: `SELECT * FROM {schema}.{table} WHERE "{key}" IN (<values>)`.
  - Merges the resulting rows into `full_data`.

Important:
- The server broadcasts to all clients connected to that DB, without further per‑table filtering at send time. You should use auth scoping (`rel`) on connect to ensure only authorized relations are requested by sub‑users. If you need additional client‑side filtering, filter messages on the client.


## Client examples

JavaScript (browser):

```html
<script>
  const token = "<your-jwt>";
  const rel = "public.parcels,public.addresses"; // required for sub-users
  const ws = new WebSocket(`ws://localhost:8088/broadcast?token=${encodeURIComponent(token)}&rel=${encodeURIComponent(rel)}`);

  ws.onopen = () => {
    console.log("connected");
    ws.send("SELECT NOW() AS ts");
  };

  ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    if (msg.type === 'batch') {
      console.log("DB batch:", msg.batch);
    } else {
      console.log("SQL result:", msg);
    }
  };

  ws.onclose = () => console.log("closed");
  ws.onerror = (err) => console.error("ws error", err);
</script>
```

Node (ws):

```js
import WebSocket from 'ws';
const token = process.env.GC2_TOKEN;
const ws = new WebSocket(`ws://localhost:8088/broadcast?token=${encodeURIComponent(token)}`);
ws.on('open', () => ws.send('SELECT 42 AS answer'));
ws.on('message', (data) => console.log(JSON.parse(data.toString())));
```


## Configuration

Environment variables (inherited from compose; also used by GC2 core):

- `POSTGIS_HOST`, `POSTGIS_PORT`, `POSTGIS_USER`, `POSTGIS_PW`, `POSTGIS_DB`
- `POSTGIS_PGBOUNCER` (true/false)
- `MODE_ENV`, `BUILD_ID`

Listener tuning (edit `pglistner.php`):

- `$batchSize` (default 10)
- `$timeThreshold` (seconds, default 2)
- `$timerFrequency` (seconds, default 1)
- `$reconnectDelay` (seconds, default 5)
- Channel name is `_gc2_notify_transaction` by default.

Ports:
- Inside container, the server binds port 80. In compose it is published as `8088` on the host.


## Development workflow

- Build & run locally via compose: `docker compose -f docker/docker-compose.yml up --build event`
- Auto‑reload: the container uses `dev-entrypoint.sh` with `watchexec` to restart the PHP process on changes under:
  - `app/models`, `app/inc`, `app/event`, `app/event/functions`, `app/event/tasks`, `app/event/sockets`
- Entry script can be overridden by setting `PHPSCRIPT` env var (defaults to `app/event/main.php`).


## Security considerations

- Always pass a valid JWT in the `token` query param.
- For sub‑users (non‑`superUser`), include only the relations they should access in `rel`.
- SQL messages are executed as the authenticated database user; apply least privilege and validate inputs where possible.
- Network access is limited within Docker networks; ensure TLS termination is handled at a reverse proxy if exposing publicly.


## Troubleshooting

- `/ping` works but WebSocket fails immediately:
  - Check `token` presence/validity.
  - If sub‑user, ensure `rel` is provided and is authorized.
- No change events received:
  - Verify your DB triggers/logic are issuing NOTIFY on `_gc2_notify_transaction` with the expected payload format.
  - Confirm the event container logs show it is “Connected and listening on channel … for DB …”.
- Batch messages contain no `full_data` rows:
  - Ensure your payload includes `INSERT` or `UPDATE` events with a key/value that can be used in the `IN` clause.
- Connection drops:
  - Check container logs for errors; the service logs worker PIDs and errors to stdout.


## Extending

- Add custom socket handlers under `app/event/sockets/` and additional routes in `main.php`.
- Implement new worker tasks under `app/event/tasks/` using Amp Parallel Worker `Task`.
- Adjust DB discovery rules in `DatabaseTask` (skip list, filters).
- Modify `PreparePayloadTask` to change grouping or enrichment behavior.
