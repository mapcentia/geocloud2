<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use Codeception\Test\Unit;

/**
 * The one-shot runtime agents are duplicated: the PHP LocalFunctionRunner reads
 * them from app/inc/runners/agents, while the standalone Go service embeds its
 * own copy in function-runner/agents (go:embed can't reach outside its module).
 * They MUST stay byte-identical — this test fails loudly if they drift.
 */
class AgentsSyncTest extends Unit
{
    protected UnitTester $tester;

    private const string PHP_AGENTS = __DIR__ . '/../../inc/runners/agents';
    private const string GO_AGENTS = __DIR__ . '/../../../function-runner/agents';

    /** Agents shared by both runners (the Go pool agents have no PHP counterpart). */
    private const array SHARED = ['node-bootstrap.cjs', 'python-bootstrap.py'];

    public function testSharedAgentsAreByteIdentical(): void
    {
        foreach (self::SHARED as $agent) {
            $php = self::PHP_AGENTS . '/' . $agent;
            $go = self::GO_AGENTS . '/' . $agent;
            $this->assertFileExists($php, "Missing PHP agent: $agent");
            $this->assertFileExists($go, "Missing Go agent: $agent");
            $this->assertSame(
                file_get_contents($php),
                file_get_contents($go),
                "Agent '$agent' has drifted between app/inc/runners/agents and function-runner/agents. " .
                "Re-sync the copies (they must be identical)."
            );
        }
    }
}
