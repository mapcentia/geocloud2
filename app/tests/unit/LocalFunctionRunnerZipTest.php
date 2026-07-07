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
 * Multi-file (zip) bundle support in LocalFunctionRunner. Requires node/python
 * on PATH (present in the gc2core container).
 */
class LocalFunctionRunnerZipTest extends Unit
{
    protected UnitTester $tester;

    /** Build a base64-encoded zip from [path => contents]. */
    private function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gc2zip') . '.zip';
        $z = new ZipArchive();
        $z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $z->addFromString($name, $contents);
        }
        $z->close();
        $b64 = base64_encode((string)file_get_contents($tmp));
        @unlink($tmp);
        return $b64;
    }

    public function testNodeZipBundleWithRelativeImport(): void
    {
        $code = $this->zip([
            'helper.mjs' => 'export const add = (a, b) => a + b;',
            'index.mjs' => "import { add } from './helper.mjs';\nexport const handler = async (e) => ({ sum: add(e.a, e.b) });",
        ]);
        $result = (new LocalFunctionRunner(['sandbox' => []]))->invoke(
            ['runtime' => 'nodejs20', 'handler' => 'index.handler', 'package' => 'zip', 'code' => $code, 'timeout_s' => 15],
            ['a' => 40, 'b' => 2], []
        );
        $this->assertEquals('succeeded', $result->status, (string)$result->error);
        $this->assertEquals(['sum' => 42], $result->output);
    }

    public function testPythonZipBundleWithLocalImport(): void
    {
        $code = $this->zip([
            'helper.py' => "def add(a, b):\n    return a + b\n",
            'index.py' => "from helper import add\ndef handler(event, context):\n    return {'sum': add(event['a'], event['b'])}\n",
        ]);
        $result = (new LocalFunctionRunner(['sandbox' => []]))->invoke(
            ['runtime' => 'python312', 'handler' => 'index.handler', 'package' => 'zip', 'code' => $code, 'timeout_s' => 15],
            ['a' => 1, 'b' => 41], []
        );
        $this->assertEquals('succeeded', $result->status, (string)$result->error);
        $this->assertEquals(['sum' => 42], $result->output);
    }

    public function testMissingEntryFileFails(): void
    {
        $code = $this->zip(['other.mjs' => 'export const x = 1;']); // no index.*
        $result = (new LocalFunctionRunner(['sandbox' => []]))->invoke(
            ['runtime' => 'nodejs20', 'handler' => 'index.handler', 'package' => 'zip', 'code' => $code, 'timeout_s' => 15],
            [], []
        );
        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('entry file', (string)$result->error);
    }

    public function testInvalidBase64Fails(): void
    {
        $result = (new LocalFunctionRunner(['sandbox' => []]))->invoke(
            ['runtime' => 'nodejs20', 'handler' => 'index.handler', 'package' => 'zip', 'code' => '!!!not base64!!!', 'timeout_s' => 15],
            [], []
        );
        $this->assertEquals('failed', $result->status);
    }
}
