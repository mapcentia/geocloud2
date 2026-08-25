<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Tests app/inc/geometry/GeometryFactory.php and its geometry subclasses —
 * the autoloadable, globals-free port of the legacy app/libs/phpgeometry_class.php.
 * The expected GML strings are pinned to the legacy output (verified byte-identical
 * at port time), so these are regression tests for the WFS-T write path.
 */

use app\inc\geometry\GeometryCollection;
use app\inc\geometry\GeometryFactory;
use app\inc\geometry\GmlConverter;
use app\inc\geometry\LineString;
use app\inc\geometry\MultiLineString;
use app\inc\geometry\MultiPoint;
use app\inc\geometry\MultiPolygon;
use app\inc\geometry\Point;
use app\inc\geometry\Polygon;
use Codeception\Test\Unit;

class GeometryFactoryTest extends Unit
{
    protected UnitTester $tester;

    private GeometryFactory $factory;

    protected function _before(): void
    {
        $this->factory = new GeometryFactory();
    }

    public function testCreateGeometryReturnsCorrectSubclass(): void
    {
        $this->assertInstanceOf(Point::class, $this->factory->createGeometry("POINT(1 2)", "EPSG:4326"));
        $this->assertInstanceOf(LineString::class, $this->factory->createGeometry("LINESTRING(1 2,3 4)", "EPSG:4326"));
        $this->assertInstanceOf(Polygon::class, $this->factory->createGeometry("POLYGON((0 0,1 0,1 1,0 0))", "EPSG:4326"));
        $this->assertInstanceOf(MultiPoint::class, $this->factory->createGeometry("MULTIPOINT(1 2,3 4)", "EPSG:4326"));
        $this->assertInstanceOf(MultiLineString::class, $this->factory->createGeometry("MULTILINESTRING((1 2,3 4))", "EPSG:4326"));
        $this->assertInstanceOf(MultiPolygon::class, $this->factory->createGeometry("MULTIPOLYGON(((0 0,1 0,1 1,0 0)))", "EPSG:4326"));
    }

    public function testCreateGeometryReturnsNullForUnknownType(): void
    {
        $this->assertNull($this->factory->createGeometry("CIRCULARSTRING(0 0,1 1,2 0)", "EPSG:4326"));
    }

    public function testPointToGml(): void
    {
        $gml = $this->factory->createGeometry("POINT(9.5 55.7)", "EPSG:4326")->toGML();
        $this->assertSame(
            "<gml:Point srsName=\"EPSG:4326\">\n"
            . "  <gml:coordinates>9.5,55.7</gml:coordinates>\n"
            . "</gml:Point>\n",
            $gml
        );
    }

    public function testPointToGmlWithoutSridOmitsSrsName(): void
    {
        $gml = $this->factory->createGeometry("POINT(9.5 55.7)")->toGML();
        $this->assertStringNotContainsString("srsName", $gml);
        $this->assertStringContainsString("<gml:coordinates>9.5,55.7</gml:coordinates>", $gml);
    }

    public function testPolygonWithHoleToGml(): void
    {
        $gml = $this->factory
            ->createGeometry("POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))", "EPSG:4326")
            ->toGML();
        $this->assertStringContainsString("<gml:outerBoundaryIs>", $gml);
        $this->assertStringContainsString("<gml:innerBoundaryIs>", $gml);
        $this->assertStringContainsString("<gml:coordinates>0,0 10,0 10,10 0,0</gml:coordinates>", $gml);
        $this->assertStringContainsString("<gml:coordinates>2,2 4,2 4,4 2,2</gml:coordinates>", $gml);
    }

    public function testMultiPointToGmlHasSrsNameOnOuterElementOnly(): void
    {
        $gml = $this->factory->createGeometry("MULTIPOINT((1 2),(3 4))", "EPSG:4326")->toGML();
        $this->assertSame(1, substr_count($gml, "srsName"));
        $this->assertStringContainsString("<gml:MultiPoint srsName=\"EPSG:4326\">", $gml);
        $this->assertSame(2, substr_count($gml, "<gml:pointMember>"));
    }

    /**
     * Legacy quirk, preserved by the port: the bare MULTIPOINT(1 2,3 4) form
     * deconstructs to ONE shape, so it renders as a single pointMember holding
     * both coordinates. Use the parenthesized form for one member per point.
     */
    public function testBareMultiPointFormRendersSingleMember(): void
    {
        $gml = $this->factory->createGeometry("MULTIPOINT(1 2,3 4)", "EPSG:4326")->toGML();
        $this->assertSame(1, substr_count($gml, "<gml:pointMember>"));
        $this->assertStringContainsString("<gml:coordinates>1,2 3,4</gml:coordinates>", $gml);
    }

    public function testMultiLineStringToGml3UsesPosList(): void
    {
        $gml = $this->factory->createGeometry("MULTILINESTRING((1 2,3 4))", "EPSG:4326")->toGML3();
        $this->assertStringContainsString("<gml:posList>1 2 3 4</gml:posList>", $gml);
        $this->assertStringNotContainsString("coordinates", $gml);
    }

    public function testGetAsMulti(): void
    {
        $this->assertSame("MULTIPOINT((1 2))", $this->factory->createGeometry("POINT(1 2)", "EPSG:4326")->getAsMulti());
        $this->assertSame("MULTILINESTRING((1 2,3 4))", $this->factory->createGeometry("LINESTRING(1 2,3 4)", "EPSG:4326")->getAsMulti());
        $this->assertSame("MULTIPOLYGON(((0 0,1 0,1 1,0 0)))", $this->factory->createGeometry("POLYGON((0 0,1 0,1 1,0 0))", "EPSG:4326")->getAsMulti());
    }

    public function testConstructionRebuildsWkt(): void
    {
        $point = $this->factory->createGeometry("POINT(1 2)", "EPSG:4326");
        $point->construction();
        $this->assertSame("POINT(1 2)", $point->getWKT());

        $polygon = $this->factory->createGeometry("POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))", "EPSG:4326");
        $polygon->construction();
        $this->assertSame("POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))", $polygon->getWKT());
    }

    public function testGeometryCollection(): void
    {
        $collection = $this->factory->createGeometryCollection(["POINT(1 2)", "LINESTRING(1 2,3 4)"]);
        $this->assertInstanceOf(GeometryCollection::class, $collection);
        $arr = $collection->getGeometryArray();
        $this->assertCount(2, $arr);
        $this->assertInstanceOf(Point::class, $arr[0]);
        $this->assertInstanceOf(LineString::class, $arr[1]);
    }

    /**
     * The legacy code kept the GML indentation depth in a file-scope global; the
     * port keeps it as instance state. Rendering the same object twice must give
     * identical output — a depth leak would change the indentation.
     */
    public function testToGmlIsIdempotentOnSameInstance(): void
    {
        $g = $this->factory->createGeometry("POLYGON((0 0,10 0,10 10,0 0))", "EPSG:4326");
        $this->assertSame($g->toGML(), $g->toGML());
    }

    /**
     * The GML written by the factory (the WFS-T write path) must convert back to
     * the original WKT through GmlConverter (the read path).
     */
    public function testGmlRoundTripsBackToWkt(): void
    {
        $wkts = [
            "POINT(9.5 55.7)",
            "LINESTRING(1 2,3 4,5 6)",
            "POLYGON((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2))",
            "MULTIPOINT((1 2),(3 4))",
            "MULTILINESTRING((1 2,3 4),(5 6,7 8))",
            "MULTIPOLYGON(((0 0,10 0,10 10,0 0),(2 2,4 2,4 4,2 2)),((20 20,30 20,30 30,20 20)))",
        ];
        foreach ($wkts as $wkt) {
            $gml = $this->factory->createGeometry($wkt, "EPSG:4326")->toGML();
            $this->assertSame([$wkt], (new GmlConverter())->gmlToWKT($gml)[0], "Round trip failed for $wkt");
        }
        // Legacy quirk, preserved by the port: a MULTIPOINT round trip gains an
        // extra parenthesis level around the member list.
        $gml = $this->factory->createGeometry("MULTIPOINT(1 2,3 4)", "EPSG:4326")->toGML();
        $this->assertSame(["MULTIPOINT((1 2,3 4))"], (new GmlConverter())->gmlToWKT($gml)[0]);
    }
}
