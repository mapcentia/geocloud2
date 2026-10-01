<?php
use app\conf\App;
use app\inc\Connection;
use app\inc\tileseeder\SeedCommand;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * One seed, end to end, through the real mapcache_seed — no stub anywhere.
 *
 * This test exists because every other test in this feature substitutes a stub
 * for the binary, and that is precisely why two defects that made the feature
 * incapable of producing a single tile shipped through review:
 *
 *  1. `grid` was validated against app/conf/grids (via Mapcache::getGrids())
 *     while every tileset in GC2's generated mapcache config declares only the
 *     inline grid `g20`, so the one grid that works was a 400 and the one the
 *     API accepted made mapcache_seed exit 1 with "grid not configured for
 *     tileset". No stub cares which grid it is given.
 *  2. The OGR datasource `-d` was passed without `-l` whenever `extent_layer`
 *     was absent — the normal case — and mapcache_seed validates the datasource
 *     before seeding: it printed "ogr datastore contains more than one layer",
 *     dumped its usage and exited 1. No stub parses argv either.
 *
 * Both are now impossible to reintroduce silently: either one turns this test's
 * row 'failed' instead of 'succeeded', with the binary's own complaint in the
 * log the assertion prints.
 *
 * It seeds zoom 0 only — a single 256x256 tile of one existing tileset, about a
 * second — so it does write one tile into that tileset's configured cache. The
 * tile is ordinary cache content: MapCache would have produced exactly the same
 * one on the first request for it, and a tile delete or an expiry removes it.
 */
class SeedRealBinaryTest extends Unit
{
    protected UnitTester $tester;

    /** The same real database the other seeder unit tests use. */
    private const string DATABASE = 'mydb';

    private ?string $uuid = null;
    private ?string $logPath = null;

    protected function _after(): void
    {
        if ($this->uuid !== null) {
            $m = new SeedJob(connection: new Connection(database: self::DATABASE));
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $this->uuid]);
        }
        if ($this->logPath !== null) {
            @unlink($this->logPath);
        }
    }

    public function testARealSeedOfOneTileReachesSucceeded(): void
    {
        $binary = App::$param['tileseeder']['seedBinary'] ?? '/usr/local/bin/mapcache_seed';
        if (!is_file($binary) || !is_executable($binary)) {
            $this->markTestSkipped("no seed binary at $binary on this install");
        }
        $config = App::$param['path'] . 'app/wms/mapcache/' . self::DATABASE . '.xml';
        if (!is_file($config)) {
            $this->markTestSkipped('no tile cache configuration for ' . self::DATABASE . ' on this install');
        }
        $seedable = $this->firstSeedableTileset($config);
        if ($seedable === null) {
            $this->markTestSkipped('no tileset with a declared grid in ' . $config);
        }
        [$tileset, $grid] = $seedable;

        $connection = new Connection(database: self::DATABASE);
        $jobs = new SeedJob(connection: $connection);
        // The tick claims the oldest pending row in the database and runs at most
        // tileseeder.maxConcurrent (default 1) seeds per node, so a foreign
        // pending row would be claimed instead of this test's, and a foreign
        // running row would fill the only slot. Both are preconditions this test
        // cannot create or clear, so it says so and skips rather than failing
        // something that is not about the code.
        if ($jobs->hasPending()) {
            $this->markTestSkipped(self::DATABASE . ' already has a pending seed job; the tick would claim that one first');
        }
        if ($jobs->countRunningOnHost(SeedJob::currentHost()) > 0) {
            $this->markTestSkipped('a seed is already running on this node; maxConcurrent leaves no slot');
        }

        // The request path's own validation, against the real config: this is
        // where defect 1 lived. With the grid taken from app/conf/grids this
        // throws UNKNOWN_GRID for the grid the tileset actually declares, before
        // anything is queued at all.
        SeedCommand::validate(self::DATABASE, $tileset, $grid, 0, 0, null, 1, $connection);

        $row = $jobs->queue(['name' => 'real seed ' . uniqid(), 'username' => 'tester', 'tileset' => $tileset,
            'grid' => $grid, 'zoom_start' => 0, 'zoom_end' => 0, 'extent_layer' => null, 'threads' => 1]);
        $this->uuid = $row['uuid'];

        // The real tick, with no --binary: whatever App::$param says, or the
        // default, is what runs. This is where defect 2 lived.
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_worker.php')
            . ' --database=' . escapeshellarg(self::DATABASE) . ' 2>&1';
        exec($cmd, $tickOut);
        $this->assertStringContainsString($this->uuid, implode("\n", $tickOut),
            'the tick claimed and spawned this job: ' . implode("\n", $tickOut));

        $final = null;
        $deadline = microtime(true) + 180;
        while (microtime(true) < $deadline) {
            $current = $jobs->get($this->uuid);
            if (!in_array($current['status'], ['pending', 'running'], true)) {
                $final = $current;
                break;
            }
            usleep(500000);
        }
        $this->assertNotNull($final, 'the seed reached a terminal status within 180s');
        $this->logPath = $final['log_path'] ?: null;
        $this->assertSame('succeeded', $final['status'],
            "seeding $tileset on grid $grid failed: " . ($final['error'] ?? '') . "\n" . ($final['log'] ?? '(no log)'));
        // Proof it was really mapcache_seed behind the row and not a stub or a
        // short-circuit: only that binary prints these, and only at the end of a
        // run it completed. "0 tiles needed to be seeded" is the same success
        // when the tile is already in the cache from an earlier run.
        $this->assertMatchesRegularExpression('/seeded \d+ tiles|tiles needed to be seeded/',
            (string)$final['log'], 'the log must carry mapcache_seed\'s own closing line');
        $this->assertNull($final['error']);
    }

    /**
     * The first tileset in the config that can actually be seeded, with one of
     * the grids it itself declares. Raster/vector variants (.mvt/.json) are
     * skipped: they are the same layer through a different format and are not
     * what a one-tile smoke seed should write.
     *
     * @return array{0: string, 1: string}|null
     */
    private function firstSeedableTileset(string $config): ?array
    {
        // LIBXML_RECOVER for the same reason SeedCommand uses it: the generated
        // config carries bare `&` characters in its mapserv URLs and a strict
        // parse rejects the whole document. Without recovery this test would
        // silently skip on every real install — which is exactly the kind of
        // vacuous pass the stubs produced.
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($config, SimpleXMLElement::class, LIBXML_NOERROR | LIBXML_RECOVER);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$xml instanceof SimpleXMLElement) {
            return null;
        }
        try {
            // An uninitialised element (LIBXML_RECOVER on a file with no root) raises
            // an Error on every access, getName() included.
            if ($xml->getName() === '') {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        foreach ($xml->tileset as $tileset) {
            $name = (string)$tileset['name'];
            if ($name === '' || preg_match('/\.(mvt|json)$/i', $name)) {
                continue;
            }
            foreach ($tileset->grid as $grid) {
                $gridName = trim((string)$grid);
                if ($gridName !== '') {
                    return [$name, $gridName];
                }
            }
        }
        return null;
    }
}
