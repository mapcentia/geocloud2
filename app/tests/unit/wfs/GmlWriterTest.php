<?php
namespace app\tests\unit\wfs;

use app\wfs\output\GmlWriter;
use Codeception\Test\Unit;

class GmlWriterTest extends Unit
{
    private function newWriter(): GmlWriter
    {
        return new GmlWriter(
            gmlNameSpace: 'public',
            gmlNameSpaceUri: 'http://example.com/mydb/public',
        );
    }

    public function testBufferAccumulatesUntilFlush(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->write('<a>1</a>');
        $w->write('<b>2</b>');
        // No output yet — verify by capturing stdout
        ob_start();
        $w->bufferFlush();
        $out = ob_get_clean();
        $this->assertSame('<a>1</a><b>2</b>', $out);
    }

    public function testWriteTagOpenWithNamespace(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('open', 'gml', 'featureMembers', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<gml:featureMembers>', ob_get_clean());
    }

    public function testWriteTagWithAttributes(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('open', null, 'feature', ['id' => '42', 'srs' => 'EPSG:4326'], false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<feature id="42" srs="EPSG:4326">', ob_get_clean());
    }

    public function testWriteTagSelfClose(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('selfclose', null, 'br', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('<br/>', ob_get_clean());
    }

    public function testWriteTagClose(): void
    {
        $w = $this->newWriter();
        $w->bufferStart();
        $w->writeTag('close', 'wfs', 'FeatureCollection', null, false);
        ob_start();
        $w->bufferFlush();
        $this->assertSame('</wfs:FeatureCollection>', ob_get_clean());
    }
}
