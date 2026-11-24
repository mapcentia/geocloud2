<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\App;
use app\inc\MbtilesCache;
use Codeception\Test\Unit;

/**
 * The MBTiles cache layout and emptying it. The files are built with the
 * schema MapCache itself creates (tables "map" and "images", view "tiles").
 */
class MbtilesCacheTest extends Unit
{
    protected UnitTester $tester;
    private string $database;
    private ?string $pathBefore;

    protected function _before(): void
    {
        include_once '/var/www/geocloud2/app/conf/App.php';
        $this->database = 'zz_mbtiles_' . uniqid();
        $this->pathBefore = App::$param['mapCache']['path'] ?? null;
        App::$param['mapCache']['path'] = sys_get_temp_dir() . '/' . $this->database . '/';
    }

    protected function _after(): void
    {
        exec('rm -rf ' . escapeshellarg(sys_get_temp_dir() . '/' . $this->database));
        if ($this->pathBefore === null) {
            unset(App::$param['mapCache']['path']);
        } else {
            App::$param['mapCache']['path'] = $this->pathBefore;
        }
    }

    private function seed(string $tileset, string $grid, int $tiles): string
    {
        $dir = MbtilesCache::dir($this->database, $tileset);
        @mkdir($dir, 0777, true);
        $file = "$dir/$grid.mbtiles";
        $pdo = new PDO('sqlite:' . $file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE images(tile_id text, tile_data blob, primary key(tile_id))');
        $pdo->exec('CREATE TABLE map(zoom_level integer, tile_column integer, tile_row integer, tile_id text, primary key(tile_row,tile_column,zoom_level))');
        $pdo->exec('CREATE VIEW tiles AS SELECT map.zoom_level, map.tile_column, map.tile_row, images.tile_data FROM map JOIN images ON images.tile_id = map.tile_id');
        // Every tile points to the same image, as MapCache does for blank tiles
        $pdo->exec("INSERT INTO images VALUES('blank', x'00')");
        for ($i = 0; $i < $tiles; $i++) {
            $pdo->exec("INSERT INTO map VALUES(1, $i, 0, 'blank')");
        }
        return $file;
    }

    private function rows(string $file, string $table): int
    {
        return (int)(new PDO('sqlite:' . $file))->query("SELECT count(*) FROM $table")->fetchColumn();
    }

    public function testDirIsOnePerTileset(): void
    {
        $this->assertSame(
            sys_get_temp_dir() . "/{$this->database}/app/wms/mapcache/mbtiles/{$this->database}/dar.adresse.mvt",
            MbtilesCache::dir($this->database, 'dar.adresse.mvt')
        );
    }

    public function testEmptiesEveryGridAndKeepsTheFiles(): void
    {
        $a = $this->seed('s.t', 'g20', 3);
        $b = $this->seed('s.t', '25832', 2);

        $this->assertSame(5, MbtilesCache::emptyTileset($this->database, 's.t'));

        foreach ([$a, $b] as $file) {
            $this->assertFileExists($file, 'MapCache keeps the file open, so it must survive');
            $this->assertSame(0, $this->rows($file, 'tiles'));
            $this->assertSame(0, $this->rows($file, 'images'), 'the tile bytes must go too, not only the index');
        }
    }

    public function testLeavesOtherTilesetsAlone(): void
    {
        $this->seed('s.t', 'g20', 1);
        $vector = $this->seed('s.t.mvt', 'g20', 4);

        MbtilesCache::emptyTileset($this->database, 's.t');

        $this->assertSame(4, $this->rows($vector, 'tiles'),
            's.t.mvt is another tileset although its name starts with s.t');
    }

    public function testATilesetThatWasNeverCachedRemovesNothing(): void
    {
        $this->assertSame(0, MbtilesCache::emptyTileset($this->database, 'never.seeded'));
    }

    public function testAFileWithoutTablesIsNotAnError(): void
    {
        $dir = MbtilesCache::dir($this->database, 's.empty');
        mkdir($dir, 0777, true);
        new PDO('sqlite:' . $dir . '/g20.mbtiles')->exec('CREATE TABLE metadata(name text, value text)');

        $this->assertSame(0, MbtilesCache::emptyTileset($this->database, 's.empty'));
    }
}
