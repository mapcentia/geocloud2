<?php
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
        $this->assertStringContainsString('&apos;', MapfilePatcher::xmlEscape("a'b"));
    }
}
