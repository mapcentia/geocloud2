<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc\runners;

use app\conf\App;
use app\inc\FunctionRunner;
use app\inc\InvocationResult;

/**
 * Production runner: delegates execution to the standalone gc2-function-runner
 * (Go) service over HTTP. That service runs as its own privileged process and
 * executes the handler inside a gVisor sandbox - keeping arbitrary user code out
 * of the PHP/web-server process entirely.
 *
 * Config (App::$param['functions']):
 *   'runnerUrl'   base URL of the service, e.g. "http://function-runner:8090"
 *   'runnerToken' shared secret sent as "Authorization: Bearer <token>"
 */
class HttpFunctionRunner implements FunctionRunner
{
    public function __construct(private array $config = [])
    {
    }

    public function invoke(array $function, array $event, array $context): InvocationResult
    {
        $cfg = $this->config ?: (App::$param['functions'] ?? []);
        $url = rtrim((string)($cfg['runnerUrl'] ?? ''), '/');
        if ($url === '') {
            return new InvocationResult('failed', null, null, "No functions.runnerUrl configured", 0);
        }
        $token = $cfg['runnerToken'] ?? null;
        // The service caps wall-clock itself; allow a little extra for transport.
        $timeoutS = max(1, (int)($function['timeout_s'] ?? 30)) + 10;

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $body = json_encode(['function' => $function, 'event' => $event, 'context' => $context]);

        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeoutS,
            'ignore_errors' => true,
        ]]);
        $start = (int)(microtime(true) * 1000);
        $raw = @file_get_contents($url . '/invoke', false, $ctx);
        $elapsed = (int)(microtime(true) * 1000) - $start;

        if ($raw === false) {
            return new InvocationResult('failed', null, null, "Function runner unreachable at $url", $elapsed);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['status'])) {
            return new InvocationResult('failed', null, $raw, "Invalid runner response", $elapsed);
        }
        return new InvocationResult(
            status: $decoded['status'],
            output: $decoded['output'] ?? null,
            logs: $decoded['logs'] ?? null,
            error: $decoded['error'] ?? null,
            durationMs: $decoded['duration_ms'] ?? $elapsed,
        );
    }
}
