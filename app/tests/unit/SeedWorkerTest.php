<?php
use app\conf\App;
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * The tick: reap what died, claim what waits, spawn a run per job, return at once.
 * It must not run a seed inline — a seed lasts hours and the tick holds flock.
 */
class SeedWorkerTest extends Unit
{
    protected UnitTester $tester;
    private array $created = [];
    private string $stub;

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_worker_stub_' . uniqid() . '.sh';
        file_put_contents($this->stub, "#!/bin/sh\nsleep 30\n");
        chmod($this->stub, 0755);
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        foreach ($this->created as $uuid) {
            $row = $m->get($uuid);
            if (!empty($row['pid'])) {
                exec('kill -9 ' . (int)$row['pid'] . ' 2>/dev/null');
            }
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $uuid]);
        }
    }

    private function queue(): array
    {
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $row = $m->queue(['name' => 'tick ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = $row['uuid'];
        return $row;
    }

    private function tick(): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_worker.php')
            . ' --database=mydb --binary=' . escapeshellarg($this->stub) . ' 2>&1';
        $start = microtime(true);
        exec($cmd, $out);
        return ['seconds' => microtime(true) - $start, 'out' => implode("\n", $out)];
    }

    public function testTheTickClaimsSpawnsAndReturnsImmediately(): void
    {
        $row = $this->queue();
        $result = $this->tick();
        $this->assertLessThan(10, $result['seconds'], 'the tick must not wait for a 30 second seed');
        $this->assertStringContainsString($row['uuid'], $result['out']);

        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $claimed = $m->get($row['uuid']);
        $this->assertSame('running', $claimed['status']);
        $this->assertNotNull($claimed['host'], 'the claim records which node took it');
    }

    public function testTheTickReapsARunWhoseHeartbeatDied(): void
    {
        $row = $this->queue();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $m->claimOne();
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET heartbeat = now() - interval '" . SeedJob::STALE_RUNNING_INTERVAL . "' - interval '5 minutes'
                             WHERE uuid = :u");
        $m->execute($res, ['u' => $row['uuid']]);

        $this->tick();
        $reaped = $m->get($row['uuid']);
        $this->assertSame('failed', $reaped['status'], 'a run that stopped heartbeating is finished by the tick');
        $this->assertStringContainsString('stale', (string)$reaped['error']);
    }

    public function testTheTickHonoursMaxConcurrent(): void
    {
        $first = $this->queue();
        $second = $this->queue();
        $this->tick();   // maxConcurrent defaults to 1
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $this->assertSame('running', $m->get($first['uuid'])['status']);
        $this->assertSame('pending', $m->get($second['uuid'])['status'], 'the second waits its turn');
    }

    public function testTheTickRemovesLogsPastTheRetentionWindow(): void
    {
        $dir = App::$param['path'] . 'app/tmp/mydb/seed';
        @mkdir($dir, 0777, true);
        $old = "$dir/" . uniqid('old_') . '.log';
        $fresh = "$dir/" . uniqid('fresh_') . '.log';
        file_put_contents($old, 'ancient');
        file_put_contents($fresh, 'recent');
        $keepHours = App::$param['tileseeder']['keepLogHours'] ?? 72;
        touch($old, time() - ($keepHours + 1) * 3600);

        $this->tick();

        $this->assertFileDoesNotExist($old, 'a log past the retention window is removed');
        $this->assertFileExists($fresh, 'a recent log is kept for debugging');
        @unlink($fresh);
    }
}
