<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

class Baselayerjs extends \app\inc\Controller
{
    public function getSettings() {
        $settingsRawJSON = '';
        $settingsRawJSON .= "{";
        $settingsRawJSON .= "\"leafletDraw\": " . ((\app\conf\App::$param['leafletDraw']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"reverseLayerOrder\": " . ((\app\conf\App::$param['reverseLayerOrder']) ? "true" : "false") . ",\n";

        $settingsRawJSON .= "\"epsg\": \"" . ((\app\conf\App::$param['epsg']) ? : "4326") . "\",\n";
        $settingsRawJSON .= "\"extraShareFields\": " . ((\app\conf\App::$param['extraShareFields']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"showDownloadOtionsInHeron\": " . ((\app\conf\App::$param['showDownloadOtionsInHeron']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"esIndexingInGui\": " . ((\app\conf\App::$param['esIndexingInGui']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"hideUngroupedLayers\": " . ((\app\conf\App::$param['hideUngroupedLayers']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"staticMapHost\": \"" . ((\app\conf\App::$param['staticMapHost']) ? : \app\conf\App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"geoserverHost\": \"" . ((\app\conf\App::$param['geoserverHost']) ? : \app\conf\App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"encoding\": \"" . ((\app\conf\App::$param['encoding']) ? : "UTF8") . "\",\n";
        $settingsRawJSON .= "\"osmConfig\": " . json_encode(\app\conf\App::$param['osmConfig']) . ",\n";
        $settingsRawJSON .= "\"customPrintParams\": " . json_encode(\app\conf\App::$param['customPrintParams']) . ",\n";
        $settingsRawJSON .= "\"gc2scheduler\": " . json_encode(\app\conf\App::$param['gc2scheduler']) . ",\n";
        $settingsRawJSON .= "\"mergeSchemata\": " . json_encode(\app\conf\App::$param['mergeSchemata']) . ",\n";
        $settingsRawJSON .= "\"showConflictOptions\": " . (json_encode(\app\conf\App::$param['showConflictOptions']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"showExtraLayerProperty\": " . (json_encode(\app\conf\App::$param['showExtraLayerProperty']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"extraLayerPropertyName\": " . (json_encode(\app\conf\App::$param['extraLayerPropertyName']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"clientConfig\": " . (json_encode(\app\conf\App::$param['clientConfig']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"metaConfig\": " . (json_encode(\app\conf\App::$param['metaConfig']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"enablePrint\": " . (json_encode(\app\conf\App::$param['enablePrint']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"enableWorkflow\": " . (json_encode(\app\conf\App::$param['enableWorkflow']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"hereApp\": " . json_encode(\app\conf\App::$param['hereApp']).",\n";
        $settingsRawJSON .= "\"subDomainsForTiles\": " . (json_encode(\app\conf\App::$param['subDomainsForTiles']) ? : "null").",\n";
        if ($settings = @file_get_contents(\app\conf\App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
            $settingsRawJSON .= "\"es_settings\": ". $settings.",\n";
        }

        foreach (\app\controllers\Mapcache::getGrids() as $k => $v) {
            $gridNames[] = $k;
        }

        $settingsRawJSON .= "\"grids\": " . (json_encode($gridNames) ? : "null")  ."\n";

        $settingsRawJSON .= "}";

        $settingsMainParsed = json_decode($settingsRawJSON, true);

        $overallSettings = [
            'main' => $settingsMainParsed
        ];

        if (\app\conf\App::$param['bingApiKey']) $overallSettings["bingApiKey"] = \app\conf\App::$param['bingApiKey'];
        if (\app\conf\App::$param['googleApiKey']) $overallSettings["googleApiKey"] = \app\conf\App::$param['googleApiKey'];
        if (\app\conf\App::$param['digitalGlobeKey']) $overallSettings["digitalGlobeKey"] = \app\conf\App::$param['digitalGlobeKey'];
        if (\app\conf\App::$param['baseLayers']) $overallSettings["setBaseLayers"] = (\app\conf\App::$param['baseLayers']);
        if (\app\conf\App::$param['baseLayersCollector']) $overallSettings["setBaseLayersCollector"] = (\app\conf\App::$param['baseLayersCollector']);
        if (\app\conf\App::$param['mapAttribution']) $overallSettings["mapAttribution"] = \app\conf\App::$param['mapAttribution'];

        $locales = array("en_US", "da_DK", "fr_FR", "es_ES", "it_IT", "de_DE", "ru_RU");
        $arr = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $requestedLan = (\app\conf\App::$param['locale']) ? : str_replace("-", "_", $arr[0]);
        // Match both language and country
        if (in_array($requestedLan, $locales)) {
            $overallSettings["gc2Al"] = $requestedLan;
            // Match only language
        } else {
            $matched = false;
            foreach ($locales as $locale) {
                if (substr($locale, 0, 2) == substr($requestedLan, 0, 2)) {
                    $overallSettings["gc2Al"] = $locale;
                    $matched = true;
                }
            }

            // Default
            if ($matched === false) $overallSettings["gc2Al"] = $locales[0];
        }

        return $overallSettings;
    }
}
