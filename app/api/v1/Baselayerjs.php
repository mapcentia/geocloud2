<?php
/**
 * Writes out JavaScript object with config params, which a web client can use.
 *
 * Long description for file (if any)...
 *  
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *  
 */

namespace app\api\v1;

use app\inc\Controller;

/**
 * Class Baselayerjs
 * @package app\api\v1
 */
class Baselayerjs extends Controller
{
    /**
     * Baselayerjs constructor.
     * Outputs the JavaScript code, making settings accessible as global variables.
     */
    function __construct()
    {
        parent::__construct();

        $baselayerjsModel = new \app\models\Baselayerjs();
        $overallSettings = $baselayerjsModel->getSettings();
        if ($_SERVER['QUERY_STRING'] === 'format=json') {
            header("Content-Type: application/json");
            echo json_encode($overallSettings, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } else {
            header("Content-Type: application/javascript");
            echo "window.gc2Options = " . json_encode($overallSettings["gc2Options"], JSON_UNESCAPED_SLASHES) . ";\n";

            if ($overallSettings['bingApiKey']) {
                echo "window.bingApiKey = '" . $overallSettings['bingApiKey'] . "';\n";
            }

            if ($overallSettings['googleApiKey']) {
                echo "window.googleApiKey = '" . $overallSettings['googleApiKey'] . "';\n";
            }

            if ($overallSettings['digitalGlobeKey']) {
                echo "window.digitalGlobeKey = '" . $overallSettings['digitalGlobeKey'] . "';\n";
            }

            if ($overallSettings['setBaseLayers']) {
                echo "window.setBaseLayers = " . json_encode($overallSettings['setBaseLayers'], JSON_UNESCAPED_SLASHES) . ";\n";
            }

            if ($overallSettings['setBaseLayersCollector']) {
                echo "window.setBaseLayersCollector = " . json_encode($overallSettings['setBaseLayersCollector'], JSON_UNESCAPED_SLASHES) . ";\n";
            }

            if ($overallSettings['mapAttribution']) {
                echo "window.mapAttribution = '" . $overallSettings['mapAttribution'] . "';\n";
            }

            if ($overallSettings['gc2Al']) {
                echo "window.gc2Al='" . $overallSettings['gc2Al'] . "'\n";
            }
        }

        exit();
    }

    public function get_index() { }
}
