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
    protected $tester;
    protected $rules;
    protected $request;

    protected function _before()
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
                "write_filter" => null,
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
                "write_filter" => null,
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
                "write_filter" => null,
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
    }

    protected function _after()
    {
    }

    // tests
    public function testTableWalkerRelationShouldFindRelationsInStatement()
    {
        $string = "SELECT * FROM (SELECT * FROM foo,bar) AS foo";
        $walker = new TableWalkerRelation();

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $arr = $walker->getRelations();
        $this->assertContains('foo', $arr);
        $this->assertContains('bar', $arr);
    }

    public function testTableWalkerRuleShouldAddWhereClauseToSelect()
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


    public function testTableWalkerRuleShouldAddWhereClauseToDelete()
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

    public function testTableWalkerRuleShouldAddWhereClauseToUpdate()
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

    public function testTableWalkerRuleShouldAddWhereClauseToInsert()
    {
        $string = "WITH upd AS (
  UPDATE listens SET sales_count = sales_count + 1 WHERE id =
    (SELECT sales_person FROM foo WHERE name = 'Acme Corporation')
    RETURNING *
)
INSERT INTO test SELECT *, current_timestamp FROM upd ON CONFLICT (did) DO UPDATE SET dname = EXCLUDED.dname";
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
}