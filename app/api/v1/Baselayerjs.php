<?php
namespace app\api\v1;

class Baselayerjs extends \app\inc\Controller
{
    function __construct()
    {
        header("content-type: application/javascript");
        echo "window.gc2Options = {\n";
        echo "leafletDraw: " . ((\app\conf\App::$param['leafletDraw']) ? "true" : "false") . ",\n";
        echo "reverseLayerOrder: " . ((\app\conf\App::$param['reverseLayerOrder']) ? "true" : "false") . ",\n";
        echo "epsg: '" . ((\app\conf\App::$param['epsg']) ? : "4326") . "',\n";
        echo "extraShareFields: " . ((\app\conf\App::$param['extraShareFields']) ? "true": "false") . ",\n";
        echo "showDownloadOtionsInHeron: " . ((\app\conf\App::$param['showDownloadOtionsInHeron']) ? "true": "false") . ",\n";
        echo "esIndexingInGui: " . ((\app\conf\App::$param['esIndexingInGui']) ? "true": "false") . ",\n";
        echo "hideUngroupedLayers: " . ((\app\conf\App::$param['hideUngroupedLayers']) ? "true": "false") . ",\n";
        echo "staticMapHost: '" . ((\app\conf\App::$param['staticMapHost']) ? : \app\conf\App::$param['host']) . "',\n";
        echo "geoserverHost: '" . ((\app\conf\App::$param['geoserverHost']) ? : \app\conf\App::$param['host']) . "',\n";
        echo "encoding: '" . ((\app\conf\App::$param['encoding']) ? : "UTF8") . "',\n";
        echo "osmConfig: " . json_encode(\app\conf\App::$param['osmConfig']) . ",\n";
        echo "customPrintParams: " . json_encode(\app\conf\App::$param['customPrintParams']) . ",\n";
        echo "gc2scheduler: " . json_encode(\app\conf\App::$param['gc2scheduler']) . ",\n";
        echo "mergeSchemata: " . json_encode(\app\conf\App::$param['mergeSchemata']) . ",\n";
        echo "showConflictOptions: " . (json_encode(\app\conf\App::$param['showConflictOptions']) ? : "null")  .",\n";
        echo "showExtraLayerProperty: " . (json_encode(\app\conf\App::$param['showExtraLayerProperty']) ? : "null")  .",\n";
        echo "extraLayerPropertyName: " . (json_encode(\app\conf\App::$param['extraLayerPropertyName']) ? : "null")  .",\n";
        echo "clientConfig: " . (json_encode(\app\conf\App::$param['clientConfig']) ? : "null")  .",\n";
        echo "metaConfig: " . (json_encode(\app\conf\App::$param['metaConfig']) ? : "null")  .",\n";
        echo "enablePrint: " . (json_encode(\app\conf\App::$param['enablePrint']) ? : "null")  .",\n";
        echo "enableWorkflow: " . (json_encode(\app\conf\App::$param['enableWorkflow']) ? : "null")  .",\n";
        echo "hereApp: " . json_encode(\app\conf\App::$param['hereApp']).",\n";
        if ($settings = @file_get_contents(\app\conf\App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
            echo "es_settings: ". $settings.",\n";
        }
        foreach (\app\controllers\Mapcache::getGrids() as $k => $v) {
            $gridNames[] = $k;
        }
        echo "grids: " . (json_encode($gridNames) ? : "null")  ."\n";
        echo "};\n";
        if (\app\conf\App::$param['bingApiKey']) {
            echo "window.bingApiKey = '" . \app\conf\App::$param['bingApiKey'] . "';\n";
        }
        if (\app\conf\App::$param['digitalGlobeKey']) {
            echo "window.digitalGlobeKey = '" . \app\conf\App::$param['digitalGlobeKey'] . "';\n";
        }
        if (\app\conf\App::$param['baseLayers']) {
            echo "window.setBaseLayers = " . json_encode(\app\conf\App::$param['baseLayers']) . ";\n";
        }
        if (\app\conf\App::$param['baseLayersCollector']) {
            echo "window.setBaseLayersCollector = " . json_encode(\app\conf\App::$param['baseLayersCollector']) . ";\n";
        }
        if (\app\conf\App::$param['mapAttribution']) {
            echo "window.mapAttribution = '" . \app\conf\App::$param['mapAttribution'] . "';\n";
        }

        $locales = array("en_US", "da_DK", "fr_FR", "es_ES", "it_IT", "de_DE", "ru_RU");
        $arr = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $requestedLan = (\app\conf\App::$param['locale']) ? : str_replace("-", "_", $arr[0]);
        // Match both language and country
        if (in_array($requestedLan, $locales)) {
            echo "window.gc2Al='" . $requestedLan . "'\n";
            // Match only language
        } else {
            foreach ($locales as $locale) {
                if (substr($locale, 0, 2) == substr($requestedLan, 0, 2)) {
                    echo "window.gc2Al='" . $locale . "'\n";
                    exit();
                }
            }
            // Default
            echo "window.gc2Al='" . $locales[0] . "'\n";
        }
        exit();
    }

    public function get_index()
    {

    }
}

