<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Route2;
use Codeception\Test\Unit;

class Route2MatchTest extends Unit
{
    private const string WFS_NO_TOKEN = 'api/v4/wfs/schema/{schema}/database/{database}/srs/[srs]/ts/[timeSlice]';
    private const string WFS = 'api/v4/wfs/schema/{schema}/srs/[srs]/[timeSlice]';

    public function testMatchesWithAllOptionalSegments(): void
    {
        $match = Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb/srs/4326/ts/12.00.00');
        $this->assertNotNull($match);
        $this->assertEquals([
            'schema' => 'public',
            'database' => 'mydb',
            'srs' => '4326',
            'timeSlice' => '12.00.00',
        ], $match['params']);
    }

    public function testMatchesWithoutOptionalSegments(): void
    {
        $match = Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb');
        $this->assertNotNull($match);
        $this->assertEquals(['schema' => 'public', 'database' => 'mydb'], $match['params']);
    }

    public function testMatchesWithOnlyFirstOptionalSegment(): void
    {
        $match = Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb/srs/4326');
        $this->assertNotNull($match);
        $this->assertEquals(['schema' => 'public', 'database' => 'mydb', 'srs' => '4326'], $match['params']);
    }

    public function testMatchesWithOptionalLabelButNoValue(): void
    {
        $match = Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb/srs/4326/ts');
        $this->assertNotNull($match);
        $this->assertEquals(['schema' => 'public', 'database' => 'mydb', 'srs' => '4326'], $match['params']);
    }

    public function testMissesWhenRequiredParamIsAbsent(): void
    {
        $this->assertNull(Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database'));
        $this->assertNull(Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public'));
    }

    public function testMissesOnLiteralMismatch(): void
    {
        $this->assertNull(Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb/foo/4326'));
        $this->assertNull(Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wms/schema/public/database/mydb'));
    }

    public function testMissesWhenRequestIsLongerThanRoute(): void
    {
        $this->assertNull(Route2::matchSignature(self::WFS_NO_TOKEN, 'api/v4/wfs/schema/public/database/mydb/srs/4326/ts/12.00.00/extra'));
    }

    public function testMatchesTrailingOptionalsWithoutLabels(): void
    {
        $match = Route2::matchSignature(self::WFS, 'api/v4/wfs/schema/public');
        $this->assertNotNull($match);
        $this->assertEquals(['schema' => 'public'], $match['params']);

        $match = Route2::matchSignature(self::WFS, 'api/v4/wfs/schema/public/srs/4326');
        $this->assertNotNull($match);
        $this->assertEquals(['schema' => 'public', 'srs' => '4326'], $match['params']);
    }

    public function testActionSegment(): void
    {
        $match = Route2::matchSignature('api/v4/user/(action)', 'api/v4/user/refresh');
        $this->assertNotNull($match);
        $this->assertEquals('refresh', $match['action']);

        $match = Route2::matchSignature('api/v4/user/(action)', 'api/v4/user');
        $this->assertNotNull($match);
        $this->assertEquals('index', $match['action']);
    }

    private const string TABLE = 'api/v4/schemas/{schema}/tables/[table]';
    private const string COLUMN = 'api/v4/schemas/{schema}/tables/{table}/columns/[column]';
    private const string SCHEMA = 'api/v4/schemas/[schema]';

    // A request that exactly fills a route has omitted=0; a parent request that
    // matches a child route only by omitting its optional tail has omitted>0.
    public function testOmittedCountsUnfilledTrailingSegments(): void
    {
        $req = 'api/v4/schemas/dagi/tables/dagi_kommune10';
        $this->assertSame(0, Route2::matchSignature(self::TABLE, $req)['omitted']);
        $this->assertSame(2, Route2::matchSignature(self::COLUMN, $req)['omitted']); // columns/[column] omitted
    }

    // Parent/child shadowing: .../tables/{t} matches both Table and Column, but the
    // most-specific (Table, omitted 0) must be dispatched first — even though Column
    // sorts earlier alphabetically in the controller scan order.
    public function testOrderBySpecificityPrefersParentOverChild(): void
    {
        $routes = [
            'Column' => new class { public function getRoute(): string { return Route2MatchTest::COLUMN_ROUTE; } },
            'Table'  => new class { public function getRoute(): string { return Route2MatchTest::TABLE_ROUTE; } },
        ];
        $ordered = Route2::orderBySpecificity($routes, 'api/v4/schemas/dagi/tables/dagi_kommune10');
        $this->assertSame(['Table', 'Column'], array_keys($ordered));
    }

    public function testOrderBySpecificityPrefersSchemaOverTable(): void
    {
        $routes = [
            'Table'  => new class { public function getRoute(): string { return Route2MatchTest::TABLE_ROUTE; } },
            'Schema' => new class { public function getRoute(): string { return Route2MatchTest::SCHEMA_ROUTE; } },
        ];
        $ordered = Route2::orderBySpecificity($routes, 'api/v4/schemas/dagi');
        $this->assertSame(['Schema', 'Table'], array_keys($ordered));
    }

    // Non-matching candidates sink to the end (they are no-ops when dispatched).
    public function testOrderBySpecificityKeepsNonMatchesLast(): void
    {
        $routes = [
            'Schema' => new class { public function getRoute(): string { return Route2MatchTest::SCHEMA_ROUTE; } },
            'Table'  => new class { public function getRoute(): string { return Route2MatchTest::TABLE_ROUTE; } },
        ];
        // Request only matches Table; Schema is longer than the request -> no match.
        $ordered = Route2::orderBySpecificity($routes, 'api/v4/schemas/dagi/tables/dagi_kommune10');
        $this->assertSame('Table', array_key_first($ordered));
    }

    // Public constants so the anonymous route stubs above can reach them.
    public const string TABLE_ROUTE = self::TABLE;
    public const string COLUMN_ROUTE = self::COLUMN;
    public const string SCHEMA_ROUTE = self::SCHEMA;
}
