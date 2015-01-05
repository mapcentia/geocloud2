<?php
namespace app\controllers;

use \app\inc\Input;

class Job extends \app\inc\Controller
{
    private $job;

    function __construct()
    {
        // Prevent unauthorized use of gc2scheduler
        if (!\app\conf\App::$param["gc2scheduler"][$_SESSION["screen_name"]]){
            $code = "401";
            header("HTTP/1.0 {$code} " . \app\inc\Util::httpCodeText($code));
            die(\app\inc\Response::toJson(array(
                "success" => false,
                "message" => "Not allowed"
            )));
        }
        $this->job = new \app\models\Job();
    }

    public function get_index()
    {
        return $this->job->getAll($_SESSION['screen_name']);
    }

    public function post_index()
    {
        return $this->job->newJob(json_decode(Input::get(null, true)), $_SESSION['screen_name']);
    }

    public function put_index()
    {
        return $this->job->updateJob(json_decode(Input::get(null, true)));
    }

    public function delete_index()
    {
        return $this->job->deleteJob(json_decode(Input::get(null, true)));
    }
}