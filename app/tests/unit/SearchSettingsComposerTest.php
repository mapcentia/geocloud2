<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

class SearchSettingsComposerTest extends \Codeception\Test\Unit
{
    public function testDefaultSettingsJsonIsOpenSearchCompatible(): void
    {
        $path = __DIR__ . '/../../conf/elasticsearch_settings.json.dist';
        $json = json_decode(file_get_contents($path), true);
        $this->assertIsArray($json, 'settings JSON must parse');

        $filter = $json['settings']['analysis']['filter']['substring'];
        $this->assertSame('edge_ngram', $filter['type'], 'camelCase edgeNGram was removed in ES7/OpenSearch');

        $range = $filter['max_gram'] - $filter['min_gram'];
        $this->assertLessThanOrEqual(
            $json['settings']['max_ngram_diff'],
            $range,
            'ngram range must not exceed index.max_ngram_diff'
        );
    }

    public function testComposeUsesDefaultWhenNull(): void
    {
        $settings = \app\opensearch\SettingsComposer::compose(null);
        $this->assertSame('edge_ngram', $settings['settings']['analysis']['filter']['substring']['type']);
        $this->assertSame(5, $settings['settings']['number_of_shards']);
    }

    public function testComposeReplacesAnalysisWhenProvided(): void
    {
        $custom = ['analyzer' => ['x' => ['type' => 'custom', 'tokenizer' => 'standard']], 'filter' => []];
        $settings = \app\opensearch\SettingsComposer::compose($custom);
        $this->assertSame($custom, $settings['settings']['analysis'], 'per-db analysis replaces default');
        $this->assertSame(5, $settings['settings']['number_of_shards'], 'other settings stay from default');
    }
}
