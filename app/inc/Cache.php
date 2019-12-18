<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use \app\conf\App;
use \Phpfastcache\CacheManager;
use \Phpfastcache\Drivers\Files\Config as FilesConfig;
use \Phpfastcache\Drivers\Redis\Config as RedisConfig;


abstract class Cache
{

    static public $instanceCache;

    /**
     * @throws \Exception
     */
    static public function setInstance()
    {
        $split = explode(":", App::$param['redisHost'] ?: "127.0.0.1:6379");
        $redisConfig = [
            'host' => $split[0],
            'port' => !empty($split[1]) ? (int)$split[1] : 6379,
            'itemDetailedDate' => true
        ];

        $fileConfig = [
            'securityKey' => "phpfastcache",
            'path' => '/var/www/geocloud2/app/tmp',
            'itemDetailedDate' => true
        ];

        $cacheType = !empty(App::$param["appCache"]["type"]) ? App::$param["appCache"]["type"] : "files";

        \app\inc\Globals::$cacheTtl = !empty(App::$param["appCache"]["ttl"]) ? App::$param["appCache"]["ttl"] : 0.00001; // Small number - must not be 0

        switch ($cacheType) {
            case "redis":
                try {
                    self::$instanceCache = CacheManager::getInstance('redis',
                        new RedisConfig($redisConfig)
                    );
                } catch
                (\Exception $exception) {
                    throw new \Exception($exception->getMessage());
                }
                break;

            default:
                try {
                    self::$instanceCache = CacheManager::getInstance('files',
                        new FilesConfig($fileConfig)
                    );
                } catch (\Exception $exception) {
                    throw new \Exception($exception->getMessage());
                }
                break;
        }
    }

    /**
     *
     */
    static public function clear()
    {
        try {
            $res = self::$instanceCache->clear();
        } catch (\Error $exception) {
            return [
                "code" => 400,
                "success" => false,
                "message" => $exception->getMessage()
            ];
        } catch (\Exception $exception) {
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

    static public function deleteItemsByTagsAll(array $tags)
    {
        self::$instanceCache->deleteItemsByTagsAll($tags);
    }

    static public function deleteItemsByTags(array $tags)
    {
        self::$instanceCache->deleteItemsByTags($tags);
    }

    /**
     * @param string $key
     * @return |null
     */
    static public function getItem(string $key)
    {
        try {
            $CachedString = self::$instanceCache->getItem($key);
        } catch (\Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException $exception) {
            $CachedString = null;
        } catch (\Error $exception) {
            $CachedString = null;
        }
        return $CachedString;
    }

    /**
     * @param $CachedString
     */
    static public function save($CachedString)
    {
        try {
            self::$instanceCache->save($CachedString); // Save the cache item just like you do with doctrine and entities
        } catch (\Error $exception) {
            error_log($exception->getMessage());
        }
    }

    static public function getStats()
    {
        return (array)self::$instanceCache->getStats();
    }

    static public function getItemsByTagsAsJsonString(array $tags)
    {
        return self::$instanceCache->getItemsByTagsAsJsonString($tags);
    }
}