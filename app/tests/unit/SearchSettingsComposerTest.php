<?php
use PHPUnit\Framework\TestCase;

class SearchSettingsComposerTest extends TestCase
{
    public function testDefaultSettingsJsonIsOpenSearchCompatible(): void
    {
        $path = __DIR__ . '/../../conf/elasticsearch_settings.json';
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
}
