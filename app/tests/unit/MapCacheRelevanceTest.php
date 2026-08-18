<?php
/**
 * Unit tests for AbstractLayerApi::affectsMapCache — the gate that decides whether a layer
 * properties change warrants rewriting the (whole-database, expensive) MapCache config. Only
 * caching-relevant def keys count; styling and other properties must not trigger a rewrite.
 */

use app\api\v4\AbstractLayerApi;
use Codeception\Test\Unit;

class MapCacheRelevanceTest extends Unit
{
    public function testCachingKeysAreRelevant(): void
    {
        foreach (AbstractLayerApi::MAPCACHE_RELEVANT_KEYS as $key) {
            $this->assertTrue(AbstractLayerApi::affectsMapCache([$key => 'x']), "$key should be relevant");
        }
    }

    public function testMixedTouchingOneRelevantKeyIsRelevant(): void
    {
        $this->assertTrue(AbstractLayerApi::affectsMapCache(['opacity' => 50, 'ttl' => 3600]));
    }

    public function testStylingAndOtherKeysAreNotRelevant(): void
    {
        $this->assertFalse(AbstractLayerApi::affectsMapCache(['opacity' => 50]));
        $this->assertFalse(AbstractLayerApi::affectsMapCache([
            'theme_column' => 't', 'label_column' => 'l', 'minscaledenom' => 1, 'maxscaledenom' => 9,
        ]));
    }

    public function testEmptyOrNullIsNotRelevant(): void
    {
        $this->assertFalse(AbstractLayerApi::affectsMapCache([]));
        $this->assertFalse(AbstractLayerApi::affectsMapCache(null));
    }
}
