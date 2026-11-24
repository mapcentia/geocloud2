<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use PDO;
use PDOException;
use RuntimeException;

/**
 * The on-disk layout of MapCache's "mbtiles" cache backend, and emptying it.
 *
 * An MBTiles file holds one tileset in one grid, so the generated config
 * (app\models\Mapcachefile) gives every tileset a directory with one file per
 * grid:
 *
 *   app/wms/mapcache/mbtiles/<database>/<tileset>/<grid>.mbtiles
 *
 * Only image tilesets: MapCache decodes a tile as an image before storing it,
 * so the vector tilesets (.mvt, .json) of an mbtiles layer or schema are kept
 * in sqlite (Mapcachefile::vectorCache()).
 *
 * A directory per tileset rather than a "<tileset>.<grid>.mbtiles" name,
 * because tileset names contain dots (schema.table, schema.table.mvt) and a
 * name pattern for one tileset would also match another's files.
 */
class MbtilesCache
{
    /** The directory that holds a tileset's files, without a trailing slash. */
    public static function dir(string $database, string $tileset): string
    {
        $path = App::$param['mapCache']['path'] ?? App::$param['path'];
        return $path . 'app/wms/mapcache/mbtiles/' . $database . '/' . $tileset;
    }

    /**
     * Empties every grid's file of a tileset and returns the number of tiles
     * removed. A tileset that was never cached has no files and removes 0.
     *
     * The rows are deleted and the files kept: MapCache holds the files open,
     * possibly on another instance that this process cannot reload, and would
     * not recreate an unlinked file. MapCache stores a tile as a row in "map"
     * that points to its bytes in "images" ("tiles" is a view over the two).
     *
     * @throws RuntimeException when a file cannot be emptied.
     */
    public static function emptyTileset(string $database, string $tileset): int
    {
        $removed = 0;
        foreach (glob(self::dir($database, $tileset) . '/*.mbtiles') ?: [] as $file) {
            try {
                $pdo = new PDO('sqlite:' . $file);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // wait briefly if MapCache is mid-write
                $removed += (int)$pdo->exec('DELETE FROM map');
                $pdo->exec('DELETE FROM images');
                $pdo = null;
            } catch (PDOException $e) {
                // A file without the tables has never cached anything
                if (!str_contains($e->getMessage(), 'no such table')) {
                    throw new RuntimeException('Could not empty ' . basename($file) . ': ' . $e->getMessage(), 0, $e);
                }
            }
        }
        return $removed;
    }
}
