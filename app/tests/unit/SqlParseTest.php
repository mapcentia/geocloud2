<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use Codeception\Test\Unit;

class SqlParseTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var array<array<string>>
     */

    protected array $rules;

    /**
     * @var array<string>
     */
    protected array $request;
    protected array $requestWithNoMatch;

    protected function _before(): void
    {
        $this->rules = [
            [
                "username" => "silke",
                "layer" => "test",
                "service" => "sql",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => "test.userid='test'",
                "write_filter" => "test.userid='test'",
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ],
            [
                "username" => "silke",
                "layer" => "foo",
                "service" => "sql",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => "foo.bar='test'",
                "write_filter" => "foo.bar='test'",
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ],
            [
                "username" => "silke",
                "layer" => "listens",
                "service" => "sql",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => "listens.uid='test'",
                "write_filter" => "listens.uid='test'",
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ],
            [
                "username" => "*",
                "layer" => "*",
                "service" => "*",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "deny",
                "read_filter" => null,
                "write_filter" => null,
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ],
        ];
        $this->request = [
            "silke", "sql", "get", "127.0.0.1"
        ];
        $this->requestWithNoMatch = [
            "stranger", "sql", "get", "127.0.0.1"
        ];
    }

    protected function _after(): void
    {
    }

    // tests
    public function testTableWalkerRelationShouldFindRelationsInStatement(): void
    {
        $string = "SELECT * FROM (SELECT * FROM foo,bar) AS foo";
        $walker = new TableWalkerRelation();

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $arr = $walker->getRelations()["all"];
        $this->assertContains('foo', $arr);
        $this->assertContains('bar', $arr);
    }

    public function testTableWalkerRuleShouldAddWhereClauseToSelect(): void
    {

        $string = "WITH max_table as (
            SELECT uid, max(timestamp) - 10000 as mx
            FROM LISTENS 
            GROUP BY uid
        ) SELECT * FROM test, foo";

        $walker = new TableWalkerRule(...$this->request);
        $walker->setRules($this->rules);

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $alteredStatement = $factory->createFromAST($select)->getSql();
        $this->assertStringContainsString("foo.bar = 'test'", $alteredStatement);
        $this->assertStringContainsString("test.userid = 'test'", $alteredStatement);
        $this->assertStringContainsString("listens.uid = 'test'", $alteredStatement);
    }

    public function testTableWalkerRuleShouldNotAddWhereClauseToSelectWhenNoMatch(): void
    {

        $string = "SELECT * FROM test, foo";
        $walker = new TableWalkerRule(...$this->requestWithNoMatch);
        $walker->setRules($this->rules);
        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        try {
            $select->dispatch($walker);
            throw new Exception("dummy");
        } catch (Exception $e) {
            if ($e->getMessage() != "DENY") {
                $this->fail();
            }
        }
    }

    public function testTableWalkerRuleShouldAddWhereClauseToDelete(): void
    {
        $string = "WITH max_table as (
            SELECT uid, max(timestamp) - 10000 as mx
            FROM LISTENS 
            GROUP BY uid
        ) DELETE FROM test.test  USING foo, test WHERE id = foo.id OR id = listens.uid";
        $walker = new TableWalkerRule(...$this->request);
        $walker->setRules($this->rules);
        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $alteredStatement = $factory->createFromAST($select)->getSql();
//        die("\n" . $alteredStatement);
        $this->assertStringContainsString("foo.bar = 'test'", $alteredStatement);
        $this->assertStringContainsString("test.userid = 'test'", $alteredStatement);
        $this->assertStringContainsString("listens.uid = 'test'", $alteredStatement);

    }

    public function testTableWalkerRuleShouldAddWhereClauseToUpdate(): void
    {
        $string = "WITH max_table as (
            SELECT uid, max(timestamp) - 10000 as mx
            FROM LISTENS 
            GROUP BY uid
        ) UPDATE ONLY test.test SET name='Joe' FROM foo WHERE id=1";
        $walker = new TableWalkerRule(...$this->request);
        $walker->setRules($this->rules);
        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $alteredStatement = $factory->createFromAST($select)->getSql();
//        die("\n" . $alteredStatement);
        $this->assertStringContainsString("foo.bar = 'test'", $alteredStatement);
        $this->assertStringContainsString("test.userid = 'test'", $alteredStatement);
        $this->assertStringContainsString("listens.uid = 'test'", $alteredStatement);
    }

    public function testTableWalkerRuleShouldAddWhereClauseToInsert(): void
    {
        $string = "WITH upd AS (
  UPDATE listens SET sales_count = sales_count + 1 WHERE id =
    (SELECT sales_person FROM foo WHERE name = 'Acme Corporation')
    RETURNING *
)
INSERT INTO test SELECT *, current_timestamp FROM foo ON CONFLICT (did) DO UPDATE SET dname = EXCLUDED.dname";
        $walker = new TableWalkerRule(...$this->request);
        $walker->setRules($this->rules);
        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $alteredStatement = $factory->createFromAST($select)->getSql();
        $this->assertStringContainsString("foo.bar = 'test'", $alteredStatement);
        $this->assertStringContainsString("test.userid = 'test'", $alteredStatement);
        $this->assertStringContainsString("listens.uid = 'test'", $alteredStatement);
    }
}