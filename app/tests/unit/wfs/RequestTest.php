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
}
