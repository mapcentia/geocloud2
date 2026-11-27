# Layer QML in v4 — design

Date: 2026-11-27. Size: a small change. Approved by the user as proposed.

## Intent

GC2 App needs to read and write a layer's QML (QGIS style) through v4, as the
old admin GUI does through `/controllers/layer/records`. The user also wants a
switch between drawing with the QML and drawing with the MapServer classes that
does not delete the QML; today the only way back to MapServer is to delete it.

## How it works today

- `settings.geometry_columns_join.qml` holds the style text.
- Saving a non-empty `qml` (Table::updateRecord) writes a QGIS project for the
  layer (`app/wms/qgsfiles/<db>_<schema>_<table>_<geom>.qgs`, Qgisfile::project)
  and sets the layer's `wmssource` to a QGIS Server URL for it.
- What decides who draws is `wmssource`, not `qml`: with the project as source
  the mapfile layer is a cascaded WMS layer and QGIS draws; the layer's classes,
  styles and labels are stored but unused, and its legend is one QGIS picture.
- Nothing clears `wmssource` when the QML is emptied; the old GUI sends
  `wmssource` itself.

## Approaches

1. **Two fields, `qml` and `use_qml`, with `use_qml` derived from the source
   (chosen).** No new column: "in use" is "the layer's source is its own
   generated project". Stays consistent with layers set up in the old GUI.
2. A stored boolean column. Would have to be kept in step with `wmssource`,
   which the old GUI also writes; two sources of truth.
3. Expose `wmssource` and let the client manage it. Leaks a server-internal URL
   and file path into the API and makes the client reproduce server logic.

## Design

On the v4 layer resource (`GET`/`PATCH /api/v4/layers/{layer}`), top level, next
to `properties` and `classes` (they are not part of the `def` JSON that
`properties` is):

| Field | Type | Meaning |
|---|---|---|
| `qml` | string \| null | The stored QML text. Storing it does not change the drawing. |
| `use_qml` | boolean | true: QGIS Server draws with the QML. false: MapServer draws with the classes. |

`PATCH` rules (`app\models\LayerQml::plan`):

- `qml` given: must be well-formed XML, else `400 INVALID_QML`. `null` or an
  empty string removes it.
- `use_qml: true` needs a QML (stored or in the same request), else
  `400 QML_MISSING`; a point, line or polygon layer, else
  `400 QML_UNSUPPORTED_GEOMETRY`; and no other source on the layer (external
  WMS, uploaded QGIS project), else `409 EXTERNAL_SOURCE`.
- `use_qml: false` keeps the QML, clears the project source and removes the
  project file; MapServer draws with the classes again.
- `use_qml` absent: the layer stays as it is. A changed QML on a layer that
  uses it rewrites the project; removing the QML of such a layer switches it
  off.
- Neither field in the request: the QML is untouched.
- Only the database owner may send them (`403 SUPER_USER_ONLY` otherwise): a
  QML becomes part of a QGIS project on the server. Reading is as for the rest
  of the layer resource.
- `POST /api/v4/layers` does not take them.

Afterwards the mapfiles are rewritten (as for any layer PATCH) and the layer's
cached columns, meta, legend and authentication entries are cleared.

## Notes

- Older servers reject the unknown fields with 400; clients send them only to a
  server that has them (`qml` present in `GET`).
- Cached tiles are not cleared by the switch, as for other style changes.
- A layer that is drawn from an external source reports `use_qml: false`.
- Well-formed XML that is not a usable QML is stored; what QGIS Server then
  draws is not validated.

## Related fixes on the same day

- The OWS proxy took a layer's source from a settings row that is not the
  layer (`schema.table` without a column part), so a QML layer with a filter on
  the request (LIMIT rule, FILTERS, versioning, labels off) failed with "Filter
  could not be applied". `SourceResolver::pickWmssource` now takes the geometry
  row.
- A layer update did not clear cached legends.
