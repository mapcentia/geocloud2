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
use app\inc\Input;
use app\inc\Route2;
use app\models\WorkflowItems;
use OpenApi\Attributes as OA;
use Override;

/**
 * The workflow log (settings.workflow) across tables, for a client's "Workflow" view: every
 * step logged by an edit or an approval on a workflow table, newest first, as it is.
 *
 * An entry carries the schema, table and gid of its feature, which is the address of the
 * approval step: PATCH /api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid} sends that
 * feature on in the flow ("touch").
 *
 * Read only. The database owner reads the whole log; a sub-user reads the entries of the tables
 * they have a role on, so a sub-user without any role gets an empty list.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "WorkflowLogEntry",
    description: "One logged workflow step of a feature.",
    required: ["id", "schema", "table", "gid", "version_gid", "status", "status_name", "operation", "user", "author", "reviewer", "publisher", "created", "roles"],
    properties: [
        new OA\Property(property: "id", description: "Id of the log entry.", type: "integer", example: 311),
        new OA\Property(property: "schema", description: "Schema of the feature's table.", type: "string", example: "my_schema"),
        new OA\Property(property: "table", description: "The feature's table.", type: "string", example: "my_table"),
        new OA\Property(property: "gid", description: "The feature's primary key value. With `schema` and `table`: PATCH /api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid} approves the feature.", type: "integer", example: 42),
        new OA\Property(property: "version_gid", description: "The feature's version id (gc2_version_gid), the same across all versions of the feature.", type: "integer", example: 17),
        new OA\Property(property: "status", description: "The status this step gave the feature: 1 drafted, 2 reviewed, 3 published.", type: "integer", enum: [1, 2, 3], example: 1),
        new OA\Property(property: "status_name", type: "string", enum: ["drafted", "reviewed", "published"], example: "drafted"),
        new OA\Property(property: "operation", description: "What the step was.", type: "string", enum: ["insert", "update", "delete"], example: "insert"),
        new OA\Property(property: "user", description: "Who made the step.", type: "string", example: "joe"),
        new OA\Property(property: "author", description: "Who had signed as author after this step, or null.", type: ["string", "null"], example: "joe"),
        new OA\Property(property: "reviewer", description: "Who had signed as reviewer after this step, or null.", type: ["string", "null"], example: null),
        new OA\Property(property: "publisher", description: "Who had signed as publisher after this step, or null.", type: ["string", "null"], example: null),
        new OA\Property(property: "created", description: "When the step was made.", type: "string", format: "date-time", example: "2026-11-27 10:15:00+00"),
        new OA\Property(property: "roles", description: "The workflow roles on the table when the step was made: sub-user => role.", type: "object", additionalProperties: new OA\AdditionalProperties(type: "string"), example: ["joe" => "author", "ann" => "reviewer"]),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'OPTIONS'])]
#[Controller(route: 'api/v4/workflow-log', scope: Scope::SUB_USER_ALLOWED)]
class WorkflowLog extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'workflow-log';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/workflow-log', operationId: 'getWorkflowLog', description: "Get the workflow log across tables, newest first: every step logged by an edit or an approval on a workflow table. The database owner gets the whole log; a sub-user the entries of the tables they have a workflow role on (none: an empty array). To send a feature on in the flow, approve it with PATCH /api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid}, using the entry's schema, table and gid.", summary: 'Get the workflow log', security: [['bearerAuth' => []]], tags: ['Workflow'])]
    #[OA\Parameter(name: 'schema', description: 'Only entries of tables in this schema.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'limit', description: 'Number of entries to return (1-1000).', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 100, maximum: 1000, minimum: 1), example: 100)]
    #[OA\Parameter(name: 'offset', description: 'Number of entries to skip.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0, minimum: 0), example: 0)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/WorkflowLogEntry")))]
    #[OA\Response(response: 400, description: 'Missing or invalid token, or a malformed parameter (INVALID_REQUEST)')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $jwt = $this->route->jwt['data'];
        $schema = Input::get('schema');
        return $this->getResponse(new WorkflowItems(connection: $this->connection)->log(
            (string)$jwt['uid'],
            !empty($jwt['superUser']),
            is_string($schema) && $schema !== '' ? $schema : null,
            (int)(Input::get('limit') ?? 100),
            (int)(Input::get('offset') ?? 0),
        ));
    }

    #[Override]
    public function post_index(): Response
    {
        // Read only
    }

    #[Override]
    public function put_index(): Response
    {
        // Read only
    }

    #[Override]
    public function patch_index(): Response
    {
        // Approval is per table: /api/v4/schemas/{schema}/tables/{table}/workflow-items/{gid}
    }

    #[Override]
    public function delete_index(): Response
    {
        // Read only
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $schema = Input::get('schema');
        if ($schema !== null && $schema !== '' && (!is_string($schema) || !preg_match('/^[\p{L}\p{N}_\-]+$/u', $schema))) {
            throw new GC2Exception("Invalid schema name", 400, null, 'INVALID_REQUEST');
        }
        foreach (['limit' => 1, 'offset' => 0] as $name => $min) {
            $value = Input::get($name);
            if ($value !== null && $value !== '' && (!is_string($value) || !ctype_digit($value) || (int)$value < $min)) {
                throw new GC2Exception("Invalid $name", 400, null, 'INVALID_REQUEST');
            }
        }
    }
}
