<?php

use app\inc\Controller;

class SqlParseTest extends \Codeception\Test\Unit
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
    public function testTableWalker()
    {
        $strings = "SELECT * FROM foo,bar";
        $walker = new \app\inc\TableWalkerRelation();

        $factory = new sad_spirit\pg_builder\StatementFactory();
        $select = $factory->createFromString($strings);
//        print_r(get_class($select) . "\n");
        $select->dispatch($walker);
        $arr = $walker->getRelations();
        $this->assertContains('foo', $arr);
        $this->assertContains('bar', $arr);



//        echo $factory->createFromAST($select)->getSql();
//        die();
    }
}