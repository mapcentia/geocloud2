# Dynamic Symbols and Labels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the fixed Symbol1/Symbol2/Label1/Label2 class-style tabs with dynamic `styles[]`/`labels[]` arrays (arbitrary count, ordered by `sortid`) with a Classes-like Add/Delete UI in three tabs: Base, Symbols, Labels.

**Architecture:** New class JSON format holds `styles: []` and `labels: []` arrays with un-prefixed keys. A static, idempotent `Classification::normalizeClass()` converts the legacy flat format in-memory; it is applied both in the editor read path (`getAll()`) and as the first step of mapfile rendering (`renderClasses()`), so mapfile generation works on non-converted JSON read raw from the DB. Frontend keeps local arrays and saves via the existing class PUT endpoint.

**Tech Stack:** PHP 8 (Codeception unit tests), ExtJS 3 admin UI, MapServer mapfiles.

**Spec:** `docs/superpowers/specs/2026-08-06-dynamic-symbols-labels-design.md`

## Global Constraints

- Only the new format is ever **written**; the legacy flat format stays permanently **readable** in both the editor path and the mapfile path.
- Keys inside `styles[]`/`labels[]` are un-prefixed: `color`, not `overlaycolor`; `text`, not `label_text`. Exception: the `style_`-named keys keep their names (`style_opacity`, `style_offsetx`, `style_offsety`, `style_polaroffsetr`, `style_polaroffsetd`).
- `name` on a style/label entry is UI-only, never written to the mapfile. Labels have an `on` boolean; styles have no on/off flag.
- Tests run inside the container: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit` (host PHP lacks the curl extension). The repo is mounted at `/var/www/geocloud2`.
- Commit after each task. Branch: `dev/multiple_styles`.

## Key format reference (used by several tasks)

Style entry keys (17 + sortid/name):
`color, outlinecolor, symbol, size, width, angle, gap, style_opacity, pattern, linecap, geomtransform, minsize, maxsize, style_offsetx, style_offsety, style_polaroffsetr, style_polaroffsetd`

Label entry keys (20 + sortid/name/on):
`force, text, minscaledenom, maxscaledenom, position, size, color, outlinecolor, buffer, repeatdistance, angle, backgroundcolor, backgroundpadding, offsetx, offsety, font, fontweight, expression, maxsize, minfeaturesize`

Legacy mapping: base style keys are identical (no prefix); Symbol2 keys are `overlay` + style key (`overlaycolor`, `overlaystyle_opacity`, `overlayminsize`, …); Label1 keys are `label_` + label key plus the on-flag `label`; Label2 likewise with `label2_`/`label2`.

---

### Task 1: `Classification::normalizeClass()` — legacy→new conversion

**Files:**
- Modify: `app/models/Classification.php` (add constants + static method)
- Test: `app/tests/unit/ClassificationFormatTest.php` (create)

**Interfaces:**
- Produces: `public static function normalizeClass(array $class): array` on `app\models\Classification`, plus `public const STYLE_KEYS` and `public const LABEL_KEYS` (arrays of strings as listed above). Idempotent; accepts nested stdClass or arrays; guarantees `styles` and `labels` keys exist as arrays; strips all legacy flat keys; converts `null` values inside entries to `""`.

- [ ] **Step 1: Write the failing tests**

Create `app/tests/unit/ClassificationFormatTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Classification;
use Codeception\Test\Unit;

class ClassificationFormatTest extends Unit
{
    protected UnitTester $tester;

    public function testConvertsLegacyBaseStyleToStylesArray(): void
    {
        $legacy = [
            "sortid" => 10,
            "name" => "My class",
            "expression" => "[type]=1",
            "color" => "#008000",
            "width" => "2",
            "outlinecolor" => "",
        ];
        $result = Classification::normalizeClass($legacy);
        $this->assertCount(1, $result['styles']);
        $this->assertEquals("#008000", $result['styles'][0]['color']);
        $this->assertEquals("2", $result['styles'][0]['width']);
        $this->assertEquals(10, $result['styles'][0]['sortid']);
        $this->assertEquals("Symbol 1", $result['styles'][0]['name']);
        $this->assertArrayNotHasKey('outlinecolor', $result['styles'][0]); // empty keys omitted
        $this->assertArrayNotHasKey('color', $result);   // legacy keys stripped
        $this->assertArrayNotHasKey('width', $result);
        $this->assertEquals([], $result['labels']);
        // base class keys preserved
        $this->assertEquals("My class", $result['name']);
        $this->assertEquals("[type]=1", $result['expression']);
    }

    public function testConvertsLegacyOverlayToSecondStyle(): void
    {
        $legacy = [
            "name" => "c",
            "color" => "#008000",
            "overlaycolor" => "#FF0000",
            "overlaysymbol" => "circle",
            "overlaystyle_opacity" => "70",
            "overlayminsize" => "5",
        ];
        $result = Classification::normalizeClass($legacy);
        $this->assertCount(2, $result['styles']);
        $this->assertEquals(20, $result['styles'][1]['sortid']);
        $this->assertEquals("Symbol 2", $result['styles'][1]['name']);
        $this->assertEquals("#FF0000", $result['styles'][1]['color']);
        $this->assertEquals("circle", $result['styles'][1]['symbol']);
        $this->assertEquals("70", $result['styles'][1]['style_opacity']);
        $this->assertEquals("5", $result['styles'][1]['minsize']);
        $this->assertArrayNotHasKey('overlaycolor', $result);
    }

    public function testNoEmptyOverlayStyleCreated(): void
    {
        $legacy = ["name" => "c", "color" => "#008000", "overlaycolor" => "", "overlaysymbol" => ""];
        $result = Classification::normalizeClass($legacy);
        $this->assertCount(1, $result['styles']);
    }

    public function testConvertsLegacyLabels(): void
    {
        $legacy = [
            "name" => "c",
            "label" => true,
            "label_text" => "[navn]",
            "label_size" => "11",
            "label_force" => true,
            "label2" => false,
            "label2_text" => "[vejnavn]",
        ];
        $result = Classification::normalizeClass($legacy);
        $this->assertCount(2, $result['labels']);
        $this->assertTrue($result['labels'][0]['on']);
        $this->assertEquals("[navn]", $result['labels'][0]['text']);
        $this->assertEquals("11", $result['labels'][0]['size']);
        $this->assertTrue($result['labels'][0]['force']);
        $this->assertEquals(10, $result['labels'][0]['sortid']);
        // label2 disabled but has content -> entry created with on=false
        $this->assertFalse($result['labels'][1]['on']);
        $this->assertEquals("[vejnavn]", $result['labels'][1]['text']);
        $this->assertEquals(20, $result['labels'][1]['sortid']);
        $this->assertArrayNotHasKey('label_text', $result);
        $this->assertArrayNotHasKey('label2', $result);
    }

    public function testNoLabelEntryWhenDisabledAndEmpty(): void
    {
        $legacy = ["name" => "c", "color" => "#008000", "label" => false, "label_text" => ""];
        $result = Classification::normalizeClass($legacy);
        $this->assertEquals([], $result['labels']);
    }

    public function testNewFormatPassesThroughUnchanged(): void
    {
        $new = [
            "name" => "c",
            "styles" => [["sortid" => 10, "name" => "Fill", "color" => "#008000"]],
            "labels" => [["sortid" => 10, "on" => true, "text" => "[a]"]],
        ];
        $result = Classification::normalizeClass($new);
        $this->assertEquals($new['styles'], $result['styles']);
        $this->assertEquals($new['labels'], $result['labels']);
        // idempotent
        $this->assertEquals($result, Classification::normalizeClass($result));
    }

    public function testNewFormatWithMissingLabelsKeyGetsEmptyArray(): void
    {
        $new = ["name" => "c", "styles" => []];
        $result = Classification::normalizeClass($new);
        $this->assertEquals([], $result['labels']);
        $this->assertEquals([], $result['styles']);
    }

    public function testNullsInsideEntriesBecomeEmptyStrings(): void
    {
        $new = ["name" => "c", "styles" => [["sortid" => 10, "color" => null]], "labels" => []];
        $result = Classification::normalizeClass($new);
        $this->assertSame("", $result['styles'][0]['color']);
    }

    public function testAcceptsNestedStdClass(): void
    {
        $new = json_decode('{"name":"c","styles":[{"sortid":10,"color":"#008000"}],"labels":[]}');
        $result = Classification::normalizeClass((array)$new);
        $this->assertEquals("#008000", $result['styles'][0]['color']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit ClassificationFormatTest.php`
Expected: FAIL/ERROR ("Call to undefined method ... normalizeClass").

- [ ] **Step 3: Implement `normalizeClass()`**

In `app/models/Classification.php`, add inside the class (after the constructor):

```php
public const STYLE_KEYS = ['color', 'outlinecolor', 'symbol', 'size', 'width', 'angle', 'gap',
    'style_opacity', 'pattern', 'linecap', 'geomtransform', 'minsize', 'maxsize',
    'style_offsetx', 'style_offsety', 'style_polaroffsetr', 'style_polaroffsetd'];

public const LABEL_KEYS = ['force', 'text', 'minscaledenom', 'maxscaledenom', 'position', 'size',
    'color', 'outlinecolor', 'buffer', 'repeatdistance', 'angle', 'backgroundcolor',
    'backgroundpadding', 'offsetx', 'offsety', 'font', 'fontweight', 'expression',
    'maxsize', 'minfeaturesize'];

/**
 * Convert a class object from the legacy flat format (Symbol1/Symbol2/Label1/Label2 keys)
 * to the new format with styles[] and labels[] arrays. Idempotent: new-format input passes
 * through unchanged (apart from null values inside entries becoming empty strings and the
 * styles/labels keys being guaranteed to exist). Legacy flat keys are always stripped.
 */
public static function normalizeClass(array $class): array
{
    // Normalize any nested stdClass objects to plain arrays
    $class = json_decode(json_encode($class), true);
    $hasNewFormat = isset($class['styles']) || isset($class['labels']);

    $legacyStyles = [];
    foreach ([['', 10, 'Symbol 1'], ['overlay', 20, 'Symbol 2']] as [$prefix, $sortid, $name]) {
        $style = [];
        foreach (self::STYLE_KEYS as $key) {
            if (!empty($class[$prefix . $key])) {
                $style[$key] = $class[$prefix . $key];
            }
            unset($class[$prefix . $key]);
        }
        if (!empty($style)) {
            $legacyStyles[] = array_merge(['sortid' => $sortid, 'name' => $name], $style);
        }
    }

    $legacyLabels = [];
    foreach ([['label', 10, 'Label 1'], ['label2', 20, 'Label 2']] as [$prefix, $sortid, $name]) {
        $label = [];
        foreach (self::LABEL_KEYS as $key) {
            if (!empty($class[$prefix . '_' . $key])) {
                $label[$key] = $class[$prefix . '_' . $key];
            }
            unset($class[$prefix . '_' . $key]);
        }
        $on = !empty($class[$prefix]);
        unset($class[$prefix]);
        if ($on || !empty($label)) {
            $legacyLabels[] = array_merge(['sortid' => $sortid, 'name' => $name, 'on' => $on], $label);
        }
    }

    if ($hasNewFormat) {
        $class['styles'] = $class['styles'] ?? [];
        $class['labels'] = $class['labels'] ?? [];
    } else {
        $class['styles'] = $legacyStyles;
        $class['labels'] = $legacyLabels;
    }

    foreach (['styles', 'labels'] as $k) {
        foreach ($class[$k] as $i => $entry) {
            if (!is_array($entry)) continue;
            foreach ($entry as $prop => $v) {
                if ($v === null) $class[$k][$i][$prop] = "";
            }
        }
    }
    return $class;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit ClassificationFormatTest.php`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/models/Classification.php app/tests/unit/ClassificationFormatTest.php
git commit -m "feat(classification): add normalizeClass converting legacy flat style/label keys to styles[]/labels[] arrays"
```

---

### Task 2: Wire `normalizeClass()` into the editor read path

**Files:**
- Modify: `app/models/Classification.php` — `getAll()` (~line 65-92) and `get()` (~line 100-130)

**Interfaces:**
- Consumes: `Classification::normalizeClass()` from Task 1.
- Produces: `getAll()`/`get()` responses where every class has `styles`/`labels` arrays and no legacy flat keys. `get()` injects only a `name` default ("Unnamed Class").

- [ ] **Step 1: Apply conversion in `getAll()`**

In `getAll()`, directly after the line

```php
$arr = $arr2 = !empty($row['class']) && is_array(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [];
```

add:

```php
$arr = array_map([self::class, 'normalizeClass'], $arr);
```

(The `$arr2` dedup loop below it is dead code — `isset($value->sortid)` is always false on arrays — leave it untouched.)

- [ ] **Step 2: Replace the flat-key defaults in `get()`**

In `get()`, replace this block:

```php
        $props = [
            "name" => "Unnamed Class",
            "label" => false,
            "label_text" => "",
            "label2_text" => "",
            "force_label" => false,
            "color" => "#FF0000",
            "outlinecolor" => "#FF0000",
            "size" => "2",
            "width" => "1"];
        foreach ($arr as $ignored) {
            foreach ($props as $key2 => $value2) {
                if (!isset($arr[$key2])) {
                    $arr[$key2] = $value2;
                }
            }
        }
```

with:

```php
        if (!isset($arr['name'])) {
            $arr['name'] = "Unnamed Class";
        }
```

(A newly added class starts with empty `styles`/`labels` arrays — guaranteed by `normalizeClass()` via `getAll()` — and the user adds symbols/labels explicitly in the UI.)

- [ ] **Step 3: Run the full unit suite**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: PASS (no regressions).

- [ ] **Step 4: Commit**

```bash
git add app/models/Classification.php
git commit -m "feat(classification): normalize classes to styles[]/labels[] format in getAll/get"
```

---

### Task 3: Mapfile rendering — static `renderStyle`/`renderLabel` on the new format

**Files:**
- Modify: `app/models/Mapfile.php` — `renderStyle` (~line 398-461), `renderOffsetPair` (~line 463-468), `renderLabel` (~line 474-549), `renderLeader` (~line 551-563)
- Test: `app/tests/unit/MapfileRenderTest.php` (create)

**Interfaces:**
- Consumes: nothing new (pure string builders; `Util::hex2RGB`, `addSquareBracket` as today).
- Produces:
  - `public static function renderStyle(array $style): string` — takes one style entry (new-format keys).
  - `public static function renderLabel(array $label, string $layerName, int $n): string` — takes one label entry; `$n` is the 1-based index used only in the `#START_LABEL{n}_{layerName}`/`#END_LABEL{n}_{layerName}` comment markers; returns `''` when `$label['on']` is falsy.
  - `private static function renderOffsetPair(array $entry, string $xKey, string $yKey): string` (unchanged logic, now static).
  - `public static function renderLeader(array $class): string` (unchanged logic, now static — reads the base-level `leader*` keys).

- [ ] **Step 1: Write the failing tests**

Create `app/tests/unit/MapfileRenderTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Mapfile;
use Codeception\Test\Unit;

class MapfileRenderTest extends Unit
{
    protected UnitTester $tester;

    public function testRenderStyleBasics(): void
    {
        $s = Mapfile::renderStyle([
            "sortid" => 10, "name" => "Fill",
            "color" => "#008000", "outlinecolor" => "#000000", "width" => "2",
            "symbol" => "circle", "size" => "10", "style_opacity" => "50",
        ]);
        $this->assertStringContainsString("STYLE\n", $s);
        $this->assertStringContainsString("COLOR 0 128 0\n", $s);
        $this->assertStringContainsString("OUTLINECOLOR 0 0 0\n", $s);
        $this->assertStringContainsString("WIDTH 2\n", $s);
        $this->assertStringContainsString("SYMBOL 'circle'\n", $s);
        $this->assertStringContainsString("SIZE 10\n", $s);
        $this->assertStringContainsString("OPACITY 50\n", $s);
        $this->assertStringContainsString("END # style\n", $s);
        // name/sortid never reach the mapfile
        $this->assertStringNotContainsString("Fill", $s);
    }

    public function testRenderStyleEmitsMinMaxSizeForEveryStyle(): void
    {
        $s = Mapfile::renderStyle(["color" => "#008000", "minsize" => "2", "maxsize" => "40"]);
        $this->assertStringContainsString("MINSIZE 2\n", $s);
        $this->assertStringContainsString("MAXSIZE 40\n", $s);
    }

    public function testRenderStyleColumnDrivenValuesGetBrackets(): void
    {
        $s = Mapfile::renderStyle(["width" => "mycol", "style_offsetx" => "xcol"]);
        $this->assertStringContainsString("WIDTH [mycol]\n", $s);
        $this->assertStringContainsString("OFFSET [xcol] 0\n", $s);
    }

    public function testRenderLabelOffReturnsEmpty(): void
    {
        $this->assertSame('', Mapfile::renderLabel(["on" => false, "text" => "[a]"], "s.t", 1));
        $this->assertSame('', Mapfile::renderLabel(["text" => "[a]"], "s.t", 1));
    }

    public function testRenderLabelBasicsAndMarkers(): void
    {
        $s = Mapfile::renderLabel([
            "on" => true, "text" => "[navn]", "size" => "12", "color" => "#112233",
            "position" => "cc", "force" => true,
        ], "myschema.mytable", 3);
        $this->assertStringContainsString("#START_LABEL3_myschema.mytable\n", $s);
        $this->assertStringContainsString("#END_LABEL3_myschema.mytable\n", $s);
        $this->assertStringContainsString("TEXT '[navn]'\n", $s);
        $this->assertStringContainsString("SIZE 12\n", $s);
        $this->assertStringContainsString("COLOR 17 34 51\n", $s);
        $this->assertStringContainsString("POSITION cc\n", $s);
        $this->assertStringContainsString("FORCE true\n", $s);
        $this->assertStringContainsString("FONT arialnormal\n", $s);
    }

    public function testRenderLabelBackgroundAlwaysGetsOutlineAndWidth(): void
    {
        // The old Label2 quirk (outline/width only when padding set) is normalized away
        $s = Mapfile::renderLabel(["on" => true, "backgroundcolor" => "#FFFFFF"], "s.t", 2);
        $this->assertStringContainsString("GEOMTRANSFORM 'labelpoly'\n", $s);
        $this->assertStringContainsString("OUTLINECOLOR 255 255 255\n", $s);
        $this->assertStringContainsString("WIDTH 1\n", $s);
        $sPad = Mapfile::renderLabel(["on" => true, "backgroundcolor" => "#FFFFFF", "backgroundpadding" => "3"], "s.t", 2);
        $this->assertStringContainsString("WIDTH 3\n", $sPad);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit MapfileRenderTest.php`
Expected: FAIL/ERROR (signature mismatch — `renderStyle` is non-static and expects (class, prefix)).

- [ ] **Step 3: Rewrite the four render methods**

In `app/models/Mapfile.php`, replace `renderStyle` and `renderOffsetPair` with:

```php
    /**
     * Render a MapServer STYLE block from a single style entry (new format, un-prefixed keys).
     */
    public static function renderStyle(array $style): string
    {
        $s = "STYLE\n";

        if (!empty($style['symbol'])) {
            $sym = $style['symbol'];
            $d = str_starts_with($sym, "[") ? "" : "'";
            $s .= "SYMBOL {$d}{$sym}{$d}\n";
        }
        if (!empty($style['pattern'])) $s .= "PATTERN {$style['pattern']} END\n";
        if (!empty($style['linecap'])) $s .= "LINECAP {$style['linecap']}\n";
        if (!empty($style['width'])) $s .= "WIDTH " . self::addSquareBracket($style['width']) . "\n";
        if (!empty($style['color'])) $s .= "COLOR " . Util::hex2RGB($style['color'], true, " ") . "\n";
        if (!empty($style['outlinecolor'])) $s .= "OUTLINECOLOR " . Util::hex2RGB($style['outlinecolor'], true, " ") . "\n";
        if (!empty($style['style_opacity'])) $s .= "OPACITY {$style['style_opacity']}\n";
        if (!empty($style['size'])) $s .= "SIZE " . self::addSquareBracket($style['size']) . "\n";

        if (!empty($style['angle'])) {
            $angle = $style['angle'];
            if (is_numeric($angle) && ((int)$angle > 360 || (int)$angle < -360)) $angle = '0';
            $s .= (is_numeric($angle) || strtolower($angle) == "auto")
                ? "ANGLE {$angle}\n"
                : "ANGLE [{$angle}]\n";
        }

        if (!empty($style['gap'])) $s .= "GAP {$style['gap']}\n";
        if (!empty($style['geomtransform'])) $s .= "GEOMTRANSFORM '{$style['geomtransform']}'\n";
        if (!empty($style['minsize'])) $s .= "MINSIZE {$style['minsize']}\n";
        if (!empty($style['maxsize'])) $s .= "MAXSIZE {$style['maxsize']}\n";

        $s .= "OFFSET " . self::renderOffsetPair($style, 'style_offsetx', 'style_offsety') . "\n";
        $s .= "POLAROFFSET " . self::renderOffsetPair($style, 'style_polaroffsetr', 'style_polaroffsetd') . "\n";

        $s .= "\nEND # style\n";
        return $s;
    }

    private static function renderOffsetPair(array $entry, string $xKey, string $yKey): string
    {
        $x = !empty($entry[$xKey]) ? self::addSquareBracket($entry[$xKey]) : "0";
        $y = !empty($entry[$yKey]) ? self::addSquareBracket($entry[$yKey]) : "0";
        return "{$x} {$y}";
    }
```

Replace `renderLabel` with:

```php
    /**
     * Render a MapServer LABEL block from a single label entry (new format, un-prefixed keys).
     * $n is the 1-based label index, used only in the comment markers that
     * Wms.php's disableLabels sed command targets.
     */
    public static function renderLabel(array $label, string $layerName, int $n): string
    {
        if (empty($label['on'])) return '';

        $s = "#START_LABEL{$n}_{$layerName}\n\n";
        $s .= "LABEL\n";
        if (!empty($label['text'])) $s .= "TEXT '{$label['text']}'\n";
        $s .= "TYPE truetype\n";
        $s .= "FONT " . (!empty($label['font']) ? $label['font'] : "arial")
            . (!empty($label['fontweight']) ? $label['fontweight'] : "normal") . "\n";

        if (!empty($label['size'])) {
            $s .= "SIZE " . self::addSquareBracket($label['size']) . "\n";
        } else {
            $s .= "SIZE 11\n";
        }

        $s .= "COLOR " . (!empty($label['color']) ? Util::hex2RGB($label['color'], true, " ") : "1 1 1") . "\n";
        $s .= "OUTLINECOLOR " . (!empty($label['outlinecolor']) ? Util::hex2RGB($label['outlinecolor'], true, " ") : "255 255 255") . "\n";
        $s .= "SHADOWSIZE 2 2\n";
        $s .= "ANTIALIAS true\n";
        $s .= "FORCE " . (!empty($label['force']) ? "true" : "false") . "\n";
        $s .= "POSITION " . (!empty($label['position']) ? $label['position'] : "auto") . "\n";
        $s .= "PARTIALS false\n";
        $s .= "MINSIZE 1\n";

        if (!empty($label['maxsize'])) $s .= "MAXSIZE {$label['maxsize']}\n";
        if (!empty($label['maxscaledenom'])) $s .= "MAXSCALEDENOM {$label['maxscaledenom']}\n";
        if (!empty($label['minscaledenom'])) $s .= "MINSCALEDENOM {$label['minscaledenom']}\n";
        if (!empty($label['buffer'])) $s .= "BUFFER {$label['buffer']}\n";
        if (!empty($label['repeatdistance'])) $s .= "REPEATDISTANCE {$label['repeatdistance']}\n";
        if (!empty($label['minfeaturesize'])) $s .= "MINFEATURESIZE {$label['minfeaturesize']}\n";

        if (!empty($label['expression'])) {
            $s .= "EXPRESSION ({$label['expression']})\n";
        }

        if (!empty($label['angle'])) {
            $angle = $label['angle'];
            if (is_numeric($angle) && ((int)$angle > 360 || (int)$angle < -360)) $angle = '0';
            $s .= (is_numeric($angle) || $angle == 'auto' || $angle == 'auto2' || $angle == 'follow')
                ? "ANGLE {$angle}\n"
                : "ANGLE [{$angle}]\n";
        }

        $s .= "WRAP \"\\n\"\n\n";
        $s .= "OFFSET " . (!empty($label['offsetx']) ? $label['offsetx'] : "0") . " " . (!empty($label['offsety']) ? $label['offsety'] : "0") . "\n\n\n";

        // Label background style
        $s .= "STYLE\n";
        if (!empty($label['backgroundcolor'])) {
            $bgColor = Util::hex2RGB($label['backgroundcolor'], true, " ");
            $s .= "GEOMTRANSFORM 'labelpoly'\n";
            $s .= "COLOR {$bgColor}\n";
            $s .= "OUTLINECOLOR {$bgColor}\n";
            $s .= "WIDTH " . (!empty($label['backgroundpadding']) ? $label['backgroundpadding'] : "1") . "\n";
        }
        $s .= "END # STYLE\n";
        $s .= "END\n";
        $s .= "#END_LABEL{$n}_{$layerName}\n";
        return $s;
    }
```

Make `renderLeader` static (body unchanged):

```php
    public static function renderLeader(array $class): string
```

(Do not change `renderClasses` yet — that is Task 4; the unit suite will not run `renderClasses` until then, but `php -l` must pass. `renderClasses` still compiles because `$this->renderStyle(...)` calls to static methods are legal PHP; it is now temporarily broken at runtime with the old argument shapes — Task 4 fixes it in the same working session. Do NOT deploy between Tasks 3 and 4.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit MapfileRenderTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/models/Mapfile.php app/tests/unit/MapfileRenderTest.php
git commit -m "refactor(mapfile): renderStyle/renderLabel take a single style/label entry (new format), static and unit-tested"
```

---

### Task 4: `renderClasses()` — normalize first, loop arrays sorted by sortid

**Files:**
- Modify: `app/models/Mapfile.php` — `renderClasses` (~line 568-607)
- Test: `app/tests/unit/MapfileRenderTest.php` (extend)

**Interfaces:**
- Consumes: `Classification::normalizeClass()` (Task 1), `renderStyle`/`renderLabel`/`renderLeader` (Task 3).
- Produces: `public static function renderClasses(array $classData, array $layerArr, string $layerName): string`. Accepts classes in EITHER format (normalizes each class as the very first step — this is the guarantee that mapfile generation works on non-converted JSON read raw from the DB). Existing call sites (`$this->renderClasses(...)` at ~lines 826 and 939) keep working unchanged (instance-call of a static method is legal PHP).

- [ ] **Step 1: Write the failing tests**

Append to `app/tests/unit/MapfileRenderTest.php`:

```php
    public function testRenderClassesWithNewFormatMultipleStylesAndLabels(): void
    {
        $classes = [[
            "name" => "c1",
            "expression" => "[t]=1",
            "styles" => [
                ["sortid" => 30, "name" => "top", "color" => "#0000FF"],
                ["sortid" => 10, "name" => "bottom", "color" => "#FF0000"],
                ["sortid" => 20, "name" => "middle", "color" => "#00FF00"],
            ],
            "labels" => [
                ["sortid" => 20, "on" => true, "text" => "[b]"],
                ["sortid" => 10, "on" => true, "text" => "[a]"],
                ["sortid" => 30, "on" => false, "text" => "[c]"],
            ],
        ]];
        $s = Mapfile::renderClasses($classes, ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("CLASS\n", $s);
        $this->assertStringContainsString("NAME 'c1'\n", $s);
        // styles ordered by sortid: red, green, blue
        $red = strpos($s, "COLOR 255 0 0");
        $green = strpos($s, "COLOR 0 255 0");
        $blue = strpos($s, "COLOR 0 0 255");
        $this->assertNotFalse($red);
        $this->assertTrue($red < $green && $green < $blue);
        // labels ordered by sortid, numbered sequentially, off-label skipped
        $a = strpos($s, "TEXT '[a]'");
        $b = strpos($s, "TEXT '[b]'");
        $this->assertTrue($a < $b);
        $this->assertStringContainsString("#START_LABEL1_s.t", $s);
        $this->assertStringContainsString("#START_LABEL2_s.t", $s);
        $this->assertStringNotContainsString("TEXT '[c]'", $s);
        $this->assertStringContainsString("END # Class\n", $s);
    }

    public function testRenderClassesWithRawLegacyFormat(): void
    {
        // Non-converted JSON straight from the DB must render correctly
        $classes = [[
            "sortid" => 10,
            "name" => "legacy",
            "color" => "#FF0000",
            "overlaycolor" => "#00FF00",
            "label" => true,
            "label_text" => "[navn]",
            "label2" => true,
            "label2_text" => "[vejnavn]",
        ]];
        $s = Mapfile::renderClasses($classes, ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("COLOR 255 0 0", $s);
        $this->assertStringContainsString("COLOR 0 255 0", $s);
        $this->assertStringContainsString("TEXT '[navn]'", $s);
        $this->assertStringContainsString("TEXT '[vejnavn]'", $s);
        $this->assertStringContainsString("#START_LABEL1_s.t", $s);
        $this->assertStringContainsString("#START_LABEL2_s.t", $s);
    }

    public function testRenderClassesEmptyStylesAndLabels(): void
    {
        $s = Mapfile::renderClasses([["name" => "empty", "styles" => [], "labels" => []]],
            ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("CLASS\n", $s);
        $this->assertStringNotContainsString("STYLE\n", $s);
        $this->assertStringNotContainsString("LABEL\n", $s);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit MapfileRenderTest.php`
Expected: the three new tests FAIL (old renderClasses signature/behavior).

- [ ] **Step 3: Rewrite `renderClasses()`**

Replace the whole method with:

```php
    /**
     * Render all CLASS blocks for a layer. Accepts classes in either the legacy flat
     * format or the new styles[]/labels[] format — each class is normalized first,
     * so raw, non-converted JSON from the database renders correctly.
     */
    public static function renderClasses(array $classData, array $layerArr, string $layerName): string
    {
        $s = '';
        foreach ($classData as $class) {
            $class = Classification::normalizeClass((array)$class);
            $s .= "CLASS\n";

            // NAME
            if (!empty($class['name'])) $s .= "NAME '" . addslashes($class['name']) . "'\n";

            // EXPRESSION
            if (!empty($class['expression'])) {
                if (!empty($layerArr['data'][0]['theme_column'])) {
                    $s .= "EXPRESSION \"{$class['expression']}\"\n";
                } else {
                    $s .= "EXPRESSION ({$class['expression']})\n";
                }
            } elseif (empty($class['expression']) && !empty($layerArr['data'][0]['theme_column'])) {
                $s .= "EXPRESSION ''\n";
            }

            // Scale denominators
            if (!empty($class['class_maxscaledenom'])) $s .= "MAXSCALEDENOM {$class['class_maxscaledenom']}\n";
            if (!empty($class['class_minscaledenom'])) $s .= "MINSCALEDENOM {$class['class_minscaledenom']}\n";

            // Styles, ordered by sortid (usort is stable in PHP 8)
            $styles = $class['styles'];
            usort($styles, fn($a, $b) => (int)($a['sortid'] ?? 0) <=> (int)($b['sortid'] ?? 0));
            foreach ($styles as $style) {
                $s .= self::renderStyle($style);
            }

            // Labels, ordered by sortid, numbered sequentially for the markers
            $labels = $class['labels'];
            usort($labels, fn($a, $b) => (int)($a['sortid'] ?? 0) <=> (int)($b['sortid'] ?? 0));
            $n = 1;
            foreach ($labels as $label) {
                $rendered = self::renderLabel($label, $layerName, $n);
                if ($rendered !== '') {
                    $s .= $rendered;
                    $n++;
                }
            }

            // Leader
            $s .= self::renderLeader($class);

            $s .= "END # Class\n";
        }
        return $s;
    }
```

(Note: the old `#LABEL2` comment line is intentionally dropped. `Classification` is in the same namespace `app\models` — no `use` statement needed. The `testRenderClassesEmptyStylesAndLabels` assertion on `STYLE\n` works because unlike the old code, no empty STYLE blocks are emitted.)

- [ ] **Step 4: Run the full unit suite**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/models/Mapfile.php app/tests/unit/MapfileRenderTest.php
git commit -m "feat(mapfile): renderClasses loops styles[]/labels[] sorted by sortid, normalizing legacy JSON first"
```

---

### Task 5: Generalize the disableLabels sed markers in Wms.php

**Files:**
- Modify: `app/controllers/Wms.php:288-294`

**Interfaces:**
- Consumes: marker format `#START_LABEL{n}_{schema.table}` / `#END_LABEL{n}_{schema.table}` from Task 3 (n is now unbounded, not just 1 and 2).

- [ ] **Step 1: Replace the two hardcoded sed commands**

Replace:

```php
                if ($disableLabels) {
                    $useFilters = true;
                    $sedCmd = 'sed -i "/#START_LABEL1_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL1_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                    $sedCmd = 'sed -i "/#START_LABEL2_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL2_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
```

with:

```php
                if ($disableLabels) {
                    $useFilters = true;
                    // Strip every numbered label block (#START_LABEL<n>_… to #END_LABEL<n>_…) for the layer.
                    // The [0-9]* covers any label count, including old mapfiles with only LABEL1/LABEL2.
                    $sedCmd = 'sed -i "/#START_LABEL[0-9]*_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL[0-9]*_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
```

- [ ] **Step 2: Verify the sed expression against a generated block**

Run (host shell):

```bash
cd /tmp/claude-1000/-home-mh-Source-geocloud2/*/scratchpad 2>/dev/null || cd /tmp
printf 'LAYER\n#START_LABEL1_s.t\nLABEL\nEND\n#END_LABEL1_s.t\n#START_LABEL3_s.t\nLABEL\nEND\n#END_LABEL3_s.t\nEND\n' > sedtest.map
sed -i "/#START_LABEL[0-9]*_s.t/,/#END_LABEL[0-9]*_s.t/c\ " sedtest.map
cat sedtest.map
```

Expected: only `LAYER`, two blank/space lines, and `END` remain — no LABEL blocks.

- [ ] **Step 3: Commit**

```bash
git add app/controllers/Wms.php
git commit -m "fix(wms): disableLabels sed strips any number of label blocks, not just LABEL1/LABEL2"
```

---

### Task 6: Wizard `createClass()` emits the new format

**Files:**
- Modify: `app/models/Classification.php` — `createClass()` (~line 653-709)
- Test: `app/tests/unit/ClassificationFormatTest.php` (extend)

**Interfaces:**
- Consumes: format from Task 1.
- Produces: `Classification::createClass()` (same signature) returning an object with base keys + `styles` (array of objects) + `labels` (array of objects; empty when no `labelText`). `styles[1]` ("Symbol 2") only when overlay wizard values are present. Consumed unchanged by `createSingle`/`createUnique`/`createEqualIntervals`/`createQuantile`/`createCluster` (they only json_encode the result). `mergeClasses()` needs no change — PHP `===` array comparison covers the nested arrays.

- [ ] **Step 1: Write the failing tests**

Append to `app/tests/unit/ClassificationFormatTest.php`:

```php
    public function testCreateClassEmitsNewFormat(): void
    {
        $data = (object)[
            "labelText" => "[navn]", "labelSize" => "9", "labelPosition" => "cc",
            "symbol" => "circle", "symbolSize" => "50", "opacity" => "25",
            "overlayColor" => "#00FF00", "overlaySymbol" => "circle",
            "overlaySize" => "35", "overlayOpacity" => "70", "force" => true,
        ];
        $c = (array)Classification::createClass("POINT", "Cluster", "[cnt]>1", 20, "#00FF00", $data);
        $this->assertEquals("Cluster", $c['name']);
        $this->assertEquals("[cnt]>1", $c['expression']);
        $this->assertEquals(20, $c['sortid']);
        $this->assertArrayNotHasKey('color', $c);          // no flat legacy keys
        $this->assertArrayNotHasKey('label_text', $c);
        $this->assertArrayNotHasKey('overlaycolor', $c);

        $styles = array_map(fn($o) => (array)$o, $c['styles']);
        $this->assertCount(2, $styles);
        $this->assertEquals("#00FF00", $styles[0]['color']);
        $this->assertEquals("circle", $styles[0]['symbol']);
        $this->assertEquals("50", $styles[0]['size']);
        $this->assertEquals("25", $styles[0]['style_opacity']);
        $this->assertEquals(10, $styles[0]['sortid']);
        $this->assertEquals("#00FF00", $styles[1]['color']);
        $this->assertEquals("35", $styles[1]['size']);
        $this->assertEquals("70", $styles[1]['style_opacity']);
        $this->assertEquals(20, $styles[1]['sortid']);

        $labels = array_map(fn($o) => (array)$o, $c['labels']);
        $this->assertCount(1, $labels);
        $this->assertTrue($labels[0]['on']);
        $this->assertEquals("[navn]", $labels[0]['text']);
        $this->assertEquals("9", $labels[0]['size']);
        $this->assertEquals("cc", $labels[0]['position']);
        $this->assertTrue($labels[0]['force']);
    }

    public function testCreateClassWithoutOverlayOrLabel(): void
    {
        $c = (array)Classification::createClass("POLYGON", "Simple", null, 10, "#FF0000", (object)[]);
        $this->assertCount(1, $c['styles']);
        $this->assertEquals("#FF0000", ((array)$c['styles'][0])['color']);
        $this->assertEquals([], $c['labels']);
    }

    public function testCreateClassPointDefaults(): void
    {
        $c = (array)Classification::createClass("POINT", "P", null, 10, "#FF0000", (object)[]);
        $s = (array)$c['styles'][0];
        $this->assertEquals("circle", $s['symbol']);
        $this->assertEquals(10, $s['size']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit ClassificationFormatTest.php`
Expected: the three new tests FAIL (old flat-format output).

- [ ] **Step 3: Rewrite `createClass()`**

Replace the method body (keep signature and docblock) with:

```php
    static function createClass(string $type, string $name = "Unnamed class", ?string $expression = null, int $sortid = 1, ?string $color = null, ?object $data = null): object
    {
        $symbol = $data->symbol ?? "";
        $size = $data->symbolSize ?? "";
        $outlineColor = $data->outlineColor ?? "";
        $color = ($color) ?: Util::randHexColor();
        if ($type == "POINT" || $type == "MULTIPOINT") {
            $symbol = $data->symbol ?? "circle";
            $size = $data->symbolSize ?? 10;
        }
        $styles = [(object)[
            "sortid" => 10,
            "name" => "Symbol 1",
            "color" => $color,
            "outlinecolor" => !empty($outlineColor) ? $outlineColor : "",
            "symbol" => $symbol,
            "angle" => !empty($data->angle) ? $data->angle : "",
            "size" => $size,
            "width" => !empty($data->lineWidth) ? $data->lineWidth : "",
            "style_opacity" => !empty($data->opacity) ? $data->opacity : "",
            "gap" => !empty($data->gap) ? $data->gap : "",
            "minsize" => !empty($data->minsize) ? $data->minsize : "",
            "maxsize" => !empty($data->maxsize) ? $data->maxsize : "",
            "style_offsetx" => !empty($data->style_offsetx) ? $data->style_offsetx : "",
            "style_offsety" => !empty($data->style_offsety) ? $data->style_offsety : "",
            "style_polaroffsetr" => !empty($data->style_polaroffsetr) ? $data->style_polaroffsetr : "",
            "style_polaroffsetd" => !empty($data->style_polaroffsetd) ? $data->style_polaroffsetd : "",
        ]];
        if (!empty($data->overlayColor) || !empty($data->overlaySymbol) || !empty($data->overlaySize) || !empty($data->overlayOpacity)) {
            $styles[] = (object)[
                "sortid" => 20,
                "name" => "Symbol 2",
                "color" => !empty($data->overlayColor) ? $data->overlayColor : "",
                "outlinecolor" => "",
                "symbol" => !empty($data->overlaySymbol) ? $data->overlaySymbol : "",
                "size" => !empty($data->overlaySize) ? $data->overlaySize : "",
                "width" => "",
                "style_opacity" => !empty($data->overlayOpacity) ? $data->overlayOpacity : "",
            ];
        }
        $labels = [];
        if (!empty($data->labelText)) {
            $labels[] = (object)[
                "sortid" => 10,
                "name" => "Label 1",
                "on" => true,
                "text" => $data->labelText,
                "size" => !empty($data->labelSize) ? $data->labelSize : "",
                "color" => !empty($data->labelColor) ? $data->labelColor : "",
                "position" => !empty($data->labelPosition) ? $data->labelPosition : "",
                "font" => !empty($data->labelFont) ? $data->labelFont : "",
                "fontweight" => !empty($data->labelFontWeight) ? $data->labelFontWeight : "",
                "angle" => !empty($data->labelAngle) ? $data->labelAngle : "",
                "backgroundcolor" => !empty($data->labelBackgroundcolor) ? $data->labelBackgroundcolor : "",
                "force" => !empty($data->force),
                "outlinecolor" => !empty($data->label_outlinecolor) ? $data->label_outlinecolor : "",
                "buffer" => !empty($data->label_buffer) ? $data->label_buffer : "",
                "repeatdistance" => !empty($data->label_repeatdistance) ? $data->label_repeatdistance : "",
                "backgroundpadding" => !empty($data->label_backgroundpadding) ? $data->label_backgroundpadding : "",
                "offsetx" => !empty($data->label_offsetx) ? $data->label_offsetx : "",
                "offsety" => !empty($data->label_offsety) ? $data->label_offsety : "",
                "expression" => !empty($data->label_expression) ? $data->label_expression : "",
                "maxsize" => !empty($data->label_maxsize) ? $data->label_maxsize : "",
                "minfeaturesize" => !empty($data->label_minfeaturesize) ? $data->label_minfeaturesize : "",
                "minscaledenom" => !empty($data->label_minscaledenom) ? $data->label_minscaledenom : "",
                "maxscaledenom" => !empty($data->label_maxscaledenom) ? $data->label_maxscaledenom : "",
            ];
        }
        return (object)[
            "sortid" => $sortid,
            "name" => $name,
            "expression" => $expression,
            "styles" => $styles,
            "labels" => $labels,
        ];
    }
```

- [ ] **Step 4: Run the full unit suite**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/models/Classification.php app/tests/unit/ClassificationFormatTest.php
git commit -m "feat(classification): wizard createClass emits styles[]/labels[] format"
```

---

### Task 7: Frontend — rewrite `editwmsclass.js` (Base + Symbols + Labels)

**Files:**
- Modify: `public/js/admin/editwmsclass.js` (full rewrite of the `wmsClass` namespace + small edits in `wmsClasses`)

**Interfaces:**
- Consumes: GET `/controllers/classification/index/{table}/{id}` returning `data[0]` with `styles`/`labels` arrays (Task 2); PUT to the same URL accepting `{data: {...base, styles: [...], labels: [...]}}` (existing endpoint, `update()` merges keys).
- Produces (used by Task 8's `admin.js` changes):
  - `wmsClass.grid` — Base PropertyGrid (unchanged name, goes in tab `a3`)
  - `wmsClass.grid2` — Symbols border-layout Panel (goes in tab `a8`)
  - `wmsClass.grid3` — Labels border-layout Panel (goes in tab `a9`)
  - `wmsClass.grid4` / `wmsClass.grid5` — REMOVED
  - `wmsClass.save(onSuccess)` — PUTs base source + `wmsClass.styles` + `wmsClass.labels`, then `writeFiles(wmsClasses.table)` and `wmsClasses.store.load()`; called by the Update button, Add and Delete.
  - `wmsClass.init(id)` — builds all three components synchronously, then loads data via Ajax.

- [ ] **Step 1: Update the `wmsClasses` references to the removed grids**

In `public/js/admin/editwmsclass.js`:

a) In the "Copy from" success handler (~line 133-141), replace

```js
                            wmsClasses.store.load();
                            Ext.getCmp("a3").remove(wmsClass.grid);
                            Ext.getCmp("a8").remove(wmsClass.grid2);
                            Ext.getCmp("a9").remove(wmsClass.grid3);
                            Ext.getCmp("a10").remove(wmsClass.grid4);
                            Ext.getCmp("a11").remove(wmsClass.grid5);
                            wmsClasses.grid.getSelectionModel().clearSelections();
                            writeFiles(wmsClasses.table);
```

with

```js
                            wmsClasses.store.load();
                            Ext.getCmp("a3").remove(wmsClass.grid);
                            Ext.getCmp("a8").remove(wmsClass.grid2);
                            Ext.getCmp("a9").remove(wmsClass.grid3);
                            wmsClasses.grid.getSelectionModel().clearSelections();
                            writeFiles(wmsClasses.table);
```

b) In the `rowclick` listener (~line 169-214), replace the body after the `record` check with:

```js
                var activeTab = Ext.getCmp("classTabs").getActiveTab();
                var a3 = Ext.getCmp("a3"), a8 = Ext.getCmp("a8"), a9 = Ext.getCmp("a9");
                a3.remove(wmsClass.grid);
                a8.remove(wmsClass.grid2);
                a9.remove(wmsClass.grid3);
                wmsClass.grid = null;
                wmsClass.grid2 = null;
                wmsClass.grid3 = null;
                wmsClass.init(record.get("id"));
                a3.add(wmsClass.grid);
                a8.add(wmsClass.grid2);
                a9.add(wmsClass.grid3);
                Ext.getCmp("classTabs").activate(0);
                a3.doLayout();
                Ext.getCmp("classTabs").activate(1);
                a8.doLayout();
                Ext.getCmp("classTabs").activate(2);
                a9.doLayout();
                Ext.getCmp("classTabs").activate(activeTab);
```

c) In `wmsClasses.onDelete` (~line 244-255), remove the two lines referencing `a10`/`a11`/`grid4`/`grid5`:

```js
            Ext.getCmp("a10").remove(wmsClass.grid4);
            Ext.getCmp("a11").remove(wmsClass.grid5);
```

- [ ] **Step 2: Rewrite the `wmsClass` namespace**

Replace everything from `Ext.namespace('wmsClass');` to the end of the file with the following. Notes for the implementer:

- `buildStyleEditors()` returns exactly the `customEditors` object currently on `grid2` (old file lines ~851-941) — same editor configs, same keys (style keys are already un-prefixed). Drop the stray `'sortid'` editor entry (sortid is edited in the list grid now).
- `buildStylePropertyNames()` returns the `propertyNames` object currently on `grid2` (old lines ~828-846).
- `buildLabelEditors()` returns the `customEditors` object currently on `grid4` (old lines ~1098-1233) with **every key's `label_` prefix stripped**, and the `'label'` key renamed to `'on'` (checkbox editor unchanged).
- `buildLabelPropertyNames()` returns `propertyNames` from `grid4` (old lines ~1067-1088) with the same key renames (`label` → `on`, `label_*` → `*`); display strings unchanged.
- `labelPositionCombo` stays as-is (old lines ~290-332) but note it is used inside `buildLabelEditors()`; keep it defined inside `wmsClass.init` before the builders are called, exactly like today.

```js
Ext.namespace('wmsClass');

wmsClass.STYLE_FIELDS = ['color', 'outlinecolor', 'pattern', 'linecap', 'symbol', 'size', 'width',
    'angle', 'gap', 'style_opacity', 'geomtransform', 'minsize', 'maxsize',
    'style_offsetx', 'style_offsety', 'style_polaroffsetr', 'style_polaroffsetd'];

wmsClass.LABEL_FIELDS = ['on', 'text', 'force', 'minscaledenom', 'maxscaledenom', 'position', 'size',
    'font', 'fontweight', 'color', 'outlinecolor', 'buffer', 'repeatdistance', 'angle',
    'backgroundcolor', 'backgroundpadding', 'offsetx', 'offsety', 'expression', 'maxsize',
    'minfeaturesize'];

wmsClass.save = function (onSuccess) {
    var data = Ext.getCmp("propGrid") ? Ext.getCmp("propGrid").getSource() : {};
    data.styles = wmsClass.styles;
    data.labels = wmsClass.labels;
    Ext.Ajax.request({
        url: '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
        method: 'put',
        params: Ext.util.JSON.encode({data: data}),
        headers: {
            'Content-Type': 'application/json; charset=utf-8'
        },
        success: function () {
            App.setAlert(App.STATUS_OK, __("Style is updated"));
            writeFiles(wmsClasses.table);
            wmsClasses.store.load();
            if (onSuccess) {
                onSuccess();
            }
        },
        failure: function (response) {
            Ext.MessageBox.show({
                title: 'Failure',
                msg: __(Ext.decode(response.responseText).message),
                buttons: Ext.MessageBox.OK,
                width: 400,
                height: 300,
                icon: Ext.MessageBox.ERROR
            });
        }
    });
};

wmsClass.init = function (id) {
    var checkboxRender = function (d) {
        var checked = d ? 'property-grid-check-on' : '';
        return '<div class="' + checked + '">';
    };
    var cc = function (value, meta) {
        meta.style = meta.style + "background-color:" + value;
        return value;
    };
    var labelPositionCombo = new Ext.form.ComboBox({
        // ... keep exactly as in the old file (lines ~290-332) ...
    });

    wmsClass.classId = id;
    wmsClass.styles = [];
    wmsClass.labels = [];

    var buildStyleEditors = function () {
        return {
            // customEditors object from the old grid2 (lines ~851-941), unchanged
            // except the 'sortid' entry is dropped
        };
    };
    var buildLabelEditors = function () {
        return {
            // customEditors object from the old grid4 (lines ~1098-1233) with
            // 'label' -> 'on' and every 'label_' prefix stripped from the keys
        };
    };

    // ---------- Base tab ----------
    wmsClass.grid = new Ext.grid.PropertyGrid({
        id: 'propGrid',
        // identical to the old propGrid definition (lines ~761-822):
        // propertyNames, customRenderers (leader_color: cc), customEditors, viewConfig
    });

    // ---------- Shared master/detail builder ----------
    // kind: 'styles' | 'labels'
    var buildItemPanel = function (kind, propGridId, propertyNames, customEditors, customRenderers, fields) {
        var listStore = new Ext.data.JsonStore({
            fields: ['idx', 'sortid', 'name'],
            data: []
        });
        var currentIdx = null;
        var arr = function () {
            return wmsClass[kind];
        };
        var reload = function (selectIdx) {
            var rows = [];
            Ext.each(arr(), function (item, i) {
                rows.push({idx: i, sortid: item.sortid, name: item.name || ""});
            });
            listStore.loadData(rows);
            if (selectIdx !== undefined && selectIdx !== null) {
                var record = listStore.getAt(selectIdx);
                if (record) {
                    listGrid.getSelectionModel().selectRecords([record]);
                    showItem(selectIdx);
                }
            }
        };
        var propGrid = new Ext.grid.PropertyGrid({
            id: propGridId,
            region: 'center',
            border: false,
            propertyNames: propertyNames,
            customEditors: customEditors,
            customRenderers: customRenderers,
            viewConfig: {
                forceFit: true
            },
            source: {},
            listeners: {
                propertychange: function (source, recordId, value) {
                    if (currentIdx !== null && arr()[currentIdx]) {
                        arr()[currentIdx][recordId] = value;
                    }
                }
            }
        });
        var showItem = function (idx) {
            currentIdx = idx;
            var item = arr()[idx] || {};
            var source = {};
            Ext.each(fields, function (f) {
                if (f === 'on' || f === 'force') {
                    source[f] = !!item[f];
                } else {
                    source[f] = (item[f] !== undefined && item[f] !== null) ? item[f] : "";
                }
            });
            delete propGrid.getStore().sortInfo;
            propGrid.getColumnModel().getColumnById('name').sortable = false;
            propGrid.setSource(source);
        };
        var listGrid = new Ext.grid.EditorGridPanel({
            region: 'north',
            height: 120,
            split: true,
            border: false,
            store: listStore,
            clicksToEdit: 2,
            sm: new Ext.grid.RowSelectionModel({
                singleSelect: true
            }),
            viewConfig: {
                forceFit: true
            },
            cm: new Ext.grid.ColumnModel({
                defaults: {
                    sortable: false,
                    menuDisabled: true
                },
                columns: [
                    {
                        header: "Sort id",
                        dataIndex: "sortid",
                        width: 50,
                        editor: new Ext.ux.form.SpinnerField({
                            minValue: -100,
                            maxValue: 9999,
                            allowDecimals: false,
                            decimalPrecision: 0,
                            incrementValue: 1,
                            accelerate: true
                        })
                    },
                    {
                        header: "Name",
                        dataIndex: "name",
                        editor: new Ext.form.TextField({})
                    }
                ]
            }),
            tbar: [
                {
                    text: '<i class="fa fa-plus"></i> ' + __("Add"),
                    handler: function () {
                        var maxSort = 0;
                        Ext.each(arr(), function (item) {
                            var v = parseInt(item.sortid, 10);
                            if (!isNaN(v) && v > maxSort) {
                                maxSort = v;
                            }
                        });
                        var entry = {sortid: maxSort + 10, name: ""};
                        if (kind === 'labels') {
                            entry.on = true;
                        }
                        arr().push(entry);
                        wmsClass.save(function () {
                            reload(arr().length - 1);
                        });
                    }
                },
                '-',
                {
                    text: '<i class="fa fa-cut"></i> ' + __("Delete"),
                    handler: function () {
                        var record = listGrid.getSelectionModel().getSelected();
                        if (!record) {
                            return false;
                        }
                        Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete it?'), function (btn) {
                            if (btn === "yes") {
                                arr().splice(record.data.idx, 1);
                                currentIdx = null;
                                propGrid.setSource({});
                                wmsClass.save(function () {
                                    reload();
                                });
                            }
                        });
                    }
                }
            ],
            listeners: {
                rowclick: function (grid, rowIndex) {
                    showItem(listStore.getAt(rowIndex).data.idx);
                },
                afteredit: function (e) {
                    var item = arr()[e.record.data.idx];
                    if (item) {
                        item[e.field] = e.value;
                    }
                }
            }
        });
        var panel = new Ext.Panel({
            layout: 'border',
            border: false,
            items: [listGrid, propGrid]
        });
        panel.reloadList = reload;
        return panel;
    };

    // ---------- Symbols tab ----------
    wmsClass.grid2 = buildItemPanel(
        'styles',
        'symbolProps',
        { /* buildStylePropertyNames(): propertyNames from old grid2 (lines ~828-846) */ },
        buildStyleEditors(),
        {
            color: cc,
            outlinecolor: cc
        },
        wmsClass.STYLE_FIELDS
    );

    // ---------- Labels tab ----------
    wmsClass.grid3 = buildItemPanel(
        'labels',
        'labelProps',
        { /* buildLabelPropertyNames(): propertyNames from old grid4 (lines ~1067-1088),
             'label' -> 'on', 'label_' prefixes stripped */ },
        buildLabelEditors(),
        {
            on: checkboxRender,
            force: checkboxRender,
            color: cc,
            outlinecolor: cc,
            backgroundcolor: cc,
            position: Ext.util.Format.comboRenderer(labelPositionCombo)
        },
        wmsClass.LABEL_FIELDS
    );

    // ---------- Load data ----------
    Ext.Ajax.request({
        url: '/controllers/classification/index/' + wmsClasses.table + '/' + id,
        method: 'get',
        success: function (response) {
            var data = Ext.decode(response.responseText).data[0];
            wmsClass.styles = data.styles || [];
            wmsClass.labels = data.labels || [];
            var baseGrid = Ext.getCmp('propGrid');
            if (baseGrid) {
                delete baseGrid.getStore().sortInfo;
                baseGrid.getColumnModel().getColumnById('name').sortable = false;
                var baseSource = {}, baseFields = [
                    'sortid', 'name', 'expression', 'class_minscaledenom', 'class_maxscaledenom',
                    'leader', 'leader_gridstep', 'leader_maxdistance', 'leader_color'
                ];
                Ext.each(baseFields, function (f) {
                    baseSource[f] = (data[f] !== undefined && data[f] !== null) ? data[f] : "";
                });
                baseGrid.setSource(baseSource);
            }
            wmsClass.grid2.reloadList();
            wmsClass.grid3.reloadList();
        },
        failure: function (response) {
            Ext.MessageBox.show({
                title: 'Failure',
                msg: __(Ext.decode(response.responseText).message),
                buttons: Ext.MessageBox.OK,
                width: 400,
                height: 300,
                icon: Ext.MessageBox.ERROR
            });
        }
    });
};
```

Also: the old `wmsClass.store` JsonStore, its giant `fields` list, its `load` listener, and grids `grid2`(old)/`grid3`(old)/`grid4`/`grid5` are all deleted — the code above fully replaces them. The dead `test()` function (old lines ~268-278) can be deleted too. `checkboxRender`/`cc`/`labelPositionCombo` move inside `wmsClass.init` as shown.

- [ ] **Step 3: Syntax check**

Run: `node --check public/js/admin/editwmsclass.js`
Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
git add public/js/admin/editwmsclass.js
git commit -m "feat(admin): Symbols/Labels master-detail editors with Add/Delete and sort_id"
```

---

### Task 8: Frontend — `admin.js` tabs and Update button

**Files:**
- Modify: `public/js/admin/admin.js` — tab definitions (~line 3629-3710), Update handler (~line 3636-3681), `updateClass` (~line 3268-3282), removeAll block (~line 3030-3037)

**Interfaces:**
- Consumes: `wmsClass.save()`, `wmsClass.grid`/`grid2`/`grid3` from Task 7.

- [ ] **Step 1: Replace the five tabs with three**

In the `classTabs` tabpanel `items` (~line 3682-3709), replace the five panels with:

```js
                                                                            items: [
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a3",
                                                                                    title: "Base"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a8",
                                                                                    title: "Symbols"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a9",
                                                                                    title: "Labels"
                                                                                }
                                                                            ]
```

- [ ] **Step 2: Replace the Update handler**

Replace the whole `handler` of the Update button (~line 3639-3679) with:

```js
                                                                                    handler: function () {
                                                                                        wmsClass.save(function () {
                                                                                            store.load();
                                                                                        });
                                                                                    }
```

(`wmsClass.save` already shows the success alert, calls `writeFiles` and reloads `wmsClasses.store`; the layer `store.load()` is the only extra step the old handler did.)

- [ ] **Step 3: Remove a10/a11 references**

a) At ~line 3030-3037, delete the two lines:

```js
        Ext.getCmp("a10").removeAll();
        Ext.getCmp("a11").removeAll();
```

b) In `updateClass` (~line 3268-3282), delete the declarations and remove-calls for `a10`/`a11`/`grid4`/`grid5`, leaving:

```js
        var a3 = Ext.getCmp("a3");
        var a8 = Ext.getCmp("a8");
        var a9 = Ext.getCmp("a9");
        a3.remove(wmsClass.grid);
        a8.remove(wmsClass.grid2);
        a9.remove(wmsClass.grid3);
        a3.doLayout();
        a8.doLayout();
        a9.doLayout();
```

c) Search the whole file for any remaining `a10`, `a11`, `grid4`, `grid5` references and remove them the same way:

Run: `grep -n 'a10\|a11\|grid4\|grid5' public/js/admin/admin.js`
Expected: no hits related to class tabs (check each remaining hit manually — ids like `a12`/`a13`/`a14` must stay).

- [ ] **Step 4: Syntax check**

Run: `node --check public/js/admin/admin.js`
Expected: exit 0.

- [ ] **Step 5: Commit**

```bash
git add public/js/admin/admin.js
git commit -m "feat(admin): three class tabs (Base/Symbols/Labels), Update delegates to wmsClass.save"
```

---

### Task 9: End-to-end verification in the running app

**Files:** none (verification only). Dev mode loads the JS sources directly (`public/admin.php` script tags) — no grunt build needed.

- [ ] **Step 1: Verify legacy conversion in the editor**

Open the GC2 admin UI in the browser (same instance as the root screenshot, layer `t_5710_born_skole_dis` or any layer with existing classes). Click the class row. Expected: three tabs (Base, Symbols, Labels); Symbols lists "Symbol 1" (and "Symbol 2" if overlay values existed); Labels lists entries only where label data existed. Values match the pre-change screenshot (e.g. Color `#008000`).

- [ ] **Step 2: Verify add/delete/reorder**

Add a second symbol (e.g. outline color + width), give it sort id 5, click Update. Expected: alert "Style is updated"; map tile re-renders with the new symbol *under* the first one (lower sort id renders first). Add a third label, delete it again. Reload the page — state persists.

- [ ] **Step 3: Verify the stored JSON and mapfile**

```bash
docker exec docker-gc2core-1 bash -c 'psql -U postgres -d mydb -c "SELECT class FROM settings.geometry_columns_join WHERE _key_ LIKE '\''%t_5710_born_skole_dis%'\''"' | head -5
grep -A5 "CLASS" /home/mh/Source/geocloud2/app/wms/mapfiles/*_wms.map | head -60
```

Expected: JSON contains `"styles":[...]` with no flat legacy keys; the mapfile has one STYLE block per symbol in sortid order and `#START_LABEL1_...`, `#START_LABEL2_...` markers per enabled label.

(If the DB/container names differ, check with `docker ps` and the `settings.geometry_columns_join` table for the actual `_key_`.)

- [ ] **Step 4: Verify the wizard still works**

Run the class wizard (single color) on a test layer. Expected: one class with `styles[0]` in the stored JSON, map renders.

- [ ] **Step 5: Run the full unit suite one last time**

Run: `docker exec -w /var/www/geocloud2/app docker-gc2core-1 php vendor/bin/codecept run unit`
Expected: PASS.

- [ ] **Step 6: Commit any fixes, update CHANGELOG**

Add a CHANGELOG.md entry under the current unreleased section:

```markdown
- **Dynamic symbols and labels.** Classes now support an arbitrary number of symbols and labels (`styles[]`/`labels[]` with `sortid` ordering) instead of the fixed Symbol1/Symbol2/Label1/Label2. The admin class editor has three tabs: Base, Symbols, Labels — the latter two with Add/Delete like the Classes grid. The legacy flat format is converted on read (editor) and in-memory during mapfile generation, so existing data keeps working without migration.
```

```bash
git add CHANGELOG.md
git commit -m "docs(CHANGELOG): document dynamic symbols/labels for classes"
```
