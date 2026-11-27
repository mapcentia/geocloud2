# Legend API in v4 — design

Date: 2026-11-27. Branch: `feat/legend-v4`. Size: a small change.

## Intent

Port `/api/v1/legend` to v4. The v1 API has no access control (anyone gets the
legend of any layer) and draws the icons with PHP MapScript on the GC2 node, so
it cannot follow a MapServer that runs on another node (`App.php` >
`mapServer.host`).

## Requirements (from the user)

- Legend access follows access to the layer.
- A layer the caller may not read is **left out of the result**; the request
  does not fail.
- Geofence DENY/LIMIT rules do not prevent access to a legend.
- The pictures come from the MapServer `mapServer.host` points at.
- JSON only. The `html` and `png` outputs of v1 are not ported.
- Hint from the user: MapServer has a call that returns only the graphic; GC2
  used it while MapScript was unavailable under PHP 7. That is
  `mode=legendicon&icon=<layer>,<class index>` (removed from v1 in c4aa1868).

## Approaches

1. **MapServer CGI `mode=legendicon` over HTTP (chosen).** One small PNG per
   class, drawn by MapServer from the schema's WMS mapfile on the MapServer
   node. No MapScript. About 3 ms per icon on the dev container.
2. WMS `GetLegendGraphic` with `RULE=<class name>`. Standard, but returns the
   icon with its label and addresses classes by name, which is not unique.
3. PHP MapScript, as v1. Needs the mapfile and the data connection on the GC2
   node; cannot follow `mapServer.host`. Rejected by the requirement.

## Design

**Route.** `GET /api/v4/legends/database/{database}/{layers}`,
`Scope::PUBLIC`, methods `GET` and `OPTIONS`. The database is in the path
because anonymous callers have no token (same reason as the v4 OWS route).
`layers` is a comma separated list of `schema.table`; a bare `schema` stands
for all layers of that schema (as v1). Each name must match
`^[\p{L}\p{N}_\-]+(\.[\p{L}\p{N}_\-]+)?$`, else `400 INVALID_REQUEST`.

**Identity.** `PublicIdentity::resolve()`, shared with OWS/WFS/OGC: Bearer
token (must be for the database in the path), HTTP Basic (viewer password),
trusted address, or anonymous. Presented but wrong credentials are refused
(401), as on OWS. Missing credentials are never challenged.

**Access per layer** (`LayerGate::canRead`, a boolean; never throws for a
missing privilege):

| Layer | Who gets the legend |
|---|---|
| Not a registered layer | nobody (left out) |
| Authentication below `Read/write` | everybody |
| `Read/write` | owner; sub-user with read privilege (own or inherited); trusted address. Not anonymous. |

Geofence rules are not consulted.

**Response.** Always a bare JSON array, in the order asked for, a layer at most
once. It may be empty. A single layer is still an array: with layers being left
out, "one id, one object" has no answer for a layer without access. This is a
deliberate deviation from the v4 "one of" convention.

```json
[{"id": "my_schema.roads", "title": "Roads",
  "classes": [{"name": "Motorvej", "expression": "'[type]'='m'", "img": "<base64 PNG>"}]}]
```

- `title`: the layer title, or the table name when it has none.
- `classes`: the named classes in mapfile order (ascending `sortid`). A class
  without a name has no entry, as in MapServer's own legends and v1.
- `expression`: `null` when the class has none.
- `img`: base64 PNG without a `data:` prefix; `null` when it could not be drawn.
- A layer whose legend comes from elsewhere gets one entry with `name: null`
  and the fetched picture re-encoded as PNG: the layer's own `legend_url`, a
  QGIS project (asked on QGIS Server on the MapServer node) or an external WMS
  (`GetLegendGraphic`). v1 named that entry `_gc2_wms_legend`.

**Class names and icons.** Names and expressions come from the layer's stored
classes (the settings row the mapfile layer is written from; a left-over
`gc2_non_postgis` row of the same table is ignored). A class is tied to its
icon by position: index *i* in `sortid` order is MapServer's class *i*.

**Remote MapServer.** Icon URL: `<mapServer.host>/cgi-bin/mapserv.fcgi?map=
<mapfile dir>/<db>_<schema>_wms.map&mode=legendicon&icon=<schema.table>,<i>`,
built from the same helpers as the OWS proxy (`Proxy::mapservUrl()`,
`Proxy::wmsMapfilePath()`). MapServer reports errors as `200 text/html`, so only
an `image/*` answer counts.

**Cache.** Per layer (`<db>_legend_v4_<md5(rel)>`), not per user: access is
decided before the cache is read. Cleared with the layer's other caches by the
existing `<db>*_legend_*` pattern. A legend with a missing picture is not cached.

## Differences from v1

- Icons are 18×12 (the mapfile's `LEGEND KEYSIZE`), not 17×17. MapServer 8
  ignores a key size given in the URL; changing `KEYSIZE` in the mapfile would
  also change existing `GetLegendGraphic` output, so it is left alone.
- No `v:` prefix on layer names, `,` instead of `;` between them, no
  `gc2_group`.
- An unknown layer is left out instead of answered with null classes.
- v1 itself is unchanged.

## Not verified

- A MapServer on another node: the URL building is unit tested against
  `mapServer.host`, but the dev setup has MapServer on the same node.
- A layer with `legend_url` and an external (non-QGIS) WMS layer: the only ones
  in the dev database are protected or stale. QGIS-backed layers were checked.

## Found on the way (fixed on this branch)

`SourceResolver::fromLayers` picked a layer's `wmssource` with
`ORDER BY _key_ LIMIT 1`; when a table also has a `gc2_non_postgis` row, that
row sorts first and the source came back empty, so the OWS proxy treated a
QGIS or external WMS layer as an ordinary one. `SourceResolver::pickWmssource`
now takes the first geometry row and falls back to the non-spatial row only
when it is the only one.

## Downstream

New operation `getLegend` (tag `Legend`, schemas `Legend`, `LegendClass`):
SDK, MCP server, website docs, GC2 App, Vidi.
