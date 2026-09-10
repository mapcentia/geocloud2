<?php
namespace app\tests\unit\ogc;

use app\inc\Route2;
use Codeception\Test\Unit;

/** The three OGC routes must not shadow each other or the other api/v4 routes. */
class RouteTest extends Unit
{
    private const string OGC = 'api/v4/ogc/database/{database}/[p1]/[p2]';
    private const string ITEMS = 'api/v4/ogc/database/{database}/collections/{collection}/items/[feature]';
    private const string MAP = 'api/v4/ogc/database/{database}/collections/{collection}/map';

    public function testLandingConformanceCollectionsAndMapHitOgc(): void
    {
        $this->assertSame(['database' => 'db'], Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db')['params']);
        $this->assertSame('conformance', Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/conformance')['params']['p1']);
        $m = Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/collections/s.t');
        $this->assertSame('collections', $m['params']['p1']);
        $this->assertSame('s.t', $m['params']['p2']);
        $this->assertSame('map', Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/map')['params']['p1']);
    }

    public function testItemsAndMapDoNotHitOgc(): void
    {
        $this->assertNull(Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/collections/s.t/items'));
        $this->assertNull(Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/collections/s.t/items/1'));
        $this->assertNull(Route2::matchSignature(self::OGC, 'api/v4/ogc/database/db/collections/s.t/map'));
    }

    public function testItemsRoute(): void
    {
        $m = Route2::matchSignature(self::ITEMS, 'api/v4/ogc/database/db/collections/s.t/items');
        $this->assertSame(['database' => 'db', 'collection' => 's.t'], $m['params']);
        $m = Route2::matchSignature(self::ITEMS, 'api/v4/ogc/database/db/collections/s.t/items/42');
        $this->assertSame('42', $m['params']['feature']);
        $this->assertNull(Route2::matchSignature(self::ITEMS, 'api/v4/ogc/database/db/collections/s.t/map'));
        // A bare collection URL also structurally fits ITEMS by omitting its optional
        // "items/[feature]" tail (same parent/child shadow shape as Table vs Column, see
        // Route2MatchTest::testOmittedCountsUnfilledTrailingSegments) -- matchSignature is
        // non-null here by design. OGC's exact fit (omitted 0) beats it via
        // Route2::orderBySpecificity, which is how the dispatcher resolves the shadow.
        $bareCollection = 'api/v4/ogc/database/db/collections/s.t';
        $itemsMatch = Route2::matchSignature(self::ITEMS, $bareCollection);
        $this->assertSame(2, $itemsMatch['omitted']);
        $this->assertSame(0, Route2::matchSignature(self::OGC, $bareCollection)['omitted']);
    }

    public function testMapRoute(): void
    {
        $m = Route2::matchSignature(self::MAP, 'api/v4/ogc/database/db/collections/s.t/map');
        $this->assertSame(['database' => 'db', 'collection' => 's.t'], $m['params']);
        $this->assertNull(Route2::matchSignature(self::MAP, 'api/v4/ogc/database/db/collections/s.t/items'));
        $this->assertNull(Route2::matchSignature(self::MAP, 'api/v4/ogc/database/db/collections/s.t'));
    }

    public function testOgcDoesNotShadowOtherV4Routes(): void
    {
        $this->assertNull(Route2::matchSignature(self::OGC, 'api/v4/ows/schema/s/database/db'));
        $this->assertNull(Route2::matchSignature(self::OGC, 'api/v4/schemas/s/tables/t/features/1'));
    }
}
