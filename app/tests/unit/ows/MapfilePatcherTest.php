<?php
use app\exceptions\ServiceException;
use app\ows\MapfilePatcher;
use Codeception\Test\Unit;

class MapfilePatcherTest extends Unit
{
    protected UnitTester $tester;

    private function map(): string { return file_get_contents(__DIR__ . '/_fixtures/sample.map'); }
    private function qgs(): string { return file_get_contents(__DIR__ . '/_fixtures/sample.qgs'); }

    public function testFilterReplacesMapfileMarker(): void
    {
        $out = MapfilePatcher::patchMapfileContent(
            $this->map(), ['test.roads' => ['(type=1)', '(width>2)']], false, ['test.roads']
        );
        $this->assertStringContainsString('WHERE (type=1) AND (width>2)', $out);
        $this->assertStringNotContainsString('/*FILTER_test.roads*/', $out);
    }

    public function testDisableLabelsRemovesAllNumberedLabelBlocks(): void
    {
        $out = MapfilePatcher::patchMapfileContent($this->map(), [], true, ['test.roads']);
        $this->assertStringNotContainsString('#START_LABEL1_test.roads', $out);
        $this->assertStringNotContainsString('#START_LABEL2_test.roads', $out);
        $this->assertStringNotContainsString('TEXT "[name]"', $out);
        $this->assertStringNotContainsString('TEXT "[ref]"', $out);
        // The DATA line and layer survive
        $this->assertStringContainsString('NAME "test.roads"', $out);
    }

    public function testNoFilterLeavesMarkerUntouched(): void
    {
        $out = MapfilePatcher::patchMapfileContent($this->map(), [], false, ['test.roads']);
        $this->assertStringContainsString('/*FILTER_test.roads*/', $out);
    }

    public function testQgsFilterAndLabels(): void
    {
        $out = MapfilePatcher::patchQgsContent(
            $this->qgs(), ['test.roads' => ['(type=1)']], true, ['test.roads']
        );
        $this->assertStringContainsString('sql=(type=1)<', $out);
        $this->assertStringContainsString('labelsEnabled="0"', $out);
        $this->assertStringNotContainsString('labelsEnabled="1"', $out);
    }

    public function testXmlEscape(): void
    {
        $this->assertEquals("a&amp;b&apos;c", MapfilePatcher::xmlEscape("a&b'c"));
    }

    public function testQgsFilterEscapesSpecialCharsWithoutBackslash(): void
    {
        $out = MapfilePatcher::patchQgsContent(
            $this->qgs(), ['test.roads' => ["name = 'a&b'"]], false, ['test.roads']
        );
        $this->assertStringContainsString("sql=name = &apos;a&amp;b&apos;<", $out);
        $this->assertStringNotContainsString('\\&', $out);
    }

    // Regression: $ and \ in a filter value must not be expanded as a preg_replace
    // backreference in the replacement string (IMPORTANT 3).
    public function testQgsFilterWithDollarAndBackslashIsNotExpandedAsBackreference(): void
    {
        $out = MapfilePatcher::patchQgsContent(
            $this->qgs(), ['test.roads' => ["col = 'a\$1b\\c'"]], false, ['test.roads']
        );
        $this->assertStringContainsString("sql=col = &apos;a\$1b\\c&apos;<", $out);
        // No stray/duplicated backreference expansion or extra backslashes
        $this->assertStringNotContainsString('\\\\c', $out);
        $this->assertStringNotContainsString('1b1b', $out);
    }

    // Regression: a filter for a layer whose FILTER marker is absent from the
    // mapfile must fail closed (throw), never silently apply no filter (IMPORTANT 5).
    public function testMapfileFilterThrowsWhenMarkerAbsent(): void
    {
        $this->expectException(ServiceException::class);
        MapfilePatcher::patchMapfileContent(
            $this->map(), ['test.other' => ["name = 'x'"]], false, ['test.other']
        );
    }

    // Same fail-closed behaviour for the QGIS project path when the datasource
    // line for the layer isn't found (IMPORTANT 5).
    public function testQgsFilterThrowsWhenDatasourceLineAbsent(): void
    {
        $this->expectException(ServiceException::class);
        MapfilePatcher::patchQgsContent(
            $this->qgs(), ['test.other' => ["name = 'x'"]], false, ['test.other']
        );
    }
}
