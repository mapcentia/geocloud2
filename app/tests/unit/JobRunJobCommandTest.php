<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Job;
use Codeception\Test\Unit;

/**
 * Job::buildGetCmd() — the get.php command line Job::runJob() spawns with
 * exec().
 *
 * The values come from the jobs table, and POST/PATCH /api/v4/scheduler/jobs
 * writes db/schema/url/type/encoding straight from a JSON body under nothing
 * but a bearer token, while POST /api/v4/scheduler/runs executes the result as
 * the web-server user. Unescaped, a `;` or `$(…)` in any of them is remote
 * code execution. Every interpolated value must therefore leave the builder as
 * a single-quoted shell argument (or an int).
 */
class JobRunJobCommandTest extends Unit
{
    protected UnitTester $tester;

    /** @param array<string, mixed> $extra */
    private function row(array $extra = []): array
    {
        return array_merge([
            'id' => 42, 'db' => 'mydb', 'schema' => 'public', 'name' => 'roads',
            'url' => 'https://example.com/x.geojson', 'epsg' => 25832, 'type' => 'AUTO',
            'encoding' => 'UTF8', 'delete_append' => '0', 'download_schema' => '1',
            'snapshot' => '0', 'extra' => null, 'presql' => null, 'postsql' => null,
        ], $extra);
    }

    public function testShellMetacharactersInUrlAndTypeAreQuotedNotExecuted(): void
    {
        $url = 'https://example.com/a"; echo pwned; "b.geojson';
        $type = 'AUTO;id';
        $cmd = Job::buildGetCmd($this->row(['url' => $url, 'type' => $type]));

        $this->assertStringContainsString(' --url ' . escapeshellarg($url) . ' ', $cmd);
        $this->assertStringContainsString(' --type ' . escapeshellarg($type) . ' ', $cmd);
        // escapeshellarg() single-quotes, so nothing outside a quoted run of
        // the command can be a metacharacter the shell acts on.
        $this->assertStringNotContainsString('; echo pwned; ', str_replace(escapeshellarg($url), '', $cmd));
        $this->assertStringContainsString("'https://example.com/a\"; echo pwned; \"b.geojson'", $cmd);
        $this->assertStringContainsString("'AUTO;id'", $cmd);
        $this->assertStringNotContainsString('--type AUTO;id', $cmd);

        // The shell agrees: running the escaped line through `echo` yields the
        // value back verbatim and executes nothing.
        $this->assertSame($url, trim((string)shell_exec('printf %s ' . escapeshellarg($url))));
    }

    public function testEverySensitiveValueIsEscapedAndNumbersAreCast(): void
    {
        $cmd = Job::buildGetCmd($this->row([
            'db' => "my`db", 'schema' => 'pub;lic', 'name' => 'road$(id)s',
            'encoding' => 'UTF8 && id', 'epsg' => '25832; rm -rf /', 'id' => '42; rm -rf /',
        ]));
        $this->assertStringContainsString(' --db ' . escapeshellarg('my`db') . ' ', $cmd);
        $this->assertStringContainsString(' --schema ' . escapeshellarg('pub;lic') . ' ', $cmd);
        $this->assertStringContainsString(' --safeName ' . escapeshellarg('road$(id)s') . ' ', $cmd);
        $this->assertStringContainsString(' --encoding ' . escapeshellarg('UTF8 && id') . ' ', $cmd);
        $this->assertStringContainsString(' --srid 25832 ', $cmd);
        $this->assertStringContainsString(' --jobId 42 ', $cmd);
        $this->assertStringNotContainsString('rm -rf /', $cmd);
    }

    public function testNameOptionIsOmittedWhenThereIsNoName(): void
    {
        $this->assertStringNotContainsString('--name', Job::buildGetCmd($this->row()));
        $this->assertStringNotContainsString('--name', Job::buildGetCmd($this->row(), ''));
        $this->assertStringContainsString('--name ' . base64_encode('Started via API v4 by me'),
            Job::buildGetCmd($this->row(), 'Started via API v4 by me'));
    }

    public function testBase64OptionsAndManualFlag(): void
    {
        $cmd = Job::buildGetCmd($this->row(['extra' => '-nlt PROMOTE_TO_MULTI', 'presql' => 'select 1']), null, true);
        $this->assertStringContainsString('--extra ' . base64_encode('-nlt PROMOTE_TO_MULTI'), $cmd);
        $this->assertStringContainsString('--preSql ' . base64_encode('select 1'), $cmd);
        $this->assertStringContainsString('--postSql null', $cmd);
        $this->assertStringContainsString('--manual 1', $cmd);
        $this->assertStringContainsString('--manual 0', Job::buildGetCmd($this->row()));
    }
}
