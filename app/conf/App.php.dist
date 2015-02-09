<?php
namespace app\conf;

class App
{
    static $param = array(
        // The hostname of the server
        "host" => "",

        // The hostname of the user module. Normally this is the same as the host above
        "userHostName" => "",

        //Server path where GeoCLoud is installed.
        "path" => "/var/www/geocloud2/",

        // When creating new databases use this db as template
        "databaseTemplate" => "template_geocloud",

        // The host of Elasticsearch
        "esHost" => "",

        // Encrypt api key in database
        "encryptSettings" => false,

        // Map attribution
        "mapAttribution" => "Powered by <a href=\"http://geocloud.mapcentia.com\">MapCentia</a> ",

        // Master password for admin. MD5 hashed.
        "masterPw" => null,

        // Render backend for tile cache ["wms" | "python"]
        "tileRenderBackend" => "python",

        // Available baselayer
        "baseLayers" => array(
            array("id" => "stamenToner", "name" => "Stamen Toner"),
            array("id" => "osm", "name" => "OSM"),
            array("id" => "mapQuestOSM", "name" => "MapQuset OSM"),
            array("id" => "googleStreets", "name" => "Google Street"),
            array("id" => "googleHybrid", "name" => "Google Hybrid"),
            array("id" => "googleSatellite", "name" => "Google Satellite"),
            array("id" => "googleTerrain", "name" => "Google Terrain"),
            //array("id" => "bingRoad", "name" => "Bing Road"),
            //array("id" => "bingAerial", "name" => "Bing Aerial"),
            //array("id" => "bingAerialWithLabels", "name" => "Bing Aerial With Labels"),
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

        // Homepage link for corner logo
        "homepage" => "http://www.mapcentia.com/en/geocloud/geocloud.htm",

        // If true, low layer sort id puts the layer on top
        "reverseLayerOrder" => true,

        // Enable the Leaflet Draw plugin in viewer
        "leafletDraw" => false,

        // Use Intercom.io for messaging MapCentia
        "intercom_io" => true,

        // Hero text on log in / sign up page
        "heroText" => "<h2>GC2 is a geospatial platform built on powerful open source software</h2><p>PostGIS, MapServer, TileCache, Elasticsearch, WMS, WFS-T, SQL API, JavaScript API, OpenLayers, Leaflet and more in one integrated platform.</p>",

        // OSM server
        //"osmConfig" => array(
        //    "server" => "hostaddr=127.0.0.1 port=5432 dbname=osm user=postgres password=1234",
        //    "schemas" => array("DK"),
        //),

        //Hide layers without a group in viewer
        "hideUngroupedLayers" => true,

        // Trust theese IPs
        "trustedAddresses" => array(
          "127.0.0.1/32"
        ),

        // Enable Elasticsearch indexing in GUI
        "esIndexingInGui" => true,

        // Enable gc2scheduler
        "gc2scheduler" => array(
            "mydb" => false,
        ),

        // Use API key for Elasticsearch Search API
        "useKeyForSearch" => false,
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