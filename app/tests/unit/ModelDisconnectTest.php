<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\Model;
use Codeception\Test\Unit;

/**
 * Model::disconnect() must drop the process-wide cached PDO connection for a
 * database so CLI scripts looping over every database do not accumulate one
 * open connection per database. Uses the container's default database
 * (POSTGRES_DB); skips when it is unreachable.
 */
class ModelDisconnectTest extends Unit
{
    protected UnitTester $tester;

    private function connectedModel(): Model
    {
        $model = new Model(new Connection());
        try {
            $model->connect();
        } catch (Throwable $e) {
            $this->markTestSkipped('Postgres not reachable: ' . $e->getMessage());
        }
        return $model;
    }

    public function testConnectStringForMatchesInstanceConnectString(): void
    {
        $connection = new Connection(database: 'some_db');
        $this->assertSame((new Model($connection))->connectString(), Model::connectStringFor($connection));
        $this->assertStringContainsString('dbname=some_db', Model::connectStringFor($connection));
    }

    public function testDisconnectDropsCachedPdoForThatConnection(): void
    {
        $model = $this->connectedModel();
        $this->assertInstanceOf(PDO::class, $model->getPdoConnection());

        Model::disconnect($model->connection);

        $this->assertNull($model->getPdoConnection(), 'cached PDO must be gone after disconnect');
        // Any Model using the same Connection sees the same (empty) cache slot.
        $this->assertNull((new Model(new Connection()))->getPdoConnection());
    }

    public function testModelReconnectsTransparentlyAfterDisconnect(): void
    {
        $model = $this->connectedModel();
        Model::disconnect($model->connection);

        $res = $model->prepare('SELECT 1 AS one');
        $model->execute($res);
        $this->assertSame(1, (int)$model->fetchRow($res)['one']);
        $this->assertInstanceOf(PDO::class, $model->getPdoConnection());

        Model::disconnect($model->connection);
    }

    public function testDisconnectOfUnknownConnectionIsANoop(): void
    {
        $model = $this->connectedModel();
        Model::disconnect(new Connection(database: 'never_connected_' . bin2hex(random_bytes(3))));
        $this->assertInstanceOf(PDO::class, $model->getPdoConnection(), 'other databases stay connected');
        Model::disconnect($model->connection);
    }
}
