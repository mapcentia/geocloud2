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
    public function getSettings()
    {
        $settingsRawJSON = '';
        $settingsRawJSON .= "{";
        $settingsRawJSON .= "\"leafletDraw\": " . (!empty(App::$param['leafletDraw']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"reverseLayerOrder\": " . (!empty(App::$param['reverseLayerOrder']) ? "true" : "false") . ",\n";

        $settingsRawJSON .= "\"epsg\": \"" . (!empty(App::$param['epsg']) ? App::$param['epsg'] : "4326") . "\",\n";
        $settingsRawJSON .= "\"extraShareFields\": " . (!empty(App::$param['extraShareFields']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"showDownloadOtionsInHeron\": " . ((App::$param['showDownloadOtionsInHeron']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"esIndexingInGui\": " . (!empty(App::$param['esIndexingInGui']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"hideUngroupedLayers\": " . (!empty(App::$param['hideUngroupedLayers']) ? "true" : "false") . ",\n";
        $settingsRawJSON .= "\"staticMapHost\": \"" . (!empty(App::$param['staticMapHost']) ? App::$param['staticMapHost'] : App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"geoserverHost\": \"" . (!empty(App::$param['geoserverHost']) ? App::$param['geoserverHost'] : App::$param['host']) . "\",\n";
        $settingsRawJSON .= "\"encoding\": \"" . (!empty(App::$param['encoding']) ? App::$param['encoding'] : "UTF8") . "\",\n";
        $settingsRawJSON .= "\"osmConfig\": " . (!empty(App::$param['osmConfig']) ? json_encode(App::$param['osmConfig']) : "null") . ",\n";
        $settingsRawJSON .= "\"customPrintParams\": " . (!empty(App::$param['customPrintParams']) ? json_encode(App::$param['customPrintParams']) : "null") . ",\n";
        $settingsRawJSON .= "\"gc2scheduler\": " . (!empty(App::$param['gc2scheduler']) ? json_encode(App::$param['gc2scheduler']) : "null") . ",\n";
        $settingsRawJSON .= "\"mergeSchemata\": " . (!empty(App::$param['mergeSchemata']) ? json_encode(App::$param['mergeSchemata']) : "null") . ",\n";
        $settingsRawJSON .= "\"showConflictOptions\": " . (!empty(App::$param['showConflictOptions']) ? json_encode(App::$param['showConflictOptions']) : "null") . ",\n";
        $settingsRawJSON .= "\"showExtraLayerProperty\": " . (!empty(App::$param['showExtraLayerProperty']) ? json_encode(App::$param['showExtraLayerProperty']) : "null") . ",\n";
        $settingsRawJSON .= "\"extraLayerPropertyName\": " . (!empty(App::$param['extraLayerPropertyName']) ? json_encode(App::$param['extraLayerPropertyName']) : "null") . ",\n";
        $settingsRawJSON .= "\"clientConfig\": " . (!empty(App::$param['clientConfig']) ? json_encode(App::$param['clientConfig']) : "null") . ",\n";
        $settingsRawJSON .= "\"metaConfig\": " . (!empty(App::$param['metaConfig']) ? json_encode(App::$param['metaConfig']) : "null") . ",\n";
        $settingsRawJSON .= "\"enablePrint\": " . (!empty(App::$param['enablePrint']) ? json_encode(App::$param['enablePrint']) : "null") . ",\n";
        $settingsRawJSON .= "\"enableWorkflow\": " . (!empty(App::$param['enableWorkflow']) ? json_encode(App::$param['enableWorkflow']) : "null") . ",\n";
        $settingsRawJSON .= "\"hereApp\": " . (!empty(App::$param['hereApp']) ? json_encode(App::$param['hereApp']) : "null") . ",\n";
        $settingsRawJSON .= "\"vidiUrl\": " . (!empty(App::$param['vidiUrl']) ? json_encode(App::$param['vidiUrl'], JSON_UNESCAPED_SLASHES) : "null") . ",\n";
        $settingsRawJSON .= "\"subDomainsForTiles\": " . (!empty(App::$param['subDomainsForTiles']) ? json_encode(App::$param['subDomainsForTiles']) : "null") . ",\n";
        $settingsRawJSON .= "\"colorPalette\": " . (!empty(App::$param['colorPalette']) ? json_encode(App::$param['colorPalette']) : "null") . ",\n";
        if ($settings = @file_get_contents(App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
            $settingsRawJSON .= "\"es_settings\": " . $settings . ",\n";
        }

        foreach (\app\controllers\Mapcache::getGrids() as $k => $v) {
            $gridNames[] = $k;
        }

        $settingsRawJSON .= "\"grids\": " . (json_encode($gridNames) ?: "null") . "\n";

        $settingsRawJSON .= "}";

        $settingsMainParsed = json_decode($settingsRawJSON, true);

        $overallSettings = ['gc2Options' => $settingsMainParsed];

        if (!empty(App::$param['bingApiKey'])) $overallSettings["bingApiKey"] = App::$param['bingApiKey'];
        if (!empty(App::$param['googleApiKey'])) $overallSettings["googleApiKey"] = App::$param['googleApiKey'];
        if (!empty(App::$param['digitalGlobeKey'])) $overallSettings["digitalGlobeKey"] = App::$param['digitalGlobeKey'];
        if (!empty(App::$param['baseLayers'])) $overallSettings["setBaseLayers"] = (App::$param['baseLayers']);
        if (!empty(App::$param['baseLayersCollector'])) $overallSettings["setBaseLayersCollector"] = (App::$param['baseLayersCollector']);
        if (!empty(App::$param['mapAttribution'])) $overallSettings["mapAttribution"] = App::$param['mapAttribution'];

        $locales = array("en_US", "da_DK", "fr_FR", "es_ES", "it_IT", "de_DE", "ru_RU");
        $arr = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $requestedLan = !empty(App::$param['locale']) ? App::$param['locale'] : str_replace("-", "_", $arr[0]);
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
