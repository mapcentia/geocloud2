<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Setting;
use app\opensearch\SettingsComposer;
use OpenApi\Attributes as OA;

/**
 * v4 Search Settings API on settings.viewer's `search.analysis` block.
 *
 * Database-level OpenSearch analysis settings (analyzers, tokenizers, filters
 * used when building the search index). Owner-only: a sub user is rejected
 * with 403 NOT_OWNER. Reading returns the stored analysis, or the static
 * default when the database has none set. Writing does not touch OpenSearch
 * itself — it only persists the configuration to be applied on future index
 * builds.
 *
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'PUT', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/search/settings', scope: Scope::SUB_USER_ALLOWED)]
class SearchSettings extends AbstractApi
{
    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'search settings';
    }

    private function assertOwner(): void
    {
        if (empty($this->route->jwt['data']['superUser'])) {
            throw new GC2Exception("Only the database owner can manage search settings", 403, null, "NOT_OWNER");
        }
    }

    public function validate(): void
    {
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/search/settings', operationId: 'getSearchSettings', description: "Get the per-database OpenSearch analysis block (the default when none is set).", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'The analysis block')]
    public function get_index(): Response
    {
        $this->assertOwner();
        $analysis = (new Setting($this->connection))->getSearchAnalysis() ?? SettingsComposer::defaultAnalysis();
        return $this->getResponse(['analysis' => $analysis]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Put(path: '/api/v4/search/settings', operationId: 'putSearchSettings', description: "Set the per-database OpenSearch analysis block. Applies to indexes built after this call.", tags: ['Search'])]
    #[AcceptableContentTypes(['application/json'])]
    #[OA\Response(response: 200, description: 'Saved')]
    public function put_index(): Response
    {
        $this->assertOwner();
        $body = json_decode((string)Input::getBody(), true);
        if (!is_array($body) || !isset($body['analysis']) || !is_array($body['analysis'])) {
            throw new GC2Exception("Body must be a JSON object with an 'analysis' object", 400, null, "INVALID_DATA");
        }
        (new Setting($this->connection))->updateSearchAnalysis($body['analysis']);
        return $this->getResponse(['analysis' => $body['analysis']]);
    }

    /**
     * @throws GC2Exception
     */
    public function post_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    /**
     * @throws GC2Exception
     */
    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    /**
     * @throws GC2Exception
     */
    public function delete_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
