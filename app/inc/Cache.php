<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use Error;
use Exception;
use Phpfastcache\CacheManager;
use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Phpfastcache\Core\Pool\TaggableCacheItemPoolInterface;
use Phpfastcache\Drivers\Files\Config as FilesConfig;
use Phpfastcache\Drivers\Redis\Config as RedisConfig;
use Phpfastcache\Drivers\Memcached\Config as MemcachedConfig;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


abstract class Cache
{

    /**
     * @var ExtendedCacheItemPoolInterface
     */
    static public $instanceCache;

    /**
     * @throws Exception
     */
    static public function setInstance(): void
    {
        $redisConfig = null;
        $memcachedConfig = null;
        if (!empty(App::$param['appCache']["host"])) {
            $split = explode(":", App::$param['appCache']["host"]);

            $redisConfig = [
                'host' => $split[0],
                'port' => !empty($split[1]) ? (int)$split[1] : 6379,
                'database' => !empty(App::$param["appCache"]["db"]) ? App::$param["appCache"]["db"] : 0,
                'itemDetailedDate' => true
            ];

            $memcachedConfig = [
                'host' => $split[0],
                'port' => !empty($split[1]) ? (int)$split[1] : 11211,
                'itemDetailedDate' => true
            ];
        }

        $fileConfig = [
            'securityKey' => "phpfastcache",
            'path' => '/var/www/geocloud2/app',
            'itemDetailedDate' => true
        ];

        $cacheType = !empty(App::$param["appCache"]["type"]) ? App::$param["appCache"]["type"] : "files";

        Globals::$cacheTtl = !empty(App::$param["appCache"]["ttl"]) ? App::$param["appCache"]["ttl"] : Globals::$cacheTtl;

        switch ($cacheType) {
            case "redis":
                try {
                    self::$instanceCache = CacheManager::getInstance('redis',
                        new RedisConfig($redisConfig)
                    );
                } catch
                (Exception $exception) {
                    throw new Exception($exception->getMessage());
                }
                break;

            case "memcached":
                try {
                    self::$instanceCache = CacheManager::getInstance('memcached',
                        new MemcachedConfig($memcachedConfig)
                    );
                } catch
                (Exception $exception) {
                    throw new Exception($exception->getMessage());
                }
                break;

            default:
                try {
                    self::$instanceCache = CacheManager::getInstance('files',
                        new FilesConfig($fileConfig)
                    );
                } catch (Exception $exception) {
                    throw new Exception($exception->getMessage());
                }
                break;
        }
    }

    /**
     * @return array<mixed>
     */
    static public function clear(): array
    {
        try {
            $res = self::$instanceCache->clear();
        } catch (Error $exception) {
            return [
                "code" => 400,
                "success" => false,
                "message" => $exception->getMessage()
            ];
        } catch (Exception $exception) {
            return [
                "code" => 400,
                "success" => false,
                "message" => $exception->getMessage()
            ];
        }
        return [
            "success" => true,
            "message" => $res
        ];
    }

    /**
     * @param array<string> $tags
     */
    static public function deleteItemsByTagsAll(array $tags): void
    {
        self::$instanceCache->deleteItemsByTags($tags, TaggableCacheItemPoolInterface::TAG_STRATEGY_ALL); // V8
    }

    /**
     * @param array<string> $tags
     */
    static public function deleteItemsByTags(array $tags): void
    {
        self::$instanceCache->deleteItemsByTags($tags, TaggableCacheItemPoolInterface::TAG_STRATEGY_ONE);
    }

    /**
     * @param string $key
     * @return ExtendedCacheItemInterface|null
     */
    static public function getItem(string $key): ?ExtendedCacheItemInterface
    {
        try {
            $CachedString = self::$instanceCache->getItem($key);
        } catch (PhpfastcacheInvalidArgumentException | Error $exception) {
            $CachedString = null;
        }
        return $CachedString;
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
     * @return array<mixed>
     */
    static public function getStats(): array
    {
        return (array)self::$instanceCache->getStats();
    }

    /**
     * @return array<mixed>
     */
    static public function getItemsByTagsAsJsonString(): array
    {
        return self::$instanceCache->getItems();
    }
}