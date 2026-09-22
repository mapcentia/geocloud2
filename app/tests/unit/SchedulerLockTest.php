<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\SchedulerLock;
use Codeception\Test\Unit;

/**
 * Advisory-lock based scheduler locking against the real gc2scheduler
 * database. Every SchedulerLock instance is its own Postgres session, so two
 * instances behave like two get.php processes.
 */
class SchedulerLockTest extends Unit
{
    protected UnitTester $tester;

    /** @var SchedulerLock[] */
    private array $sessions = [];
    private int $jobId;

    protected function _before(): void
    {
        // A job id nobody else uses: negative ids never occur in the jobs table.
        $this->jobId = -random_int(1000, 999999);
    }

    protected function _after(): void
    {
        foreach ($this->sessions as $s) {
            $s->release();
        }
        $this->sessions = [];
        $pdo = new PDO("pgsql:dbname=gc2scheduler;host=" . getenv('POSTGRES_HOST') . ";port=" . (getenv('POSTGRES_PORT') ?: 5432), getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'));
        $pdo->exec("DELETE FROM started_jobs WHERE id < 0 OR db = 'schedlocktest'");
    }

    private function session(): SchedulerLock
    {
        try {
            $s = new SchedulerLock(new Connection(database: 'gc2scheduler'));
        } catch (Throwable $e) {
            $this->markTestSkipped('gc2scheduler not reachable: ' . $e->getMessage());
        }
        $this->sessions[] = $s;
        return $s;
    }

    public function testJobLockIsExclusivePerJobAndReleasedWithTheSession(): void
    {
        $a = $this->session();
        $b = $this->session();
        $this->assertTrue($a->tryJobLock($this->jobId));
        $this->assertFalse($b->tryJobLock($this->jobId), 'second session must not get the same job lock');
        $this->assertTrue($b->tryJobLock($this->jobId - 1), 'a different job is independent');

        $a->release();
        array_shift($this->sessions);
        $acquired = false;
        for ($i = 0; $i < 40 && !$acquired; $i++) { // the backend releases session locks asynchronously; allow up to ~2 s
            $acquired = $b->tryJobLock($this->jobId);
            if (!$acquired) {
                usleep(50000);
            }
        }
        $this->assertTrue($acquired, 'closing the holder releases the lock (within 2 s)');
    }

    public function testSlotsAreLimitedAndReusable(): void
    {
        $a = $this->session();
        $b = $this->session();
        $c = $this->session();
        $big = 1000; // far above any real maxJobs, so A and B always get a slot
        $s1 = $a->acquireSlot($big, null, 1, 5);
        $s2 = $b->acquireSlot($big, null, 1, 5);
        $this->assertNotSame($s1, $s2);
        $this->assertFalse($c->trySlot($s1));
        $this->assertFalse($c->trySlot($s2));

        // Every slot up to max(s1, s2) is held (by A, B or unrelated runs), so C must wait;
        // releasing A from the wait callback lets C take exactly A's slot.
        $limit = max($s1, $s2);
        $waits = 0;
        $slot = $c->acquireSlot($limit, function () use (&$waits, $a) {
            $waits++;
            $a->release();
        }, 1, 30);
        array_shift($this->sessions); // A is released; drop it from _after()'s cleanup
        $this->assertSame($s1, $slot);
        $this->assertGreaterThanOrEqual(1, $waits);
    }

    public function testAcquireSlotTimesOut(): void
    {
        $a = $this->session();
        $b = $this->session();
        $s = $a->acquireSlot(1000, null, 1, 5);
        // Hold every slot up to $s so B has nothing to find, even if unrelated
        // runs release a lower slot mid-test.
        for ($i = 1; $i <= $s; $i++) {
            $a->trySlot($i);
        }
        $this->expectException(RuntimeException::class);
        $b->acquireSlot($s, null, 1, 2);
    }

    public function testRegistryLifecycle(): void
    {
        $s = $this->session();
        $this->assertTrue($s->tryJobLock($this->jobId));
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'lock test', 4242, 1, 'unit-host');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);

        $run = $s->runningRun($this->jobId);
        $this->assertSame($uuid, $run['uuid']);
        $this->assertSame('running', $run['status']);
        $this->assertSame('unit-host', $run['host']);
        $this->assertSame(1, (int)$run['slot']);
        $this->assertNull($run['heartbeat']);

        $s->heartbeat($uuid);
        $this->assertNotNull($s->runningRun($this->jobId)['heartbeat']);

        $this->assertContains($uuid, array_column($s->runsFor('schedlocktest'), 'uuid'), 'runsFor lists the running row');
        $this->assertSame($uuid, $s->latestRunForPid(4242, 'unit-host')['uuid']);

        $s->finishRun($uuid, 'failed', 'boom');
        $this->assertNull($s->runningRun($this->jobId));
        $finished = array_values(array_filter($s->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $uuid))[0];
        $this->assertSame('failed', $finished['status']);
        $this->assertSame('boom', $finished['exit_reason']);
        $this->assertNotNull($finished['finished_at']);
    }

    public function testHeartbeatAfterReleaseDoesNotThrow(): void
    {
        $s = $this->session();
        $this->assertTrue($s->tryJobLock($this->jobId));
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'lock test', 4244, 1, 'unit-host');

        $s->release();
        array_shift($this->sessions); // already released; drop it from _after()'s cleanup

        $s->heartbeat($uuid); // session is gone: must return quietly, not throw

        // and it wrote nothing: the row is exactly as startRun() left it.
        $check = $this->session();
        $row = $check->runningRun($this->jobId);
        $this->assertSame($uuid, $row['uuid']);
        $this->assertSame('running', $row['status']);
        $this->assertNull($row['heartbeat'], 'a released session must not have written a heartbeat');
    }

    /**
     * get.php registers the run right after the job lock, long before it knows
     * which slot it will get (the slot wait is unbounded), so the row must be
     * insertable with slot null and updatable once a slot is held.
     */
    public function testRunIsRegisteredWithoutASlotAndTheSlotIsAssignedLater(): void
    {
        $s = $this->session();
        $this->assertTrue($s->tryJobLock($this->jobId));
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'no slot yet', 4245, null, 'unit-host');

        $run = $s->runningRun($this->jobId);
        $this->assertSame($uuid, $run['uuid']);
        $this->assertNull($run['slot'], 'a run waiting for a slot is registered with slot null');
        $this->assertSame($uuid, $s->run($uuid, 'schedlocktest')['uuid'], 'run() finds it by uuid');
        $this->assertNull($s->run($uuid, 'someotherdb'), 'run() is scoped to one database');

        $s->assignSlot($uuid, 7);
        $this->assertSame(7, (int)$s->runningRun($this->jobId)['slot']);

        $s->finishRun($uuid, 'succeeded');
        $this->assertSame(7, (int)$s->run($uuid, 'schedlocktest')['slot'], 'the slot survives the finish');
    }

    /** Pids are reused; a $since bound keeps an old row from ending a new wait. */
    public function testLatestRunForPidRespectsTheSinceBound(): void
    {
        $s = $this->session();
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'old', 4246, 1, 'unit-host');
        $s->finishRun($uuid, 'succeeded');

        $this->assertSame($uuid, $s->latestRunForPid(4246, 'unit-host')['uuid'], 'unbounded lookup finds it');
        $this->assertSame($uuid, $s->latestRunForPid(4246, 'unit-host', date('c', time() - 60))['uuid']);
        $this->assertNull($s->latestRunForPid(4246, 'unit-host', date('c', time() + 60)), 'a row from before $since must not match');
    }

    public function testSkippedRowIsRecordedAndNotRunning(): void
    {
        $s = $this->session();
        $uuid = $s->recordSkipped($this->jobId, 'schedlocktest', 'lock test', 4243, 'unit-host', 'already running');
        $this->assertNull($s->runningRun($this->jobId));
        $row = array_values(array_filter($s->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $uuid))[0];
        $this->assertSame('skipped', $row['status']);
        $this->assertNotNull($row['finished_at']);
    }

    public function testReaperMarksUnlockedRunningRowsLostAndLeavesLockedOnes(): void
    {
        $holder = $this->session();
        $this->assertTrue($holder->tryJobLock($this->jobId));
        $live = $holder->startRun($this->jobId, 'schedlocktest', 'live', 1, 1, 'unit-host');

        $deadJob = $this->jobId - 7;
        $dead = $holder->startRun($deadJob, 'schedlocktest', 'dead', 2, 2, 'unit-host'); // no lock for this id

        $other = $this->session();
        $lost = $other->reap();
        $this->assertGreaterThanOrEqual(1, $lost);
        $this->assertSame('running', $holder->runningRun($this->jobId)['status'], 'a row whose lock is held stays running');
        $this->assertNull($holder->runningRun($deadJob), 'a row without a lock is lost');
        $deadRow = array_values(array_filter($holder->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $dead))[0];
        $this->assertSame('lost', $deadRow['status']);
        $this->assertSame($live, $holder->runningRun($this->jobId)['uuid']);
    }

    public function testLatestRunIgnoresSkippedRows(): void
    {
        $s = $this->session();
        $this->assertNull($s->latestRun($this->jobId));
        $first = $s->startRun($this->jobId, 'schedlocktest', 'a', 1, 1, 'unit-host');
        $s->finishRun($first, 'succeeded');
        $s->recordSkipped($this->jobId, 'schedlocktest', 'a', 2, 'unit-host', 'cooldown');
        $latest = $s->latestRun($this->jobId);
        $this->assertSame($first, $latest['uuid'], 'skipped rows never count as a run');
        $this->assertSame('succeeded', $latest['status']);
    }
    public function testRunLogIsStoredTruncatedAndKeptOutOfListings(): void
    {
        $s = $this->session();
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'log test', 4247, 1, 'unit-host');
        $s->writeLog($uuid, "Info: one\nInfo: two");
        $this->assertSame("Info: one\nInfo: two", $s->run($uuid, 'schedlocktest')['log']);
        foreach ($s->runsFor('schedlocktest') as $row) {
            $this->assertArrayNotHasKey('log', $row, 'listings never carry the log');
        }
        $big = str_repeat('x', SchedulerLock::LOG_MAX_BYTES + 100) . 'END';
        $s->finishRun($uuid, 'succeeded', null, $big);
        $stored = $s->run($uuid, 'schedlocktest')['log'];
        $this->assertStringStartsWith('[log truncated to last ' . SchedulerLock::LOG_MAX_BYTES . ' bytes]', $stored);
        $this->assertStringEndsWith('END', $stored);
        $this->assertSame(SchedulerLock::LOG_MAX_BYTES, strlen(substr($stored, strpos($stored, "\n") + 1)));
        // writeLog still works on a finished row (the shutdown hook's last write)
        $s->writeLog($uuid, 'final');
        $this->assertSame('final', $s->run($uuid, 'schedlocktest')['log']);
        $s->release();
    }

}
