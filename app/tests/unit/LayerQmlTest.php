<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\models\LayerQml;
use Codeception\Test\Unit;

/**
 * The rules of a layer's `qml` and `use_qml` (PATCH /api/v4/layers/{layer}).
 */
class LayerQmlTest extends Unit
{
    protected UnitTester $tester;

    private const A = '<qgis><renderer-v2 type="singleSymbol"/></qgis>';
    private const B = '<qgis><renderer-v2 type="categorizedSymbol"/></qgis>';

    private function code(callable $fn): string
    {
        try {
            $fn();
        } catch (GC2Exception $e) {
            return $e->getErrorCode() . ' ' . $e->getCode();
        }
        return 'no error';
    }

    public function testStoringAQmlDoesNotSwitchItOn(): void
    {
        $this->assertSame(['qml' => self::A, 'use' => false, 'write' => false],
            LayerQml::plan(null, false, false, true, self::A, null));
    }

    public function testSwitchingOnWritesTheProject(): void
    {
        // with the stored QML
        $this->assertSame(['qml' => self::A, 'use' => true, 'write' => true],
            LayerQml::plan(self::A, false, false, false, null, true));
        // or with one sent in the same request
        $this->assertSame(['qml' => self::B, 'use' => true, 'write' => true],
            LayerQml::plan(null, false, false, true, self::B, true));
    }

    public function testSwitchingOffKeepsTheQml(): void
    {
        $this->assertSame(['qml' => self::A, 'use' => false, 'write' => false],
            LayerQml::plan(self::A, true, false, false, null, false));
    }

    public function testAChangedQmlInUseRewritesTheProjectAndAnUnchangedOneDoesNot(): void
    {
        $this->assertSame(['qml' => self::B, 'use' => true, 'write' => true],
            LayerQml::plan(self::A, true, false, true, self::B, null));
        $this->assertSame(['qml' => self::A, 'use' => true, 'write' => false],
            LayerQml::plan(self::A, true, false, true, self::A, null));
        $this->assertSame(['qml' => self::A, 'use' => true, 'write' => false],
            LayerQml::plan(self::A, true, false, false, null, true));
    }

    public function testRemovingAQmlInUseSwitchesItOff(): void
    {
        $this->assertSame(['qml' => null, 'use' => false, 'write' => false],
            LayerQml::plan(self::A, true, false, true, null, null));
    }

    public function testErrors(): void
    {
        $this->assertSame('QML_MISSING 400', $this->code(fn() => LayerQml::plan(null, false, false, false, null, true)));
        $this->assertSame('QML_MISSING 400', $this->code(fn() => LayerQml::plan(self::A, true, false, true, null, true)));
        $this->assertSame('EXTERNAL_SOURCE 409', $this->code(fn() => LayerQml::plan(self::A, false, true, false, null, true)));
        $this->assertSame('INVALID_QML 400', $this->code(fn() => LayerQml::plan(null, false, false, true, '<qgis><open></qgis>', null)));
        $this->assertSame('INVALID_QML 400', $this->code(fn() => LayerQml::plan(null, false, false, true, 'not xml', null)));
        // A layer with an external source can still store a QML and be told "off"
        $this->assertSame(['qml' => self::A, 'use' => false, 'write' => false],
            LayerQml::plan(null, false, true, true, self::A, false));
    }

    public function testIsOwnProject(): void
    {
        $own = 'http://127.0.0.1:80/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2//app/wms/qgsfiles/mydb_dagi_kommune_the_geom.qgs&SERVICE=WMS&LAYER=dagi.kommune&';
        $this->assertTrue(LayerQml::isOwnProject($own, 'mydb', 'dagi.kommune.the_geom'));
        // Another layer's project, another database's, an uploaded project, an external WMS, nothing
        $this->assertFalse(LayerQml::isOwnProject($own, 'mydb', 'dagi.region.the_geom'));
        $this->assertFalse(LayerQml::isOwnProject($own, 'other', 'dagi.kommune.the_geom'));
        $this->assertFalse(LayerQml::isOwnProject('http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2/app/tmp/mydb/upload/my.qgs&LAYERS=x', 'mydb', 'dagi.kommune.the_geom'));
        $this->assertFalse(LayerQml::isOwnProject('https://wms.example.com/ows?layers=a', 'mydb', 'dagi.kommune.the_geom'));
        $this->assertFalse(LayerQml::isOwnProject(null, 'mydb', 'dagi.kommune.the_geom'));
        $this->assertFalse(LayerQml::isOwnProject('', 'mydb', 'dagi.kommune.the_geom'));
    }
}
