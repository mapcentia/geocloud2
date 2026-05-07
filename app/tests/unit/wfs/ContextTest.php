<?php
namespace app\tests\unit\wfs;

use app\inc\Connection;
use app\inc\Model;
use app\wfs\Context;
use Codeception\Test\Unit;

class ContextTest extends Unit
{
    public function testFieldsAccessible(): void
    {
        $conn = new Connection(database: 'mydb');
        $ctx = new Context(
            connection: $conn,
            database: 'mydb',
            schema: 'public',
            user: 'alice',
            parentUser: false,
            trusted: true,
            host: 'http://example.com',
            thePath: 'http://example.com/wfs/mydb/public',
            startTime: 1700000000.0,
        );
        $this->assertSame($conn, $ctx->connection);
        $this->assertSame('mydb', $ctx->database);
        $this->assertSame('public', $ctx->schema);
        $this->assertSame('alice', $ctx->user);
        $this->assertFalse($ctx->parentUser);
        $this->assertTrue($ctx->trusted);
    }

    public function testModelHelperReturnsModelOnSameConnection(): void
    {
        $conn = new Connection(database: 'mydb');
        $ctx = new Context(
            connection: $conn,
            database: 'mydb', schema: 'public', user: 'alice',
            parentUser: false, trusted: false,
            host: '', thePath: '', startTime: 0.0,
        );
        $m = $ctx->model();
        $this->assertInstanceOf(Model::class, $m);
        $this->assertSame($conn, $m->connection);
    }
}
