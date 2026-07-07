# gc2-function-runner

Data-plane service that executes GC2 Lambda-like functions in a sandbox. It is
the production counterpart to the in-process PHP `LocalFunctionRunner`: running
as its own (privileged) service lets it exec the language runtime **and** a
gVisor sandbox, which the PHP/web-server user cannot.

The PHP control plane talks to it through `app\inc\runners\HttpFunctionRunner`
(`POST /invoke`). Set `functionRunner` to `HttpFunctionRunner::class` and
`functions.runnerUrl` / `functions.runnerToken` in `app/conf/App.php`.

## Protocol

`POST /invoke` (optionally `Authorization: Bearer <GC2_RUNNER_TOKEN>`)

```json
{
  "function": {"runtime":"nodejs20","handler":"index.handler","code":"...","timeout_s":30,"memory_mb":128,"env":{}},
  "event":    {"any":"json"},
  "context":  {"token":"<scoped jwt>","apiBaseUrl":"http://gc2core","uid":"..."}
}
```

Response: `{"status":"succeeded|failed","output":<any>,"logs":"...","error":"...","duration_ms":12}`

`GET /healthz` → `ok`.

Runtimes ship the same per-language agents as the PHP runner
(`agents/node-bootstrap.cjs`, `agents/python-bootstrap.py`, embedded via
`go:embed`). The handler receives `(event, context)`; `context.token` +
`context.apiBaseUrl` let it call back into the GC2 data plane.

## Config (env)

| var | default | meaning |
|-----|---------|---------|
| `GC2_RUNNER_ADDR` | `:8090` | listen address |
| `GC2_RUNNER_TOKEN` | _(none)_ | shared secret; if set, `/invoke` requires the bearer token |
| `GC2_RUNNER_SANDBOX` | _(none)_ | JSON array command prefix wrapping the runtime |
| `GC2_RUNNER_WORKDIR` | OS temp | scratch base dir |
| `GC2_RUNNER_POOL` | _(off)_ | set to `1` to enable warm pools (skip cold start) |
| `GC2_RUNNER_POOL_MAX` | `8` | max concurrent invocations |
| `GC2_RUNNER_POOL_IDLE_PER_KEY` | `4` | max idle warm instances kept per function identity |
| `GC2_RUNNER_POOL_IDLE_TTL_SEC` | `60` | idle instance time-to-live before reaping |

### Warm pools

With `GC2_RUNNER_POOL=1` the service keeps long-lived runtime processes alive,
keyed by function identity (runtime + handler + code + env). A repeat invocation
reuses an idle instance over a persistent stdin/stdout channel instead of
spawning a fresh process — e.g. a Node handler drops from ~110 ms cold to ~1 ms
warm. Each instance serves one invocation at a time; concurrency comes from
running up to `GC2_RUNNER_POOL_MAX` instances. The scoped token/context is sent
per invocation (never baked into the instance); per-call wall-clock timeouts
kill the instance rather than reuse a stuck runtime; idle instances are reaped
after the TTL. The sandbox wraps the long-lived process, so under gVisor each
warm instance *is* a reused sandbox (the Lambda execution-environment model).

`GC2_RUNNER_SANDBOX` placeholders: `{workdir}` `{memory_mb}` `{timeout_s}` `{runtime}`.

- dev / no isolation: unset, or `["unshare","-rn","--"]` (drops network)
- **production (gVisor)** — run each invocation in a runsc container:
  ```
  GC2_RUNNER_SANDBOX='["docker","run","--runtime=runsc","--rm","--network=none",
    "--memory={memory_mb}m","-v","{workdir}:{workdir}","-w","{workdir}","gc2/fn-{runtime}"]'
  ```
  (mount the docker socket into this service), **or** run this whole container
  under runsc and leave the sandbox empty.

## Build & run

```sh
go build -o gc2-function-runner .
GC2_RUNNER_TOKEN=secret GC2_RUNNER_SANDBOX='[]' ./gc2-function-runner
# docker:
docker build -t gc2/function-runner .
docker run -p 8090:8090 -e GC2_RUNNER_TOKEN=secret gc2/function-runner
```

See `docker/docker-compose.yml` for the `function-runner` service.
