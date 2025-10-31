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
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Route2;
use app\models\Preparedstatement as PreparedstatementModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;


/**
 * Class Method
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/interfaces', scope: Scope::SUB_USER_ALLOWED)]
class RpcInterface extends AbstractApi
{
    private PreparedstatementModel $pres;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/interfaces', operationId: 'getTypeScript', description: "Get TypeScript API", tags: ['Methods'])]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Method"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['text/plain', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $pres = new PreparedstatementModel($this->connection);
        return $this->textResponse($pres->getTypeScriptApi());

    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function post_index(): Response
    {
        // TODO: Implement post_index() method.
    }

    public function patch_index(): Response
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }
}

