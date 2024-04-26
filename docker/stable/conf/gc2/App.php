<?php
namespace app\conf;


/**
 * Class App
 * @package app\conf
 */
class App
{
    /**
     * @var array<mixed>
     */
    static $param = array(

        "memoryLimit" => "512M",

        //Server path where GeoCLoud is installed.
        "path" => "/var/www/geocloud2/",

        // When creating new databases use this db as template
        "databaseTemplate" => "template_geocloud",

        "SqlApiSettings" => [
            "statement_timeout" => 120
        ],

        "sessionHandler" => [
            "type" => "redis",
            "host" => "redis:6379", // without tcp:
            "db" => 1,
        ],

        // Internal cache system
        "appCache" => [
            "type" => "redis",
            "host" => "redis:6379", // without tcp:
            "ttl" => 3600,
            "db" => 0,
        ],

        // MapCache config
        // In Docker use the names of the containers
        "mapCache" => [
            // MapCache host URL.
            "host" => "http://mapcache:5555",

            // WMS backend for MapCache. Define gc2core host seen from MapCache container.
            "wmsHost" => "http://gc2core:80",

            // Type of cache back-end. "disk" or "sqlite"
            "type" => "disk",
        ],

        // Encrypt api key in database
        "encryptSettings" => false,

        // Map attribution
        "mapAttribution" => "Powered by <a href=\"http://geocloud.mapcentia.com\">MapCentia</a> ",

        // Master password for admin. MD5 hashed.
        "masterPw" => null,

        // Available baselayer
        "baseLayers" => array(
            array("id" => "osm", "name" => "OSM"),
        ),

        // Bing key
        "bingApiKey" => 'your_bing_map_key',

        //Force a locale. If not set it'll set set from the browsers language settings.
        //"locale" => "en_US",

        // Default EPSG when uploading data files. If not set it defaults to 4326.
        "epsg" => "4326",

        // Default encoding when uploading data files. If not set it defaults to UTF8
        "encoding" => "UTF8",

        // Logo
        "loginLogo" => "/assets/images/MapCentia_500.png",

        // If true, low layer sort id puts the layer on top
        "reverseLayerOrder" => false,

        // Trust these IPs
        "trustedAddresses" => array(
        //  "127.0.0.1/32"
        ),

        // Enable Elasticsearch indexing in GUI
        "esIndexingInGui" => true,

        //Show workflow options
        "enableWorkflow" => array(
            "*" => true,
        ),

        // Enable gc2scheduler
        "gc2scheduler" => array(
            "test" => true,
        ),

        // Allowed origins for CORS
        "AccessControlAllowOrigin" => [
            "*"
        ],

        // Meta properties
        "vidiUrl" => "http://127.0.0.1:3000",

    );
    function __construct(){
        // This is the autoload function and include path setting. No need to fiddle with this.
        spl_autoload_register(function ($className) {
            $ds = DIRECTORY_SEPARATOR;
            $dir = App::$param['path'];
            $className = strtr($className, '\\', $ds);
            $file = "{$dir}{$className}.php";
            if (is_readable($file)) {
                require_once $file;
            }
        });
        set_include_path(get_include_path() . PATH_SEPARATOR . App::$param['path'] . PATH_SEPARATOR . App::$param['path'] . "app" . PATH_SEPARATOR . App::$param['path'] . "app/libs/PEAR/");
    }
}
