<?php


use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use Codeception\Test\Unit;

class SqlParseTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testTableWalkerRelationShouldFindRelationsInStatement()
    {
        $string = "SELECT * FROM foo,bar";
        $walker = new TableWalkerRelation();

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
//        print_r(get_class($select) . "\n");
        $select->dispatch($walker);
        $arr = $walker->getRelations();
        $this->assertContains('foo', $arr);
        $this->assertContains('bar', $arr);


//        echo $factory->createFromAST($select)->getSql();
//        die();
    }

    public function testTableWalkerRuleShouldAddWhereClause()
    {
        $rules = [
            [
                "username" => "silke",
                "layer" => "test.test",
                "service" => "sql",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => "test.userid=1",
                "write_filter" => null,
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ], [
                "username" => "silke",
                "layer" => "foo",
                "service" => "sql",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => "foo.bar=1",
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


        $string = "SELECT * FROM test.test, foo";

        $walker = new TableWalkerRule();
        $walker->setRules($rules);

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($string);
        $select->dispatch($walker);
        $alteredStatement = $factory->createFromAST($select)->getSql();
        die("\n" . $alteredStatement);
    }
}