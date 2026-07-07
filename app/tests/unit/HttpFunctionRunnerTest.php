<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\runners\HttpFunctionRunner;
use Codeception\Test\Unit;

/**
 * Integration test for the PHP -> Go runner round trip. Requires a running
 * gc2-function-runner reachable at GC2_FUNCTION_RUNNER_URL (default
 * http://localhost:8090). Skips cleanly when the service isn't up.
 */
class HttpFunctionRunnerTest extends Unit
{
    protected UnitTester $tester;

    private function url(): string
    {
        return getenv('GC2_FUNCTION_RUNNER_URL') ?: 'http://localhost:8090';
    }

    private function token(): ?string
    {
        return getenv('GC2_FUNCTION_RUNNER_TOKEN') ?: 'secret123';
    }

    private function skipIfDown(): void
    {
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        if (@file_get_contents($this->url() . '/healthz', false, $ctx) === false) {
            $this->markTestSkipped('gc2-function-runner not reachable at ' . $this->url());
        }
    }

    private function runner(): HttpFunctionRunner
    {
        return new HttpFunctionRunner(['runnerUrl' => $this->url(), 'runnerToken' => $this->token()]);
    }

    public function testInvokesPythonViaService(): void
    {
        $this->skipIfDown();
        $result = $this->runner()->invoke(
            ['runtime' => 'python312', 'handler' => 'index.handler',
                'code' => "def handler(event, context):\n    return {'sum': event['a'] + event['b']}\n",
                'timeout_s' => 15],
            ['a' => 40, 'b' => 2],
            ['function' => 'demo'],
        );
        $this->assertEquals('succeeded', $result->status, (string)$result->error);
        $this->assertEquals(['sum' => 42], $result->output);
        $this->assertNotNull($result->durationMs);
    }

    public function testHandlerErrorIsReportedViaService(): void
    {
        $this->skipIfDown();
        $result = $this->runner()->invoke(
            ['runtime' => 'nodejs20', 'handler' => 'index.handler',
                'code' => 'export const handler = async () => { throw new Error("kaboom"); };',
                'timeout_s' => 15],
            [],
            [],
        );
        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('kaboom', (string)$result->error);
    }

    public function testUnreachableRunnerFailsGracefully(): void
    {
        $runner = new HttpFunctionRunner(['runnerUrl' => 'http://127.0.0.1:1']);
        $result = $runner->invoke(['runtime' => 'python312', 'handler' => 'index.handler', 'code' => 'x'], [], []);
        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('unreachable', (string)$result->error);
    }
}
