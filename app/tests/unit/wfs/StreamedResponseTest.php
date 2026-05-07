<?php
namespace app\tests\unit\wfs;

use app\api\v4\Responses\StreamedResponse;
use Codeception\Test\Unit;

class StreamedResponseTest extends Unit
{
    public function testGetDataReturnsNull(): void
    {
        $r = new StreamedResponse('text/xml', fn() => null);
        $this->assertNull($r->getData());
    }

    public function testStatusDefaultsTo200(): void
    {
        $r = new StreamedResponse('text/xml', fn() => null);
        $this->assertSame(200, $r->getStatus());
    }

    public function testContentTypeAndCallbackAccessible(): void
    {
        $cb = fn() => null;
        $r = new StreamedResponse('application/gml+xml', $cb, 201);
        $this->assertSame('application/gml+xml', $r->contentType);
        $this->assertSame($cb, $r->callback);
        $this->assertSame(201, $r->getStatus());
    }
}
