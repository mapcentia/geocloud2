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
        //"esHost" => "elasticsearch",

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

        // Is MapServer >= 7 used?
        "mapserver_ver_7" => true,

        // Encrypt api key in database
        "encryptSettings" => false,

        // Map attribution
        "mapAttribution" => "Powered by <a href=\"http://geocloud.mapcentia.com\">MapCentia</a> ",

        // Master password for admin. MD5 hashed.
        "masterPw" => null,

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
        "reverseLayerOrder" => false,

        // Use Intercom.io for messaging MapCentia
        "intercom_io" => true,

        // Hero text on log in / sign up page
        "heroText" => "<h2>GC2 is a geospatial platform built on powerful open source software</h2><p>PostGIS, MapServer, TileCache, Elasticsearch, WMS, WFS-T, SQL API, JavaScript API, OpenLayers, Leaflet and more in one integrated platform.</p>",

        // OSM server
        //"osmConfig" => array(
        //    "server" => "hostaddr=127.0.0.1 port=5432 dbname=osm user=postgres password=1234",
        //    "schemas" => array("DK"),
        //),

        // Trust these IPs
        "trustedAddresses" => array(
          "127.0.0.1/32"
        ),

        // Enable Elasticsearch indexing in GUI
        "esIndexingInGui" => true,

        //Show download options in Heron-mc
        "showDownloadOtionsInHeron" => true,

        //Show workflow options
        "enableWorkflow" => array(
            "*" => true,
        ),

        // Enable gc2scheduler
        "gc2scheduler" => array(
            "test" => true,
        ),

        // Use API key for Elasticsearch Search API
        "useKeyForSearch" => false,

        // Allowed origins for CORS
        "AccessControlAllowOrigin" => [
            "*"
        ],

        // Meta properties
        "metaConfig" => [
            [
                "fieldsetName" => "Layer type",
                "fields" => [
                    [
                        "name" => "vidi_layer_type",
                        "type" => "checkboxgroup",
                        "title" => "Type",
                        "values" => [
                            ["name" => "Tile", "value" => "t"],
                            ["name" => "Vector", "value" => "v"],
                            ["name" => "WebGL", "value" => "w"],
                            ["name" => "MVT", "value" => "mvt"],
                        ],
                        "default" => "t",
                    ],
                ]

            ],
            [
                "fieldsetName" => "Editor",
                "fields" => [
                    [
                        "name" => "vidi_layer_editable",
                        "type" => "checkbox",
                        "title" => "Editable",
                        "default" => false,
                    ],
                ]

            ],
            [
                "fieldsetName" => "Tile settings",
                "fields" => [
                    [
                        "name" => "single_tile",
                        "type" => "checkbox",
                        "title" => "Use tile cache",
                        "default" => false,
                    ],
                    [
                        "name" => "tiles_service_uri",
                        "type" => "text",
                        "title" => "Tiles service uri",
                    ]
                ]

            ],
            [
                "fieldsetName" => "Vector settings",
                "fields" => [
                    [
                        "name" => "load_strategy",
                        "type" => "combo",
                        "title" => "Load strategy",
                        "values" => [
                            ["name" => "Static", "value" => "s"],
                            ["name" => "Dynamic", "value" => "d"],
                        ],
                        "default" => "s",
                    ],
                    [
                        "name" => "max_features",
                        "type" => "text",
                        "title" => "Max features",
                        "default" => "100",
                    ],
                ]

            ],
            [
                "fieldsetName" => "Filters",
                "fields" => [
                    [
                        "name" => "filter_config",
                        "type" => "textarea",
                        "title" => "Filter config",
                    ],
                    [
                        "name" => "predefined_filters",
                        "type" => "textarea",
                        "title" => "Predefined filters",
                    ],
                    [
                        "name" => "default_match",
                        "type" => "combo",
                        "title" => "Default match",
                        "values" => [
                            ["name" => "All", "value" => "all"],
                            ["name" => "Any", "value" => "any"],
                        ],
                        "default" => "any",
                    ],
                ]

            ],
            [
                "fieldsetName" => "References",
                "fields" => [
                    [
                        "name" => "referenced_by",
                        "type" => "textarea",
                        "title" => "Referenced by",
                    ],
                ]
            ],
            [
                "fieldsetName" => "Layer tree",
                "fields" => [
                    [
                        "name" => "vidi_sub_group",
                        "type" => "text",
                        "title" => "Sub group",
                    ],
                ]

            ],
        ],
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
