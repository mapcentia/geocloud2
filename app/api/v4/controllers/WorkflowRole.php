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
use app\inc\Model;
use app\inc\Route2;
use app\models\WorkflowItems;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The workflow roles of sub-users on a table. A role decides how far a sub-user's edits and
 * approvals take a feature: author (drafted), reviewer (reviewed), publisher (published).
 * The database owner needs no role and acts as publisher.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "WorkflowRole",
    description: "The workflow role of one sub-user on a table.",
    required: ["subuser", "role"],
    properties: [
        new OA\Property(property: "subuser", title: "Sub-user", description: "Sub-user name.", type: "string", example: "joe"),
        new OA\Property(property: "role", title: "Role", description: "author: edits become drafts. reviewer: edits and approvals count as reviewed. publisher: edits and approvals are published. none: no part in the workflow; the sub-user sees published features only and cannot edit the table.", type: "string", enum: ["none", "author", "reviewer", "publisher"], example: "reviewer"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'PATCH', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/workflow-roles', scope: Scope::SUB_USER_ALLOWED)]
class WorkflowRole extends AbstractApi
{
    private string $schemaName;
    private string $tableName;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'workflow-roles';
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/workflow-roles', operationId: 'getWorkflowRoles', description: "Get the workflow roles of the sub-users on a table. Only sub-users that have been given a role are listed.", summary: 'Get workflow roles', security: [['bearerAuth' => []]], tags: ['Workflow'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/WorkflowRole")))]
    #[OA\Response(response: 404, description: 'The table does not exist (TABLE_NOT_FOUND)')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $roles = new WorkflowItems(connection: $this->connection)->roles($this->schemaName, $this->tableName);
        $rows = [];
        foreach ($roles as $subuser => $role) {
            $rows[] = ['subuser' => (string)$subuser, 'role' => $role];
        }
        return $this->getResponse($rows);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/workflow-roles', operationId: 'patchWorkflowRoles', description: "Set the workflow role of one or more sub-users on a table. Sub-users not named keep their role. Only the database owner can set roles.", summary: 'Set workflow roles', security: [['bearerAuth' => []]], tags: ['Workflow'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\RequestBody(description: 'Role(s) to set.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/WorkflowRole"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/WorkflowRole"))]))]
    #[OA\Response(response: 303, description: 'Roles updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 403, description: 'Only the database owner can set roles (SUPER_USER_ONLY)')]
    #[OA\Response(response: 404, description: 'The table does not exist (TABLE_NOT_FOUND)')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        if (empty($this->route->jwt['data']['superUser'])) {
            throw new GC2Exception("Only the database owner can set workflow roles", 403, null, 'SUPER_USER_ONLY');
        }
        $data = json_decode(Input::getBody(), true);
        $roles = [];
        foreach (array_is_list($data) ? $data : [$data] as $item) {
            $roles[$item['subuser']] = $item['role'];
        }
        new WorkflowItems(connection: $this->connection)->setRoles($this->schemaName, $this->tableName, $roles);
        return $this->patchResponse("/api/v4/schemas/$this->schemaName/tables/$this->tableName/workflow-roles");
    }

    #[Override]
    public function post_index(): Response
    {
        // Roles are set with PATCH
    }

    #[Override]
    public function put_index(): Response
    {
        // Roles are set with PATCH
    }

    #[Override]
    public function delete_index(): Response
    {
        // A role is removed by setting it to none
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        [$this->schemaName, $this->tableName] = self::relation($this->route, $this->connection);
        if (Input::getMethod() === 'patch') {
            $this->validateRequest(self::getAssert(), Input::getBody(), 'patch');
        }
    }

    public static function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'subuser' => new Assert\Required([new Assert\Type('string'), new Assert\NotBlank()]),
            'role' => new Assert\Required([new Assert\Choice(choices: WorkflowItems::ROLES)]),
        ]);
    }

    /**
     * The single, existing table a workflow route is about. The workflow routes do not go
     * through initiate(): who may do what on them is decided by the workflow role, not by
     * schema ownership (a reviewer works in the owner's schema).
     *
     * @return array{0: string, 1: string} schema and table
     * @throws GC2Exception
     */
    public static function relation(Route2 $route, Connection $connection): array
    {
        $schema = (string)$route->getParam('schema');
        $table = (string)$route->getParam('table');
        $name = '/^[\p{L}\p{N}_\-]+$/u';
        if (!preg_match($name, $schema) || !preg_match($name, $table)) {
            throw new GC2Exception("Use one schema and one table", 400, null, 'INVALID_REQUEST');
        }
        try {
            new Model(connection: $connection)->isTableOrView("$schema.$table");
        } catch (GC2Exception) {
            throw new GC2Exception("Table not found: $schema.$table", 404, null, 'TABLE_NOT_FOUND');
        }
        return [$schema, $table];
    }
}
