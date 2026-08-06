# Dynamic symbols and labels for WMS classes

**Date:** 2026-08-06
**Branch:** dev/multiple_styles
**Status:** Approved design

## Problem

The class style editor supports exactly two symbols (Symbol1 = un-prefixed keys, Symbol2 = `overlay*`-prefixed keys) and two labels (`label_*`, `label2_*`). MapServer itself allows any number of STYLE and LABEL blocks per CLASS. Users should be able to add an arbitrary number of symbols and labels per class, ordered by a `sortid`, through a UI that works like the existing Classes grid (Add/Delete + list + property grid).

## Decisions made

- **Data format:** real arrays (`styles: []`, `labels: []`) inside each class object, with lazy conversion of the legacy flat format on read. No one-time DB migration.
- **Consumers:** only GC2 itself (admin UI and mapfile generation) reads the class JSON — the format can change freely, no legacy keys need to be emitted by the API.
- **UI:** each symbol/label row has *Sort id* and an optional *Name* (UI-only, never written to the mapfile).
- **Persistence:** client-managed arrays saved through the existing `PUT /controllers/classification/index/{table}/{classId}` endpoint. No new REST sub-resources.
- **Mapfile rendering must work on non-converted class JSON** read raw from the database — conversion is applied in-memory in the rendering path itself.

## 1. Data model

Each class in `settings.geometry_columns_join.class` (JSON array of class objects) gains two arrays replacing the flat style/label keys:

```json
{
  "sortid": 10,
  "name": "My class",
  "expression": "...",
  "class_minscaledenom": "", "class_maxscaledenom": "",
  "leader": false, "leader_gridstep": "", "leader_maxdistance": "", "leader_color": "",
  "styles": [
    { "sortid": 10, "name": "Fill", "color": "#008000", "outlinecolor": "", "symbol": "",
      "size": "", "width": "", "angle": "", "gap": "", "style_opacity": "", "pattern": "",
      "linecap": "", "geomtransform": "", "minsize": "", "maxsize": "",
      "style_offsetx": "", "style_offsety": "", "style_polaroffsetr": "", "style_polaroffsetd": "" }
  ],
  "labels": [
    { "sortid": 10, "name": "Road name", "on": true, "force": false, "text": "[name]",
      "minscaledenom": "", "maxscaledenom": "", "position": "", "size": "", "color": "",
      "outlinecolor": "", "buffer": "", "repeatdistance": "", "angle": "",
      "backgroundcolor": "", "backgroundpadding": "", "offsetx": "", "offsety": "",
      "font": "", "fontweight": "", "expression": "", "maxsize": "", "minfeaturesize": "" }
  ]
}
```

Notes:

- Keys inside `styles[]`/`labels[]` are **un-prefixed** (`color`, not `overlaycolor`; `text`, not `label_text`).
- `name` is optional and only used in the UI list.
- `on` on a label corresponds to the current `label`/`label2` checkbox. Styles have no on/off flag — a style either exists in the array or it does not.
- Base-level class keys (`sortid`, `name`, `expression`, `class_minscaledenom`, `class_maxscaledenom`, `leader*`) are unchanged.

### Legacy conversion (`normalizeClass`)

A **static, shared, idempotent** helper — `Classification::normalizeClass(array $class): array`:

- If the class already has a `styles` key, it is returned unchanged (new format passes through).
- Otherwise the legacy flat keys are converted in-memory:
  - Non-empty base style keys → `styles[0]` (`sortid` 10, `name` "Symbol 1").
  - Non-empty `overlay*` keys → `styles[1]` (`sortid` 20, `name` "Symbol 2"). If no overlay key holds a value, no `styles[1]` entry is created (no empty "Symbol 2").
  - `label`/`label_*` → `labels[0]` (`sortid` 10), `label2`/`label2_*` → `labels[1]` (`sortid` 20) — each only created when the label is enabled or at least one of its keys is non-empty.
  - The legacy flat keys are removed from the returned object.
- Only the new format is ever written. Legacy JSON remaining in the DB is converted on every read until the class is next saved from the editor; there is no deadline for conversion — the legacy format stays permanently readable in both the editor path and the mapfile path.

## 2. Backend — `app/models/Classification.php`

- `getAll()`: run `normalizeClass()` on each class before returning.
- `get($id)`: same logic on top of `getAll()`; the injected flat-key defaults (`color`, `size`, `width`, `label_text`, `label2_text`, …) are dropped. Only `name` keeps a default ("Unnamed Class"). A newly added class starts with empty `styles`/`labels` arrays and the user adds symbols/labels explicitly in the UI.
- `update($id, $data)`: unchanged mechanism — key-level merge on the class object. When the frontend sends `styles`/`labels`, those arrays replace the stored ones wholesale.
- `createClass()` (wizard): emits the new format — `styles[0]` (base), `styles[1]` (overlay, only when the wizard data carries overlay values), `labels[0]`.
- `mergeClasses()`: unchanged — PHP `===` array comparison works for the nested arrays; the wizard-cache logic still preserves externally modified properties per class. An externally modified `styles` array is preserved as a whole.
- `copyClasses()`: unchanged (copies raw JSON).

## 3. Mapfile rendering — `app/models/Mapfile.php`

- `renderClasses()` calls `Classification::normalizeClass()` on every class **as the very first step**, before rendering. The mapfile path reads `class` JSON raw from the DB row (`sortClasses($row['class'])`, `Mapfile.php:103`) without going through `Classification::getAll()`, so this guarantees correct rendering of non-converted classes — regardless of whether the layer was ever opened in the new editor, and regardless of whether the JSON comes from `class`, the wizard/`class_cache` flow, or an external tool still writing the legacy format.
- `renderStyle(array $style): string`: takes a style object directly instead of (class, prefix). `minsize`/`maxsize` are rendered for **all** styles (currently primary-only; MapServer supports them per STYLE).
- `renderLabel(array $label, string $layerName, int $n): string`: takes a label object; `$n` is only used in the `#START_LABEL{n}_...` comment markers. The Label2 background quirk (outline/width only when padding set) is normalized to the Label1 behavior: when `backgroundcolor` is set, always emit `OUTLINECOLOR` and `WIDTH` (default 1).
- `renderClasses()`: loops `styles` and `labels` sorted numerically by `sortid` (stable sort — equal `sortid` preserves array order). Both `renderStyle` and `renderLabel` know only the new format.

## 4. Frontend

### `public/js/admin/admin.js`

- The five fixed tabs a3/a8/a9/a10/a11 (Base, Symbol1, Symbol2, Label1, Label2) are replaced by three: **Base** (a3, unchanged content), **Symbols** (a8), **Labels** (a9). a10/a11 and all references to `wmsClass.grid4`/`grid5` are removed.
- The **Update** button collects: the Base property grid source + the current `styles`/`labels` arrays held by `wmsClass`, and PUTs them as today (`/controllers/classification/index/{table}/{classId}`), followed by `writeFiles()` and store reloads.

### `public/js/admin/editwmsclass.js` (`wmsClass.init`)

- **Base tab (a3):** as today — `sortid`, `name`, `expression`, scale denominators, leader fields.
- **Symbols tab (a8):** master/detail —
  - Top: a small `EditorGridPanel` listing the class's styles with inline-editable *Sort id* and *Name* columns, toolbar with **Add**/**Delete**.
  - Bottom: a property grid with the style fields, using the same editors/renderers as today (ColorField, comboboxes fed by `wmsLayer.numFieldsForStore`, spinners, etc.).
  - Clicking a row loads that style into the property grid; property-grid edits write back to the local array entry.
- **Labels tab (a9):** same master/detail pattern with the label fields (including the `on` checkbox, position combo, font combos, `wmsLayer.fieldsForStoreBrackets` for text).
- **Add:** appends `{sortid: <highest existing + 10>, name: ""}` to the local array, selects the new row, and saves immediately via the same PUT as Update (keeps DB and mapfile in sync via `writeFiles`).
- **Delete:** removes the selected row after a confirm dialog, then saves immediately.
- The editor/renderer definitions for style fields and label fields are factored into two builder functions (`buildStyleEditors()`, `buildLabelEditors()`) so they are defined once — today grid2/grid3 and grid4/grid5 are copy-paste duplicates.

## 5. Edge cases

- Empty `styles` → CLASS without STYLE blocks (valid in MapServer); empty `labels` → no LABEL blocks.
- Legacy classes with no non-empty overlay keys convert to a single style (no empty "Symbol 2").
- Equal `sortid` values: stable sort preserves array order.
- `null` values are never sent to the client (existing `get()` behavior applies to nested objects too).

## 6. Testing

Codeception tests run inside the `docker-gc2core-1` container:

- Unit tests for `normalizeClass()`: legacy → new conversion (base only, base + overlay, labels on/off, already-new-format passthrough/idempotency).
- Unit tests for `renderClasses()` with 3+ styles and 3+ labels, and with raw legacy-format input (the non-converted path).
- Unit test for wizard `createClass()` output shape.
- Manual verification: open a layer with legacy classes in the admin UI, confirm conversion, add/delete symbols and labels, confirm mapfile output and rendered WMS.
