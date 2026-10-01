<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\models\Mapcachefile;
use app\models\SchemaSettings;
use Codeception\Test\Unit;

/**
 * The one test that protects every existing install: with no schema settings
 * stored, the generated MapCache document must be exactly what it was before
 * schema settings existed. The fixture was captured from the previous code.
 *
 * If this fails after a change to the per-schema loop, the likely cause is
 * reusing layerSettings()' fallbacks (expires 30, metaSize null) instead of the
 * loop's own (60 and 3).
 *
 * The fixture is a snapshot of whatever mydb contained when it was captured, so
 * adding or removing a layer or schema in mydb fails this test for a reason that
 * has nothing to do with the code. **Do not simply recapture it from the current
 * generator** — that would silently assert that the code agrees with itself and
 * void the guarantee. A legitimate recapture runs the generator as it stood
 * BEFORE this feature:
 *
 *   git show <commit-before-schema-settings>:app/models/Mapcachefile.php
 *
 * into a scratch copy under a different class name, generate with that, and write
 * the result to app/tests/_data/mapcache_mydb_before.xml.
 */
class MapcacheConfigUnchangedTest extends Unit
{
    protected UnitTester $tester;

    public function testAnInstallWithNoSchemaSettingsGeneratesTheSameDocument(): void
    {
        $fixture = codecept_data_dir('mapcache_mydb_before.xml');
        if (!file_exists($fixture)) {
            $this->markTestSkipped('baseline fixture not captured');
        }
        $settings = new SchemaSettings(connection: new Connection(database: 'mydb'));
        if ($settings->all() !== []) {
            $this->markTestSkipped('settings.schema_settings is not empty, so this is not a clean baseline');
        }
        $generated = (new Mapcachefile(new Connection(database: 'mydb')))->generate();
        $this->assertSame(file_get_contents($fixture), $generated,
            'the generated config changed while no schema settings are stored');
    }
}
