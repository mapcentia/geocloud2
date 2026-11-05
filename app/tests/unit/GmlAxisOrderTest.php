<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\OgrGml;
use Codeception\Test\Unit;

/**
 * The axis order the scheduler's WFS imports land in, through the real GDAL —
 * no stub, because the thing under test is what the GML driver does with a
 * srsName, and a stub cannot have an opinion about that.
 *
 * It replaces a perl pass (get.php fetchPart, "SRS normalizer") that rewrote
 * every srsName in the downloaded GML to the short EPSG:<code> form before
 * handing the file to ogr2ogr. That started life as a way to stop GDAL trying
 * to dereference the legacy "epsg.xml#<code>" URL, but when it was widened to
 * the urn: and def/crs/ forms it quietly took on a second job: GDAL honours the
 * authority axis order for those forms (lat,lon for EPSG:4326 and 4258) and
 * ignores it for the short form, so the rewrite was what kept those imports in
 * the order the file writes them.
 *
 * OgrGml::OPEN_OPTIONS says that out loud instead. These tests pin both halves:
 * what GC2's open options produce, and what GDAL does without them — the
 * reason they exist.
 */
class GmlAxisOrderTest extends Unit
{
    protected UnitTester $tester;

    /** gml:pos written as "<first> <second>" straight from the fixture. */
    private const string LAT_LON_POS = '57.048 9.921';
    private const string PROJECTED_POS = '555000 6321000';

    private ?string $dir = null;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/gml-axis-' . getmypid() . '-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function _after(): void
    {
        if ($this->dir !== null && is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
    }

    /**
     * Every srsName form a WFS in the wild sends us, and the gml:pos that goes
     * with it. The expectation column is in the test, not here.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function forms(): array
    {
        return [
            // Authority-compliant WFS 2.0.0 / 1.1.0 spelling. EPSG:4326 is lat,lon
            // by the authority, which is what makes this form interesting.
            'urn 4326' => ['urn:ogc:def:crs:EPSG::4326', self::LAT_LON_POS],
            'def/crs 4326' => ['http://www.opengis.net/def/crs/EPSG/0/4326', self::LAT_LON_POS],
            // Legacy GML spelling; the one that used to make GDAL reach for the network.
            'epsg.xml 4326' => ['http://www.opengis.net/gml/srs/epsg.xml#4326', self::LAT_LON_POS],
            // Projected CRS: easting,northing is x,y in every spelling, so none of
            // this can move it. The Danish jobs are almost all here.
            'urn 25832' => ['urn:ogc:def:crs:EPSG::25832', self::PROJECTED_POS],
            'def/crs 25832' => ['http://www.opengis.net/def/crs/EPSG/0/25832', self::PROJECTED_POS],
            'epsg.xml 25832' => ['http://www.opengis.net/gml/srs/epsg.xml#25832', self::PROJECTED_POS],
        ];
    }

    private function writeGml(string $label, string $srsName, string $pos): string
    {
        $path = $this->dir . '/' . preg_replace('/[^a-z0-9]+/i', '_', $label) . '.gml';
        file_put_contents($path, <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <wfs:FeatureCollection xmlns:wfs="http://www.opengis.net/wfs/2.0" xmlns:gml="http://www.opengis.net/gml/3.2" xmlns:test="http://example.com/test">
              <wfs:member><test:points gml:id="p.1"><test:navn>Aalborg</test:navn>
                <test:geom><gml:Point srsName="$srsName"><gml:pos>$pos</gml:pos></gml:Point></test:geom></test:points></wfs:member>
            </wfs:FeatureCollection>
            XML);
        return $path;
    }

    /**
     * @param list<string> $openOptions
     * @return string the first feature's geometry, e.g. "POINT (9.921 57.048)"
     */
    private function readPoint(string $gmlPath, array $openOptions): string
    {
        // GDAL caches the parsed schema in a .gfs beside the file; a stale one
        // would answer instead of the driver.
        @unlink(preg_replace('/\.gml$/', '.gfs', $gmlPath));
        $cmd = 'ogrinfo -al -nomd';
        foreach ($openOptions as $o) {
            $cmd .= ' -oo ' . escapeshellarg($o);
        }
        $cmd .= ' ' . escapeshellarg($gmlPath) . ' 2>/dev/null';
        exec($cmd, $out);
        foreach ($out as $line) {
            if (preg_match('/POINT \([-0-9. ]+\)/', $line, $m) === 1) {
                return $m[0];
            }
        }
        return implode("\n", $out);
    }

    private function skipWithoutGdal(): void
    {
        exec('which ogrinfo 2>/dev/null', $o, $code);
        if ($code !== 0) {
            $this->markTestSkipped('no ogrinfo on this install');
        }
    }

    /**
     * The contract the scheduler relies on: with GC2's open options, a GML file
     * is read in the coordinate order it is written in, whichever way the WFS
     * chose to spell the CRS. Both import paths (get.php getCmd and fetchPart)
     * pass these, so both land the same rows for the same file.
     */
    public function testGc2sOpenOptionsReadEveryFormInFileOrder(): void
    {
        $this->skipWithoutGdal();

        foreach ($this->forms() as $label => [$srsName, $pos]) {
            $path = $this->writeGml($label, $srsName, $pos);
            $expected = 'POINT (' . $pos . ')';
            $this->assertSame(
                $expected,
                $this->readPoint($path, OgrGml::OPEN_OPTIONS),
                "$label was not read in file order"
            );
        }
    }

    /**
     * Why those options exist. Left to its default (SWAP_COORDINATES=AUTO) GDAL
     * swaps the authority-spelled forms of a lat,lon CRS and leaves everything
     * else alone — so an import without the options disagrees with one that has
     * them, on exactly those two forms.
     *
     * This is a pin on GDAL's behaviour, not on GC2's: it passes before and
     * after the change. It is here so that a GDAL bump that alters any of it
     * shows up as a failure next to the code that depends on it.
     */
    public function testWithoutTheOptionsGdalSwapsTheAuthoritySpelledLatLonForms(): void
    {
        $this->skipWithoutGdal();

        $swapped = 'POINT (9.921 57.048)';
        $asWritten = 'POINT (' . self::LAT_LON_POS . ')';

        $expected = [
            'urn 4326' => $swapped,
            'def/crs 4326' => $swapped,
            'epsg.xml 4326' => $asWritten,
            'urn 25832' => 'POINT (' . self::PROJECTED_POS . ')',
            'def/crs 25832' => 'POINT (' . self::PROJECTED_POS . ')',
            'epsg.xml 25832' => 'POINT (' . self::PROJECTED_POS . ')',
        ];

        foreach ($this->forms() as $label => [$srsName, $pos]) {
            $path = $this->writeGml($label, $srsName, $pos);
            $this->assertSame($expected[$label], $this->readPoint($path, []), "$label, GDAL default");
        }
    }
}
