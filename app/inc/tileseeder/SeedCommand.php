<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\tileseeder;

use app\conf\App;
use app\controllers\Mapcache;
use app\exceptions\GC2Exception;

/**
 * The mapcache_seed invocation for one seed job.
 *
 * argv() returns a list, never a string, so the shell never re-parses a value the
 * caller supplied: v3 built one string and a tileset containing `; rm -rf /` ran.
 * The database password is only ever in env(), because a command line is readable
 * with ps by everyone on the node.
 */
final class SeedCommand
{
    public function __construct(
        private readonly string  $database,
        private readonly string  $tileset,
        private readonly string  $grid,
        private readonly int     $zoomStart,
        private readonly int     $zoomEnd,
        private readonly ?string $extentLayer,
        private readonly int     $threads,
    )
    {
    }

    /**
     * Everything that can be known before a process starts. Throws 400 rather than
     * letting mapcache_seed fail minutes later in a log nobody reads.
     *
     * @throws GC2Exception
     */
    public static function validate(string $database, string $tileset, string $grid,
                                    int    $zoomStart, int $zoomEnd, ?string $extentLayer, int $threads): void
    {
        foreach (['tileset' => $tileset, 'grid' => $grid, 'extent_layer' => $extentLayer] as $field => $value) {
            if ($value !== null && (str_contains($value, '..') || !preg_match('/^[A-Za-z0-9_.:\-]+$/', $value))) {
                throw new GC2Exception("Invalid $field", 400, null, 'INVALID_REQUEST');
            }
        }
        $config = self::configPath($database);
        if (!is_file($config)) {
            throw new GC2Exception('No tile cache configuration for this database', 404, null, 'NOT_FOUND');
        }
        if (!str_contains((string)file_get_contents($config), '<tileset name="' . $tileset . '"')) {
            throw new GC2Exception('Tileset not found', 404, null, 'TILESET_NOT_FOUND');
        }
        if (!array_key_exists($grid, Mapcache::getGrids())) {
            throw new GC2Exception('Unknown grid', 400, null, 'UNKNOWN_GRID');
        }
        if ($zoomStart < 0 || $zoomEnd < 0 || $zoomStart > $zoomEnd) {
            throw new GC2Exception('zoom_start must be >= 0 and <= zoom_end', 400, null, 'INVALID_REQUEST');
        }
        $maxThreads = App::$param['tileseeder']['maxThreads'] ?? 4;
        if ($threads < 1 || $threads > $maxThreads) {
            throw new GC2Exception("threads must be between 1 and $maxThreads", 400, null, 'INVALID_REQUEST');
        }
    }

    /**
     * @param array{host:string, port:string, user:string} $pgParams
     * @return list<string>
     */
    public function argv(array $pgParams): array
    {
        $argv = [
            App::$param['tileseeder']['seedBinary'] ?? '/usr/local/bin/mapcache_seed',
            '-c', self::configPath($this->database),
            '-t', $this->tileset,
            '-g', $this->grid,
            '-z', $this->zoomStart . ',' . $this->zoomEnd,
            '-n', (string)$this->threads,
            '-d', sprintf('PG:host=%s port=%s user=%s dbname=%s',
                $pgParams['host'], $pgParams['port'], $pgParams['user'], $this->database),
            '-v',
        ];
        if ($this->extentLayer !== null) {
            $argv[] = '-l';
            $argv[] = $this->extentLayer;
        }
        return $argv;
    }

    /** @return array<string, string> */
    public function env(string $pgPassword): array
    {
        return ['PGPASSWORD' => $pgPassword];
    }

    private static function configPath(string $database): string
    {
        return App::$param['path'] . 'app/wms/mapcache/' . $database . '.xml';
    }
}
