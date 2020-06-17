<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v3;

use app\inc\Controller;
use app\inc\Input;

class Oauth extends Controller
{
    /**
     * @var \app\models\Session
     */
    private $session;

    public function __construct()
    {
        parent::__construct();
        $this->session = new \app\models\Session();
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v3/oauth",
     *   tags={"OAuth"},
     *   summary="Get token",
     *   @OA\RequestBody(
     *     description="OAuth password grant parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="grant_type",type="string", example="password"),
     *         @OA\Property(property="username",type="string", example="user@example.com"),
     *         @OA\Property(property="password",type="string", example="1234luggage"),
     *         @OA\Property(property="database",type="string", example="roads"),
     *         @OA\Property(property="client_id",type="string", example="xxxxxxxxxx"),
     *         @OA\Property(property="client_secret",type="string", example="xxxxxxxxxx")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="access_token",type="string", example="MTQ0NjJkZmQ5OTM2NDE1ZTZjNGZmZjI3"),
     *         @OA\Property(property="token_type",type="string", example="bearer"),
     *         @OA\Property(property="expires_in",type="integer",  example=3600),
     *         @OA\Property(property="refresh_token",type="string", example="IwOGYzYTlmM2YxOTQ5MGE3YmNmMDFkNTVk"),
     *         @OA\Property(property="scope",type="string", example="sql")
     *       )
     *     )
     *   )
     * )
     */
    public function post_token() {
        $data = json_decode(Input::getBody(), true) ? : [];
        if (!empty($data["username"]) && !empty($data["password"])) {
            try {
                return $this->session->start($data["username"], $data["password"], null, $data["database"], true);
            } catch (\TypeError $exception) {
                return [
                    "error" => "invalid_request",
                    "error_description" => $exception->getMessage(),
                    "code" => 500
                ];
            } catch (\Exception $exception) {
                return [
                    "error" => "invalid_request",
                    "error_description" => $exception->getMessage(),
                    "code" => 500
                ];
            }
        } else {
            return [
                "error" => "invalid_request",
                "error_description" => "Username or password parameter was not provided",
                "code" => 400
            ];
        }
    }
}