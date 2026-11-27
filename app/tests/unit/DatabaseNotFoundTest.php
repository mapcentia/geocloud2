<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\DatabaseNotFound;
use Codeception\Test\Unit;

class DatabaseNotFoundTest extends Unit
{
    protected UnitTester $tester;

    public function testUnknownDatabaseBecomesA404WithoutTheDriverMessage(): void
    {
        $e = new PDOException('SQLSTATE[08006] [7] connection to server at "db.internal" (10.0.0.4), port 5432 failed: FATAL:  database "nope" does not exist');
        $t = DatabaseNotFound::translate($e, 'nope');
        $this->assertInstanceOf(GC2Exception::class, $t);
        $this->assertSame(404, $t->getCode());
        $this->assertSame('DATABASE_NOT_FOUND', $t->getErrorCode());
        $this->assertSame('Database not found', $t->getMessage());
    }

    public function testADatabaseThatRefusesConnectionsIsTheSame(): void
    {
        $e = new PDOException('FATAL:  database "template0" is not currently accepting connections');
        $this->assertInstanceOf(GC2Exception::class, DatabaseNotFound::translate($e, 'template0'));
    }

    public function testOtherErrorsPassThroughUnchanged(): void
    {
        // A missing relation in an existing database is not a missing database
        $e = new PDOException('SQLSTATE[42P01]: Undefined table: relation "s.t" does not exist');
        $this->assertSame($e, DatabaseNotFound::translate($e, 'mydb'));
        // ... nor is another database's name in the message
        $e = new PDOException('FATAL:  database "other" does not exist');
        $this->assertSame($e, DatabaseNotFound::translate($e, 'mydb'));
        $e = new RuntimeException('boom');
        $this->assertSame($e, DatabaseNotFound::translate($e, 'mydb'));
    }
}
