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
     */
    public function get_index()
    {
        return $this->session->check();
    }

    /**
     * @return array
     */
    public function get_stop()
    {
        return $this->session->stop();
    }
}