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

        "metaConfig" => [
            [
                "fieldsetName" => "Konflikt",
                "fields" => [
                    [
                        "name" => "short_conflict_meta_desc",
                        "type" => "text",
                        "title" => "Kort beskrivelse",
                    ],
                    [
                        "name" => "long_conflict_meta_desc",
                        "type" => "textarea",
                        "title" => "Lang beskrivelse",
                    ],
                ]
            ],
            [
                "fieldsetName" => "Info pop-up",
                "fields" => [
                    [
                        "name" => "info_template",
                        "type" => "textarea",
                        "title" => "Pop-up template",
                    ],
                    [
                        "name" => "info_element_selector",
                        "type" => "text",
                        "title" => "Element selector",
                    ],
                    [
                        "name" => "info_function",
                        "type" => "textarea",
                        "title" => "Function",
                    ],
                    [
                        "name" => "select_function",
                        "type" => "textarea",
                        "title" => "Select function",
                    ],
                    [
                        "name" => "accordion_summery_prefix",
                        "type" => "text",
                        "title" => "Accordion summery prefix",
                    ],
                    [
                        "name" => "accordion_summery",
                        "type" => "text",
                        "title" => "Accordion summery",
                    ],

                ]

            ],
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
                    [
                        "name" => "default_layer_type",
                        "type" => "combo",
                        "title" => "Default",
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
                "fieldsetName" => "Tables",
                "fields" => [
                    [
                        "name" => "zoom_on_table_click",
                        "type" => "checkbox",
                        "title" => "Zoom on select",
                        "default" => false,
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
                    ],
                    [
                        "name" => "tiles_selected_style",
                        "type" => "textarea",
                        "title" => "Selected style",
                    ],
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
                        "default" => "500",
                    ],
                    [
                        "name" => "use_clustering",
                        "type" => "checkbox",
                        "title" => "Use clustering",
                        "default" => false,
                    ],
                    [
                        "name" => "point_to_layer",
                        "type" => "textarea",
                        "title" => "Point to layer",
                    ],
                    [
                        "name" => "vector_style",
                        "type" => "textarea",
                        "title" => "Style function",
                    ],
                    [
                        "name" => "show_table_on_side",
                        "type" => "checkbox",
                        "title" => "Show table",
                        "default" => false,
                    ],
                    [
                        "name" => "reload_interval",
                        "type" => "text",
                        "title" => "Reload Interval",
                    ],
                    [
                        "name" => "disable_vector_feature_info",
                        "type" => "checkbox",
                        "title" => "Disable feature info",
                        "default" => false,
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
                    [
                        "name" => "filter_immutable",
                        "type" => "checkbox",
                        "title" => "Immutable",
                        "default" => false,
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
                    [
                        "name" => "default_open_tools",
                        "type" => "textarea",
                        "title" => "Open tools",
                    ],
                    [
                        "name" => "disable_check_box",
                        "type" => "checkbox",
                        "title" => "Disable check box",
                        "default" => false,
                    ],
                ],
            ],
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
