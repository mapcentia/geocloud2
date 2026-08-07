<?php
use app\ows\Request;
use Codeception\Test\Unit;

class RequestTest extends Unit
{
    protected UnitTester $tester;

    public function testDetectsWmsFromServiceParam(): void
    {
        $r = Request::parse('GET', ['SERVICE' => 'WMS', 'LAYERS' => 'test.roads', 'REQUEST' => 'GetMap'], 'SERVICE=WMS&LAYERS=test.roads', null);
        $this->assertEquals('wms', $r->service);
        $this->assertEquals(['test.roads'], $r->layers);
    }

    public function testDetectsWfsFromService(): void
    {
        $r = Request::parse('GET', ['SERVICE' => 'WFS', 'TYPENAME' => 'test.roads'], 'SERVICE=WFS&TYPENAME=test.roads', null);
        $this->assertEquals('wfs', $r->service);
        $this->assertEquals(['test.roads'], $r->layers);
    }

    public function testDetectsUtfgridFromFormat(): void
    {
        $r = Request::parse('GET', ['SERVICE' => 'WMS', 'FORMAT' => 'mvt', 'LAYERS' => 'test.roads'], 'SERVICE=WMS&FORMAT=mvt&LAYERS=test.roads', null);
        $this->assertEquals('utfgrid', $r->service);
    }

    public function testStripsNamespaceFromLayerNames(): void
    {
        $r = Request::parse('GET', ['SERVICE' => 'WMS', 'LAYERS' => 'ns:test.roads,ns:test.rivers'], 'x', null);
        $this->assertEquals(['test.roads', 'test.rivers'], $r->layers);
    }

    public function testDecodesAndParenthesizesFilters(): void
    {
        $filters = ['test.roads' => ['type=1', 'width>2']];
        $enc = rtrim(strtr(base64_encode(json_encode($filters)), '+/', '-_'), '=');
        $r = Request::parse('GET', ['SERVICE' => 'WMS', 'LAYERS' => 'test.roads', 'filters' => $enc], 'x', null);
        $this->assertEquals(['test.roads' => ['(type=1)', '(width>2)']], $r->filters);
    }

    public function testDisableLabels(): void
    {
        $r = Request::parse('GET', ['SERVICE' => 'WMS', 'LAYERS' => 'test.roads', 'labels' => 'false'], 'x', null);
        $this->assertTrue($r->disableLabels);
        $r2 = Request::parse('GET', ['SERVICE' => 'WMS', 'LAYERS' => 'test.roads'], 'x', null);
        $this->assertFalse($r2->disableLabels);
    }

    public function testParsesLayersFromWfsPostXml(): void
    {
        $body = '<?xml version="1.0"?><wfs:GetFeature service="WFS" xmlns:wfs="http://www.opengis.net/wfs">'
              . '<wfs:Query typeName="ns:test.roads"/></wfs:GetFeature>';
        $r = Request::parse('POST', [], '', $body);
        $this->assertEquals('wfs', $r->service);
        $this->assertEquals(['test.roads'], $r->layers);
        $this->assertEquals($body, $r->rawPostBody);
    }

    public function testPostWithoutLayersThrows(): void
    {
        $this->expectException(\app\exceptions\ServiceException::class);
        Request::parse('POST', [], '', '<?xml version="1.0"?><wfs:GetFeature xmlns:wfs="http://www.opengis.net/wfs"/>');
    }

    public function testFromHttpReadsQueryFromUrlOnPost(): void
    {
        $filters = rtrim(strtr(base64_encode(json_encode(['test.roads' => ['type=1']])), '+/', '-_'), '=');
        $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $prevQs = $_SERVER['QUERY_STRING'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['QUERY_STRING'] = 'SERVICE=WFS&LAYERS=test.roads&filters=' . $filters;
        try {
            $r = \app\ows\Request::fromHttp();
            $this->assertEquals('wfs', $r->service);
            $this->assertEquals(['test.roads'], $r->layers);
            $this->assertEquals(['test.roads' => ['(type=1)']], $r->filters);
        } finally {
            if ($prevMethod === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $prevMethod;
            if ($prevQs === null) unset($_SERVER['QUERY_STRING']); else $_SERVER['QUERY_STRING'] = $prevQs;
        }
    }
}
