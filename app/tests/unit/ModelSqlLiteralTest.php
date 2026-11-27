<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\Model;
use Codeception\Test\Unit;

class ModelSqlLiteralTest extends Unit
{
    protected UnitTester $tester;

    public function testQuotesAndDoublesEmbeddedQuotes(): void
    {
        $this->assertSame("'roads'", Model::sqlLiteral('roads'));
        $this->assertSame("''", Model::sqlLiteral(''));
        $this->assertSame("'o''brien'", Model::sqlLiteral("o'brien"));
        $this->assertSame("'a'''' OR 1=1 --'", Model::sqlLiteral("a'' OR 1=1 --"));
        // A backslash is an ordinary character in a standard SQL literal
        $this->assertSame("'a\\b'", Model::sqlLiteral('a\\b'));
    }

    public function testRejectsANulByte(): void
    {
        $this->expectException(GC2Exception::class);
        Model::sqlLiteral("a\0b");
    }
}
