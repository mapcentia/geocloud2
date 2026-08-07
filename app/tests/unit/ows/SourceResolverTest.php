<?php
use app\ows\SourceResolver;
use Codeception\Test\Unit;

class SourceResolverTest extends Unit
{
    protected UnitTester $tester;

    public function testParseQgsPathExtractsMapParam(): void
    {
        $conn = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=/data/projects/my.qgs&LAYERS=x";
        $this->assertEquals('/data/projects/my.qgs', SourceResolver::parseQgsPath($conn));
    }

    public function testParseQgsPathReturnsNullForNonQgs(): void
    {
        $this->assertNull(SourceResolver::parseQgsPath("http://example.com/wms?map=/data/foo.map"));
        $this->assertNull(SourceResolver::parseQgsPath(null));
        $this->assertNull(SourceResolver::parseQgsPath("the_geom FROM roads"));
    }

    public function testParseWmsSourceReturnsPartsWithUpperCaseQuery(): void
    {
        $conn = "https://user:pass@example.com/geoserver/wms?service=wms&version=1.3.0";
        $src = SourceResolver::parseWmsSource($conn);
        $this->assertEquals('https', $src['scheme']);
        $this->assertEquals('example.com', $src['host']);
        $this->assertEquals('user', $src['user']);
        $this->assertArrayHasKey('VERSION', $src['query']);
        $this->assertEquals('1.3.0', $src['query']['VERSION']);
    }

    public function testParseWmsSourceReturnsNullForNonHttp(): void
    {
        $this->assertNull(SourceResolver::parseWmsSource("the_geom FROM (SELECT ...) as foo"));
        $this->assertNull(SourceResolver::parseWmsSource(null));
    }
}
