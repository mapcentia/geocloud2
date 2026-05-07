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

    public function testDropNameSpaceStripsNonWhitelistedAttributes(): void
    {
        // Whitelisted: service, version, typeName, srsName, fid, id, outputFormat,
        // maxFeatures, resultType — kept. Others — removed.
        $in  = '<wfs:GetFeature service="WFS" version="1.0.0" foo="bar" xmlns:wfs="http://x">';
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringContainsString('service="WFS"', $out);
        $this->assertStringContainsString('version="1.0.0"', $out);
        $this->assertStringNotContainsString('foo="bar"', $out);
        $this->assertStringNotContainsString('xmlns:wfs', $out);
    }

    public function testDropNameSpaceStripsElementPrefixesExceptGml(): void
    {
        // Non-gml prefixes on opening/closing tags are stripped.
        $in  = '<wfs:Query><gml:pos>1 2</gml:pos></wfs:Query>';
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringContainsString('<gml:pos>', $out);
        $this->assertStringContainsString('</gml:pos>', $out);
        $this->assertStringNotContainsString('<wfs:Query>', $out);
        $this->assertStringNotContainsString('</wfs:Query>', $out);
    }

    public function testDropNameSpaceHandlesSingleQuotedAttributes(): void
    {
        // Legacy regex uses (\".*?\"|\'.*?\') — so single-quoted attribute
        // values must be stripped just like double-quoted ones.
        $in  = "<root foo='bar' xmlns:wfs='http://x'>";
        $out = NameSpaces::dropNameSpace($in);
        $this->assertStringNotContainsString("foo='bar'", $out);
        $this->assertStringNotContainsString('xmlns:wfs', $out);
    }

    public function testDropAllNameSpacesStripsPrefix(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('wfs:Filter'));
    }

    public function testDropAllNameSpacesStripsAllPrefixSegments(): void
    {
        // Legacy uses /[\w-]*:/ globally, so multiple "prefix:" segments are removed.
        $this->assertSame('bar', NameSpaces::dropAllNameSpaces('ns:foo:bar'));
    }

    public function testDropAllNameSpacesTrimsOpenLayersDoubleQuotes(): void
    {
        // OpenLayers wraps ogc:PropertyName values in double quotes
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('"wfs:Filter"'));
        $this->assertSame('foo', NameSpaces::dropAllNameSpaces('"foo"'));
    }

    public function testDropAllNameSpacesPassesThroughUnprefixed(): void
    {
        $this->assertSame('Filter', NameSpaces::dropAllNameSpaces('Filter'));
    }
}
