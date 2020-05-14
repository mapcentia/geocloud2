<?php


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

    public function post_token() {
        $data = json_decode(Input::getBody(), true) ? : [];
        if (!empty($data["username"]) && !empty($data["password"])) {
            try {
                return $this->session->start($data["username"], $data["password"], null, $data["database"], true);
            } catch (\TypeError $exception) {
                return [
                    "success" => false,
                    "error" => $exception->getMessage(),
                    "code" => 500
                ];
            }
        } else {
            return [
                "success" => false,
                "error" => "User or password parameter was not provided",
                "code" => 400
            ];

        }

    }
}