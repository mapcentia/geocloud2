<?php
namespace app\tests\unit\ogc;

use app\ogc\Crs;
use Codeception\Test\Unit;

class CrsTest extends Unit
{
    public function testEpsgFromUris(): void
    {
        $this->assertSame(4326, Crs::epsg(Crs::CRS84));
        $this->assertSame(4326, Crs::epsg('http://www.opengis.net/def/crs/EPSG/0/4326'));
        $this->assertSame(25832, Crs::epsg('http://www.opengis.net/def/crs/EPSG/0/25832'));
        $this->assertNull(Crs::epsg('EPSG:25832'));
        $this->assertNull(Crs::epsg('http://www.opengis.net/def/crs/EPSG/0/abc'));
    }

    public function testUriRoundTrip(): void
    {
        $this->assertSame('http://www.opengis.net/def/crs/EPSG/0/3857', Crs::uri(3857));
        $this->assertSame(3857, Crs::epsg(Crs::uri(3857)));
    }

    public function testAxisOrder(): void
    {
        $this->assertFalse(Crs::isLatLon(Crs::CRS84));
        $this->assertTrue(Crs::isLatLon(Crs::uri(4326)));
        $this->assertFalse(Crs::isLatLon(Crs::uri(25832)));
    }

    public function testToXYSwapsOnlyLatLon(): void
    {
        $this->assertSame([9.0, 55.0, 10.0, 56.0], Crs::toXY([55.0, 9.0, 56.0, 10.0], Crs::uri(4326)));
        $this->assertSame([9.0, 55.0, 10.0, 56.0], Crs::toXY([9.0, 55.0, 10.0, 56.0], Crs::CRS84));
    }

    public function testWfsBboxCrs(): void
    {
        $this->assertSame('EPSG:4326', Crs::wfsBboxCrs(Crs::CRS84));                    // longitude first in WfsFilter
        $this->assertSame('urn:ogc:def:crs:EPSG::4326', Crs::wfsBboxCrs(Crs::uri(4326))); // latitude first in WfsFilter
        $this->assertSame('EPSG:25832', Crs::wfsBboxCrs(Crs::uri(25832)));
    }

    public function testWmsCrsAndBbox(): void
    {
        $this->assertSame('EPSG:4326', Crs::wmsCrs(Crs::CRS84));
        $this->assertSame('EPSG:25832', Crs::wmsCrs(Crs::uri(25832)));
        // WMS 1.3.0 + EPSG:4326 is lat/lon regardless of how the client addressed it
        $this->assertSame('55,9,56,10', Crs::wmsBbox([9.0, 55.0, 10.0, 56.0], Crs::CRS84));
        $this->assertSame('55,9,56,10', Crs::wmsBbox([9.0, 55.0, 10.0, 56.0], Crs::uri(4326)));
        $this->assertSame('500000,6100000,510000.5,6110000', Crs::wmsBbox([500000.0, 6100000.0, 510000.5, 6110000.0], Crs::uri(25832)));
    }

    public function testNumFormatting(): void
    {
        $this->assertSame('10', Crs::num(10.0));
        $this->assertSame('-0.5', Crs::num(-0.5));
        $this->assertSame('9.123456789', Crs::num(9.123456789));
        $this->assertSame('0', Crs::num(0.0));
    }
}
