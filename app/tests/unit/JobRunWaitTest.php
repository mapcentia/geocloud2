<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\SchedulerLock;
use app\models\Job;
use Codeception\Test\Unit;

/**
 * Job::waitForRun() in isolation, without spawning a full get.php run.
 *
 * The bug this covers: childPidOf() used to be sampled only after the first
 * sleep(1) inside runJob()'s wait loop. A run fast enough to finish (and
 * take its `timeout` wrapper with it) inside that first second was never
 * found by pid, and the loop fell through to a ~30s grace-period fallback
 * instead of noticing immediately that the wrapper (and therefore the run)
 * was already gone. runJob() now samples the child pid promptly (polling
 * every 100ms for up to 2s right after spawning) and delegates the actual
 * wait to waitForRun(), which this test exercises directly against a real
 * gc2scheduler session -- no live process tree required.
 */
class JobRunWaitTest extends Unit
{
    protected UnitTester $tester;

    private const string DB = 'waittest';

    private ?SchedulerLock $lock = null;

    protected function _after(): void
    {
        $this->lock?->release();
        $pdo = new PDO(
            "pgsql:dbname=gc2scheduler;host=" . getenv('POSTGRES_HOST') . ";port=" . (getenv('POSTGRES_PORT') ?: 5432),
            getenv('POSTGRES_USER'),
            getenv('POSTGRES_PASSWORD')
        );
        $pdo->exec("DELETE FROM started_jobs WHERE db = '" . self::DB . "'");
    }

    /** A pid guaranteed to be dead by the time the caller uses it. */
    private function deadPid(): int
    {
        $pid = (int)exec('sh -c "exit 0" > /dev/null 2>&1 & echo $!');
        usleep(200000);
        return $pid;
    }

    private function job(): Job
    {
        try {
            return new Job(new Connection(database: 'gc2scheduler'));
        } catch (Throwable $e) {
            $this->markTestSkipped('gc2scheduler not reachable: ' . $e->getMessage());
        }
    }

    private function lock(): SchedulerLock
    {
        try {
            $this->lock = new SchedulerLock(new Connection(database: 'gc2scheduler'));
            return $this->lock;
        } catch (Throwable $e) {
            $this->markTestSkipped('gc2scheduler not reachable: ' . $e->getMessage());
        }
    }

    public function testReturnsNullPromptlyWhenTheWrapperIsDeadAndNoRunWasEverRegistered(): void
    {
        $job = $this->job();
        $lock = $this->lock();
        $wrapperPid = $this->deadPid();

        $start = microtime(true);
        $run = $job->waitForRun($wrapperPid, null, gethostname() ?: 'unknown', $lock);
        $elapsed = microtime(true) - $start;

        $this->assertNull($run, 'nothing was ever registered under this pid');
        $this->assertLessThan(3.0, $elapsed, 'must not fall back to a multi-second grace period');
    }

    public function testReturnsTheFinishedRowWhenTheChildRegisteredAndFinished(): void
    {
        $job = $this->job();
        $lock = $this->lock();
        $host = gethostname() ?: 'unknown';
        $wrapperPid = $this->deadPid();
        $childPid = 424242;

        $uuid = $lock->startRun(-777, self::DB, 'w', $childPid, 1, $host);
        $lock->finishRun($uuid, 'succeeded');

        $start = microtime(true);
        $run = $job->waitForRun($wrapperPid, $childPid, $host, $lock);
        $elapsed = microtime(true) - $start;

        $this->assertNotNull($run);
        $this->assertSame('succeeded', $run['status']);
        $this->assertSame($uuid, $run['uuid']);
        $this->assertLessThan(3.0, $elapsed);
    }
}
