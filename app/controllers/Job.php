<?php
namespace app\controllers;

use \app\inc\Input;
use \app\conf\Connection;

class Job extends \app\inc\Controller
{
    private $job;
    function __construct(){
        $this->job = new \app\models\Job();
    }
    public function get_test()
    {
        return $this->job->createCronJobs();
    }
    public function get_index()
    {
        return $this->job->getAll();
    }

    public function post_index()
    {
        return $this->job->newJob(json_decode(Input::get(null, true)));
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