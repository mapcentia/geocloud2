<?php
use app\ows\SourceResolver;
use Codeception\Test\Unit;

class SourceResolverTest extends Unit
{
    protected UnitTester $tester;

    public function testPickWmssourceSkipsTheNonSpatialRow(): void
    {
        $qgs = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=/data/projects/my.qgs&LAYERS=x";
        // Ordered by _key_, the non-spatial row comes before "the_geom"
        $rows = [
            ['_key_' => 's.t.gc2_non_postgis', 'wmssource' => null],
            ['_key_' => 's.t.the_geom', 'wmssource' => $qgs],
        ];
        $this->assertSame($qgs, SourceResolver::pickWmssource($rows));
        // ... also when the geometry row sorts first
        $this->assertSame($qgs, SourceResolver::pickWmssource([
            ['_key_' => 's.t.a_geom', 'wmssource' => $qgs],
            ['_key_' => 's.t.gc2_non_postgis', 'wmssource' => null],
        ]));
    }

    public function testPickWmssourceSkipsTheRowKeyedWithoutAColumn(): void
    {
        $qgs = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=/data/projects/my.qgs&LAYERS=x";
        // "s.t" sorts before "s.t.the_geom"
        $this->assertSame($qgs, SourceResolver::pickWmssource([
            ['_key_' => 's.t', 'wmssource' => null],
            ['_key_' => 's.t.the_geom', 'wmssource' => $qgs],
        ]));
        $this->assertSame($qgs, SourceResolver::pickWmssource([
            ['_key_' => 's.t', 'wmssource' => ''],
            ['_key_' => 's.t.gc2_non_postgis', 'wmssource' => null],
            ['_key_' => 's.t.the_geom', 'wmssource' => $qgs],
        ]));
        // Only such a row: it is all there is
        $this->assertSame('x', SourceResolver::pickWmssource([['_key_' => 's.t', 'wmssource' => 'x']]));
    }

    public function testPickWmssourceKeepsFormerChoiceOtherwise(): void
    {
        // An ordinary layer: no source
        $this->assertNull(SourceResolver::pickWmssource([
            ['_key_' => 's.t.gc2_non_postgis', 'wmssource' => null],
            ['_key_' => 's.t.the_geom', 'wmssource' => null],
        ]));
        // Two geometry columns: the first by _key_, as before
        $this->assertSame('a', SourceResolver::pickWmssource([
            ['_key_' => 's.t.geom_a', 'wmssource' => 'a'],
            ['_key_' => 's.t.geom_b', 'wmssource' => 'b'],
        ]));
        // A table registered without geometry only
        $this->assertSame('x', SourceResolver::pickWmssource([['_key_' => 's.t.gc2_non_postgis', 'wmssource' => 'x']]));
        // Unknown layer
        $this->assertNull(SourceResolver::pickWmssource([]));
    }

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
