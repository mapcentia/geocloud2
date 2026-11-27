<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

/**
 * The built-in MapServer symbols a style's `symbol` can name.
 *
 * Single source of truth: every generated mapfile gets these as inline SYMBOL
 * blocks (render(), used by app\models\Mapfile), and GET /api/v4/symbols
 * serves the same definitions (all()) so a client can list and draw them.
 * Hand-kept copies of this list have drifted before, so add or change a
 * symbol here and nowhere else.
 *
 * Stateless: constants only, safe under a resident PHP SAPI.
 */
final class MapSymbols
{
    /**
     * In mapfile order. `points` are [x, y] pairs as MapServer reads them;
     * `anchorpoint` is present only where the symbol defines one.
     *
     * Several "line" symbols (dashed1, continue, dash-dot-dot, dot-dot) are
     * the same ellipse as circle: the dash look comes from the style's
     * PATTERN/GAP, not from the symbol.
     */
    private const SYMBOLS = [
        ['name' => 'triangle', 'type' => 'vector', 'filled' => true, 'points' => [[0, 1], [0.5, 0], [1, 1], [0, 1]]],
        ['name' => 'circle', 'type' => 'ellipse', 'filled' => true, 'points' => [[1, 1]]],
        ['name' => 'square', 'type' => 'vector', 'filled' => true, 'points' => [[0, 1], [0, 0], [1, 0], [1, 1], [0, 1]]],
        ['name' => 'star', 'type' => 'vector', 'filled' => true, 'points' => [[0, 0.375], [0.35, 0.375], [0.5, 0], [0.65, 0.375], [1, 0.375], [0.75, 0.625], [0.875, 1], [0.5, 0.75], [0.125, 1], [0.25, 0.625]]],
        ['name' => 'hatch1', 'type' => 'vector', 'filled' => false, 'points' => [[0, 1], [1, 0]]],
        ['name' => 'dashed1', 'type' => 'ellipse', 'filled' => true, 'points' => [[1, 1]]],
        ['name' => 'arrow', 'type' => 'vector', 'filled' => true, 'points' => [[0, 0.4], [3, 0.4], [3, 0], [5, 0.8], [3, 1.6], [3, 1.2], [0, 1.2], [0, 0.4]], 'anchorpoint' => [0, 0.5]],
        ['name' => 'arrow2', 'type' => 'vector', 'filled' => true, 'points' => [[0, 0.8], [1, 0.4], [0, 0], [0, 0.8]], 'anchorpoint' => [0, 0.5]],
        ['name' => 'continue', 'type' => 'ellipse', 'filled' => true, 'points' => [[1, 1]]],
        ['name' => 'dashed-line-short', 'type' => 'ellipse', 'filled' => true, 'points' => [[10, 1]]],
        ['name' => 'dashed-line-long', 'type' => 'ellipse', 'filled' => true, 'points' => [[10, 10]]],
        ['name' => 'dash-dot', 'type' => 'ellipse', 'filled' => true, 'points' => [[20, 6], [2, 6]]],
        ['name' => 'dash-dot-dot', 'type' => 'ellipse', 'filled' => true, 'points' => [[1, 1]]],
        ['name' => 'dot-dot', 'type' => 'ellipse', 'filled' => true, 'points' => [[1, 1]]],
    ];

    /**
     * @return list<array{name: string, type: string, filled: bool, points: list<array{0: int|float, 1: int|float}>, anchorpoint?: array{0: int|float, 1: int|float}}>
     */
    public static function all(): array
    {
        return self::SYMBOLS;
    }

    /**
     * The definitions as MapServer SYMBOL blocks, for the MAP level of a mapfile.
     */
    public static function render(): string
    {
        $s = '';
        foreach (self::SYMBOLS as $symbol) {
            $s .= "SYMBOL\n";
            $s .= "NAME \"{$symbol['name']}\"\n";
            $s .= "TYPE " . strtoupper($symbol['type']) . "\n";
            $s .= "FILLED " . ($symbol['filled'] ? "TRUE" : "FALSE") . "\n";
            $s .= "POINTS\n";
            foreach ($symbol['points'] as $point) {
                $s .= self::pair($point) . "\n";
            }
            $s .= "END\n";
            if (isset($symbol['anchorpoint'])) {
                $s .= "ANCHORPOINT " . self::pair($symbol['anchorpoint']) . "\n";
            }
            $s .= "END\n\n";
        }
        return $s;
    }

    /**
     * "x y" in plain decimals: json_encode is locale independent and never
     * uses an exponent for values of this size.
     */
    private static function pair(array $point): string
    {
        return json_encode($point[0]) . ' ' . json_encode($point[1]);
    }
}
