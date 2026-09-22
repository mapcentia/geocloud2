<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\RunLog;
use app\inc\SchedulerLock;
use Codeception\Test\Unit;

/** The scheduler run log kept for started_jobs.log (fed by get.php's output buffer). */
class RunLogTest extends Unit
{
    protected UnitTester $tester;

    public function testAppendsChunksVerbatim(): void
    {
        $log = new RunLog();
        $log->append("\nInfo: Run x registered");
        $log->append('');
        $log->append("\nInfo: Fetching remote file...");
        $this->assertSame("\nInfo: Run x registered\nInfo: Fetching remote file...", $log->contents());
        $this->assertFalse($log->isTruncated());
        $this->assertSame(strlen($log->contents()), $log->bytes());
    }

    public function testKeepsOnlyTheTailAndSaysSo(): void
    {
        $log = new RunLog(maxBytes: 10);
        $log->append('0123456789');
        $this->assertFalse($log->isTruncated());
        $log->append('abcdef');
        $this->assertTrue($log->isTruncated());
        $this->assertSame(10, $log->bytes());
        $this->assertSame("[log truncated to last 10 bytes]\n6789abcdef", $log->contents());
    }

    public function testDefaultCapMatchesTheRegistry(): void
    {
        $this->assertSame(1048576, SchedulerLock::LOG_MAX_BYTES);
        $log = new RunLog();
        $log->append(str_repeat('x', SchedulerLock::LOG_MAX_BYTES + 1));
        $this->assertSame(SchedulerLock::LOG_MAX_BYTES, $log->bytes());
    }
}
