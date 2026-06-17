<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Response;
use app\inc\Util;
use app\models\Job as JobModel;

class Job extends Controller
{
    /**
     * @var JobModel
     */
    private JobModel $job;

    function __construct()
    {
        parent::__construct();
        // Prevent unauthorized use of gc2scheduler
        if (!App::$param["gc2scheduler"][$_SESSION["screen_name"]] && empty(App::$param["gc2scheduler"]["*"])) {
            $code = "401";
            header("HTTP/1.0 $code " . Util::httpCodeText($code));
            die(Response::toJson(array(
                "success" => false,
                "message" => "Not allowed"
            )));
        }
        $this->job = new JobModel();
    }

    /**
     * @return  array
     */
    public function get_index(): array
    {
        return $this->job->getAll($_SESSION['screen_name']);
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->job->newJob(json_decode(Input::get(null, true)), $_SESSION['screen_name']);
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->job->updateJob(json_decode(Input::get(null, true)));
    }

    /**
     * @return array
     */
    public function delete_index(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->job->deleteJob(json_decode(Input::get(null, true)));
    }

    /**
     * @return array
     */
    public function get_run(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        if (!$response['success']) {
            return $response;
        }
        $id = (int)Input::getPath()->part(4);
        if (empty($id)) {
            return [
                "success" => false,
                "message" => "Id missing",
            ];
        }
        $this->job->runJob($id, $_SESSION['screen_name'], 'Started from web-ui', false, null, true);
        return ["success" => true ];
    }
}