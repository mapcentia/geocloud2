<?php
namespace app\tests\unit\wfs;

use app\wfs\Request;
use Codeception\Test\Unit;

class RequestTest extends Unit
{
    public function testConstructorAssignsAllFields(): void
    {
        $req = new Request(
            operation: 'GETFEATURE',
            version: '1.1.0',
            service: 'WFS',
            outputFormat: 'GML3',
            typeNames: ['mytable'],
            properties: null,
            featureIds: null,
            bbox: null,
            resultType: null,
            srsName: 'EPSG:4326',
            srs: 4326,
            maxFeatures: 100,
            timeSlice: null,
            filter: null,
            transactionBody: null,
            rawPostBody: null,
        );
        $this->assertSame('GETFEATURE', $req->operation);
        $this->assertSame('1.1.0', $req->version);
        $this->assertSame(['mytable'], $req->typeNames);
        $this->assertSame(4326, $req->srs);
    }

    public function testFromHttpGetWithMinimalParams(): void
    {
        $_GET = [
            'SERVICE' => 'WFS',
            'VERSION' => '1.1.0',
            'REQUEST' => 'GetCapabilities',
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $ctx = $this->makeContext();
        $req = Request::fromHttp($ctx, rawBody: '');

        $this->assertSame('GETCAPABILITIES', $req->operation);
        $this->assertSame('1.1.0', $req->version);
        $this->assertSame('WFS', $req->service);
        $this->assertNull($req->typeNames);
    }

    public function testFromHttpGetWithTypeAndBbox(): void
    {
        $_GET = [
            'SERVICE'     => 'WFS',
            'VERSION'     => '1.1.0',
            'REQUEST'     => 'GetFeature',
            'TYPENAME'    => 'mytable,other',
            'BBOX'        => '0,0,10,10,EPSG:4326',
            'MAXFEATURES' => '50',
            'SRSNAME'     => 'EPSG:4326',
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $req = Request::fromHttp($this->makeContext(), rawBody: '');

        $this->assertSame('GETFEATURE', $req->operation);
        $this->assertSame(['mytable', 'other'], $req->typeNames);
        $this->assertSame(['0', '0', '10', '10', 'EPSG:4326'], $req->bbox);
        $this->assertSame(50, $req->maxFeatures);
        $this->assertSame(4326, $req->srs);
    }

    private function makeContext(): \app\wfs\Context
    {
        return new \app\wfs\Context(
            connection: new \app\inc\Connection(database: 'mydb'),
            database: 'mydb', schema: 'public', user: 'alice',
            parentUser: false, trusted: true,
            host: 'http://example.com', thePath: 'http://example.com/wfs/mydb/public',
            startTime: 0.0,
        );
    }
}
