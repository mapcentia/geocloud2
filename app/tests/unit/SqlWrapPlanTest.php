<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\models\Sql;
use Codeception\Test\Unit;

/**
 * Sql that remembers the cursor statement sql() declares, so a test can ask
 * Postgres how that exact statement is planned.
 */
class PlanCapturingSql extends Sql
{
    public ?string $declared = null;

    public function prepare(string $sql): PDOStatement
    {
        if (str_starts_with($sql, 'DECLARE curs CURSOR FOR')) {
            $this->declared = $sql;
        }
        return parent::prepare($sql);
    }
}

/**
 * The SQL API wraps the caller's SELECT before running it. The wrapping must
 * not change how the caller's SELECT is planned: the API always reads the
 * whole result (up to the row cap), so the plan has to be the one Postgres
 * picks for reading everything.
 *
 * The case here is the one that hurt in production: a set-returning function
 * in the SELECT list (its ROWS 1000 estimate inflates the row count) over a
 * join with no usable index. Planned for "a few rows", Postgres picks a nested
 * loop with a join filter, which compares every row with every row.
 *
 * Everything is created inside one transaction that is rolled back.
 */
class SqlWrapPlanTest extends Unit
{
    protected UnitTester $tester;
    private PlanCapturingSql $sql;
    private string $schema;

    private const ROWS_A = 2000;
    private const ROWS_B = 5000;

    protected function _before(): void
    {
        Cache::setInstance();
        $this->sql = new PlanCapturingSql('4326', new Connection(database: 'mydb'));
        $this->schema = 'zz_plantest_' . uniqid();
        $s = $this->schema;
        $this->sql->begin();
        $this->sql->execQuery("CREATE SCHEMA $s");
        $this->sql->execQuery("CREATE TABLE $s.a AS SELECT g AS gid, 'Byg-' || g AS bygid,
            ST_Multi(ST_SetSRID(ST_MakePoint(700000 + g, 6170000 + g), 25832)) AS the_geom
            FROM generate_series(1, " . self::ROWS_A . ") g");
        $this->sql->execQuery("CREATE TABLE $s.b AS SELECT g AS id, 'BYG-' || g AS byg_id,
            (g % 9)::text AS bevaringsvaerdi FROM generate_series(1, " . self::ROWS_B . ") g");
        $this->sql->execQuery("ANALYZE $s.a");
        $this->sql->execQuery("ANALYZE $s.b");
        $this->sql->execQuery("CREATE VIEW $s.points AS SELECT b.id, a.gid, b.bevaringsvaerdi, a.the_geom
            FROM $s.a a JOIN $s.b b ON lower(a.bygid) = lower(b.byg_id)");
    }

    protected function _after(): void
    {
        $this->sql->rollback();
    }

    private function userQuery(): string
    {
        return "SELECT id, gid, ST_Transform((ST_Dump(the_geom)).geom, 4326) AS the_geom
                FROM {$this->schema}.points WHERE bevaringsvaerdi != '-1'";
    }

    /** Join node types of a statement's plan, outermost first. */
    private function joins(string $statement): array
    {
        $res = $this->sql->prepare("EXPLAIN (FORMAT JSON) $statement");
        $res->execute();
        $plan = json_decode($res->fetchColumn(), true)[0]['Plan'];
        $found = [];
        $walk = function (array $node) use (&$walk, &$found): void {
            if (in_array($node['Node Type'], ['Nested Loop', 'Merge Join', 'Hash Join'], true)) {
                $found[] = $node['Node Type'];
            }
            foreach ($node['Plans'] ?? [] as $child) {
                $walk($child);
            }
        };
        $walk($plan);
        return $found;
    }

    public function testWrappedSelectIsPlannedLikeTheRawSelect(): void
    {
        $raw = $this->joins($this->userQuery());
        $this->assertNotContains('Nested Loop', $raw,
            'fixture is wrong: the raw SELECT must itself avoid the nested loop');

        $out = $this->sql->sql($this->userQuery(), format: 'json');

        $this->assertNotNull($this->sql->declared, 'sql() did not declare a cursor');
        // Same transaction, so the settings sql() made with SET LOCAL still apply.
        $this->assertSame($raw, $this->joins($this->sql->declared),
            "the SQL API's wrapping changed the join plan of the caller's SELECT");

        $this->assertCount(self::ROWS_A, $out['data'], 'every row must still be returned');
    }

    public function testRowCapStillApplies(): void
    {
        $before = App::$param['limits'] ?? null;
        // Not a multiple of the fetch batch, so the cap has to cut a batch short.
        App::$param['limits']['sqlJson'] = 150;
        try {
            $out = $this->sql->sql("SELECT gid FROM {$this->schema}.a ORDER BY gid", format: 'json');
        } finally {
            if ($before === null) {
                unset(App::$param['limits']);
            } else {
                App::$param['limits'] = $before;
            }
        }
        $this->assertCount(150, $out['data']);
        $this->assertSame(150, (int)end($out['data'])['gid']);
        $this->assertStringNotContainsString(' LIMIT ', (string)$this->sql->declared);
    }
}
