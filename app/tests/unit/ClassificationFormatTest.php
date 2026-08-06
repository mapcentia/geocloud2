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
}
