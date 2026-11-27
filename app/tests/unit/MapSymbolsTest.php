<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\MapSymbols;
use app\models\Mapfile;
use Codeception\Test\Unit;

/**
 * app\inc\MapSymbols is the single source of the built-in MapServer symbols:
 * the mapfile block and GET /api/v4/symbols are both generated from it. These
 * tests pin the definitions to what the mapfile has always contained.
 */
class MapSymbolsTest extends Unit
{
    protected UnitTester $tester;

    private const NAMES = [
        'triangle', 'circle', 'square', 'star', 'hatch1', 'dashed1', 'arrow', 'arrow2', 'continue',
        'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'dot-dot',
    ];

    private function byName(string $name): array
    {
        foreach (MapSymbols::all() as $symbol) {
            if ($symbol['name'] === $name) {
                return $symbol;
            }
        }
        $this->fail("No symbol named $name");
    }

    public function testNamesInMapfileOrder(): void
    {
        $this->assertSame(self::NAMES, array_column(MapSymbols::all(), 'name'));
    }

    public function testDefinitionShape(): void
    {
        foreach (MapSymbols::all() as $symbol) {
            $name = $symbol['name'];
            $this->assertContains($symbol['type'], ['vector', 'ellipse'], $name);
            $this->assertIsBool($symbol['filled'], $name);
            $this->assertSame($name !== 'hatch1', $symbol['filled'], $name);
            $this->assertNotEmpty($symbol['points'], $name);
            $this->assertTrue(array_is_list($symbol['points']), $name);
            foreach ($symbol['points'] as $point) {
                $this->assertCount(2, $point, $name);
                $this->assertTrue(array_is_list($point), $name);
                foreach ($point as $n) {
                    $this->assertTrue(is_int($n) || is_float($n), $name);
                }
            }
            if (in_array($name, ['arrow', 'arrow2'], true)) {
                $this->assertSame([0, 0.5], $symbol['anchorpoint'], $name);
            } else {
                $this->assertArrayNotHasKey('anchorpoint', $symbol, $name);
            }
        }
    }

    public function testExactGeometry(): void
    {
        $expected = [
            'triangle' => ['vector', [[0, 1], [.5, 0], [1, 1], [0, 1]]],
            'circle' => ['ellipse', [[1, 1]]],
            'square' => ['vector', [[0, 1], [0, 0], [1, 0], [1, 1], [0, 1]]],
            'star' => ['vector', [[0, .375], [.35, .375], [.5, 0], [.65, .375], [1, .375], [.75, .625], [.875, 1], [.5, .75], [.125, 1], [.25, .625]]],
            'hatch1' => ['vector', [[0, 1], [1, 0]]],
            'dashed1' => ['ellipse', [[1, 1]]],
            'arrow' => ['vector', [[0, 0.4], [3, 0.4], [3, 0], [5, 0.8], [3, 1.6], [3, 1.2], [0, 1.2], [0, 0.4]]],
            'arrow2' => ['vector', [[0, 0.8], [1, 0.4], [0, 0], [0, 0.8]]],
            'continue' => ['ellipse', [[1, 1]]],
            'dashed-line-short' => ['ellipse', [[10, 1]]],
            'dashed-line-long' => ['ellipse', [[10, 10]]],
            'dash-dot' => ['ellipse', [[20, 6], [2, 6]]],
            'dash-dot-dot' => ['ellipse', [[1, 1]]],
            'dot-dot' => ['ellipse', [[1, 1]]],
        ];
        foreach ($expected as $name => [$type, $points]) {
            $symbol = $this->byName($name);
            $this->assertSame($type, $symbol['type'], $name);
            $this->assertSame($points, $symbol['points'], $name);
        }
    }

    public function testRenderMatchesDefinitions(): void
    {
        $s = MapSymbols::render();
        $this->assertSame(14, substr_count($s, "SYMBOL\n"));
        foreach (self::NAMES as $name) {
            $this->assertSame(1, substr_count($s, "NAME \"$name\"\n"), $name);
        }
        $this->assertStringContainsString("NAME \"star\"\nTYPE VECTOR\nFILLED TRUE\nPOINTS\n0 0.375\n0.35 0.375\n", $s);
        $this->assertStringContainsString("NAME \"hatch1\"\nTYPE VECTOR\nFILLED FALSE\nPOINTS\n0 1\n1 0\nEND\nEND\n", $s);
        $this->assertStringContainsString("NAME \"dash-dot\"\nTYPE ELLIPSE\nFILLED TRUE\nPOINTS\n20 6\n2 6\nEND\nEND\n", $s);
        $this->assertStringContainsString("3 1.2\n0 1.2\n0 0.4\nEND\nANCHORPOINT 0 0.5\nEND\n", $s);
        $this->assertSame(2, substr_count($s, "ANCHORPOINT 0 0.5\n"));
        // Plain decimals only: no exponent, no locale comma
        $this->assertDoesNotMatchRegularExpression('/\d[eE][-+]?\d|\d,\d/', $s);
    }

    public function testMapfileDelegates(): void
    {
        $mapfile = (new ReflectionClass(Mapfile::class))->newInstanceWithoutConstructor();
        $this->assertSame(MapSymbols::render(), $mapfile->renderSymbols());
    }
}
