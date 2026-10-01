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
    private array $extraFiles = [];

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_stub_' . uniqid() . '.sh';
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        foreach ($this->extraFiles as $file) {
            @unlink($file);
        }
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

    /**
     * Queue a row and claim it, the state seed_run.php expects to find. Claims by
     * uuid rather than SeedJob::claimOne() (oldest pending): claimOne() would
     * happily hand back a stranger's row left pending by another suite or a real
     * worker, and this test must run against the one it just queued.
     */
    private function claimed(): array
    {
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $row = $m->queue(['name' => 'run ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = $row['uuid'];
        $res = $m->prepare("UPDATE settings.seed_jobs
                                SET status = 'running', started = now(), heartbeat = now(), host = 'test'
                              WHERE uuid = :uuid AND status = 'pending'
                            RETURNING *");
        $m->execute($res, ['uuid' => $row['uuid']]);
        $claimed = $m->fetchRow($res);
        $this->assertNotNull($claimed, 'the row this test just queued was claimable');
        return $claimed;
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

    /**
     * The test above only ever exercises the pre-spawn cancel check (Minor 2):
     * requestCancel() lands before seed_run.php is even started, so no child is
     * ever spawned and stopChild() — the round-1 Critical fix, at three call
     * sites — runs nowhere in the suite. This spawns seed_run.php itself with
     * proc_open() so the test can act *after* a real mapcache_seed stand-in is
     * running, cancel mid-run, and prove the SIGTERM/grace/SIGKILL sequence
     * actually stops a live child rather than only a hoped-for one.
     */
    public function testACancelRequestMidRunStopsARealChildAndEndsCancelled(): void
    {
        $this->writeStub('trap "exit 0" TERM; while true; do echo tick; sleep 1; done');
        $row = $this->claimed();

        $outPath = $this->stub . '.out';
        $this->extraFiles[] = $outPath;
        $out = fopen($outPath, 'w');
        $cmd = [PHP_BINARY, App::$param['path'] . 'app/scripts/seed_run.php',
            '--database=mydb', '--uuid=' . $row['uuid'], '--binary=' . $this->stub];
        $process = proc_open($cmd, [1 => $out, 2 => $out], $pipes);
        $this->assertIsResource($process, 'seed_run.php could be spawned');

        // Give seed_run.php time to pass the pre-spawn check and actually start
        // the stub, so the cancel below lands while a real child is running.
        sleep(1);
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $m->requestCancel($row['uuid']);

        $exit = proc_close($process);
        fclose($out);

        $this->assertSame(2, $exit);
        $done = $m->get($row['uuid']);
        $this->assertSame('cancelled', $done['status']);
        $this->assertNotNull($done['finished']);
        $this->assertNotNull($done['pid'], 'a real child was spawned before it was cancelled');
        $this->assertFalse(is_dir('/proc/' . $done['pid']), 'the child was reaped, not left running');
    }

    /**
     * Important 1 of the whole-branch review. heartbeat() and finish() both carry
     * `AND status = 'running'`, and requestCancel() answers 'noop' for anything
     * else, so a row taken out of 'running' while its run is alive — reapStale()
     * after a long PDO stall, or the tick's own 200 ms posix_kill($pid, 0) spawn
     * probe reading a false negative — used to leave a seed that could not be
     * observed, cancelled or counted, writing tiles until the 12-hour timeout,
     * while countRunningOnHost() dropped to 0 and the next tick claimed another
     * seed on top of it.
     *
     * The run must notice instead: a heartbeat that updates no rows means someone
     * else owns the row now, so stop the child and exit without writing a status
     * over theirs. Remove the `=== 0` branch in seed_run.php's loop and this test
     * fails on its "exited" assertion — the stub runs for a minute, far longer
     * than the 25 s this waits — and, if it somehow did exit, on the error text,
     * which a finalise() call would have replaced.
     */
    public function testARunWhoseRowLeavesRunningStopsItsChildAndKeepsOutOfTheStatus(): void
    {
        $this->writeStub('trap "exit 0" TERM; i=0; while [ $i -lt 60 ]; do echo tick; sleep 1; i=$((i+1)); done');
        $row = $this->claimed();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));

        $outPath = $this->stub . '.out';
        $this->extraFiles[] = $outPath;
        $out = fopen($outPath, 'w');
        $process = proc_open([PHP_BINARY, App::$param['path'] . 'app/scripts/seed_run.php',
            '--database=mydb', '--uuid=' . $row['uuid'], '--binary=' . $this->stub], [1 => $out, 2 => $out], $pipes);
        $this->assertIsResource($process, 'seed_run.php could be spawned');

        try {
            // setPid() is written only once the stub is actually running, so this
            // is what proves there is a live child to orphan in the first place.
            $pid = null;
            $deadline = microtime(true) + 10;
            while ($pid === null && microtime(true) < $deadline) {
                $pid = $m->get($row['uuid'])['pid'] ?? null;
                if ($pid === null) {
                    usleep(100000);
                }
            }
            $this->assertNotNull($pid, 'the run started its child and recorded the pid');
            $this->assertDirectoryExists('/proc/' . $pid, 'the child really is running');

            // Exactly what reapStale() does to a row whose heartbeat went quiet.
            $reaper = $m->prepare("UPDATE settings.seed_jobs
                                      SET status = 'failed', finished = now(), error = 'stale: no heartbeat from somewhere'
                                    WHERE uuid = :u");
            $m->execute($reaper, ['u' => $row['uuid']]);

            // The run heartbeats every 5 s; 25 s is five chances to notice.
            $exit = null;
            $deadline = microtime(true) + 25;
            while (microtime(true) < $deadline) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                usleep(200000);
            }
            $this->assertNotNull($exit, 'the run noticed it no longer owns its row and exited');
            $this->assertSame(1, $exit, 'it exits abnormally: it did not finish the job');

            $after = $m->get($row['uuid']);
            $this->assertSame('failed', $after['status'], 'the reaper\'s status stands');
            $this->assertStringContainsString('stale: no heartbeat', (string)$after['error'],
                'the run must not overwrite the outcome the other writer decided');

            // clearstatcache() per poll: the assertDirectoryExists() above put
            // "/proc/$pid exists" in PHP's stat cache, which is never invalidated
            // for a path this process only reads — without this the loop spins on
            // a cached answer and the assertion fails on a child that is long gone.
            $gone = microtime(true) + 5;
            do {
                clearstatcache(true, '/proc/' . $pid);
                if (!is_dir('/proc/' . $pid)) {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $gone);
            $this->assertDirectoryDoesNotExist('/proc/' . $pid,
                'the seed child is stopped, not left writing tiles with nothing able to cancel it');
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, SIGKILL);
                }
                proc_close($process);
            }
            fclose($out);
        }
    }

    /**
     * Tests 1 and 2 finish in well under 5s, so the loop body — heartbeat() and
     * the in-loop tail() — never runs at all; a stub outliving one poll is the
     * only way to exercise the mechanism the 10-minute stale window depends on.
     * The stub also writes far more than logTailBytes, to prove the stored log
     * stays bounded rather than growing unboundedly on a stale cached size.
     */
    public function testHeartbeatAdvancesAndTheLogStaysBoundedAcrossAPoll(): void
    {
        $tailBytes = App::$param['tileseeder']['logTailBytes'] ?? 8192;
        $this->writeStub('i=0; while [ $i -lt 2000 ]; do '
            . 'echo "0123456789012345678901234567890123456789"; i=$((i+1)); done; sleep 6; exit 0');
        $row = $this->claimed();
        $before = $row['heartbeat'];
        $this->assertSame(0, $this->runSeedScript($row['uuid']));

        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $done = $m->get($row['uuid']);
        $this->assertSame('succeeded', $done['status']);
        $this->assertGreaterThan(strtotime((string)$before), strtotime((string)$done['heartbeat']),
            'the loop beat at least once while the child was still running');
        $this->assertLessThanOrEqual($tailBytes, strlen((string)$done['log']),
            'the stub wrote far more than logTailBytes; the stored log must still be bounded');
    }
}
