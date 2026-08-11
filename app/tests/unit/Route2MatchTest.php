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
}
