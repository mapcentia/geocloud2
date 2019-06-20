<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\models;

use \app\conf\App;

class Baselayerjs extends \app\inc\Controller
{
    public function getSettings() {
        $settingsRawJSON = '';
        $settingsRawJSON .= "{";
        $settingsRawJSON .= "\"leafletDraw\": " . ((App::$param['leafletDraw']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"reverseLayerOrder\": " . ((App::$param['reverseLayerOrder']) ? "true" : "false") . ",\n";

        $settingsRawJSON .= "\"epsg\": \"" . (isset(App::$param['epsg']) ? App::$param['epsg'] : "4326") . "\",\n";
        $settingsRawJSON .= "\"extraShareFields\": " . ((App::$param['extraShareFields']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"showDownloadOtionsInHeron\": " . ((App::$param['showDownloadOtionsInHeron']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"esIndexingInGui\": " . ((App::$param['esIndexingInGui']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"hideUngroupedLayers\": " . ((App::$param['hideUngroupedLayers']) ? "true": "false") . ",\n";
        $settingsRawJSON .= "\"staticMapHost\": \"" . (isset(App::$param['staticMapHost']) ? App::$param['staticMapHost'] : App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"geoserverHost\": \"" . ((App::$param['geoserverHost']) ? : App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"encoding\": \"" . ((App::$param['encoding']) ? : "UTF8") . "\",\n";
        $settingsRawJSON .= "\"osmConfig\": " . json_encode(App::$param['osmConfig']) . ",\n";
        $settingsRawJSON .= "\"customPrintParams\": " . json_encode(App::$param['customPrintParams']) . ",\n";
        $settingsRawJSON .= "\"gc2scheduler\": " . json_encode(App::$param['gc2scheduler']) . ",\n";
        $settingsRawJSON .= "\"mergeSchemata\": " . (isset(App::$param['mergeSchemata']) ? json_encode(App::$param['mergeSchemata']) : "null") . ",\n";
        $settingsRawJSON .= "\"showConflictOptions\": " . (json_encode(App::$param['showConflictOptions']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"showExtraLayerProperty\": " . (json_encode(App::$param['showExtraLayerProperty']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"extraLayerPropertyName\": " . (isset(App::$param['extraLayerPropertyName']) ? App::$param['extraLayerPropertyName'] : "null")  .",\n";
        $settingsRawJSON .= "\"clientConfig\": " . (isset(App::$param['clientConfig']) ? App::$param['clientConfig'] : "null")  .",\n";
        $settingsRawJSON .= "\"metaConfig\": " . (json_encode(App::$param['metaConfig']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"enablePrint\": " . (json_encode(App::$param['enablePrint']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"enableWorkflow\": " . (json_encode(App::$param['enableWorkflow']) ? : "null")  .",\n";
        $settingsRawJSON .= "\"hereApp\": " . json_encode(App::$param['hereApp']).",\n";
        $settingsRawJSON .= "\"subDomainsForTiles\": " . (isset(App::$param['subDomainsForTiles']) ? App::$param['subDomainsForTiles'] : "null")  .",\n";

        if ($settings = @file_get_contents(App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
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

        if (App::$param['bingApiKey']) $overallSettings["bingApiKey"] = App::$param['bingApiKey'];
        if (App::$param['googleApiKey']) $overallSettings["googleApiKey"] = App::$param['googleApiKey'];
        if (App::$param['digitalGlobeKey']) $overallSettings["digitalGlobeKey"] = App::$param['digitalGlobeKey'];
        if (App::$param['baseLayers']) $overallSettings["setBaseLayers"] = (App::$param['baseLayers']);
        if (App::$param['baseLayersCollector']) $overallSettings["setBaseLayersCollector"] = (App::$param['baseLayersCollector']);
        if (App::$param['mapAttribution']) $overallSettings["mapAttribution"] = App::$param['mapAttribution'];
        if (App::$param['vidiUrl']) $overallSettings["vidiUrl"] = App::$param['vidiUrl'];

        $locales = array("en_US", "da_DK", "fr_FR", "es_ES", "it_IT", "de_DE", "ru_RU");
        $arr = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $requestedLan = isset(App::$param['locale']) ? App::$param['locale'] : str_replace("-", "_", $arr[0]);
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
