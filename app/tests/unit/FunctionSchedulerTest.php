<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\exceptions\GC2Exception;
use app\inc\FunctionScheduler;
use Codeception\Test\Unit;

class FunctionSchedulerTest extends Unit
{
    protected UnitTester $tester;

    public function testEveryMinuteIsAlwaysDue(): void
    {
        $this->assertTrue(FunctionScheduler::isDue('* * * * *', new DateTime('2026-06-30 12:34:00')));
    }

    public function testSpecificMinuteMatchesOnlyThatMinute(): void
    {
        $this->assertTrue(FunctionScheduler::isDue('34 12 * * *', new DateTime('2026-06-30 12:34:00')));
        $this->assertFalse(FunctionScheduler::isDue('35 12 * * *', new DateTime('2026-06-30 12:34:00')));
    }

    public function testInvalidCronIsNeverDue(): void
    {
        $this->assertFalse(FunctionScheduler::isDue('not a cron', new DateTime()));
    }

    public function testAssertValidScheduleAcceptsValidCron(): void
    {
        FunctionScheduler::assertValidSchedule(['schedule' => '*/5 * * * *']);
        FunctionScheduler::assertValidSchedule(null);
        FunctionScheduler::assertValidSchedule(['event' => 'whatever']); // no schedule key
        $this->assertTrue(true); // reached here without throwing
    }

    public function testAssertValidScheduleRejectsBadCron(): void
    {
        $this->expectException(GC2Exception::class);
        FunctionScheduler::assertValidSchedule(['schedule' => 'every wednesday']);
    }

    public function testAssertValidScheduleRejectsNonStringSchedule(): void
    {
        $this->expectException(GC2Exception::class);
        FunctionScheduler::assertValidSchedule(['schedule' => 12345]);
    }
}
