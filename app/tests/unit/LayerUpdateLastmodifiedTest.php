<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Layer;
use Codeception\Test\Unit;

/**
 * Layer::updateLastmodified() on a relation that is not a registered layer.
 *
 * It throws, and it should keep throwing: for a caller that just imported data,
 * a relation without geometry columns means something went wrong. The contract
 * is pinned here because app/scripts/get.php calls it from cleanUp(), where a
 * job whose source held no features legitimately has no relation to touch — so
 * that call site guards instead of the model going quiet for everyone.
 */
class LayerUpdateLastmodifiedTest extends Unit
{
    protected UnitTester $tester;

    private function layer(): Layer
    {
        try {
            $layer = new Layer(connection: new Connection(database: 'mydb'));
            $layer->prepare("SELECT 1 FROM settings.geometry_columns_join LIMIT 1")->execute();
            return $layer;
        } catch (Throwable $e) {
            $this->markTestSkipped('mydb not reachable: ' . $e->getMessage());
        }
    }

    public function testThrowsForARelationThatIsNotARegisteredLayer(): void
    {
        $layer = $this->layer();
        $this->expectException(GC2Exception::class);
        $this->expectExceptionMessage('columns not found');
        // A relation an empty import never created: no rows in geometry_columns.
        $layer->updateLastmodified('public', 'no_such_relation_' . uniqid());
    }
}
