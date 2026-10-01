<?php
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\tileseeder\SeedCommand;
use Codeception\Test\Unit;

/**
 * The mapcache_seed command line. v3 interpolated the request's layer, grid,
 * extent and zooms into a shell string, so a bearer token was enough to run
 * arbitrary commands, and it put the database password where ps could read it.
 * Both are pinned here.
 *
 * So are the two defects the whole-branch review found, which every earlier test
 * missed because it either stubbed the binary or took its grid from the same
 * wrong source the code did: `grid` must be validated against the grids the
 * tileset itself declares (not app/conf/grids), and `-d` must never be passed
 * without `-l`.
 */
class SeedCommandTest extends Unit
{
    protected UnitTester $tester;

    private string $database = 'seedcmdtest';

    /** How many <resolutions> the fixture's grid has, i.e. its zoom levels. */
    private const int FIXTURE_LEVELS = 4;

    /**
     * A grid name generated per run, so it cannot possibly be a file in
     * app/conf/grids. That is the whole point: the fixture's tileset declares
     * this grid, and validate() must accept it on the strength of that
     * declaration alone. Validating against Mapcache::getGrids() — what the code
     * did before the review — rejects it with UNKNOWN_GRID, exactly as it
     * rejected the real "g20" that every real tileset declares.
     */
    private string $declaredGrid;

    /** Defined in the fixture's config, but NOT declared by its tileset. */
    private string $undeclaredGrid;

    protected function _before(): void
    {
        $this->declaredGrid = 'gridtest_' . uniqid();
        $this->undeclaredGrid = 'otherfixturegrid_' . uniqid();
        $resolutions = implode(' ', array_slice([1638.4, 819.2, 409.6, 204.8, 102.4], 0, self::FIXTURE_LEVELS));
        file_put_contents($this->configPath(), <<<XML
            <mapcache>
              <grid name="{$this->declaredGrid}">
                <extent>120000 5900000 1000000 6500000</extent>
                <resolutions>$resolutions</resolutions>
              </grid>
              <grid name="{$this->undeclaredGrid}">
                <resolutions>1638.4 819.2</resolutions>
              </grid>
              <tileset name="myschema.roads">
                <grid>{$this->declaredGrid}</grid>
              </tileset>
              <tileset name="myschema.gridless"/>
            </mapcache>
            XML);
    }

    protected function _after(): void
    {
        @unlink($this->configPath());
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->database . '.xml';
    }

    /**
     * A connection validate() only ever uses when extent_layer is given (to prove
     * that relation exists). It addresses mydb — a real database every other unit
     * test here already uses — while the config fixture above is written under
     * this test's own fake database name; the two parameters are independent.
     */
    private function connection(): Connection
    {
        return new Connection(database: 'mydb');
    }

    private function command(array $over = []): SeedCommand
    {
        return new SeedCommand(
            database: $over['database'] ?? 'mydb',
            tileset: $over['tileset'] ?? 'myschema.roads',
            grid: $over['grid'] ?? 'g20',
            zoomStart: $over['zoomStart'] ?? 0,
            zoomEnd: $over['zoomEnd'] ?? 4,
            extentLayer: $over['extentLayer'] ?? null,
            threads: $over['threads'] ?? 2,
        );
    }

    public function testEveryValueIsItsOwnArgumentAndNothingIsInterpolated(): void
    {
        $argv = $this->command(['tileset' => 'myschema.roads; rm -rf /'])->argv([
            'host' => 'db', 'port' => '5432', 'user' => 'mydb',
        ]);
        // argv is a list, not a string: the shell never parses these.
        $this->assertContains('myschema.roads; rm -rf /', $argv, 'the value travels as one argument');
        $this->assertNotContains('rm', $argv, 'and never as a command of its own');
        $this->assertSame('-t', $argv[array_search('myschema.roads; rm -rf /', $argv, true) - 1]);
    }

    public function testThePasswordIsInTheEnvironmentNotInArgv(): void
    {
        $argv = $this->command(['extentLayer' => 'myschema.municipality'])
            ->argv(['host' => 'db', 'port' => '5432', 'user' => 'mydb']);
        // Asserted on the argv that *does* carry the PG datasource (extent_layer
        // set): without that, -d is absent altogether and this proves nothing.
        $this->assertContains('-d', $argv, 'this case is the one that passes a datasource');
        $this->assertStringNotContainsString('secret', implode(' ', $argv));
        $this->assertStringNotContainsString('password', strtolower(implode(' ', $argv)));
        $this->assertSame(['PGPASSWORD' => 'secret'], $this->command()->env('secret'));
    }

    /**
     * Critical 2. mapcache_seed validates its OGR datasource before seeding
     * anything, and `-d` with no `-l` makes it print "ogr datastore contains more
     * than one layer…", dump its usage and exit 1 — so an unconditional `-d`
     * broke every seed without an extent_layer, which is the normal case
     * (extent_layer is optional in the API, in the spec's own example and in v3's
     * mapping). Remove the condition in argv() and this test fails on its first
     * assertion.
     */
    public function testTheOgrDatasourceIsOnlyPassedTogetherWithItsLayer(): void
    {
        $pg = ['host' => 'db', 'port' => '5432', 'user' => 'mydb'];

        $withoutExtent = $this->command()->argv($pg);
        $this->assertNotContains('-d', $withoutExtent, 'no extent layer means no ogr datasource at all');
        $this->assertNotContains('-l', $withoutExtent);
        $this->assertStringNotContainsString('PG:', implode(' ', $withoutExtent));

        $withExtent = $this->command(['extentLayer' => 'myschema.municipality'])->argv($pg);
        $d = array_search('-d', $withExtent, true);
        $l = array_search('-l', $withExtent, true);
        $this->assertNotFalse($d, 'an extent layer needs the datasource it lives in');
        $this->assertNotFalse($l);
        $this->assertStringStartsWith('PG:host=db port=5432 user=mydb dbname=', $withExtent[$d + 1]);
        $this->assertSame('myschema.municipality', $withExtent[$l + 1]);
    }

    /**
     * Critical 1, in the direction that was actually broken: the grid a tileset
     * declares in this database's own mapcache config is accepted, whether or not
     * a file of that name exists in app/conf/grids. $declaredGrid is generated per
     * run precisely so it cannot be in app/conf/grids, so the pre-review check
     * (array_key_exists($grid, Mapcache::getGrids())) refuses it and this test
     * fails — the same way it refused the real g20 that every GC2-generated
     * tileset declares and nothing else accepts.
     */
    public function testAGridTheTilesetItselfDeclaresIsAccepted(): void
    {
        $this->assertArrayNotHasKey($this->declaredGrid, \app\controllers\Mapcache::getGrids(),
            'the fixture grid must not exist in app/conf/grids, or this test proves nothing');
        SeedCommand::validate($this->database, 'myschema.roads', $this->declaredGrid, 0, 1, null, 1, $this->connection());
        $this->assertTrue(true, 'validate() accepted the tileset\'s own grid');
    }

    /**
     * The other direction: a grid that exists in app/conf/grids but is not one
     * this tileset declares must be refused, because mapcache_seed itself refuses
     * it ("grid not configured for tileset", exit 1) minutes later. Under the
     * pre-review check this is a pass, so validate() queued a job that could
     * never run. Skipped — not passed — on an install with no grid files, where
     * there is no such name to try.
     */
    public function testAGridFromAppConfGridsIsRefusedWhenTheTilesetDoesNotDeclareIt(): void
    {
        $installGrids = array_keys(\app\controllers\Mapcache::getGrids());
        $installGrids = array_values(array_filter($installGrids, fn($g) => (string)$g !== $this->declaredGrid));
        if ($installGrids === []) {
            $this->markTestSkipped('this install has no grids in app/conf/grids to try');
        }
        try {
            SeedCommand::validate($this->database, 'myschema.roads', (string)$installGrids[0], 0, 1, null, 1, $this->connection());
            $this->fail('accepted a grid the tileset does not declare: ' . $installGrids[0]);
        } catch (GC2Exception $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('UNKNOWN_GRID', $e->getErrorCode());
            $this->assertStringContainsString($this->declaredGrid, $e->getMessage(),
                'the message must name the grids the tileset does have: that is what an operator needs');
        }
    }

    /** A tileset with no <grid> children at all can be seeded with nothing, and
     *  the message must say so rather than naming an empty list. */
    public function testATilesetWithoutGridsRefusesEveryGrid(): void
    {
        try {
            SeedCommand::validate($this->database, 'myschema.gridless', $this->declaredGrid, 0, 1, null, 1, $this->connection());
            $this->fail('accepted a grid for a tileset that declares none');
        } catch (GC2Exception $e) {
            $this->assertSame('UNKNOWN_GRID', $e->getErrorCode());
            $this->assertStringContainsString('no grids', $e->getMessage());
        }
    }

    public function testValidationRejectsWhatCannotBeSeeded(): void
    {
        // A grid neither the tileset nor the install has.
        $this->expectException(GC2Exception::class);
        SeedCommand::validate($this->database, 'myschema.roads', 'NoSuchGrid', 0, 4, null, 1, $this->connection());
    }

    public function testValidationRejectsZoomAndThreadRanges(): void
    {
        $grid = $this->declaredGrid;
        $cases = [
            [5, 2, 1, 'INVALID_REQUEST', 'zoom_start above zoom_end'],
            [-1, 4, 1, 'INVALID_REQUEST', 'negative zoom'],
            [0, 1, 0, 'INVALID_REQUEST', 'threads below one'],
            [0, 1, 99, 'INVALID_REQUEST', 'threads above the cap'],
        ];
        foreach ($cases as [$start, $end, $threads, $code, $why]) {
            try {
                SeedCommand::validate($this->database, 'myschema.roads', $grid, $start, $end, null, $threads, $this->connection());
                $this->fail("accepted $why");
            } catch (GC2Exception $e) {
                $this->assertSame(400, $e->getCode(), $why);
                $this->assertSame($code, $e->getErrorCode(), "$why must fail on its own check, not an earlier one");
            }
        }
    }

    /**
     * Spec §7: "both integers within the grid's levels". The fixture grid has
     * FIXTURE_LEVELS resolutions, so the highest zoom it has is
     * FIXTURE_LEVELS - 1; without the level check, zoom_end: 40 passed validation
     * and reached the binary. The accepted case in the same test is what keeps the
     * bound from being tightened by one.
     */
    public function testZoomMustBeWithinTheGridsOwnLevels(): void
    {
        $top = self::FIXTURE_LEVELS - 1;
        SeedCommand::validate($this->database, 'myschema.roads', $this->declaredGrid, 0, $top, null, 1, $this->connection());

        foreach ([$top + 1, 40] as $tooDeep) {
            try {
                SeedCommand::validate($this->database, 'myschema.roads', $this->declaredGrid, 0, $tooDeep, null, 1, $this->connection());
                $this->fail("accepted zoom_end $tooDeep on a $top-level grid");
            } catch (GC2Exception $e) {
                $this->assertSame(400, $e->getCode());
                $this->assertSame('INVALID_REQUEST', $e->getErrorCode());
                $this->assertStringContainsString('zoom_end', $e->getMessage());
            }
        }
    }

    /**
     * Spec §7, extent_layer: an unknown one is a 404 now, not an exit-1 seed run
     * minutes later in a log nobody reads. The positive side of this check — a
     * relation that does exist is accepted — is covered by the API cests, which
     * create their own table and own the privilege half of the rule too.
     */
    public function testAnUnknownExtentLayerIsRefusedBeforeAnythingIsQueued(): void
    {
        // The accepted case first: public.spatial_ref_sys exists in every PostGIS
        // database, so a check that simply always threw would fail here rather
        // than pass the refusal below for the wrong reason.
        SeedCommand::validate($this->database, 'myschema.roads', $this->declaredGrid, 0, 1,
            'public.spatial_ref_sys', 1, $this->connection());

        try {
            SeedCommand::validate($this->database, 'myschema.roads', $this->declaredGrid, 0, 1,
                'nosuchschema.nosuchrelation', 1, $this->connection());
            $this->fail('accepted an extent layer that does not exist');
        } catch (GC2Exception $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertSame('EXTENT_LAYER_NOT_FOUND', $e->getErrorCode());
        }
    }

    public function testPathTraversalInTheTilesetIsRejected(): void
    {
        $this->expectException(GC2Exception::class);
        SeedCommand::validate($this->database, '../../etc/passwd', $this->declaredGrid, 0, 1, null, 1, $this->connection());
    }
}
