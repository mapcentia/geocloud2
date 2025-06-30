<?php
namespace app\inc;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

/**
 * Class Metrics
 * 
 * A singleton class for managing Prometheus metrics across the application
 */
class Metrics
{
    /**
     * @var CollectorRegistry
     */
    private static $registry;

    /**
     * Get the Prometheus collector registry
     * 
     * @return CollectorRegistry
     */
    public static function getRegistry(): CollectorRegistry
    {
        if (self::$registry === null) {
            // Initialize the registry with Redis adapter if configured, otherwise use in-memory adapter
            $redisConfig = \app\conf\App::$param["metricsCache"] ?? null;
            
            if ($redisConfig && $redisConfig["type"] === "redis") {
                $adapter = new Redis([
                    'host' => $redisConfig["host"] ?? 'valkey',
                    'database' => $redisConfig["db"] ?? 3,
                    'timeout' => 0.1, // in seconds
                ]);
                self::$registry = new CollectorRegistry($adapter);
            } else {
                // Use the default adapter (APCu or in memory)
                self::$registry = new CollectorRegistry(new InMemory());
            }
        }
        
        return self::$registry;
    }

    /**
     * Reset the registry (mainly for testing purposes)
     */
    public static function resetRegistry(): void
    {
        self::$registry = null;
    }
}
