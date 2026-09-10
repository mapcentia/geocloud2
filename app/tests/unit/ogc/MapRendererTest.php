<?php
namespace app\tests\unit\ogc;

use app\ogc\MapRenderer;
use Codeception\Test\Unit;

class MapRendererTest extends Unit
{
    public function testDefaultWidthAndAspectHeight(): void
    {
        // bbox 2 wide, 1 high → 1024 x 512
        $this->assertSame([1024, 512], MapRenderer::size(null, null, [0.0, 0.0, 2.0, 1.0]));
    }

    public function testHeightFromWidth(): void
    {
        $this->assertSame([300, 600], MapRenderer::size(300, null, [0.0, 0.0, 1.0, 2.0]));
    }

    public function testWidthFromHeight(): void
    {
        $this->assertSame([400, 200], MapRenderer::size(null, 200, [10.0, 10.0, 14.0, 12.0]));
    }

    public function testBothGivenAreKept(): void
    {
        $this->assertSame([64, 64], MapRenderer::size(64, 64, [0.0, 0.0, 9.0, 1.0]));
    }

    public function testClampedToMaxAndMin(): void
    {
        $this->assertSame([16384, 16384], MapRenderer::size(16384, null, [0.0, 0.0, 1.0, 100.0]));
        $this->assertSame([1, 1], MapRenderer::size(1, null, [0.0, 0.0, 100.0, 0.0001]));
    }
}
