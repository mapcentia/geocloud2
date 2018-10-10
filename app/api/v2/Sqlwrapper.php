<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Route;
use \app\inc\Input;
use \GuzzleHttp\Client;

include_once(__DIR__ . "../../../libs/PEAR/XML/Unserializer.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Serializer.php");

/**
 * Class Qgis
 * @package app\api\v1
 */
class Sqlwrapper extends \app\inc\Controller
{
    /**
     * Sqlwrapper constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function post_index()
    {
        // Init the Guzzle client
        $client = new Client([
            'timeout' => 10.0,
        ]);

        $response = [];

        $rasterData = null;

        $data = explode(",", Input::get("custom_data"));
        $schema = explode(".", $data[0])[0];
        $db = Route::getParam("user");

        if (sizeof($data) == 9) {
            $getFeatureInfoUrl = "http://127.0.0.1/ows/{$db}/{$schema}?LAYERS={$data[0]}&QUERY_LAYERS={$data[0]}&STYLES=&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetFeatureInfo&BBOX={$data[5]},{$data[6]},{$data[7]},{$data[8]}&FEATURE_COUNT=1&HEIGHT={$data[2]}&WIDTH={$data[1]}&FORMAT=image%2Fpng&INFO_FORMAT=application%2Fvnd.ogc.gml&SRS=EPSG%3A4326&X={$data[3]}&Y={$data[4]}";
            //die($getFeatureInfoUrl);
            try {
                $res = $client->get($getFeatureInfoUrl);
            } catch (\Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }

            $xml = $res->getBody();

            $unserializer = new \XML_Unserializer(array(
                'parseAttributes' => false,
                'typeHints' => false
            ));

            // Unserialize the transaction response
            $status = $unserializer->unserialize($xml);

            // Check if transaction response could be unserialized
            if (gettype($status) != "boolean" && $status !== true) {
                $response['success'] = false;
                $response['message'] = "Could not unserialize transaction response";
                $response['code'] = 500;
                return $response;
            }

            // Get unserialized data
            $arr = $unserializer->getUnserializedData();

            // Check if WFS returned a service exception
            if (isset($arr["ServiceException"])) {
                $response['success'] = false;
                $response['message'] = $arr;
                $response['code'] = "500";
                return $response;
            }

            $rasterData = ($arr["{$data[0]}_layer"]["{$data[0]}_feature"]);

        }

        $form = [
            "q" => Input::get("q"),
            "base64" => Input::get("base64"),
            "srs" => Input::get("srs"),
            "lifetime" => Input::get("lifetime"),
            "client_encoding" => Input::get("client_encoding"),
            "format" => Input::get("format"),
            "key" => Input::get("key"),
        ];

        $url = "http://127.0.0.1/api/v2/sql/" . $db;

        // POST the transaction
        try {
            $res = $client->post($url, ['form_params' => $form]);
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $result = json_decode($res->getBody(), true);

        if ($rasterData) {
            //$result["features"][0]["properties"] = [];
            $result["features"][0]["properties"]["value_0"] = $rasterData["value_0"];
            $result["features"][0]["properties"]["x"] = $rasterData["x"];
            $result["features"][0]["properties"]["y"] = $rasterData["y"];
            $result["features"][0]["properties"]["class"] = $rasterData["class"];
            $result["features"][0]["properties"]["red"] = $rasterData["red"];
            $result["features"][0]["properties"]["green"] = $rasterData["green"];
            $result["features"][0]["properties"]["blue"] = $rasterData["blue"];
        }

        $response['json'] = json_encode($result);
        return $response;
    }
}