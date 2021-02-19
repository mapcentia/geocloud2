<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Route;
use app\models\Preparedstatement as PreparedstatementModel;
use GuzzleHttp\Client;
use InvalidArgumentException;
use TypeError;
use GuzzleHttp\Exception\RequestException;



/**
 * Class Preparedstatement
 * @package app\api\v2
 */
class Preparedstatement extends Controller
{

    /**
     * @var Client
     */
    protected $client;

    /**
     * Preparedstatement constructor.
     */
    function __construct()
    {
        parent::__construct();

        // Init the Guzzle client
        $this->client = new Client([
            'timeout' => 20.0,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * @return array<mixed>
     */
    public function get_index(): array
    {
        // Get the URI params from request
        // /{user}
        $user = Route::getParam("user");
        $preparedstatement = new PreparedstatementModel();

        // Check if body and if so, when set input params
        // ==============================================
        if (Input::getBody()) {

            $q = Input::getBody();

            // If JSON body when set GET input params
            // ======================================
            if ($q != null) {

                // Set input params from JSON
                // ==========================
                try {
                    $arr = json_decode($q, true);
                    if (!isset($arr["params"])) {
                        $arr["params"] = [];
                    }
                    Input::setParams(
                        [
                            "uuid" => $arr["uuid"],
                            "params" => json_encode($arr["params"]), // Keep as JSON
                            "key" => $arr["key"],
                            "srs" => $arr["srs"],
                        ]
                    );
                } catch (InvalidArgumentException $e) {
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 500;
                    return $response;
                }
            }
        }

        try {
            $statement = $preparedstatement->getByUuid(Input::get("uuid"));
        } catch (TypeError $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 500;
            return $response;
        }

        if (!$statement["success"]) {
            return $statement;
        }


        // Decode params
        try {
            $params = json_decode(Input::get("params") ?: "{}", true);
        } catch (InvalidArgumentException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 500;
            return $response;
        }

        $sql = strtr($statement["data"]["statement"], $params);

        try {
            $url = "http://127.0.0.1/api/v2/sql/{$user}";
            $body = json_encode(
                [
                    "q" => $sql,
                    "key" => Input::getApiKey() ?? Input::get("key"),
                    "srs" => Input::get("srs") ?: "4326",
                ]
            );
            $esResponse = $this->client->post($url, ['body' => $body]);

        } catch (RequestException $e) {
            $response['success'] = false;
            $response['message'] = $e->getResponse()->getBody()->getContents();
            $response['code'] = $e->getCode();
            return $response;
        }

        $obj = $esResponse->getBody();
        $response['json'] = $obj;
        return $response;
    }

    /**
     * @return array<mixed>
     */
    public function post_index(): array
    {
        return $this->get_index();
    }
}
