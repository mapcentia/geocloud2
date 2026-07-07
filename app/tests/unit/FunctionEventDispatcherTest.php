<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\exceptions\GC2Exception;
use app\inc\FunctionEventDispatcher;
use Codeception\Test\Unit;

class FunctionEventDispatcherTest extends Unit
{
    protected UnitTester $tester;

    public function testMatchesTableAndOp(): void
    {
        $triggers = ['event' => ['table' => 'public.foo', 'on' => ['insert', 'update']]];
        $this->assertTrue(FunctionEventDispatcher::matches('I', 'public.foo', $triggers));
        $this->assertTrue(FunctionEventDispatcher::matches('U', 'public.foo', $triggers));
        $this->assertFalse(FunctionEventDispatcher::matches('D', 'public.foo', $triggers), 'delete not subscribed');
    }

    public function testTableMismatchDoesNotMatch(): void
    {
        $triggers = ['event' => ['table' => 'public.bar']];
        $this->assertFalse(FunctionEventDispatcher::matches('I', 'public.foo', $triggers));
    }

    public function testDefaultOnMatchesAllOps(): void
    {
        $triggers = ['event' => ['table' => 'public.foo']]; // no "on" => all ops
        $this->assertTrue(FunctionEventDispatcher::matches('I', 'public.foo', $triggers));
        $this->assertTrue(FunctionEventDispatcher::matches('U', 'public.foo', $triggers));
        $this->assertTrue(FunctionEventDispatcher::matches('D', 'public.foo', $triggers));
    }

    public function testNonEventTriggerNeverMatches(): void
    {
        $this->assertFalse(FunctionEventDispatcher::matches('I', 'public.foo', ['schedule' => '* * * * *']));
    }

    public function testAssertValidEventAcceptsValid(): void
    {
        FunctionEventDispatcher::assertValidEvent(['event' => ['table' => 'public.foo', 'on' => ['insert', 'delete']]]);
        FunctionEventDispatcher::assertValidEvent(null);
        FunctionEventDispatcher::assertValidEvent(['schedule' => '* * * * *']); // no event key
        $this->assertTrue(true);
    }

    public function testAssertValidEventRejectsUnqualifiedTable(): void
    {
        $this->expectException(GC2Exception::class);
        FunctionEventDispatcher::assertValidEvent(['event' => ['table' => 'foo']]);
    }

    public function testAssertValidEventRejectsBadOp(): void
    {
        $this->expectException(GC2Exception::class);
        FunctionEventDispatcher::assertValidEvent(['event' => ['table' => 'public.foo', 'on' => ['truncate']]]);
    }
}
