<?php
namespace app\tests\unit\ogc;

use app\exceptions\GC2Exception;
use app\ogc\Crs;
use app\ogc\ItemsParams;
use app\ogc\MapParams;
use app\ogc\Params;
use Codeception\Test\Unit;

class ParamsTest extends Unit
{
    private array $allowed = [Crs::CRS84, 'http://www.opengis.net/def/crs/EPSG/0/4326', 'http://www.opengis.net/def/crs/EPSG/0/25832'];

    private function expect400(string $errorCode, callable $fn): void
    {
        try {
            $fn();
            $this->fail("expected GC2Exception $errorCode");
        } catch (GC2Exception $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame($errorCode, $e->getErrorCode());
        }
    }

    public function testItemsDefaults(): void
    {
        $p = ItemsParams::fromQuery([], $this->allowed);
        $this->assertSame(10, $p->limit);
        $this->assertSame(0, $p->offset);
        $this->assertNull($p->bbox);
        $this->assertSame(Crs::CRS84, $p->bboxCrs);
        $this->assertSame(Crs::CRS84, $p->crs);
        $this->assertNull($p->datetime);
    }

    public function testItemsParsesEverything(): void
    {
        $p = ItemsParams::fromQuery([
            'limit' => '50', 'offset' => '100', 'bbox' => '9,55,10,56',
            'bbox-crs' => Crs::uri(4326), 'crs' => Crs::uri(25832), 'datetime' => '2024-01-01T00:00:00Z', 'f' => 'json',
        ], $this->allowed);
        $this->assertSame(50, $p->limit);
        $this->assertSame(100, $p->offset);
        $this->assertSame([9.0, 55.0, 10.0, 56.0], $p->bbox);   // as given, not swapped
        $this->assertSame(Crs::uri(4326), $p->bboxCrs);
        $this->assertSame(Crs::uri(25832), $p->crs);
        $this->assertSame('2024-01-01T00:00:00Z', $p->datetime);
    }

    public function testItemsLimitIsClampedAndValidated(): void
    {
        $this->assertSame(10000, ItemsParams::fromQuery(['limit' => '99999'], $this->allowed)->limit);
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['limit' => '0'], $this->allowed));
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['limit' => 'abc'], $this->allowed));
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['offset' => '-1'], $this->allowed));
    }

    public function testItemsRejectsUnknownParameterAndFormats(): void
    {
        $this->expect400('UNKNOWN_PARAMETER', fn() => ItemsParams::fromQuery(['foo' => 'bar'], $this->allowed));
        $this->expect400('UNSUPPORTED_FORMAT', fn() => ItemsParams::fromQuery(['f' => 'html'], $this->allowed));
    }

    public function testItemsBboxValidation(): void
    {
        $this->assertSame([9.0, 55.0, 10.0, 56.0], ItemsParams::fromQuery(['bbox' => '9,55,0,10,56,100'], $this->allowed)->bbox); // 6 numbers: z dropped
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['bbox' => '9,55,10'], $this->allowed));
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['bbox' => '9,55,10,x'], $this->allowed));
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['bbox' => '10,55,9,56'], $this->allowed)); // minx > maxx
    }

    public function testItemsCrsMustBeAllowed(): void
    {
        $this->expect400('INVALID_CRS', fn() => ItemsParams::fromQuery(['crs' => Crs::uri(3044)], $this->allowed));
        $this->expect400('INVALID_CRS', fn() => ItemsParams::fromQuery(['bbox-crs' => 'EPSG:4326'], $this->allowed));
    }

    public function testItemsDatetimeInstantOnly(): void
    {
        $this->expect400('DATETIME_INTERVAL_UNSUPPORTED', fn() => ItemsParams::fromQuery(['datetime' => '2024-01-01/2024-02-01'], $this->allowed));
        $this->expect400('INVALID_PARAMETER', fn() => ItemsParams::fromQuery(['datetime' => 'yesterday'], $this->allowed));
    }

    public function testSingleItemAllowsOnlyCrsDatetimeAndF(): void
    {
        $p = ItemsParams::fromQuery(['crs' => Crs::uri(25832)], $this->allowed, single: true);
        $this->assertSame(Crs::uri(25832), $p->crs);
        $this->expect400('UNKNOWN_PARAMETER', fn() => ItemsParams::fromQuery(['limit' => '5'], $this->allowed, single: true));
    }

    public function testMapDefaultsAndParsing(): void
    {
        $p = MapParams::fromQuery([], $this->allowed, false);
        $this->assertNull($p->bboxXY);
        $this->assertNull($p->width);
        $this->assertNull($p->height);
        $this->assertSame('png', $p->format);
        $this->assertTrue($p->transparent);
        $this->assertNull($p->bgcolor);
        $this->assertSame([], $p->collections);

        $p = MapParams::fromQuery([
            'bbox' => '55,9,56,10', 'bbox-crs' => Crs::uri(4326), 'crs' => Crs::uri(25832),
            'width' => '256', 'height' => '128', 'f' => 'jpeg', 'transparent' => 'false', 'bgcolor' => '0xFF00aa',
            'datetime' => '2024-01-01', 'collections' => 'public.a,public.b',
        ], $this->allowed, true);
        $this->assertSame([9.0, 55.0, 10.0, 56.0], $p->bboxXY);   // lat/lon input converted to x/y
        $this->assertSame(256, $p->width);
        $this->assertSame(128, $p->height);
        $this->assertSame('jpeg', $p->format);
        $this->assertFalse($p->transparent);
        $this->assertSame('FF00aa', $p->bgcolor);
        $this->assertSame(['public.a', 'public.b'], $p->collections);
    }

    public function testMapJpegDefaultsToOpaqueAndValidates(): void
    {
        $this->assertFalse(MapParams::fromQuery(['f' => 'jpeg'], $this->allowed, false)->transparent);
        $this->expect400('UNSUPPORTED_FORMAT', fn() => MapParams::fromQuery(['f' => 'gif'], $this->allowed, false));
        $this->expect400('INVALID_PARAMETER', fn() => MapParams::fromQuery(['width' => '99999'], $this->allowed, false));
        $this->expect400('INVALID_PARAMETER', fn() => MapParams::fromQuery(['bgcolor' => 'red'], $this->allowed, false));
        $this->expect400('UNKNOWN_PARAMETER', fn() => MapParams::fromQuery(['collections' => 'a.b'], $this->allowed, false)); // only on /map
        $this->expect400('INVALID_PARAMETER', fn() => MapParams::fromQuery([], $this->allowed, true)); // /map needs collections
    }

    public function testAssertJson(): void
    {
        Params::assertJson([]);
        Params::assertJson(['f' => 'json']);
        $this->expect400('UNSUPPORTED_FORMAT', fn() => Params::assertJson(['f' => 'xml']));
    }
}
