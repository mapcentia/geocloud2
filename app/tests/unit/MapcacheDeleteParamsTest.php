<?php
/**
 * Unit tests for the bbox/zoom query-parameter parsing of the MapCache tileset-delete controller.
 * These map directly to mapcache_seed's -e (extent) and -z (zoom range) flags, so malformed input
 * must be rejected (a silently-dropped scope would turn a small invalidation into a full-tileset
 * delete).
 */

use app\api\v4\controllers\MapcacheTileset;
use app\exceptions\GC2Exception;
use Codeception\Test\Unit;

class MapcacheDeleteParamsTest extends Unit
{
    public function testBboxAbsentIsNull(): void
    {
        $this->assertNull(MapcacheTileset::parseBbox(null));
        $this->assertNull(MapcacheTileset::parseBbox(''));
    }

    public function testBboxValid(): void
    {
        $this->assertSame('890000,7260000,1730000,7870000',
            MapcacheTileset::parseBbox('890000,7260000,1730000,7870000'));
        $this->assertSame('1.5,2,3,4.25', MapcacheTileset::parseBbox('1.5,2,3,4.25'));
    }

    public function testBboxWrongLengthThrows(): void
    {
        $this->expectException(GC2Exception::class);
        MapcacheTileset::parseBbox('1,2,3');
    }

    public function testBboxNonNumericThrows(): void
    {
        $this->expectException(GC2Exception::class);
        MapcacheTileset::parseBbox('a,b,c,d');
    }

    public function testZoomAbsentIsNull(): void
    {
        $this->assertNull(MapcacheTileset::parseZoom(null));
        $this->assertNull(MapcacheTileset::parseZoom(''));
    }

    public function testZoomValid(): void
    {
        $this->assertSame('5,6', MapcacheTileset::parseZoom('5,6'));
        $this->assertSame('7', MapcacheTileset::parseZoom('7'));
    }

    public function testZoomTooManyThrows(): void
    {
        $this->expectException(GC2Exception::class);
        MapcacheTileset::parseZoom('5,6,7');
    }

    public function testZoomNonIntegerThrows(): void
    {
        $this->expectException(GC2Exception::class);
        MapcacheTileset::parseZoom('5.5');
    }
}
