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
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\WorkflowItems;
use OpenApi\Attributes as OA;
use Override;

/**
 * The workflow items of a table: for each feature that has been edited under workflow, where
 * it stands (drafted, reviewed, published) and who signed it. PATCH on an item is the approval
 * step: it signs the feature with the caller's role and moves it to that role's status.
 *
 * Access is by workflow role, not by schema ownership: the database owner (who acts as
 * publisher) and sub-users with a role on the table.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "WorkflowItem",
    description: "Where one feature stands in the workflow of its table: its latest logged step.",
    required: ["gid", "version_gid", "status", "status_name", "operation", "user", "author", "reviewer", "publisher", "created"],
    properties: [
        new OA\Property(property: "gid", description: "The feature's primary key value. The id of the item in the path.", type: "integer", example: 42),
        new OA\Property(property: "version_gid", description: "The feature's version id (gc2_version_gid), the same across all versions of the feature.", type: "integer", example: 17),
        new OA\Property(property: "status", description: "1 drafted, 2 reviewed, 3 published.", type: "integer", enum: [1, 2, 3], example: 1),
        new OA\Property(property: "status_name", type: "string", enum: ["drafted", "reviewed", "published"], example: "drafted"),
        new OA\Property(property: "operation", description: "What the latest step was.", type: "string", enum: ["insert", "update", "delete"], example: "insert"),
        new OA\Property(property: "user", description: "Who made the latest step.", type: "string", example: "joe"),
        new OA\Property(property: "author", description: "Who signed as author, or null.", type: ["string", "null"], example: "joe"),
        new OA\Property(property: "reviewer", description: "Who signed as reviewer, or null.", type: ["string", "null"], example: null),
        new OA\Property(property: "publisher", description: "Who signed as publisher, or null.", type: ["string", "null"], example: null),
        new OA\Property(property: "created", description: "When the latest step was made.", type: "string", format: "date-time", example: "2026-11-27 10:15:00+00"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'PATCH', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/workflow-items/[gid]', scope: Scope::SUB_USER_ALLOWED)]
class WorkflowItem extends AbstractApi
{
    private string $schemaName;
    private string $tableName;
    /** @var ?list<string> */
    private ?array $gids = null;
    private string $role;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'workflow-items';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid}', operationId: 'getWorkflowItem', description: "Get the workflow items of a table, newest first. Without `gid` the list holds what the caller can still move forward: for a reviewer the drafted features, for a publisher (and the database owner) the drafted and reviewed ones; `all=true` lists every item. With `gid`, that item whatever its status. Needs a workflow role on the table or the database owner.", summary: 'Get workflow items', security: [['bearerAuth' => []]], tags: ['Workflow'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'gid', description: 'Primary key value of a feature, or a comma separated list. Omit to list.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '42')]
    #[OA\Parameter(name: 'all', description: 'List every item, not only those the caller can move forward.', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/WorkflowItem"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/WorkflowItem"))]))]
    #[OA\Response(response: 403, description: 'The caller has no role in the workflow of the table (NO_WORKFLOW_ROLE)')]
    #[OA\Response(response: 404, description: 'The table or an item does not exist (TABLE_NOT_FOUND, WORKFLOW_ITEM_NOT_FOUND)')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $model = new WorkflowItems(connection: $this->connection);
        if ($this->gids !== null) {
            $items = $model->items($this->schemaName, $this->tableName, gids: $this->gids);
            $found = array_column($items, 'gid');
            foreach ($this->gids as $gid) {
                if (!in_array((int)$gid, $found, true)) {
                    throw new GC2Exception("Workflow item not found: $gid", 404, null, 'WORKFLOW_ITEM_NOT_FOUND');
                }
            }
            return $this->getResponse($items, single: count($this->gids) === 1);
        }
        $all = filter_var(Input::get('all'), FILTER_VALIDATE_BOOLEAN);
        return $this->getResponse($model->items($this->schemaName, $this->tableName, $all ? null : $this->role));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid}', operationId: 'patchWorkflowItem', description: "Approve: sign the feature(s) with the caller's workflow role and move them to that role's status (author: drafted, reviewer: reviewed, publisher and the database owner: published). The state before the step is kept as an ended version. Takes no body. With a list of ids it is all or nothing.", summary: 'Approve workflow items', security: [['bearerAuth' => []]], tags: ['Workflow'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'gid', description: 'Primary key value of a feature, or a comma separated list.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '42')]
    #[OA\Response(response: 303, description: 'Approved')]
    #[OA\Response(response: 403, description: 'The caller has no role in the workflow of the table (NO_WORKFLOW_ROLE)')]
    #[OA\Response(response: 404, description: 'The table or a feature does not exist (TABLE_NOT_FOUND, FEATURE_NOT_FOUND)')]
    #[OA\Response(response: 409, description: "The feature is already at or past the caller's status (WORKFLOW_STATUS_CONFLICT), is an ended version (VERSION_ENDED), or the table has no workflow (WORKFLOW_NOT_ENABLED)")]
    #[AcceptableContentTypes(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        new WorkflowItems(connection: $this->connection)->approve(
            $this->schemaName, $this->tableName, $this->gids, (string)$this->route->jwt['data']['uid'], $this->role
        );
        return $this->patchResponse("/api/v4/schemas/$this->schemaName/tables/$this->tableName/workflow-items/", $this->gids);
    }

    #[Override]
    public function post_index(): Response
    {
        // Items come from edits through WFS-T and the Feature API
    }

    #[Override]
    public function put_index(): Response
    {
        // Items come from edits through WFS-T and the Feature API
    }

    #[Override]
    public function delete_index(): Response
    {
        // The log is not edited
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        [$this->schemaName, $this->tableName] = WorkflowRole::relation($this->route, $this->connection);
        $gid = $this->route->getParam('gid');
        if ($gid !== null && $gid !== '') {
            if (!preg_match('/^\d+(,\d+)*$/', $gid)) {
                throw new GC2Exception("Invalid id. Use a feature id or a comma separated list of ids", 400, null, 'INVALID_REQUEST');
            }
            $this->gids = explode(',', $gid);
        } elseif (Input::getMethod() === 'patch') {
            throw new GC2Exception("PATCH needs the id of the item(s) to approve", 400, null, 'INVALID_REQUEST');
        }
        $jwt = $this->route->jwt['data'];
        $this->role = new WorkflowItems(connection: $this->connection)->roleOf(
            $this->schemaName, $this->tableName, (string)$jwt['uid'], !empty($jwt['superUser'])
        );
        if ($this->role === 'none') {
            throw new GC2Exception("You don't have a role in the workflow of $this->schemaName.$this->tableName", 403, null, 'NO_WORKFLOW_ROLE');
        }
    }
}
