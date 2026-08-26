<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

/**
 * Unit tests for app\models\Elasticsearch::mapPg2EsType — the PG→ES/OpenSearch
 * type inference. Guards that the legacy (v1/v2) output is byte-identical and
 * that the modern (v4) branch produces up-to-date types.
 */
class ElasticsearchMappingTest extends \Codeception\Test\Unit
{
    private function es(): \app\models\Elasticsearch
    {
        return new \app\models\Elasticsearch();
    }

    public function testLegacyTypesUnchanged(): void
    {
        $es = $this->es();
        $this->assertSame(['type' => 'text'], $es->mapPg2EsType('string'));
        $this->assertSame(['type' => 'text'], $es->mapPg2EsType('text'));
        $this->assertSame(['type' => 'text'], $es->mapPg2EsType('uuid'));
        $this->assertSame(['type' => 'integer'], $es->mapPg2EsType('int'));
        // Pre-existing quirk: numeric/double fall through to text in legacy.
        $this->assertSame(['type' => 'text'], $es->mapPg2EsType('double'));
        $this->assertSame(['type' => 'text'], $es->mapPg2EsType('decimal'));
        $this->assertSame(['type' => 'boolean'], $es->mapPg2EsType('boolean'));
        $this->assertSame(['type' => 'geo_shape'], $es->mapPg2EsType('geometry'));
        $this->assertSame(['type' => 'geo_point'], $es->mapPg2EsType('geometry', true));
        $this->assertSame('date', $es->mapPg2EsType('timestamptz')['type']);
        $this->assertSame('Y-MM-dd HH:mm:ss.SSSSSSZ', $es->mapPg2EsType('timestamptz')['format']);
    }

    public function testModernTypes(): void
    {
        $es = $this->es();
        // int -> long (safe superset; covers bigint, no overflow)
        $this->assertSame('long', $es->mapPg2EsType('int', false, true)['type']);
        // numeric/double -> double (fixes text-for-numbers + precision)
        $this->assertSame('double', $es->mapPg2EsType('double', false, true)['type']);
        $this->assertSame('double', $es->mapPg2EsType('decimal', false, true)['type']);
        // uuid -> keyword (exact match)
        $this->assertSame('keyword', $es->mapPg2EsType('uuid', false, true)['type']);
        // text stays text (keyword multi-field is added by createMapFromTable)
        $this->assertSame('text', $es->mapPg2EsType('string', false, true)['type']);
        $this->assertSame('text', $es->mapPg2EsType('text', false, true)['type']);
        // date format uses yyyy, not week-year Y
        $this->assertSame('yyyy-MM-dd HH:mm:ss.SSSSSSZ', $es->mapPg2EsType('timestamptz', false, true)['format']);
        // geometry unchanged by modern
        $this->assertSame(['type' => 'geo_point'], $es->mapPg2EsType('geometry', true, true));
        $this->assertSame(['type' => 'boolean'], $es->mapPg2EsType('boolean', false, true));
    }
}
