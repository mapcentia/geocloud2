<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Classification;
use Codeception\Test\Unit;

class ClassificationIdsTest extends Unit
{
    protected UnitTester $tester;

    public function testGenerateIdReturnsEightHexChars(): void
    {
        $id = Classification::generateId();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
        $this->assertNotEquals($id, Classification::generateId());
    }

    public function testEnsureIdsAddsMissingIdsEverywhere(): void
    {
        $classes = [
            [
                "sortid" => 10,
                "name" => "A",
                "styles" => [["sortid" => 10, "color" => "#008000"]],
                "labels" => [["sortid" => 10, "on" => true, "text" => "[name]"]],
            ],
        ];
        $result = Classification::ensureIds($classes);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['labels'][0]['id']);
    }

    public function testEnsureIdsIsIdempotentAndKeepsExistingIds(): void
    {
        $classes = [
            [
                "id" => "aaaaaaaa",
                "name" => "A",
                "styles" => [["id" => "bbbbbbbb", "color" => "#008000"], ["color" => "#ff0000"]],
                "labels" => [],
            ],
        ];
        $once = Classification::ensureIds($classes);
        $this->assertEquals("aaaaaaaa", $once[0]['id']);
        $this->assertEquals("bbbbbbbb", $once[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $once[0]['styles'][1]['id']);
        $this->assertNotEquals("bbbbbbbb", $once[0]['styles'][1]['id']);
        $twice = Classification::ensureIds($once);
        $this->assertEquals($once, $twice);
    }

    public function testEnsureIdsNormalizesLegacyClasses(): void
    {
        $legacy = [
            [
                "sortid" => 10,
                "name" => "Legacy",
                "color" => "#008000",
                "label" => true,
                "label_text" => "[name]",
            ],
        ];
        $result = Classification::ensureIds($legacy);
        $this->assertArrayNotHasKey('color', $result[0]);
        $this->assertCount(1, $result[0]['styles']);
        $this->assertCount(1, $result[0]['labels']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['styles'][0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['labels'][0]['id']);
    }

    public function testEnsureIdsReplacesNonStringIds(): void
    {
        $classes = [
            [
                "id" => 3,
                "name" => "A",
                "styles" => [["id" => 1, "color" => "#008000"]],
                "labels" => [],
            ],
            [
                "id" => "aaaaaaaa",
                "name" => "B",
                "styles" => [],
                "labels" => [],
            ],
        ];
        $result = Classification::ensureIds($classes);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[0]['styles'][0]['id']);
        $this->assertEquals("aaaaaaaa", $result[1]['id']);
    }

    public function testEnsureIdsDedupesDuplicateClassIds(): void
    {
        $classes = [
            ["id" => "dupe0000", "name" => "A", "styles" => [], "labels" => []],
            ["id" => "dupe0000", "name" => "B", "styles" => [], "labels" => []],
            ["id" => "keep1111", "name" => "C", "styles" => [], "labels" => []],
        ];
        $result = Classification::ensureIds($classes);
        // First occurrence keeps the id; the duplicate is reassigned a fresh unique one.
        $this->assertEquals("dupe0000", $result[0]['id']);
        $this->assertNotEquals("dupe0000", $result[1]['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $result[1]['id']);
        // An unrelated valid id further down is never clobbered by the reassignment.
        $this->assertEquals("keep1111", $result[2]['id']);
        // All class ids are now unique.
        $ids = array_column($result, 'id');
        $this->assertCount(count($ids), array_unique($ids));
        // Idempotent afterwards.
        $this->assertEquals($result, Classification::ensureIds($result));
    }

    public function testEnsureIdsDedupesDuplicateEntryIdsPerKind(): void
    {
        $classes = [[
            "id" => "class000", "name" => "A",
            "styles" => [
                ["id" => "same2222", "color" => "#008000"],
                ["id" => "same2222", "color" => "#ff0000"],
            ],
            // A label may reuse a style's id (different addressing scope) and is left untouched.
            "labels" => [["id" => "same2222", "on" => true, "text" => "[name]"]],
        ]];
        $result = Classification::ensureIds($classes);
        $this->assertEquals("same2222", $result[0]['styles'][0]['id']);
        $this->assertNotEquals("same2222", $result[0]['styles'][1]['id']);
        $styleIds = array_column($result[0]['styles'], 'id');
        $this->assertCount(count($styleIds), array_unique($styleIds));
        // Labels are a separate scope, so the label keeps the id shared with a style.
        $this->assertEquals("same2222", $result[0]['labels'][0]['id']);
    }

    public function testNextSortId(): void
    {
        $this->assertEquals(10, Classification::nextSortId([]));
        $this->assertEquals(40, Classification::nextSortId([["sortid" => 10], ["sortid" => 30]]));
        $this->assertEquals(10, Classification::nextSortId([["name" => "no sortid"]]));
        $this->assertEquals(30, Classification::nextSortId([["sortid" => "20"]]));
    }

    public function testMergeClassesIgnoresIdsWhenComparing(): void
    {
        // Stored classes carry ids, the wizard cache does not. The comparison that decides
        // "was this edited externally?" must ignore ids, or every wizard re-run would treat
        // all classes as externally modified and stop applying wizard updates.
        $cached = [
            ["name" => "A", "styles" => [["sortid" => 10, "color" => "#008000"]], "labels" => []],
        ];
        $existing = [
            ["id" => "aaaaaaaa", "name" => "A",
                "styles" => [["id" => "bbbbbbbb", "sortid" => 10, "color" => "#008000"]], "labels" => []],
        ];
        $incoming = [
            ["name" => "A", "styles" => [["sortid" => 10, "color" => "#ff0000"]], "labels" => []],
        ];
        $merged = Classification::mergeClasses($cached, $existing, $incoming);
        $this->assertEquals("#ff0000", $merged[0]['styles'][0]['color']);
        // A genuinely external edit is still preserved
        $existing[0]['styles'][0]['color'] = "#0000ff";
        $merged = Classification::mergeClasses($cached, $existing, $incoming);
        $this->assertEquals("#0000ff", $merged[0]['styles'][0]['color']);
    }
}
