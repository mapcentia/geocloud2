<?php
/**
 * Unit tests for the pure tileset→layer parsing in the MapCache proxy controller. These cover the
 * per-service extraction (WMS/WMTS KVP, WMTS RESTful, TMS, Google Maps), vector-suffix stripping,
 * the "is this a tile fetch" heuristic used to fail closed, and the service-path tail extraction.
 */

use app\api\v4\controllers\Mapcache;
use Codeception\Test\Unit;

class MapcacheTilesetTest extends Unit
{
    /** @param array<string,mixed> $query keys as they arrive (upper-cased by the controller) */
    private function layers(string $service, array $segments, array $query = []): array
    {
        return Mapcache::extractLayers($service, $segments, array_change_key_case($query, CASE_UPPER));
    }

    public function testWmsKvpLayers(): void
    {
        $this->assertSame(['s.a', 's.b'], $this->layers('wms', ['wms'], ['LAYERS' => 's.a,s.b']));
    }

    public function testWmtsKvpLayer(): void
    {
        $this->assertSame(['s.a'], $this->layers('wmts', ['wmts'], ['LAYER' => 's.a']));
    }

    public function testWmtsRestfulTileset(): void
    {
        $segments = ['wmts', '1.0.0', 's.a', 'default', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('wmts', $segments));
    }

    public function testWmtsRestfulCapabilitiesHasNoTileset(): void
    {
        $segments = ['wmts', '1.0.0', 'WMTSCapabilities.xml'];
        $this->assertSame([], $this->layers('wmts', $segments));
    }

    public function testTmsTilesetStripsGrid(): void
    {
        $segments = ['tms', '1.0.0', 's.a@g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('tms', $segments));
    }

    public function testGoogleMapsTileset(): void
    {
        $segments = ['gmaps', 's.a', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('gmaps', $segments));
    }

    public function testVectorSuffixIsStripped(): void
    {
        $this->assertSame(['s.a'], $this->layers('wms', ['wms'], ['LAYERS' => 's.a.mvt']));
        $segments = ['wmts', '1.0.0', 's.a.json', 'default', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('wmts', $segments));
    }

    public function testUnqualifiedNameIsDropped(): void
    {
        // Tilesets are always "schema.table"; an unqualified name is not a resolvable layer.
        $this->assertSame([], $this->layers('wms', ['wms'], ['LAYERS' => 'justname']));
    }

    public function testUnknownServiceHasNoTileset(): void
    {
        $this->assertSame([], $this->layers('demo', ['demo'], []));
    }

    public function testLooksLikeTileFetch(): void
    {
        $this->assertTrue(Mapcache::looksLikeTileFetch([], ['REQUEST' => 'GetTile']));
        $this->assertTrue(Mapcache::looksLikeTileFetch([], ['REQUEST' => 'GetMap']));
        $this->assertTrue(Mapcache::looksLikeTileFetch(['wmts', '1.0.0', 's.a', 'default', 'g20', '8', '136', '78.png'], []));
        $this->assertFalse(Mapcache::looksLikeTileFetch(['wms'], []));
        $this->assertFalse(Mapcache::looksLikeTileFetch(['wmts', '1.0.0', 'WMTSCapabilities.xml'], []));
    }

    public function testTail(): void
    {
        $this->assertSame('wmts/1.0.0/s.a/default/g20/8/136/78.png',
            Mapcache::tail('/api/v4/mapcache/database/mydb/wmts/1.0.0/s.a/default/g20/8/136/78.png', 'mydb'));
        $this->assertSame('wms', Mapcache::tail('/api/v4/mapcache/database/mydb/wms', 'mydb'));
        $this->assertSame('', Mapcache::tail('/api/v4/mapcache/database/mydb', 'mydb'));
    }
}
