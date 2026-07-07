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
 * Phase 0 runner: executes a function synchronously on the local host.
 *
 * Flow per invocation:
 *   1. Materialise the handler code + a per-runtime agent into a scratch dir.
 *   2. Run "<sandbox> <runtime-bin> <agent> <code> <fn> <event> <result>"
 *      under `timeout` for a wall-clock cap.
 *   3. Read the result file (success) or stderr (failure); collect logs+timing.
 *
 * The sandbox is a configurable command prefix so the isolation boundary can
 * be swapped without touching this class:
 *   - []                       -> direct execution (no isolation; dev only)
 *   - ['unshare', '-rn', '--'] -> drops network egress (default; works
 *                                 unprivileged). FS/syscalls are NOT isolated.
 *   - gVisor (production)       -> e.g.
 *       ['runsc', 'run', ...] over an OCI bundle, or
 *       ['docker', 'run', '--runtime=runsc', '--rm', '--network=none',
 *        '--memory={memory_mb}m', '-v', '{workdir}:{workdir}', '-w',
 *        '{workdir}', '{image}']
 *     which adds a user-space kernel: syscall + filesystem isolation and
 *     cgroup-enforced memory. Placeholders {workdir}/{memory_mb}/{timeout_s}
 *     are substituted per invocation.
 *
 * Not yet covered (later phases): warm pools, async invoke, multi-file/zip
 * bundles, and minting a scoped JWT into the context for data-plane callbacks.
 */
class LocalFunctionRunner implements FunctionRunner
{
    private const int MAX_LOG_BYTES = 64 * 1024;

    private const array DEFAULT_RUNTIMES = [
        'nodejs20' => ['bin' => 'node', 'ext' => 'mjs', 'agent' => 'node-bootstrap.cjs'],
        'python312' => ['bin' => 'python3', 'ext' => 'py', 'agent' => 'python-bootstrap.py'],
    ];

    /**
     * @param array $config Optional override; falls back to App::$param['functions'].
     *                      Keys: 'runtimes', 'sandbox', 'workDir'.
     */
    public function __construct(private array $config = [])
    {
    }

    public function invoke(array $function, array $event, array $context): InvocationResult
    {
        $cfg = $this->config ?: (App::$param['functions'] ?? []);
        $runtimes = ($cfg['runtimes'] ?? []) + self::DEFAULT_RUNTIMES;
        $sandbox = $cfg['sandbox'] ?? [];
        $baseDir = $cfg['workDir'] ?? sys_get_temp_dir();

        $runtimeKey = $function['runtime'] ?? '';
        if (!isset($runtimes[$runtimeKey])) {
            return new InvocationResult('failed', null, null, "Unsupported runtime: $runtimeKey", 0);
        }
        $runtime = $runtimes[$runtimeKey];
        $timeoutS = max(1, (int)($function['timeout_s'] ?? 30));
        $memoryMb = max(16, (int)($function['memory_mb'] ?? 128));

        [$file, $fn] = $this->splitHandler($function['handler'] ?? 'index.handler');

        $workDir = $baseDir . DIRECTORY_SEPARATOR . 'gc2fn_' . bin2hex(random_bytes(8));
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            return new InvocationResult('failed', null, null, "Could not create work dir", 0);
        }

        $start = (int)(microtime(true) * 1000);
        try {
            $agentPath = $workDir . DIRECTORY_SEPARATOR . '_agent_' . $runtime['agent'];
            $eventPath = $workDir . DIRECTORY_SEPARATOR . 'event.json';
            $resultPath = $workDir . DIRECTORY_SEPARATOR . 'result.json';

            $codePath = $this->materializeCode($function, $file, $runtime, $workDir);
            copy(__DIR__ . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . $runtime['agent'], $agentPath);
            file_put_contents($eventPath, json_encode($event));

            $cmd = $this->buildCommand($sandbox, $runtime['bin'], $timeoutS, $memoryMb, $workDir,
                [$agentPath, $codePath, $fn, $eventPath, $resultPath]);

            $env = $this->buildEnv($function, $context);
            [$exitCode, $stdout, $stderr] = $this->run($cmd, $workDir, $env);
            $durationMs = (int)(microtime(true) * 1000) - $start;

            $logs = $this->truncate(trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')));

            if ($exitCode === 124 || $exitCode === 137) {
                return new InvocationResult('failed', null, $logs, "Timed out after {$timeoutS}s", $durationMs);
            }
            if ($exitCode === 0 && is_file($resultPath)) {
                $output = json_decode((string)file_get_contents($resultPath), true);
                return new InvocationResult('succeeded', $output, $logs, null, $durationMs);
            }
            $error = $stderr !== '' ? $this->truncate(trim($stderr)) : "Handler exited with code $exitCode";
            return new InvocationResult('failed', null, $logs, $error, $durationMs);
        } catch (\Throwable $e) {
            return new InvocationResult('failed', null, null, $e->getMessage(), (int)(microtime(true) * 1000) - $start);
        } finally {
            $this->cleanup($workDir);
        }
    }

    /**
     * Write the function's code into the work dir and return the entry file path.
     * Inline: a single source file "<file>.<ext>". Zip: extract the base64 bundle
     * and resolve the handler's entry file within it.
     *
     * @throws \RuntimeException on a bad/unsafe bundle or missing entry.
     */
    private function materializeCode(array $function, string $file, array $runtime, string $workDir): string
    {
        if (($function['package'] ?? 'inline') === 'zip') {
            $this->extractZip((string)($function['code'] ?? ''), $workDir);
            $entry = $this->resolveEntry($workDir, $file, $runtime['ext']);
            if ($entry === null) {
                throw new \RuntimeException("Handler entry file for '$file' not found in bundle");
            }
            return $entry;
        }
        $codePath = $workDir . DIRECTORY_SEPARATOR . $file . '.' . $runtime['ext'];
        @mkdir(dirname($codePath), 0700, true);
        file_put_contents($codePath, (string)($function['code'] ?? ''));
        return $codePath;
    }

    /**
     * Extract a base64-encoded zip into $workDir, rejecting zip-slip paths.
     *
     * @throws \RuntimeException
     */
    private function extractZip(string $base64, string $workDir): void
    {
        $data = base64_decode($base64, true);
        if ($data === false) {
            throw new \RuntimeException("code is not valid base64 for a zip package");
        }
        $tmp = $workDir . DIRECTORY_SEPARATOR . '_bundle.zip';
        file_put_contents($tmp, $data);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new \RuntimeException("could not open zip package");
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (str_starts_with($name, '/') || str_contains($name, '..')) {
                $zip->close();
                throw new \RuntimeException("unsafe path in zip: $name");
            }
        }
        $zip->extractTo($workDir);
        $zip->close();
        @unlink($tmp);
    }

    /**
     * Find the handler's entry file in an extracted bundle. Node resolves .mjs
     * (respecting package.json type), .js or .cjs; Python resolves .py.
     */
    private function resolveEntry(string $workDir, string $file, string $ext): ?string
    {
        $candidates = $ext === 'mjs' ? ['mjs', 'js', 'cjs'] : [$ext];
        foreach ($candidates as $e) {
            $path = $workDir . DIRECTORY_SEPARATOR . $file . '.' . $e;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Split a handler reference "file.fn" into [file, fn]. With no dot, the
     * whole value is the function name and the file defaults to "index".
     *
     * @return array{0: string, 1: string}
     */
    private function splitHandler(string $handler): array
    {
        $pos = strrpos($handler, '.');
        if ($pos === false) {
            return ['index', $handler];
        }
        return [substr($handler, 0, $pos), substr($handler, $pos + 1)];
    }

    /**
     * Build the shell command, substituting sandbox placeholders.
     */
    private function buildCommand(array $sandbox, string $bin, int $timeoutS, int $memoryMb, string $workDir, array $args): string
    {
        $subst = static fn(string $t): string => strtr($t, [
            '{workdir}' => $workDir,
            '{memory_mb}' => (string)$memoryMb,
            '{timeout_s}' => (string)$timeoutS,
        ]);

        $parts = ['timeout', '--kill-after=5', (string)$timeoutS];
        foreach ($sandbox as $token) {
            $parts[] = $subst((string)$token);
        }
        $parts[] = $bin;
        foreach ($args as $a) {
            $parts[] = $a;
        }
        return implode(' ', array_map('escapeshellarg', $parts));
    }

    /**
     * Minimal, non-inherited environment: PATH, the function's own env vars,
     * and the execution context as GC2_CONTEXT.
     */
    private function buildEnv(array $function, array $context): array
    {
        $env = ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'];
        $funcEnv = $function['env'] ?? null;
        if (is_string($funcEnv)) {
            $funcEnv = json_decode($funcEnv, true);
        }
        if (is_array($funcEnv)) {
            foreach ($funcEnv as $k => $v) {
                $env[(string)$k] = (string)$v;
            }
        }
        $env['GC2_CONTEXT'] = json_encode($context);
        return $env;
    }

    /**
     * Run a command capturing stdout/stderr and the exit code.
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function run(string $cmd, string $workDir, array $env): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes, $workDir, $env);
        if (!is_resource($proc)) {
            return [1, '', 'Could not start process'];
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        return [$exitCode, $stdout ?: '', $stderr ?: ''];
    }

    private function truncate(string $s): string
    {
        if (strlen($s) <= self::MAX_LOG_BYTES) {
            return $s;
        }
        return substr($s, 0, self::MAX_LOG_BYTES) . "\n...[truncated]";
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->cleanup($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
