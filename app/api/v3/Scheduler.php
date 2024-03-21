<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v3;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\models\Job;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
class Scheduler extends Controller
{
    private Job $job;
    private string $db;

    /**
     * @throws GC2Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->job = new Job();
        $this->db = Jwt::extractPayload(Input::getJwtToken())["data"]["database"];
    }

    /**
     * @return never
     */
    #[OA\Post(path: '/api/v3/scheduler/{jobid}', operationId: 'startSchedulerJob', tags: ['Scheduler'])]
    #[OA\Parameter(name: 'jobid', description: 'Job id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '876')]
    #[OA\Response(response: 202, description: 'Accepted')]
    public function post_index(): array
    {
        $id = Route::getParam("id");
        $body = Input::getBody();
        $data = json_decode($body);
        $force = !empty($data->force);
        $name = null;
        if (!empty($data->name)) {
            $name = $data->name;
        }
        if (is_numeric($id)) {
            $this->job->runJob((int)$id, $this->db, $name, $force);
        } else {
            $jobs = $this->job->getAll($this->db)['data'];
            foreach ($jobs as $job) {
                if ($job['schema'] == $id) {
                    $this->job->runJob($job['id'], $this->db, $name);
                }
            }
        }
        header("Location: /api/v4/scheduler");
        return ['code' => 202];
    }

    /**
     * @return array[]
     */
    #[OA\Get(path: '/api/v3/scheduler', operationId: 'getRunningSchedulerJobs', tags: ['Scheduler'])]
    #[OA\Response(response: 200, description: 'OK',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'uuid', description: 'Job uuid', type: 'string', example: 'my_index'),
            new OA\Property(property: 'id', description: 'Job id', type: 'string', example: 'my_index'),
            new OA\Property(property: 'pid', description: 'Job pid', type: 'string', example: 'my_index'),
        ], type: 'object'))]
    public function get_index(): array
    {
        $fromDb = $this->job->getAllStartedJobs($this->db);
        $cmd = "pgrep timeout";
        exec($cmd, $out);
        $res = [];
        // Find active pids
        foreach ($fromDb as $value) {
            if (in_array($value["pid"], $out)) {
                $res[] = ["uuid" => $value["uuid"], "pid" => $value["pid"], "id" => $value["id"], "name" => $value["name"]];
            }
        }
        return ["jobs" => $res];
    }
}