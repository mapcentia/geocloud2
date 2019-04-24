<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Input;
use \app\inc\Route;
use \GuzzleHttp\Client;

/**
 * Class Preparedstatement
 * @package app\api\v2
 */
class Preparedstatement extends \app\inc\Controller
{

    /**
     * @var \GuzzleHttp\Client
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
     * @return array
     */
    public function get_index(): array
    {
        // Get the URI params from request
        // /{user}
        $user = Route::getParam("user");
        $preparedstatement = new \app\models\Preparedstatement();

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
                    $arr = \GuzzleHttp\json_decode($q, true);
                    Input::setParams(
                        [
                            "uuid" => $arr["uuid"],
                            "params" => \GuzzleHttp\json_encode($arr["params"]), // Keep as JSON
                            "key" => $arr["key"],
                        ]
                    );
                } catch (\InvalidArgumentException $e) {
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 500;
                    return $response;
                }
            }
        }

        try {
            $statement = $preparedstatement->getByUuid(Input::get("uuid"));
        } catch (\TypeError $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 500;
            return $response;
        }

        if (!$statement["success"]) {
            return $statement;
        }
        $params = Input::get("params") ?:"{}";

        // Decode params
        try {
            $params = \GuzzleHttp\json_decode($params, true);
        } catch (\InvalidArgumentException $e) {
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
                    "key" => Input::get("key")
                ]
            );
            $esResponse = $this->client->post($url, ['body' => $body]);

        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getResponse()->getBody()->getContents();
            $response['code'] = $e->getCode();
            return $response;
        }

        $obj = $esResponse->getBody();
        $response['json'] = $obj;
        return $response;
    }

    public function post_index()
    {
        return $this->get_index();
    }
}
