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

    public function testNextSortId(): void
    {
        $this->assertEquals(10, Classification::nextSortId([]));
        $this->assertEquals(40, Classification::nextSortId([["sortid" => 10], ["sortid" => 30]]));
        $this->assertEquals(10, Classification::nextSortId([["name" => "no sortid"]]));
        $this->assertEquals(30, Classification::nextSortId([["sortid" => "20"]]));
    }
}
