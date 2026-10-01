<?php
use app\conf\App;
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * The tick: reap what died, claim what waits, spawn a run per job, return at once.
 * It must not run a seed inline — a seed lasts hours and the tick holds flock.
 *
 * Once the image is rebuilt with the real cron line, the once-a-minute tick
 * running in this same dev container can claim one of these tests' own pending
 * rows between a test queuing it and the test's own tick() call and spawn the
 * real mapcache_seed against it. These tests use a tileset that doesn't exist,
 * so a stray spawn like that fails fast rather than touching real cache data —
 * but it can still flip an assertion. If a test here flakes with no obvious
 * cause, check for exactly that race before assuming the code is wrong.
 */
class SeedWorkerTest extends Unit
{
    protected UnitTester $tester;
    /** @var array<int, array{database: string, uuid: string}> */
    private array $created = [];
    private string $stub;

    /** Written by the stub into the log the spawned child inherits (fd 1/2). The
     *  real mapcache_seed binary never prints this, so its presence is proof the
     *  process behind a row really is this test's stub — dropping --binary
     *  forwarding, or spawning the wrong binary, leaves it absent. */
    private const string STUB_MARKER = 'SEED_WORKER_TEST_STUB_MARKER';

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_worker_stub_' . uniqid() . '.sh';
        file_put_contents($this->stub, "#!/bin/sh\necho " . self::STUB_MARKER . "\nsleep 30\n");
        chmod($this->stub, 0755);
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        foreach ($this->created as $ref) {
            $this->killTree($ref['database'], $ref['uuid']);
            $m = new SeedJob(connection: new Connection(database: $ref['database']));
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $ref['uuid']]);
        }
    }

    /**
     * Kills whatever the tick spawned for one row, by two independent handles —
     * neither is reliable alone. The row's own `pid` column is written by
     * seed_run.php only once it has fully bootstrapped (connected, fetched the
     * row, proc_open'd the seed binary), and most tests here don't wait for that
     * before returning (only testTheTickClaimsSpawnsAndReturnsImmediately does);
     * relying on it alone left a real spawned chain running past its row's
     * deletion in this very round, self-terminating only because the test stub
     * happens to sleep just 30s — a real seed would have run for hours. So this
     * also matches on the uuid in the wrapper/runner's own command line, which
     * exists the instant they're exec'd, no bootstrap required. And killing one
     * matched pid is not enough either: `timeout`, its `php seed_run.php` child
     * and the seed binary proc_open spawns under it all share the process group
     * setsid created, so a single `kill -9 <pid>` only removes one link and
     * orphans the rest to init — kill the whole group instead.
     *
     * The group kill must go through posix_kill(), not exec('kill -9 -- -$pgid').
     * exec() runs through /bin/sh, which is dash here, and dash's builtin kill
     * rejects the `--` end-of-options marker ("Illegal number: -") and signals
     * nothing — silently, since the call is wrapped in 2>/dev/null. That left
     * four orphaned `sleep 30` and two unreaped stubs straight after a green
     * suite; it self-cleared only because the stub sleeps 30s, the exact masking
     * round 1 found one layer up. posix_kill() sends the signal directly, no
     * shell involved. Guarded against the one way this could go wrong instead of
     * right: if a spawn ever loses setsid, a matched pid's pgid is this test
     * process's own group, and killing it would take codeception down with it —
     * skip any pgid equal to posix_getpgrp().
     */
    private function killTree(string $database, string $uuid): void
    {
        // pgrep -f matches against the full command line of every process on the
        // node, including the one this exec() itself just spawned to run pgrep —
        // that wrapping shell's own argv literally contains the uuid text (same
        // self-match seed_worker.php's own pgrep line hit in round 1). Bracket
        // one character of the pattern, same fix: it still matches the real
        // process but never appears verbatim in the invoking command.
        $pattern = '[' . $uuid[0] . ']' . substr($uuid, 1);
        $pids = [];
        exec('pgrep -f -- ' . escapeshellarg($pattern), $pids);
        $m = new SeedJob(connection: new Connection(database: $database));
        $row = $m->get($uuid);
        if (!empty($row['pid'])) {
            $pids[] = (string)$row['pid'];
        }
        $targets = array_values(array_filter(array_unique(array_map('intval', $pids)), fn($p) => $p > 0));
        foreach ($targets as $pid) {
            $pgidOut = [];
            exec('ps -o pgid= -p ' . $pid . ' 2>/dev/null', $pgidOut);
            $pgid = (int)trim($pgidOut[0] ?? '0');
            if ($pgid > 0 && $pgid !== posix_getpgrp()) {
                posix_kill(-$pgid, SIGKILL);
            }
            exec('kill -9 ' . $pid . ' 2>/dev/null');
        }

        // SIGKILL to the whole group lands on the stub and its parent chain at
        // essentially the same instant, so the stub briefly becomes a zombie
        // under a parent that is itself dying — the kernel reparents it to this
        // container's init, which reaps it asynchronously, not instantly. This
        // test is not that parent (the shell exec() spawned to launch the chain
        // already exited once it backgrounded it), so it cannot wait() on it
        // directly; it can only wait for init to finish. posix_kill($pid, 0)
        // still succeeds against a zombie (the pid is still in the process
        // table, just defunct) and only starts failing once init has reaped it,
        // so polling that is what actually confirms nothing is left — not just
        // that a kill signal was sent.
        $deadline = microtime(true) + 2.0;
        foreach ($targets as $pid) {
            while (posix_kill($pid, 0) && microtime(true) < $deadline) {
                usleep(50000);
            }
        }
    }

    private function queue(string $database = 'mydb'): array
    {
        $m = new SeedJob(connection: new Connection(database: $database));
        $row = $m->queue(['name' => 'tick ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = ['database' => $database, 'uuid' => $row['uuid']];
        return $row;
    }

    /** @param string[] $databases */
    private function tick(array $databases = ['mydb']): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_worker.php')
            . ' --database=' . escapeshellarg(implode(',', $databases))
            . ' --binary=' . escapeshellarg($this->stub) . ' 2>&1';
        $start = microtime(true);
        exec($cmd, $out);
        return ['seconds' => microtime(true) - $start, 'out' => implode("\n", $out)];
    }

    /**
     * Polls up to $timeoutSeconds for $check() to go true. Used to prove the
     * spawned seed_run.php actually reached its own setPid() call — only the
     * child process can write that, so waiting for it (instead of trusting the
     * tick's stdout or a timing budget) is what makes a broken $runner path, a
     * missing nohup/setsid, a dropped `&` or an unpassed --binary fail the test.
     */
    private function waitUntil(callable $check, float $timeoutSeconds = 5.0): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if ($check()) {
                return true;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);
        return $check();
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

        // The status flip alone only proves claimOne() ran; it says nothing about
        // whether a process was actually spawned, or which binary it ran. pid/
        // log_path are written only by seed_run.php itself once it has
        // bootstrapped and opened its log, and the marker in that log is written
        // only by this test's own stub — proving both that a real child exists
        // and that it is the stub, not (if --binary forwarding ever silently
        // broke) the real mapcache_seed shelling out against the real database.
        $after = null;
        $spawned = $this->waitUntil(function () use ($m, $row, &$after) {
            $r = $m->get($row['uuid']);
            if (empty($r['pid']) || empty($r['log_path'])) {
                return false;
            }
            $contents = @file_get_contents($r['log_path']);
            if ($contents === false || !str_contains($contents, self::STUB_MARKER)) {
                return false;
            }
            $after = $r;
            return true;
        });
        $this->assertTrue($spawned,
            'the spawned child never wrote the stub marker into its log within 5s — ' .
            'either nothing was spawned, or something other than this test\'s stub was run');
        $this->assertFileExists($after['log_path'], 'the spawned run writes its log where it says it did');
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

    /**
     * Spec §6: concurrency is per node, not per database. One pending row each in
     * two real databases (mydb and aau — both reachable from this container, both
     * carrying settings.seed_jobs) and a single tick with maxConcurrent's default
     * of 1 must claim exactly one of the two, never both. Round 1 assigned $live
     * inside the per-database loop, so it reset for every database and let a
     * single tick spawn one run per database instead of one run for the whole
     * node; this is the test that catches that regression (the older
     * testTheTickHonoursMaxConcurrent only ever exercises one database, so a
     * count that resets per database still looks correct to it).
     */
    public function testTheTickHonoursMaxConcurrentAcrossDatabases(): void
    {
        $first = $this->queue('mydb');
        $second = $this->queue('aau');
        $this->tick(['mydb', 'aau']);   // maxConcurrent defaults to 1, node-wide

        $mMydb = new SeedJob(connection: new Connection(database: 'mydb'));
        $mAau = new SeedJob(connection: new Connection(database: 'aau'));
        $firstStatus = $mMydb->get($first['uuid'])['status'];
        $secondStatus = $mAau->get($second['uuid'])['status'];

        $running = (int)($firstStatus === 'running') + (int)($secondStatus === 'running');
        $this->assertSame(1, $running,
            "expected exactly one of the two databases to claim (got mydb=$firstStatus, aau=$secondStatus)");
        $this->assertContains('pending', [$firstStatus, $secondStatus], 'the other database\'s row must still be waiting');
    }

    /**
     * Extends the node-wide cap to the specific bug round 2 shipped: a row
     * already 'running' on this host — simulating exactly what an earlier
     * tick's claim leaves behind — sits in one database while a 'pending' row
     * waits in the other. Round 2's $live was a running prefix sum: a database
     * visited *before* the running row's database could never see it, so
     * --database=mydb,aau (pending row first, running row second) claimed the
     * pending row anyway, leaving two seeds live against a cap of one;
     * reversing the order happened to refuse correctly. testTheTickHonours-
     * MaxConcurrentAcrossDatabases above cannot catch this — both its rows
     * start 'pending', so there is nothing for a database-order bug to hide
     * behind. This one asserts both orders, since the bug was order-dependent.
     */
    public function testTheTickHonoursMaxConcurrentAcrossDatabasesRegardlessOfOrder(): void
    {
        $blocking = $this->queue('aau');
        $m = new SeedJob(connection: new Connection(database: 'aau'));
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET status = 'running', host = :host, started = now(), heartbeat = now()
                             WHERE uuid = :u");
        $m->execute($res, ['host' => SeedJob::currentHost(), 'u' => $blocking['uuid']]);
        $mMydb = new SeedJob(connection: new Connection(database: 'mydb'));

        // Pending database visited first, blocked database visited second: the
        // exact order the reviewer reproduced the bug with.
        $waitingFirst = $this->queue('mydb');
        $this->tick(['mydb', 'aau']);
        $this->assertSame('pending', $mMydb->get($waitingFirst['uuid'])['status'],
            'pending-database-first order: a running row in the database visited later must still block it');
        $this->assertSame(0, $mMydb->countRunningOnHost(SeedJob::currentHost()),
            'pending-database-first order: nothing in mydb may have been claimed');

        // Blocked database visited first, pending database visited second: the
        // order that happened to refuse correctly even under round 2's bug.
        $waitingSecond = $this->queue('mydb');
        $this->tick(['aau', 'mydb']);
        // Count rather than check $waitingSecond alone: mydb now holds two pending
        // rows, and claimOne() takes the oldest, so a cap that let exactly one
        // extra claim through would take $waitingFirst and leave this one pending.
        $this->assertSame(0, $mMydb->countRunningOnHost(SeedJob::currentHost()),
            'pending-database-second order: nothing in mydb may have been claimed');
        $this->assertSame('pending', $mMydb->get($waitingSecond['uuid'])['status'],
            'pending-database-second order: a running row in the database visited earlier must still block it');
    }

    /**
     * Capacity comes from settings.seed_jobs, not from counting live processes:
     * a row already 'running' on this host — no process behind it needed for this
     * test — must occupy the one slot maxConcurrent (default 1) allows, exactly as
     * a real run claimed by an earlier tick would. Without the database-backed
     * count (e.g. back on a process count that double-counts a run's timeout
     * wrapper and its php child, or one that silently reads 0) this test claims
     * the waiting row anyway.
     */
    public function testTheTickHonoursCapacityFromTheDatabase(): void
    {
        $blocking = $this->queue();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET status = 'running', host = :host, started = now(), heartbeat = now()
                             WHERE uuid = :u");
        $m->execute($res, ['host' => SeedJob::currentHost(), 'u' => $blocking['uuid']]);

        $waiting = $this->queue();
        $this->tick();
        $this->assertSame('pending', $m->get($waiting['uuid'])['status'],
            'a running row already on this host fills the only slot');

        $res = $m->prepare("UPDATE settings.seed_jobs SET status = 'succeeded', finished = now() WHERE uuid = :u");
        $m->execute($res, ['u' => $blocking['uuid']]);
        $this->tick();
        $this->assertSame('running', $m->get($waiting['uuid'])['status'],
            'the slot frees once the blocking row is no longer running');
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
