<?php
namespace app\tests\unit\wfs;

use app\wfs\helpers\NameSpaces;
use Codeception\Test\Unit;

class NameSpacesTest extends Unit
{
    public function testDropLastChrs(): void
    {
        $this->assertSame('hell', NameSpaces::dropLastChrs('hello', 1));
        $this->assertSame('', NameSpaces::dropLastChrs('hi', 2));
    }

    public function testDropFirstChrs(): void
    {
        $this->assertSame('llo', NameSpaces::dropFirstChrs('hello', 2));
    }

    public function testDropNameSpace(): void
    {
        // Strip xmlns:foo="..." attributes; keep prefix on element names.
        $in  = '<wfs:Filter xmlns:wfs="http://x"><PropertyName>a</PropertyName></wfs:Filter>';
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringNotContainsString('xmlns:wfs', $out);
        $this->assertStringContainsString('<wfs:Filter>', $out);
    }

    public function testDropAllNameSpaces(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('wfs:Filter'));
        $this->assertSame('foo,bar', NameSpaces::dropAllNameSpaces('ns:foo,ns:bar'));
    }
}
