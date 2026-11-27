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
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Layer;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;


#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0,)]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: '/api/v4/meta/database/{database}/[query]', scope: Scope::PUBLIC)]
class MetaPublic extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'relations';
    }


    /**
     * @return Response
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    #[OA\Get(path: '/api/v4/meta/database/{database}/{query}', operationId: 'getMetaDataPublic', summary: 'Get relation metadata without a token', tags: ['Metadata'])]
    #[OA\Parameter(name: 'query', description: 'Schema-qualified relation name, schema name, or tag (tag:name). Comma-separated values are supported.', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'noRestriction', description: 'Leaves out column restrictions on the returned metadata. Restrictions can be quite extensive.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Meta"))]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $db = $this->route->getParam('database');
        $this->connection->database = $db;
        $layers = new Layer(connection: $this->connection);
        if (!in_array(Input::get('noRestriction'), ['', 'true', '1', 't'], true)) {
            $restriction = true; // Leave out column restrictions
        } else {
            $restriction = false;
        }
        $res = $layers->getAll(
            db:$db,
            auth: false,
            query: $this->route->getParam("query"),
            parse: true,
            lookupForeignTables: false,
            restriction: $restriction,
        );
        $rows = $res["data"];
        $r = Meta::processRows($rows);
        return new GetResponse(data: ['relations' => $r]);
    }

    public function patch_index(): Response
    {

    }

    #[Override]
    public function validate(): void
    {

    }

    #[Override]
    public function post_index(): Response
    {
        // TODO: Implement post_index() method.
    }

    #[Override]
    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.

    }
}
