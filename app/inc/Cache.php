<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;
use Error;
use Exception;
use Phpfastcache\CacheManager;
use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Phpfastcache\Core\Pool\TaggableCacheItemPoolInterface;
use Phpfastcache\Drivers\Redis\Config as RedisConfig;
use Phpfastcache\Drivers\Rediscluster\Config as RedisClustersConfig;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use Redis;
use RedisCluster;


abstract class Cache
{

    /**
     * @var ExtendedCacheItemPoolInterface
     */
    static public ExtendedCacheItemPoolInterface $instanceCache;

    /**
     * @throws Exception
     */
    static public function setInstance(): void
    {
        if (App::$param['appCache']["type"] == "redis") {
            if (!empty(App::$param['appCache']["host"])) {
                $u = parse_url(App::$param['appCache']["host"]);
                $scheme = $u['scheme'] ?? 'tcp';
                $host = $u['host'] ?? $u['path'] ?? 'redis';
                $port = $u['port'] ?? 6379;
            } else {
                throw new GC2Exception('Could not determine redis host', 500, null, 'CACHE_ERROR');
            }
            $db = !empty(App::$param["appCache"]["db"]) ? App::$param["appCache"]["db"] : 0;
            $redis = new Redis([
                'host' => $scheme . '://' . $host,
                'port' => (int)$port,
                'ssl' => $scheme == 'tls' ? ['verify_peer' => false] : null,
            ]);
            $redis->select($db);
            $redisConfig = [
                'itemDetailedDate' => true,
                'useStaticItemCaching' => false,
                'redisClient' => $redis,
            ];
            if ($db) {
                $redis->select($db);
                $redisConfig['database'] = $db;
            }
            self::$instanceCache = CacheManager::getInstance('redis', new RedisConfig($redisConfig));

        } elseif (App::$param['appCache']["type"] == "redisCluster") {
            if (!empty(App::$param['appCache']["seeds"])) {
                $seeds = App::$param['appCache']["seeds"];
            } else {
                throw new GC2Exception('Could not determine redis seeds', 500, null, 'CACHE_ERROR');
            }
            $redisCluster = new RedisCluster(
                NULL,
                $seeds,
                1.5,
                1.5,
                true,
                NULL,
                (!empty(App::$param['appCache']["tls"]) ? ["verify_peer" => false] : null)
            );
            $redisConfig = [
                'itemDetailedDate' => true,
                'useStaticItemCaching' => false,
                'redisClusterClient' => $redisCluster,
            ];
            self::$instanceCache = CacheManager::getInstance('redisCluster', new RedisClustersConfig($redisConfig));
        } else {
            throw new GC2Exception('Cache type must be either redis or redisCluster', 500, null, 'CACHE_ERROR');
        }
        Globals::$cacheTtl = !empty(App::$param["appCache"]["ttl"]) ? App::$param["appCache"]["ttl"] : Globals::$cacheTtl;
    }

    /**
     * @return array
     */
    static public function clear(): array
    {
        $res = self::$instanceCache->clear();
        return [
            "success" => true,
            "message" => $res
        ];
    }

    static public function deleteItemsByTagsAll(array $tags): void
    {
        self::$instanceCache->deleteItemsByTags($tags, TaggableCacheItemPoolInterface::TAG_STRATEGY_ALL); // V8
    }

    /**
     * @param string $key
     * @throws InvalidArgumentException
     */
    static public function deleteItem(string $key): void
    {
        self::$instanceCache->deleteItem($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    static public function deleteItems(array $keys): void
    {
        self::$instanceCache->deleteItems($keys);
    }


    /**
     * @throws InvalidArgumentException
     */
    static public function deleteByPatterns(array $patterns): void
    {
        $keys = [];
        foreach ($patterns as $pattern) {
            try {
                $keys = array_merge($keys, self::getAllKeys($pattern));
            } catch (Error) {
                $items = self::getAllItems($pattern);
                foreach ($items as $key => $item) {
                    $keys[] = $key;
                }
            }
        }
        self::deleteItems($keys);

    }

    /**
     * @param string $key
     * @return ExtendedCacheItemInterface|null
     */
    static public function getItem(string $key): ?ExtendedCacheItemInterface
    {
        try {
            $CachedString = self::$instanceCache->getItem($key);
        } catch (PhpfastcacheInvalidArgumentException|Error) {
            $CachedString = null;
        }
        return $CachedString;
    }

    static private function getAllItems(string $pattern): iterable
    {
        try {
            $items = self::$instanceCache->getAllItems($pattern);
        } catch (Exception|Error) {
            $items = null;
        }

        return $items;
    }

    /**
     * @param string $pattern
     * @return array
     */
    static private function getAllKeys(string $pattern): array
    {
        return self::$instanceCache->getAllKeys($pattern);
    }

    /**
     * @param ExtendedCacheItemInterface $CachedString
     */
    static public function save(ExtendedCacheItemInterface $CachedString): void
    {
        try {
            self::$instanceCache->save($CachedString);
        } catch (Error $exception) {
            error_log($exception->getMessage());
        }
    }

    /**
     * @return array
     */
    static public function getStats(): array
    {
        return (array)self::$instanceCache->getStats();
    }

    /**
     * @return array
     */
    static public function getItemsByTagsAsJsonString(): array
    {
        return self::$instanceCache->getItems();
    }
}