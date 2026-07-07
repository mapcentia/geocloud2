<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
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
use app\models\Func as FuncModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;

/**
 * TypeScript interface for functions, generated from schemas inferred by
 * dry-runs (POST /api/v4/functions/{name}/invocations?dry=true). Separate
 * route from the Func controller so it isn't shadowed by functions/{name}.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/function-interfaces', scope: Scope::SUB_USER_ALLOWED)]
class FunctionInterface extends AbstractApi
{
    private FuncModel $func;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->func = new FuncModel($connection);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/function-interfaces', operationId: 'getFunctionTypeScript', description: "Get a TypeScript interface for functions, inferred from dry-runs.", tags: ['Functions'])]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType(mediaType: "text/plain"))]
    #[AcceptableAccepts(['text/plain', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        return $this->textResponse($this->func->getFunctionsTypeScript());
    }

    public function put_index(): Response
    {
    }

    public function post_index(): Response
    {
    }

    public function patch_index(): Response
    {
    }

    public function delete_index(): Response
    {
    }

    public function validate(): void
    {
    }
}
