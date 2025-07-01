<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use app\conf\Connection;
use app\conf\App;
use app\models\Database;
use app\inc\Metrics;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\Gauge;


/**
 * Class Tilecache
 * @package app\controllers
 */
class Tilecache extends Controller
{
    /**
     * @var string
     */
    private $db;

    // Prometheus metrics
    private ?Counter $cacheOperationCounter = null;
    private ?Histogram $cacheOperationDuration = null;
    private ?Counter $filesRemovedCounter = null;
    private ?Gauge $cacheSizeGauge = null;
    private bool $metricsEnabled = false;

    /**
     * Tilecache constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->db = Input::getPath()->part(2);
        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->db = $dbSplit[1];
        }
        
        // Check if metrics are enabled in the configuration
        $this->metricsEnabled = App::$param['enableMetrics'] ?? false;
        
        // Initialize Prometheus metrics only if enabled
        if ($this->metricsEnabled) {
            $registry = Metrics::getRegistry();
            
            // Counter for tracking cache operations
            $this->cacheOperationCounter = $registry->getOrRegisterCounter(
                'geocloud2',
                'tilecache_operations_total',
                'Total number of tile cache operations',
                ['operation', 'cache_type', 'status']
            );
            
            // Histogram for tracking operation duration
            $this->cacheOperationDuration = $registry->getOrRegisterHistogram(
                'geocloud2',
                'tilecache_operation_duration_seconds',
                'Duration of tile cache operations in seconds',
                ['operation', 'cache_type'],
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5]
            );
            
            // Counter for tracking number of files removed
            $this->filesRemovedCounter = $registry->getOrRegisterCounter(
                'geocloud2',
                'tilecache_files_removed_total',
                'Total number of tile cache files removed',
                ['cache_type', 'schema']
            );
            
            // Gauge for tracking cache sizes before deletion
            $this->cacheSizeGauge = $registry->getOrRegisterGauge(
                'geocloud2',
                'tilecache_size_bytes',
                'Size of tile cache in bytes before deletion',
                ['cache_type', 'layer']
            );
        }
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
    {
        // Start timer for operation duration if metrics are enabled
        $startTime = $this->metricsEnabled ? microtime(true) : 0;
        
        $layer = new \app\models\Layer();
        $cache = $layer->getAll(Database::getDb(), true, Input::getPath()->part(4), false, true, false)["data"][0]["def"]->cache;

        // Default
        // =======
        $cache = $cache ?: App::$param["mapCache"]["type"];

        $response = [];
        switch ($cache) {
            case "sqlite":
                if (Input::getPath()->part(4) === "schema") {
                    $response = $this->auth(null, array());
                    if (!$response['success']) {
                        // Record failed authorization if metrics enabled
                        if ($this->metricsEnabled) {
                            $this->cacheOperationCounter->inc([
                                'operation' => 'delete_schema', 
                                'cache_type' => 'sqlite', 
                                'status' => 'auth_failed'
                            ]);
                        }
                        return $response;
                    }
                    $schema = Input::getPath()->part(5);
                    $file = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $schema . ".sqlite3";
                    
                    // Record cache size before deletion if metrics enabled
                    if ($this->metricsEnabled && file_exists($file)) {
                        $fileSize = filesize($file);
                        $this->cacheSizeGauge->set(['cache_type' => 'sqlite', 'layer' => $schema], $fileSize);
                    }
                    
                    @unlink($file);
                    
                    // Record successful operation if metrics enabled
                    if ($this->metricsEnabled) {
                        $this->cacheOperationCounter->inc([
                            'operation' => 'delete_schema', 
                            'cache_type' => 'sqlite', 
                            'status' => 'success'
                        ]);
                        $this->filesRemovedCounter->inc(['cache_type' => 'sqlite', 'schema' => $schema], 1);
                    }
                    
                    $response['success'] = true;
                    $response['message'] = "Tile cache for schema deleted";
                    
                    // Record operation duration if metrics enabled
                    if ($this->metricsEnabled) {
                        $endTime = microtime(true);
                        $this->cacheOperationDuration->observe(
                            $endTime - $startTime,
                            ['operation' => 'delete_schema', 'cache_type' => 'sqlite']
                        );
                    }
                    
                    return $response;
                } else {
                    $parts = explode(".", Input::getPath()->part(4));
                    $searchStr = $parts[0] . "." . $parts[1];
                    $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));
                    if (!$response['success']) {
                        // Record failed authorization if metrics enabled
                        if ($this->metricsEnabled) {
                            $this->cacheOperationCounter->inc([
                                'operation' => 'delete_layer', 
                                'cache_type' => 'sqlite', 
                                'status' => 'auth_failed'
                            ]);
                        }
                        return $response;
                    }
                }
                if ($searchStr) {
                    $res = self::unlikeSQLiteFile($searchStr, $this->metricsEnabled ? $this->filesRemovedCounter : null, $this->metricsEnabled ? $this->cacheSizeGauge : null);
                    if (!$res["success"]) {
                        // Record failed operation if metrics enabled
                        if ($this->metricsEnabled) {
                            $this->cacheOperationCounter->inc([
                                'operation' => 'delete_layer', 
                                'cache_type' => 'sqlite', 
                                'status' => 'failed'
                            ]);
                        }
                        $response['success'] = false;
                        $response['message'] = $res["message"];
                        $response['code'] = '403';
                        return $response;
                    }
                    
                    // Record successful operation if metrics enabled
                    if ($this->metricsEnabled) {
                        $this->cacheOperationCounter->inc([
                            'operation' => 'delete_layer', 
                            'cache_type' => 'sqlite', 
                            'status' => 'success'
                        ]);
                    }
                    
                    $response['success'] = true;
                    $response['message'] = "Tile cache deleted.";
                } else {
                    // Record failed operation if metrics enabled
                    if ($this->metricsEnabled) {
                        $this->cacheOperationCounter->inc([
                            'operation' => 'delete_layer', 
                            'cache_type' => 'sqlite', 
                            'status' => 'no_cache'
                        ]);
                    }
                    
                    $response['success'] = false;
                    $response['message'] = "No tile cache to delete.";
                }
                break;

            case "disk":
                if (Input::getPath()->part(4) === "schema") {
                    $response = $this->auth(null, array());
                    if (!$response['success']) {
                        // Record failed authorization if metrics enabled
                        if ($this->metricsEnabled) {
                            $this->cacheOperationCounter->inc([
                                'operation' => 'delete_schema', 
                                'cache_type' => 'disk', 
                                'status' => 'auth_failed'
                            ]);
                        }
                        return $response;
                    }
                    $layer = Input::getPath()->part(5);
                    $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . Input::getPath()->part(5) . ".*";
                } else {
                    $parts = explode(".", Input::getPath()->part(4));
                    $layer = $parts[0] . "." . $parts[1];
                    $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));
                    if (!$response['success']) {
                        // Record failed authorization if metrics enabled
                        if ($this->metricsEnabled) {
                            $this->cacheOperationCounter->inc([
                                'operation' => 'delete_layer', 
                                'cache_type' => 'disk', 
                                'status' => 'auth_failed'
                            ]);
                        }
                        return $response;
                    }
                    $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . $layer;

                }
                $res = self::unlinkTiles($dir, $layer, $this->metricsEnabled ? $this->filesRemovedCounter : null, $this->metricsEnabled ? $this->cacheSizeGauge : null);
                if (!$res["success"]) {
                    // Record failed operation if metrics enabled
                    if ($this->metricsEnabled) {
                        $this->cacheOperationCounter->inc([
                            'operation' => 'delete_layer', 
                            'cache_type' => 'disk', 
                            'status' => 'failed'
                        ]);
                    }
                    
                    $response['success'] = false;
                    $response['message'] = $res["message"];
                    $response['code'] = '403';
                    return $response;
                }
                
                // Record successful operation if metrics enabled
                if ($this->metricsEnabled) {
                    $this->cacheOperationCounter->inc([
                        'operation' => 'delete_layer', 
                        'cache_type' => 'disk', 
                        'status' => 'success'
                    ]);
                }
                
                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";
                break;

            case "bdb";
                $dba = dba_open(App::$param['path'] . "app/wms/mapcache/bdb/" . Connection::$param["postgisdb"] . "/" . "feature.polygon/bdb_feature.polygon.db", "c", "db4");

                $keyCount = 0;
                $key = dba_firstkey($dba);
                while ($key !== false && $key !== null) {
                    dba_delete($key, $dba);
                    $key = dba_nextkey($dba);
                    $keyCount++;
                }
                dba_sync($dba);
                
                // Record successful operation if metrics enabled
                if ($this->metricsEnabled) {
                    $this->cacheOperationCounter->inc([
                        'operation' => 'delete_keys', 
                        'cache_type' => 'bdb', 
                        'status' => 'success'
                    ]);
                    $this->filesRemovedCounter->inc([
                        'cache_type' => 'bdb', 
                        'schema' => 'feature.polygon'
                    ], $keyCount);
                }

                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";
                break;
        }
        
        // Record operation duration if metrics enabled
        if ($this->metricsEnabled) {
            $endTime = microtime(true);
            $this->cacheOperationDuration->observe(
                $endTime - $startTime,
                ['operation' => 'delete', 'cache_type' => $cache]
            );
        }
        
        return $response;
    }

    /**
     * @param string $layerName
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    static function bust(string $layerName): array
    {
        // Check if metrics are enabled in the configuration
        $metricsEnabled = App::$param['enableMetrics'] ?? false;
        $startTime = $metricsEnabled ? microtime(true) : 0;
        
        $layer = new \app\models\Layer();
        $cache = isset($layer->getAll(Database::getDb(), true, $layerName, false, true, false)["data"][0]["def"]->cache) ? $layer->getAll(Database::getDb(), true, $layerName, false, true, false)["data"][0]["def"]->cache : null;
        $cache = $cache ?: App::$param["mapCache"]["type"];
        $response = [];
        $res = null;
        
        // If metrics are enabled, get the registry and create counters
        $filesRemovedCounter = null;
        $cacheSizeGauge = null;
        
        if ($metricsEnabled) {
            $registry = Metrics::getRegistry();
            $filesRemovedCounter = $registry->getOrRegisterCounter(
                'geocloud2',
                'tilecache_files_removed_total',
                'Total number of tile cache files removed',
                ['cache_type', 'schema']
            );
            
            $cacheSizeGauge = $registry->getOrRegisterGauge(
                'geocloud2',
                'tilecache_size_bytes',
                'Size of tile cache in bytes before deletion',
                ['cache_type', 'layer']
            );
            
            $cacheOperationCounter = $registry->getOrRegisterCounter(
                'geocloud2',
                'tilecache_operations_total',
                'Total number of tile cache operations',
                ['operation', 'cache_type', 'status']
            );
            
            $cacheOperationDuration = $registry->getOrRegisterHistogram(
                'geocloud2',
                'tilecache_operation_duration_seconds',
                'Duration of tile cache operations in seconds',
                ['operation', 'cache_type'],
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5]
            );
            
            // Record operation started
            $cacheOperationCounter->inc([
                'operation' => 'bust', 
                'cache_type' => $cache, 
                'status' => 'started'
            ]);
        }

        switch ($cache) {
            case "sqlite":
                $res = self::unlikeSQLiteFile($layerName, $filesRemovedCounter, $cacheSizeGauge);
                break;
            case "disk":
                $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . $layerName;
                $res = self::unlinkTiles($dir, $layerName, $filesRemovedCounter, $cacheSizeGauge);
                break;
        }

        if (!$res["success"]) {
            $response['success'] = false;
            $response['message'] = $res["message"];
            $response['code'] = '406';
            
            // Record failed operation if metrics enabled
            if ($metricsEnabled) {
                $cacheOperationCounter = Metrics::getRegistry()->getOrRegisterCounter(
                    'geocloud2',
                    'tilecache_operations_total',
                    'Total number of tile cache operations',
                    ['operation', 'cache_type', 'status']
                );
                
                $cacheOperationCounter->inc([
                    'operation' => 'bust', 
                    'cache_type' => $cache, 
                    'status' => 'failed'
                ]);
            }
            
            return $response;
        }
        
        $response['success'] = true;
        $response['message'] = "Tile cache deleted.";
        
        // Record successful operation and duration if metrics enabled
        if ($metricsEnabled) {
            $cacheOperationCounter = Metrics::getRegistry()->getOrRegisterCounter(
                'geocloud2',
                'tilecache_operations_total',
                'Total number of tile cache operations',
                ['operation', 'cache_type', 'status']
            );
            
            $cacheOperationCounter->inc([
                'operation' => 'bust', 
                'cache_type' => $cache, 
                'status' => 'success'
            ]);
            
            $endTime = microtime(true);
            $cacheOperationDuration = Metrics::getRegistry()->getOrRegisterHistogram(
                'geocloud2',
                'tilecache_operation_duration_seconds',
                'Duration of tile cache operations in seconds',
                ['operation', 'cache_type'],
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 2, 5]
            );
            
            $cacheOperationDuration->observe(
                $endTime - $startTime,
                ['operation' => 'bust', 'cache_type' => $cache]
            );
        }
        
        return $response;
    }

    /**
     * @param string $layerName
     * @param Counter|null $filesRemovedCounter Optional Prometheus counter for metrics
     * @param Gauge|null $cacheSizeGauge Optional Prometheus gauge for metrics
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    private static function unlikeSQLiteFile(string $layerName, ?Counter $filesRemovedCounter = null, ?Gauge $cacheSizeGauge = null): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll(Database::getDb(), true, $layerName, false, true, false);
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock == true) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        $file1 = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $layerName . ".sqlite3";
        $file2 = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $layerName . ".json.sqlite3";
        
        // Track file sizes and counts if metrics are enabled
        $filesRemoved = 0;
        $totalSize = 0;
        
        if ($cacheSizeGauge !== null) {
            // Measure and record file sizes before deletion
            if (file_exists($file1)) {
                $size1 = filesize($file1);
                $totalSize += $size1;
            }
            
            if (file_exists($file2)) {
                $size2 = filesize($file2);
                $totalSize += $size2;
            }
            
            if ($totalSize > 0) {
                $cacheSizeGauge->set(['cache_type' => 'sqlite', 'layer' => $layerName], $totalSize);
            }
        }
        
        // Delete the files
        if (file_exists($file1)) {
            @unlink($file1);
            $filesRemoved++;
        }
        
        if (file_exists($file2)) {
            @unlink($file2);
            $filesRemoved++;
        }
        
        // Record the number of files removed if metrics are enabled
        if ($filesRemovedCounter !== null && $filesRemoved > 0) {
            $filesRemovedCounter->inc(['cache_type' => 'sqlite', 'schema' => explode('.', $layerName)[0]], $filesRemoved);
        }
        
        $response['success'] = true;
        return $response;
    }

    /**
     * @param string $dir
     * @param string $layerName
     * @param Counter|null $filesRemovedCounter Optional Prometheus counter for metrics
     * @param Gauge|null $cacheSizeGauge Optional Prometheus gauge for metrics
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    private static function unlinkTiles(string $dir, string $layerName, ?Counter $filesRemovedCounter = null, ?Gauge $cacheSizeGauge = null): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll(Database::getDb(), true, $layerName, false, true, false);
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock == true) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        
        if ($dir) {
            // Track directory size before deletion if metrics are enabled
            if ($cacheSizeGauge !== null) {
                $dirSize = self::getDirSize($dir);
                if ($dirSize > 0) {
                    $cacheSizeGauge->set(['cache_type' => 'disk', 'layer' => $layerName], $dirSize);
                }
            }
            
            // Count files in directory before removal
            $fileCount = 0;
            if ($filesRemovedCounter !== null) {
                $fileCount = self::countFiles($dir);
            }
            
            // Remove the directory
            exec("rm -R {$dir} 2> /dev/null");
            
            // Handle wildcard case
            if (strpos($dir, ".*") !== false) {
                $dir = str_replace(".*", "", $dir);
                exec("rm -R {$dir} 2> /dev/null");
            }
            
            // Record files removed if metrics are enabled
            if ($filesRemovedCounter !== null && $fileCount > 0) {
                $filesRemovedCounter->inc(['cache_type' => 'disk', 'schema' => explode('.', $layerName)[0]], $fileCount);
            }
            
            $response['success'] = true;
        } else {
            $response['success'] = false;
        }
        return $response;
    }
    
    /**
     * Gets the total size of a directory in bytes
     * 
     * @param string $dir Directory path
     * @return int Size in bytes
     */
    private static function getDirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        
        // Use du command to get directory size
        $output = [];
        exec("du -sb " . escapeshellarg($dir) . " 2> /dev/null | awk '{print $1}'", $output);
        
        if (!empty($output[0]) && is_numeric($output[0])) {
            return (int)$output[0];
        }
        
        return 0;
    }
    
    /**
     * Counts the number of files in a directory (recursively)
     * 
     * @param string $dir Directory path
     * @return int Number of files
     */
    private static function countFiles(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        
        // Use find command to count files
        $output = [];
        exec("find " . escapeshellarg($dir) . " -type f 2> /dev/null | wc -l", $output);
        
        if (!empty($output[0]) && is_numeric($output[0])) {
            return (int)$output[0];
        }
        
        return 0;
    }

}