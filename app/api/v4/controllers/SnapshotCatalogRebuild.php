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
use app\api\v4\Responses\AcceptedResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\snapshot\SnapshotStorageFactory;
use app\models\SnapshotCatalogRebuild as RebuildModel;
use OpenApi\Attributes as OA;
use Override;

/**
 * Full rewrites of the database's static STAC catalog of snapshots.
 *
 * Publishing a snapshot only writes the catalog documents of the relation it
 * touched. POST here queues a rewrite of every document (202); the cron worker
 * (app/scripts/snapshot_worker.php) runs it, and GET returns its status.
 * Super-user only.
 *
 * @package app\api\v4
 */
#[OA\Schema(
    schema: "SnapshotCatalogRebuildAccepted",
    description: "A queued rewrite of the snapshot catalog.",
    properties: [
        new OA\Property(property: "id", type: "string", example: "0b8c2f2e-2f5a-4d9c-9d3a-1b2c3d4e5f60"),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "_links", type: "object", properties: [new OA\Property(property: "self", type: "string", example: "/api/v4/snapshots/catalog/rebuilds/0b8c2f2e-2f5a-4d9c-9d3a-1b2c3d4e5f60")]),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SnapshotCatalogRebuild",
    description: "Status of a rewrite of the snapshot catalog.",
    properties: [
        new OA\Property(property: "id", type: "string", example: "0b8c2f2e-2f5a-4d9c-9d3a-1b2c3d4e5f60"),
        new OA\Property(property: "status", type: "string", enum: ["pending", "running", "succeeded", "failed"]),
        new OA\Property(property: "documents", description: "Number of catalog documents the rewrite covered.", type: "integer", example: 4321, nullable: true),
        new OA\Property(property: "failed_documents", description: "Number of those that could not be written.", type: "integer", example: 0, nullable: true),
        new OA\Property(property: "error", type: "string", nullable: true),
        new OA\Property(property: "username", type: "string"),
        new OA\Property(property: "created", type: "string", format: "date-time"),
        new OA\Property(property: "started", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "finished", type: "string", format: "date-time", nullable: true),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'OPTIONS'])]
#[Controller(route: 'api/v4/snapshots/catalog/rebuilds/[id]', scope: Scope::SUPER_USER_ONLY)]
class SnapshotCatalogRebuild extends AbstractApi
{
    private const string BASE = '/api/v4/snapshots/catalog/rebuilds';

    private RebuildModel $rebuild;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->rebuild = new RebuildModel($connection);
        $this->resource = 'snapshotCatalogRebuild';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => $row['uuid'],
            'status' => $row['status'],
            'documents' => $row['documents'] !== null ? (int)$row['documents'] : null,
            'failed_documents' => $row['failed_documents'] !== null ? (int)$row['failed_documents'] : null,
            'error' => $row['error'],
            'username' => $row['username'],
            'created' => $row['created'],
            'started' => $row['started'],
            'finished' => $row['finished'],
        ];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/snapshots/catalog/rebuilds/{id}', operationId: 'getSnapshotCatalogRebuild', description: "Get one catalog rebuild (object), or several by comma separated ids (array).", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Rebuild id, or comma separated ids', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/SnapshotCatalogRebuild"),
                new OA\Schema(type: 'array', items: new OA\Items(ref: "#/components/schemas/SnapshotCatalogRebuild"))])),
            new OA\Response(response: 403, description: 'Super-user only'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    #[OA\Get(path: '/api/v4/snapshots/catalog/rebuilds', operationId: 'getSnapshotCatalogRebuilds', description: "List the newest catalog rebuilds.", tags: ['Snapshots'],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/SnapshotCatalogRebuild"))),
            new OA\Response(response: 403, description: 'Super-user only'),
        ],
    )]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $id = $this->route->getParam('id');
        if (!empty($id)) {
            $list = [];
            foreach (explode(',', (string)$id) as $one) {
                $list[] = $this->present($this->rebuild->get(trim($one)));
            }
            return $this->getResponse($list, single: count($list) === 1);
        }
        return $this->getResponse(array_map($this->present(...), $this->rebuild->list()));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/snapshots/catalog/rebuilds', operationId: 'postSnapshotCatalogRebuild', description: "Queue a rewrite of the database's whole STAC catalog of snapshots (catalog.json, and every collection.json, latest.json and item.json). Publishing a snapshot only updates the documents of its own relation, so this is the way to repair a catalog that has drifted or to bring it up to a newer document layout. Takes no request body. Returns 202; poll the returned link for status. The duration grows with the number of published snapshots.", tags: ['Snapshots'])]
    #[OA\Response(response: 202, description: 'Accepted; poll _links.self for status.', content: new OA\JsonContent(ref: "#/components/schemas/SnapshotCatalogRebuildAccepted"))]
    #[OA\Response(response: 403, description: 'Super-user only')]
    #[OA\Response(response: 406, description: 'POST with a resource id is not allowed')]
    #[OA\Response(response: 409, description: 'A catalog rebuild is already pending or running')]
    #[OA\Response(response: 501, description: 'Snapshot storage is not configured')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        // Same check the worker makes. Throws 501 SNAPSHOT_NOT_CONFIGURED.
        SnapshotStorageFactory::fromApp();
        $active = $this->rebuild->activeId();
        if ($active !== null) {
            throw new GC2Exception("A catalog rebuild is already pending or running ($active)", 409, null, "CATALOG_REBUILD_IN_PROGRESS");
        }
        $id = $this->rebuild->create($this->route->jwt["data"]["uid"]);
        return new AcceptedResponse(['id' => $id, 'status' => 'pending', '_links' => ['self' => self::BASE . "/$id"]]);
    }

    /**
     * @throws GC2Exception
     */
    public function put_index(): Response
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

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        if (Input::getMethod() === 'post' && !empty($this->route->getParam('id'))) {
            $this->postWithResource();
        }
    }
}
