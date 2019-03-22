<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Input;

/**
 * Class Session
 * @package app\api\v1
 */
class Session extends \app\inc\Controller
{
    /**
     * @var \app\models\Session
     */
    private $session;

    /**
     * Session constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->session = new \app\models\Session();
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/v2/session/start",
     *   tags={"session"},
     *   summary="Starts the session",
     *   @OA\Parameter(
     *     name="user",
     *     in="query",
     *     description="User name",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="password",
     *     in="query",
     *     description="Password",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="schema",
     *     in="query",
     *     description="Schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_start(): array
    {
        try {
            return $this->session->start(Input::get("user"), Input::get("password"), Input::get("schema"));
        } catch (\TypeError $exception) {
            return [
                "success" => false,
                "error" => $exception->getMessage(),
                "code" => 500
            ];
        }
    }

    /**
     * @return array
     * 
     * @OA\Post(
     *   path="/v2/session/start",
     *   tags={"session"},
     *   summary="Starts the session",
     *   @OA\RequestBody(
     *     description="Credentials",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="user",type="string"),
     *         @OA\Property(property="password",type="string"),
     *         @OA\Property(property="schema",type="string"),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function post_start(): array
    {
        $data = json_decode(Input::getBody(), true) ? : [];
        Input::setParams(
            [
                "user" => $data["user"],
                "password" => $data["password"],
                "schema" => $data["schema"],
            ]
        );
        return $this->get_start();
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/v2/session",
     *   tags={"session"},
     *   summary="Checks the session",
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_index()
    {
        return $this->session->check();
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/v2/session/stop",
     *   tags={"session"},
     *   summary="Destroys the session",
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_stop()
    {
        return $this->session->stop();
    }
}