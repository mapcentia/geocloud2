<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Tests app/inc/geometry/GmlConverter.php — the autoloadable, globals-free port
 * of gmlConverter from the legacy app/libs/phpgeometry_class.php. Expected WKT
 * outputs are pinned to the legacy output (verified byte-identical at port time).
 * The state-reset tests guard the worker-safety property the port introduced:
 * all parser state is instance state, reset on every gmlToWKT() call.
 */

use app\inc\geometry\GmlConverter;
use Codeception\Test\Unit;

class GmlConverterTest extends Unit
{
    protected UnitTester $tester;

    public function testGml2PointCoordinates(): void
    {
        $gml = '<g><gml:Point srsName="EPSG:4326"><gml:coordinates>9.5,55.7</gml:coordinates></gml:Point></g>';
        [$wkt, $srid] = (new GmlConverter())->gmlToWKT($gml);
        $this->assertSame(["POINT(9.5 55.7)"], $wkt);
        // srsName ATTRIBUTES are deliberately not captured (legacy behavior)
        $this->assertSame([], $srid);
    }

    public function testGml3PointPos(): void
    {
        $gml = '<g><gml:Point><gml:pos>9.5 55.7</gml:pos></gml:Point></g>';
        $this->assertSame(["POINT(9.5 55.7)"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    public function testGml3LineStringPosList(): void
    {
        $gml = '<g><gml:LineString><gml:posList>1 2 3 4 5 6</gml:posList></gml:LineString></g>';
        $this->assertSame(["LINESTRING(1 2,3 4,5 6)"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    public function testGml2PolygonWithIsland(): void
    {
        $gml = '<g><gml:Polygon>'
            . '<gml:outerBoundaryIs><gml:LinearRing><gml:coordinates>0,0 10,0 10,10 0,0</gml:coordinates></gml:LinearRing></gml:outerBoundaryIs>'
            . '<gml:innerBoundaryIs><gml:LinearRing><gml:coordinates>2,2 4,2 4,4 2,2</gml:coordinates></gml:LinearRing></gml:innerBoundaryIs>'
            . '</gml:Polygon></g>';
        $this->assertSame(
            ["POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))"],
            (new GmlConverter())->gmlToWKT($gml)[0]
        );
    }

    public function testGml3ExteriorInterior(): void
    {
        $gml = '<g><gml:Polygon>'
            . '<gml:exterior><gml:LinearRing><gml:posList>0 0 10 0 10 10 0 0</gml:posList></gml:LinearRing></gml:exterior>'
            . '<gml:interior><gml:LinearRing><gml:posList>2 2 4 2 4 4 2 2</gml:posList></gml:LinearRing></gml:interior>'
            . '</gml:Polygon></g>';
        $this->assertSame(
            ["POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))"],
            (new GmlConverter())->gmlToWKT($gml)[0]
        );
    }

    public function testGml3MultiSurfaceBecomesMultiPolygon(): void
    {
        $gml = '<g><gml:MultiSurface><gml:surfaceMember><gml:Polygon>'
            . '<gml:exterior><gml:LinearRing><gml:posList>0 0 10 0 10 10 0 0</gml:posList></gml:LinearRing></gml:exterior>'
            . '</gml:Polygon></gml:surfaceMember></gml:MultiSurface></g>';
        $this->assertSame(["MULTIPOLYGON(((0 0,10 0,10 10,0 0)))"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    public function testGml3MultiCurveBecomesMultiLineString(): void
    {
        $gml = '<g><gml:MultiCurve><gml:curveMember><gml:LineString>'
            . '<gml:posList>1 2 3 4</gml:posList>'
            . '</gml:LineString></gml:curveMember></gml:MultiCurve></g>';
        $this->assertSame(["MULTILINESTRING((1 2,3 4))"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    public function testDefaultSplitTagSeparatesFeatureMembers(): void
    {
        $gml = '<g>'
            . '<gml:featureMember><f><gml:Point><gml:coordinates>1,2</gml:coordinates></gml:Point></f></gml:featureMember>'
            . '<gml:featureMember><f><gml:Point><gml:coordinates>3,4</gml:coordinates></gml:Point></f></gml:featureMember>'
            . '</g>';
        $this->assertSame(["POINT(1 2)", "POINT(3 4)"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    public function testCustomSplitTag(): void
    {
        $gml = '<g>'
            . '<item><gml:Point><gml:coordinates>1,2</gml:coordinates></gml:Point></item>'
            . '<item><gml:Point><gml:coordinates>3,4</gml:coordinates></gml:Point></item>'
            . '</g>';
        $this->assertSame(["POINT(1 2)", "POINT(3 4)"], (new GmlConverter())->gmlToWKT($gml, ["ITEM"])[0]);
    }

    public function testNamespacePrefixesAreStripped(): void
    {
        $gml = '<wfs:FeatureCollection xmlns:wfs="http://www.opengis.net/wfs">'
            . '<gml:featureMembers><t:poi xmlns:t="x"><t:the_geom>'
            . '<gml:Point><gml:pos>9.5 55.7</gml:pos></gml:Point>'
            . '</t:the_geom></t:poi></gml:featureMembers></wfs:FeatureCollection>';
        $this->assertSame(["POINT(9.5 55.7)"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }

    /**
     * An srsName ELEMENT ("not normal. Used when serializing array to xml")
     * captures the srid and, for urn form, flips the axis order to lon/lat.
     */
    public function testSrsNameElementCapturesSridAndFlipsUrnAxisOrder(): void
    {
        $gml = '<geom><srsName>urn:ogc:def:crs:EPSG::4326</srsName>'
            . '<gml:Point><gml:coordinates>55.7,9.5</gml:coordinates></gml:Point></geom>';
        [$wkt, $srid] = (new GmlConverter())->gmlToWKT($gml);
        $this->assertSame(["POINT(9.5 55.7)"], $wkt);
        $this->assertSame(["4326"], $srid);
    }

    /**
     * Worker-safety regression: every gmlToWKT() call must start from clean
     * state. A leaked axisOrder from a previous urn-CRS parse would flip the
     * coordinates of the next parse (this leak existed in the legacy version,
     * where srid/axisOrder were never reset between calls).
     */
    public function testInstanceIsReusableWithoutStateLeak(): void
    {
        $converter = new GmlConverter();
        // First parse establishes latitude-first axis order and an srid.
        $converter->gmlToWKT('<geom><srsName>urn:ogc:def:crs:EPSG::4326</srsName>'
            . '<gml:Point><gml:coordinates>55.7,9.5</gml:coordinates></gml:Point></geom>');
        // Second parse on the same instance must NOT inherit the flip or the srid.
        [$wkt, $srid] = $converter->gmlToWKT('<g><gml:Point><gml:coordinates>9.5,55.7</gml:coordinates></gml:Point></g>');
        $this->assertSame(["POINT(9.5 55.7)"], $wkt);
        $this->assertSame([], $srid);
    }

    public function testSecondCallMatchesFreshInstance(): void
    {
        $gml = '<g><gml:Polygon>'
            . '<gml:outerBoundaryIs><gml:LinearRing><gml:coordinates>0,0 10,0 10,10 0,0</gml:coordinates></gml:LinearRing></gml:outerBoundaryIs>'
            . '</gml:Polygon></g>';
        $converter = new GmlConverter();
        $converter->gmlToWKT('<g><gml:Point><gml:coordinates>1,2</gml:coordinates></gml:Point></g>');
        $this->assertSame((new GmlConverter())->gmlToWKT($gml), $converter->gmlToWKT($gml));
    }

    public function testParseEpsgCode(): void
    {
        $this->assertSame("4326", GmlConverter::parseEpsgCode("EPSG:4326"));
        $this->assertSame("25832", GmlConverter::parseEpsgCode("urn:ogc:def:crs:EPSG::25832"));
        $this->assertSame("4326", GmlConverter::parseEpsgCode("urn:x-ogc:def:crs:EPSG:4326"));
    }

    public function testGetAxisOrderFromEpsg(): void
    {
        $this->assertSame("longitude", GmlConverter::getAxisOrderFromEpsg("EPSG:4326"));
        $this->assertSame("latitude", GmlConverter::getAxisOrderFromEpsg("urn:ogc:def:crs:EPSG::4326"));
        $this->assertSame("latitude", GmlConverter::getAxisOrderFromEpsg("urn:x-ogc:def:crs:EPSG:25832"));
    }
}
