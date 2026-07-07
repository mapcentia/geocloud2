<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\FunctionToken;
use app\inc\runners\LocalFunctionRunner;
use Codeception\Test\Unit;

/**
 * The Phase 1 killer feature, end to end: a handler uses its minted scoped
 * token to call back into the GC2 data plane (/api/v4/sql) and read data.
 *
 * Integration test - needs a live GC2 instance reachable on the internal URL
 * and `python3` on PATH (both true inside the gc2core container). Skips
 * cleanly when the API cannot be reached so it never breaks unrelated runs.
 */
class FunctionCallbackIntegrationTest extends Unit
{
    protected UnitTester $tester;

    private const string API = 'http://localhost';
    private const string PASSWORD = 'A1abcabcabc';

    public function testHandlerCanQueryDatabaseWithScopedToken(): void
    {
        $ts = (string)(new DateTime())->getTimestamp() . bin2hex(random_bytes(3));

        // 1. Provision a super user and grab its API key (the JWT signing secret).
        $created = $this->post('/api/v2/user', [
            'name' => 'Callback test user ' . $ts,
            'email' => 'cb' . $ts . '@example.com',
            'password' => self::PASSWORD,
        ]);
        if ($created === null || empty($created['data']['screenname'])) {
            $this->markTestSkipped('GC2 API not reachable; skipping callback integration test.');
        }
        $uid = $created['data']['screenname'];

        $session = $this->post('/api/v2/session/start', [
            'user' => $uid, 'password' => self::PASSWORD, 'schema' => 'public',
        ]);
        $secret = $session['data']['api_key'] ?? null;
        $this->assertNotEmpty($secret, 'Expected an api_key from session start');

        // 2. Mint a scoped token exactly as the controller does.
        $token = FunctionToken::mintWithSecret($secret, [
            'uid' => $uid,
            'database' => $uid,
            'superUser' => true,
            'userGroup' => null,
        ], 'callback_fn', 60);

        // 3. A handler that calls back into /api/v4/sql with that token.
        $code = <<<'PY'
import json, urllib.request, urllib.error
def handler(event, context):
    req = urllib.request.Request(
        context['apiBaseUrl'] + '/api/v4/sql',
        data=json.dumps({'q': 'SELECT 1 as n'}).encode(),
        method='POST',
        headers={
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + context['token'],
        })
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return {'status': r.status, 'body': json.loads(r.read().decode())}
    except urllib.error.HTTPError as e:
        return {'status': e.code, 'body': e.read().decode()}
PY;

        $runner = new LocalFunctionRunner(['sandbox' => []]); // network needed for the callback
        $function = [
            'runtime' => 'python312',
            'handler' => 'index.handler',
            'code' => $code,
            'env' => null,
            'memory_mb' => 128,
            'timeout_s' => 20,
        ];
        $context = ['apiBaseUrl' => self::API, 'token' => $token, 'uid' => $uid];

        $result = $runner->invoke($function, [], $context);

        $this->assertEquals('succeeded', $result->status, $result->error ?? '');
        $this->assertEquals(200, $result->output['status'] ?? null,
            'Callback to /api/v4/sql should succeed. Body: ' . json_encode($result->output['body'] ?? null));
        // The SELECT 1 as n result must be present somewhere in the response.
        $this->assertStringContainsString('1', json_encode($result->output['body'] ?? ''));
    }

    /**
     * Minimal JSON POST helper. Returns the decoded body, or null on transport
     * failure.
     */
    private function post(string $path, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents(self::API . $path, false, $ctx);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }
}
