<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\RangeNotSatisfiable;
use app\inc\snapshot\RangeRequest;
use Codeception\Test\Unit;

class RangeRequestTest extends Unit
{
    protected UnitTester $tester;

    /** @return array<string, array{?string, int, ?array{int,int}}> */
    public static function satisfiable(): array
    {
        return [
            'no header' => [null, 100, null],
            'empty header' => ['', 100, null],
            'closed' => ['bytes=0-3', 100, [0, 3]],
            'open end' => ['bytes=90-', 100, [90, 99]],
            'suffix' => ['bytes=-10', 100, [90, 99]],
            'suffix larger than file' => ['bytes=-500', 100, [0, 99]],
            'end clamped' => ['bytes=95-200', 100, [95, 99]],
            'single last byte' => ['bytes=99-99', 100, [99, 99]],
            'uppercase unit' => ['BYTES=0-3', 100, [0, 3]],
            'mixed case unit' => ['Bytes=90-', 100, [90, 99]],
            'multiple ranges ignored' => ['bytes=0-1,5-6', 100, null],
            'other unit ignored' => ['items=0-1', 100, null],
            'garbage ignored' => ['bytes=abc', 100, null],
        ];
    }

    /** @dataProvider satisfiable */
    public function testParse(?string $header, int $size, ?array $expected): void
    {
        $r = RangeRequest::parse($header, $size);
        if ($expected === null) {
            $this->assertNull($r);
            return;
        }
        $this->assertSame($expected, [$r->start, $r->end]);
        $this->assertSame($expected[1] - $expected[0] + 1, $r->length());
        $this->assertSame("bytes {$expected[0]}-{$expected[1]}/$size", $r->contentRange($size));
    }

    /** @return array<string, array{string, int}> */
    public static function unsatisfiable(): array
    {
        return [
            'start beyond size' => ['bytes=100-', 100],
            'start beyond end' => ['bytes=5-3', 100],
            'empty suffix' => ['bytes=-0', 100],
            'empty file' => ['bytes=0-0', 0],
            'both empty' => ['bytes=-', 100],
        ];
    }

    /** @dataProvider unsatisfiable */
    public function testUnsatisfiableThrows(string $header, int $size): void
    {
        try {
            RangeRequest::parse($header, $size);
            $this->fail('expected RangeNotSatisfiable');
        } catch (RangeNotSatisfiable $e) {
            $this->assertSame($size, $e->size);
        }
    }
}
