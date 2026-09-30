<?php
use app\exceptions\GC2Exception;
use app\inc\tileseeder\SeedCommand;
use Codeception\Test\Unit;

/**
 * The mapcache_seed command line. v3 interpolated the request's layer, grid,
 * extent and zooms into a shell string, so a bearer token was enough to run
 * arbitrary commands, and it put the database password where ps could read it.
 * Both are pinned here.
 */
class SeedCommandTest extends Unit
{
    protected UnitTester $tester;

    private string $database = 'seedcmdtest';

    protected function _before(): void
    {
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"myschema.roads\"/>\n</mapcache>\n");
    }

    protected function _after(): void
    {
        @unlink($this->configPath());
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->database . '.xml';
    }

    private function command(array $over = []): SeedCommand
    {
        return new SeedCommand(
            database: $over['database'] ?? 'mydb',
            tileset: $over['tileset'] ?? 'myschema.roads',
            grid: $over['grid'] ?? 'GoogleMapsCompatible',
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
        $argv = $this->command()->argv(['host' => 'db', 'port' => '5432', 'user' => 'mydb']);
        $this->assertStringNotContainsString('secret', implode(' ', $argv));
        $this->assertStringNotContainsString('password', strtolower(implode(' ', $argv)));
        $this->assertSame(['PGPASSWORD' => 'secret'], $this->command()->env('secret'));
    }

    public function testValidationRejectsWhatCannotBeSeeded(): void
    {
        // A grid the install does not have.
        $this->expectException(GC2Exception::class);
        SeedCommand::validate($this->database, 'myschema.roads', 'NoSuchGrid', 0, 4, null, 1);
    }

    public function testValidationRejectsZoomAndThreadRanges(): void
    {
        foreach ([[5, 2, 1, 'zoom_start above zoom_end'], [0, 4, 0, 'threads below one'], [0, 4, 99, 'threads above the cap'], [-1, 4, 1, 'negative zoom']] as [$start, $end, $threads, $why]) {
            try {
                SeedCommand::validate($this->database, 'myschema.roads', 'GoogleMapsCompatible', $start, $end, null, $threads);
                $this->fail("accepted $why");
            } catch (GC2Exception $e) {
                $this->assertSame(400, $e->getCode(), $why);
            }
        }
    }

    public function testPathTraversalInTheTilesetIsRejected(): void
    {
        $this->expectException(GC2Exception::class);
        SeedCommand::validate($this->database, '../../etc/passwd', 'GoogleMapsCompatible', 0, 1, null, 1);
    }
}
