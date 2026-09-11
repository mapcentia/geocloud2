<?php
namespace app\tests\unit\ows;

use app\ows\Proxy;
use Codeception\Test\Unit;

class ProxyBackendErrorTest extends Unit
{
    public function testIsExpectedContentTypeMatchesPrefixCaseInsensitively(): void
    {
        $this->assertTrue(Proxy::isExpectedContentType('image/png', 'image/'));
        $this->assertTrue(Proxy::isExpectedContentType('Image/JPEG; charset=binary', 'image/'));
        $this->assertFalse(Proxy::isExpectedContentType('text/html; charset=UTF-8', 'image/'));
        $this->assertFalse(Proxy::isExpectedContentType('application/vnd.ogc.se_xml', 'image/'));
        $this->assertTrue(Proxy::isExpectedContentType('text/html', null));
    }

    public function testBackendErrorMessageStripsMapServerHtml(): void
    {
        $html = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 3.2//EN\">\n<HTML><HEAD><TITLE>MapServer Message</TITLE></HEAD><BODY BGCOLOR=\"#FFFFFF\">\nmsLoadMap(): Unable to access file. (/var/www/geocloud2/app/wms/mapfiles/mydb_dagi_wms.map)\n</BODY></HTML>";
        $this->assertSame(
            'MapServer Message msLoadMap(): Unable to access file. (/var/www/geocloud2/app/wms/mapfiles/mydb_dagi_wms.map)',
            Proxy::backendErrorMessage($html)
        );
    }

    public function testBackendErrorMessageStripsServiceExceptionXml(): void
    {
        $xml = "<?xml version='1.0' encoding=\"UTF-8\" standalone=\"no\" ?>\n<ServiceExceptionReport version=\"1.3.0\"><ServiceException code=\"InvalidCRS\">msWMSLoadGetMapParams(): WMS server error. Invalid CRS given.</ServiceException></ServiceExceptionReport>";
        $this->assertSame('msWMSLoadGetMapParams(): WMS server error. Invalid CRS given.', Proxy::backendErrorMessage($xml));
    }

    public function testBackendErrorMessageTruncatesAndFallsBack(): void
    {
        $this->assertSame('Map backend returned no body', Proxy::backendErrorMessage("  \n "));
        $this->assertSame(500, strlen(Proxy::backendErrorMessage(str_repeat('x', 900))));
    }
}
