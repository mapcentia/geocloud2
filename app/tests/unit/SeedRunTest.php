<?php
use app\conf\App;
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * app/scripts/seed_run.php runs one claimed job. It is driven here with a stub
 * instead of mapcache_seed — the recipe the scheduler's stop test uses — so the
 * state machine can be asserted without producing tiles.
 */
class SeedRunTest extends Unit
{
    protected UnitTester $tester;
    private string $stub;
    private array $created = [];

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_stub_' . uniqid() . '.sh';
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        foreach ($this->created as $uuid) {
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $uuid]);
        }
    }

    private function writeStub(string $body): void
    {
        file_put_contents($this->stub, "#!/bin/sh\n" . $body . "\n");
        chmod($this->stub, 0755);
    }

    /** Queue a row and claim it, the state seed_run.php expects to find. */
    private function claimed(): array
    {
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $row = $m->queue(['name' => 'run ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = $row['uuid'];
        return $m->claimOne();
    }

    private function runSeedScript(string $uuid): int
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_run.php')
            . ' --database=mydb --uuid=' . escapeshellarg($uuid)
            . ' --binary=' . escapeshellarg($this->stub) . ' 2>&1';
        exec($cmd, $out, $code);
        return $code;
    }

    public function testASuccessfulSeedEndsSucceededWithItsOutputInTheLog(): void
    {
        $this->writeStub('echo "seeding tile 1"; echo "done"; exit 0');
        $row = $this->claimed();
        $this->assertSame(0, $this->runSeedScript($row['uuid']));

        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $done = $m->get($row['uuid']);
        $this->assertSame('succeeded', $done['status']);
        $this->assertNotNull($done['finished']);
        $this->assertNotNull($done['pid'], 'the run records the pid it started');
        $this->assertStringContainsString('seeding tile 1', (string)$done['log']);
        $this->assertNull($done['error']);
    }

    public function testANonZeroExitEndsFailedWithTheCodeInTheError(): void
    {
        $this->writeStub('echo "cannot open config"; exit 3');
        $row = $this->claimed();
        $this->assertSame(1, $this->runSeedScript($row['uuid']));
        $done = new SeedJob(connection: new Connection(database: 'mydb'))->get($row['uuid']);
        $this->assertSame('failed', $done['status']);
        $this->assertStringContainsString('3', (string)$done['error'], 'the exit code is recorded');
        $this->assertStringContainsString('cannot open config', (string)$done['log']);
    }

    public function testACancelRequestStopsTheChildAndEndsCancelled(): void
    {
        $this->writeStub('trap "exit 0" TERM; while true; do echo tick; sleep 1; done');
        $row = $this->claimed();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        // Ask for cancellation before the run starts: the row is running with no
        // child yet, which is the window between claim and spawn.
        $m->requestCancel($row['uuid']);
        $this->assertSame(2, $this->runSeedScript($row['uuid']));
        $done = $m->get($row['uuid']);
        $this->assertSame('cancelled', $done['status']);
        $this->assertNotNull($done['finished']);
    }
}
