<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\models\Mapcachefile;
use Codeception\Test\Unit;

/**
 * The draw order of the merged per-schema tileset.
 *
 * That tileset is one image drawn from every layer in the schema, and its WMS
 * source carries them in a single LAYERS list — where the order IS the draw order,
 * later layers on top. The query behind it had no ORDER BY and settings.getColumns()
 * selects sort_id without sorting by it, so the order was whatever Postgres
 * happened to return: measured on geodk, extent (sort_id 10) came out after
 * baggrund (sort_id 20), i.e. drawn on top of it, and nothing guaranteed the same
 * order from one run of the config generator to the next.
 *
 * Low sort_id means bottom, so LAYERS is ordered by sort_id ascending.
 */
class MapcacheLayerOrderTest extends Unit
{
    protected UnitTester $tester;

    /** @return array<int,array{0:string,1:?int}> name and sort_id, by sort_id */
    private function expected(string $schema): array
    {
        $model = new app\inc\Model(connection: new Connection(database: 'mydb'));
        $res = $model->prepare(
            "SELECT split_part(_key_, '.', 2) AS tbl, sort_id
               FROM settings.geometry_columns_join
              WHERE split_part(_key_, '.', 1) = :schema AND COALESCE(enableows, true)
              ORDER BY sort_id NULLS FIRST, 1"
        );
        $model->execute($res, ['schema' => $schema]);
        return array_map(fn($r) => [$r['tbl'], $r['sort_id']], $model->fetchAll($res, 'assoc'));
    }

    private function layersOf(string $xml, string $schema): array
    {
        if (!preg_match('/<!-- ' . preg_quote($schema, '/') . ' -->.*?<LAYERS>([^<]*)</s', $xml, $m)) {
            $this->fail("no merged source found for schema $schema");
        }
        return explode(',', $m[1]);
    }

    public function testTheMergedSourceDrawsLowSortIdFirst(): void
    {
        $xml = (new Mapcachefile(new Connection(database: 'mydb')))->generate();
        $layers = $this->layersOf($xml, 'geodk');

        $sortIds = [];
        foreach ($layers as $qualified) {
            $tbl = explode('.', $qualified, 2)[1] ?? '';
            foreach ($this->expected('geodk') as [$name, $sortId]) {
                if ($name === $tbl) {
                    $sortIds[] = (int)$sortId;
                    break;
                }
            }
        }
        $this->assertNotEmpty($sortIds, 'geodk has layers with a sort_id in this install');
        $sorted = $sortIds;
        sort($sorted);
        $this->assertSame($sorted, $sortIds,
            'LAYERS must be in ascending sort_id order, so the lowest is drawn first and ends up at the bottom');
    }

    /**
     * Without a tie-break the order among equal sort_ids is Postgres' choice, which
     * can differ between two runs — and then the generated config changes for no
     * reason, which is exactly what the byte-identical guard is there to catch.
     */
    public function testTheOrderIsStableAcrossTwoGenerations(): void
    {
        $first = (new Mapcachefile(new Connection(database: 'mydb')))->generate();
        $second = (new Mapcachefile(new Connection(database: 'mydb')))->generate();
        foreach (['geodk', 'public', 'dagi'] as $schema) {
            if (!str_contains($first, "<!-- $schema -->")) {
                continue;
            }
            $this->assertSame($this->layersOf($first, $schema), $this->layersOf($second, $schema),
                "the LAYERS order for $schema must not change between generations");
        }
    }
}
