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
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->job->newJob(json_decode(Input::get(null, true)), $_SESSION['screen_name']);
    }

    public function put_index()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->job->updateJob(json_decode(Input::get(null, true)));
    }

    public function delete_index()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->job->deleteJob(json_decode(Input::get(null, true)));
    }

    public function get_run()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->job->runJob(Input::getPath()->part(4), $_SESSION['screen_name']);
    }
}