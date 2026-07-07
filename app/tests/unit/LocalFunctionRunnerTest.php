<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\runners\LocalFunctionRunner;
use Codeception\Test\Unit;

/**
 * Exercises the Phase 0 LocalFunctionRunner end to end. Requires `node` and
 * `python3` on PATH (present in the gc2core container).
 */
class LocalFunctionRunnerTest extends Unit
{
    protected UnitTester $tester;

    private function func(array $overrides): array
    {
        return array_merge([
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => '',
            'env' => null,
            'memory_mb' => 128,
            'timeout_s' => 10,
        ], $overrides);
    }

    public function testNodeHandlerReturnsResult(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $function = $this->func([
            'runtime' => 'nodejs20',
            'code' => 'export const handler = async (event) => ({ sum: event.a + event.b });',
        ]);
        $result = $runner->invoke($function, ['a' => 2, 'b' => 3], []);

        $this->assertEquals('succeeded', $result->status, $result->error ?? '');
        $this->assertEquals(['sum' => 5], $result->output);
        $this->assertNotNull($result->durationMs);
    }

    public function testPythonHandlerReturnsResult(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $function = $this->func([
            'runtime' => 'python312',
            'code' => "def handler(event, context):\n    return {'sum': event['a'] + event['b']}\n",
        ]);
        $result = $runner->invoke($function, ['a' => 4, 'b' => 5], []);

        $this->assertEquals('succeeded', $result->status, $result->error ?? '');
        $this->assertEquals(['sum' => 9], $result->output);
    }

    public function testEnvVarsArePassedToHandler(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $function = $this->func([
            'code' => 'export const handler = async () => ({ level: process.env.LOG_LEVEL });',
            'env' => ['LOG_LEVEL' => 'debug'],
        ]);
        $result = $runner->invoke($function, [], []);

        $this->assertEquals('succeeded', $result->status, $result->error ?? '');
        $this->assertEquals(['level' => 'debug'], $result->output);
    }

    public function testHandlerErrorIsCaptured(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $function = $this->func([
            'code' => 'export const handler = async () => { throw new Error("boom"); };',
        ]);
        $result = $runner->invoke($function, [], []);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('boom', (string)$result->error);
    }

    public function testTimeoutIsEnforced(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $function = $this->func([
            'timeout_s' => 1,
            'code' => 'export const handler = async () => { await new Promise(r => setTimeout(r, 5000)); return {done: true}; };',
        ]);
        $result = $runner->invoke($function, [], []);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Timed out', (string)$result->error);
    }

    public function testUnsupportedRuntimeFails(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => []]);
        $result = $runner->invoke($this->func(['runtime' => 'ruby']), [], []);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Unsupported runtime', (string)$result->error);
    }

    public function testNetworkIsBlockedUnderUnshareSandbox(): void
    {
        $runner = new LocalFunctionRunner(['sandbox' => ['unshare', '-rn', '--']]);
        $function = $this->func([
            'code' => 'import dns from "node:dns";'
                . 'export const handler = async () => new Promise((resolve) => {'
                . '  dns.lookup("example.com", (err, addr) => resolve({ blocked: !!err, addr: addr || null }));'
                . '});',
        ]);
        $result = $runner->invoke($function, [], []);

        $this->assertEquals('succeeded', $result->status, $result->error ?? '');
        $this->assertTrue($result->output['blocked'] ?? false, 'Expected network egress to be blocked by unshare -rn');
    }
}
