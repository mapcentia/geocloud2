<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Util;
use Codeception\Test\Unit;

/**
 * Util::ogrLineIsFatal() — how app/scripts/get.php reads ogr2ogr's output.
 *
 * ogr2ogr can exit 0 having imported nothing, so the scheduler also scans what
 * it printed. The rule used to be "contains ERROR, except exactly
 * 'ERROR 1: HTTP error code : 404'", which failed jobs whose data loaded
 * perfectly well: GDAL reports some conditions at ERROR level and recovers.
 *
 * The lines below are real output. Job 6301 (Horsens cowiplan:lokalplaner, WFS
 * 1.0.0, download_schema on) started failing when the image moved from GDAL
 * 3.9.2 to 3.12.4 — 3.12 refuses to dereference the legacy srsName URL and says
 * so, where 3.9.2 said nothing. The same run also drew a 503 from the server's
 * DescribeFeatureType. Both times GDAL went on to import all 585 features in
 * EPSG:25832.
 */
class UtilOgrLineIsFatalTest extends Unit
{
    protected UnitTester $tester;

    public function testOrdinaryOutputIsNotAnError(): void
    {
        $this->assertFalse(Util::ogrLineIsFatal(''));
        $this->assertFalse(Util::ogrLineIsFatal('INFO: Open of `/tmp/lokalplaner.gml'));
        $this->assertFalse(Util::ogrLineIsFatal('Warning 1: Layer creation options ignored since an existing layer is being appended to.'));
        $this->assertFalse(Util::ogrLineIsFatal('0...10...20...30...40...50...60...70...80...90...100 - done.'));
    }

    public function testGdalDiagnosticsItRecoversFromAreNotFatal(): void
    {
        // GDAL 3.12 reading a legacy GML srsName; buildOgr2ogrCmd() forces
        // -a_srs anyway, and GDAL itself reads 25832 off the end of the URL.
        $this->assertFalse(Util::ogrLineIsFatal(
            'ERROR 1: Cannot import http://www.opengis.net/gml/srs/epsg.xml#25832 due to ALLOW_NETWORK_ACCESS=NO'
        ), 'a legacy srsName URL is not a failed import');

        // A schema lookup against the source server. get.php only ever hands
        // ogr2ogr a local file, so an HTTP error is always DOWNLOAD_SCHEMA or an
        // xlink -- whatever the status.
        $this->assertFalse(Util::ogrLineIsFatal('ERROR 1: HTTP error code : 404'), 'the status this rule used to name');
        $this->assertFalse(Util::ogrLineIsFatal('ERROR 1: HTTP error code : 503'), 'Horsens WFS answers 503');
        $this->assertFalse(Util::ogrLineIsFatal('ERROR 1: HTTP error code : 500'));
        $this->assertFalse(Util::ogrLineIsFatal('ERROR 4: HTTP error code : 403'));
    }

    public function testRealFailuresStayFatal(): void
    {
        $this->assertTrue(Util::ogrLineIsFatal('ERROR 1: Terminating translation prematurely after failed translation of layer lokalplaner (use -skipfailures to skip errors)'));
        $this->assertTrue(Util::ogrLineIsFatal("ERROR 1: INSERT command for new feature failed.\nERROR:  permission denied"));
        $this->assertTrue(Util::ogrLineIsFatal('FAILURE: Unable to open datasource `/tmp/nope.gml\' with the following drivers.'));
        $this->assertTrue(Util::ogrLineIsFatal('ERROR 1: Unable to find driver `PostgreSQL\'.'));
        $this->assertTrue(Util::ogrLineIsFatal('ERROR 1: Connection to database failed'));
        // Close to a pardoned line, but not one of them
        $this->assertTrue(Util::ogrLineIsFatal('ERROR 1: HTTP error code : not-a-number'),
            'only a numeric status is a schema lookup');
        $this->assertTrue(Util::ogrLineIsFatal('ERROR 1: ALLOW_NETWORK_ACCESS=NO is set, but the table could not be created'),
            'the words alone do not pardon a line');
    }
}
