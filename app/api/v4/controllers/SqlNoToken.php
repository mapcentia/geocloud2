<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Statement;
use app\inc\Util;
use app\models\Setting;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0)]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/sql/database/{database}', scope: Scope::PUBLIC)]
class SqlNoToken extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct(connection: $connection);
        $this->resource = 'sqlNoToken';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/sql/database/{database}', operationId: 'postSqlNoToken', description: "Run SQL statements without a token.", tags: ['Sql'])]
    #[OA\Parameter(name: 'database', description: 'Database name to use.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'mydb')]
    #[OA\RequestBody(description: 'SQL statement(s) to run.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sql"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 500, description: 'Internal error. Most likely an SQL error.')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_index(): Response
    {
        $db = $this->route->getParam('database');
        $this->connection->database = $db;
        $defaultUser = new \app\models\User(
            parentDb: $db
        )->getDefaultUser();
        $this->route->jwt['data']['superUser'] = false;
        $this->route->jwt['data']['uid'] = $defaultUser['screenname'];
        $this->route->jwt['data']['userGroup'] = $defaultUser['usergroup'];
        $this->route->jwt['data']['database'] = $db;
        return new Sql($this->route, $this->connection)->post_index();
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST without request body is not allowed.", 400);
        }
        $decodedBody = json_decode($body);
        if (is_array($decodedBody)) {
            foreach ($decodedBody as $value) {
                $this->validateRequest(Sql::getAssert(), json_encode($value), Input::getMethod());
            }
        } else {
            $this->validateRequest(Sql::getAssert(), $body, Input::getMethod());
        }
    }

    public function get_index(): Response
    {
    }

    public function patch_index(): Response
    {
    }

    public function delete_index(): Response
    {
    }

    public function post_database(): Response
    {
    }

    public function put_index(): Response
    {
    }

}
