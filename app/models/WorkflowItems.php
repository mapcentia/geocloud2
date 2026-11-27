<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\controllers\Tilecache;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Model;
use Throwable;

/**
 * Workflow on a table, for the v4 API: who has which role, what is waiting, and the step that
 * moves a feature forward.
 *
 * A table with workflow has a status on every feature: 1 drafted, 2 reviewed, 3 published.
 * Only published features are visible to users without a role. A sub-user's role (author,
 * reviewer, publisher) decides how far their edits and approvals take a feature. Every edit
 * through WFS-T or the Feature API and every approval is logged in settings.workflow; the
 * latest entry per feature is its workflow item.
 *
 * Connection-injected and session-free, with bound parameters throughout. The legacy
 * app\models\Workflow serves the old GUI.
 */
class WorkflowItems extends Model
{
    public const array ROLES = ['none', 'author', 'reviewer', 'publisher'];

    /** The status a role's signature gives a feature. */
    public const array LEVEL = ['author' => 1, 'reviewer' => 2, 'publisher' => 3];

    public const array STATUS_NAMES = [1 => 'drafted', 2 => 'reviewed', 3 => 'published'];

    public function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * @return array<string, string> sub-user => role
     */
    public function roles(string $schema, string $table): array
    {
        $res = $this->prepare("SELECT roles FROM settings.geometry_columns_join
                               WHERE split_part(_key_, '.', 1) = :schema AND split_part(_key_, '.', 2) = :table
                                 AND roles IS NOT NULL ORDER BY _key_");
        $this->execute($res, ['schema' => $schema, 'table' => $table]);
        $roles = [];
        while ($row = $this->fetchRow($res)) {
            $decoded = json_decode((string)$row['roles'], true);
            if (is_array($decoded)) {
                $roles += array_filter($decoded, 'is_string');
            }
        }
        return $roles;
    }

    /**
     * Sets roles on every settings row of the table, so that whichever row a reader picks has
     * them.
     *
     * @param array<string, string> $roles sub-user => role
     * @throws GC2Exception
     */
    public function setRoles(string $schema, string $table, array $roles): void
    {
        foreach ($roles as $role) {
            if (!in_array($role, self::ROLES, true)) {
                throw new GC2Exception("Unknown role: $role", 400, null, 'INVALID_REQUEST');
            }
        }
        $merged = array_merge($this->roles($schema, $table), $roles);
        $res = $this->prepare("UPDATE settings.geometry_columns_join SET roles = :roles
                               WHERE split_part(_key_, '.', 1) = :schema AND split_part(_key_, '.', 2) = :table");
        $this->execute($res, ['roles' => json_encode((object)$merged), 'schema' => $schema, 'table' => $table]);
        Cache::deleteByPatterns([
            $this->connection->database . '_' . md5("$schema.$table") . '*',
            $this->connection->database . '*_meta_*',
        ]);
    }

    /**
     * The role that decides what $user may do: their own, or publisher for the owner.
     */
    public function roleOf(string $schema, string $table, string $user, bool $owner): string
    {
        if ($owner) {
            return 'publisher';
        }
        $role = $this->roles($schema, $table)[$user] ?? 'none';
        return in_array($role, self::ROLES, true) ? $role : 'none';
    }

    /**
     * The table's workflow items: the latest log entry of each feature, newest first.
     *
     * @param ?string $pendingFor a role: only items that role can still move forward
     * @param ?list<string> $gids only these features
     * @return list<array<string, mixed>>
     */
    public function items(string $schema, string $table, ?string $pendingFor = null, ?array $gids = null): array
    {
        $params = ['schema' => $schema, 'table' => $table];
        $where = ['f_schema_name = :schema AND f_table_name = :table'];
        if ($gids !== null) {
            $marks = [];
            foreach (array_values($gids) as $i => $gid) {
                $marks[] = ":gid$i";
                $params["gid$i"] = (int)$gid;
            }
            $where[] = 'gid IN (' . implode(',', $marks) . ')';
        }
        $items = $this->latest(implode(' AND ', $where), $params);
        return $pendingFor === null ? $items : array_values(array_filter($items, fn($item) => self::isPending($item, $pendingFor)));
    }

    /**
     * The workflow log as it is: every logged step (settings.workflow), newest first. For the
     * owner the whole log, for a sub-user the entries of the tables they have a role on.
     *
     * @param ?string $schema only tables of this schema
     * @return list<array<string, mixed>>
     */
    public function log(string $user, bool $owner, ?string $schema = null, int $limit = 100, int $offset = 0): array
    {
        $params = [];
        $where = [];
        if (!$owner) {
            $res = $this->prepare("SELECT DISTINCT split_part(_key_, '.', 1) AS s, split_part(_key_, '.', 2) AS t, roles ->> :u AS role
                                   FROM settings.geometry_columns_join WHERE roles ->> :u2 IS NOT NULL");
            $this->execute($res, ['u' => $user, 'u2' => $user]);
            $marks = [];
            while ($row = $this->fetchRow($res)) {
                if (!isset(self::LEVEL[$row['role']])) {
                    continue;
                }
                $n = count($marks);
                $marks[] = "(:s$n, :t$n)";
                $params["s$n"] = $row['s'];
                $params["t$n"] = $row['t'];
            }
            if (!$marks) {
                return [];
            }
            $where[] = '(f_schema_name, f_table_name) IN (' . implode(',', $marks) . ')';
        }
        if ($schema !== null) {
            $where[] = 'f_schema_name = :schema';
            $params['schema'] = $schema;
        }
        $sql = "SELECT id, f_schema_name, f_table_name, gid, version_gid, status, operation, gc2_user, created,
                       workflow -> 'author' AS author, workflow -> 'reviewer' AS reviewer, workflow -> 'publisher' AS publisher,
                       hstore_to_json(roles) AS roles
                FROM settings.workflow" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
                ORDER BY created DESC, id DESC LIMIT " . max(1, min(1000, $limit)) . " OFFSET " . max(0, $offset);
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        $entries = [];
        while ($row = $this->fetchRow($res)) {
            $entries[] = ['id' => (int)$row['id']] + self::present($row)
                + ['roles' => (object)(json_decode((string)$row['roles'], true) ?: [])];
        }
        return $entries;
    }

    /**
     * Whether a role can still move the item forward. A deleted feature has no current version
     * left to sign.
     */
    public static function isPending(array $item, string $role): bool
    {
        return isset(self::LEVEL[$role]) && $item['status'] < self::LEVEL[$role] && $item['operation'] !== 'delete';
    }

    /**
     * The latest log entry of each feature among the entries that match.
     *
     * @param string $where trusted SQL with named placeholders
     * @return list<array<string, mixed>>
     */
    private function latest(string $where, array $params): array
    {
        $sql = "SELECT * FROM (
                    SELECT DISTINCT ON (f_schema_name, f_table_name, version_gid)
                           f_schema_name, f_table_name, gid, version_gid, status, operation, gc2_user, created, id,
                           workflow -> 'author' AS author, workflow -> 'reviewer' AS reviewer, workflow -> 'publisher' AS publisher
                    FROM settings.workflow
                    WHERE $where
                    ORDER BY f_schema_name, f_table_name, version_gid, created DESC, id DESC
                ) AS latest ORDER BY created DESC, id DESC";
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        $items = [];
        while ($row = $this->fetchRow($res)) {
            $items[] = self::present($row);
        }
        return $items;
    }

    /** One log row as the API shows it. */
    private static function present(array $row): array
    {
        $status = (int)$row['status'];
        return [
            'schema' => $row['f_schema_name'],
            'table' => $row['f_table_name'],
            'gid' => (int)$row['gid'],
            'version_gid' => (int)$row['version_gid'],
            'status' => $status,
            'status_name' => self::STATUS_NAMES[$status] ?? null,
            'operation' => $row['operation'],
            'user' => $row['gc2_user'],
            'author' => $row['author'],
            'reviewer' => $row['reviewer'],
            'publisher' => $row['publisher'],
            'created' => $row['created'],
        ];
    }

    /**
     * Signs features with $role on behalf of $user and moves them to that role's status:
     * the previous state is kept as an ended version, the current row gets the new status and
     * signature, and the step is logged. All or nothing.
     *
     * @param list<string> $gids primary key values
     * @throws GC2Exception
     */
    public function approve(string $schema, string $table, array $gids, string $user, string $role): void
    {
        $level = self::LEVEL[$role] ?? null;
        if ($level === null) {
            throw new GC2Exception("You don't have a role in the workflow of $schema.$table", 403, null, 'NO_WORKFLOW_ROLE');
        }
        $rel = "$schema.$table";
        if (empty($this->doesColumnExist($rel, 'gc2_status')['exists'])) {
            throw new GC2Exception("Workflow is not enabled on $rel", 409, null, 'WORKFLOW_NOT_ENABLED');
        }
        $name = $this->doubleQuoteQualifiedName($rel);
        $pk = '"' . str_replace('"', '""', $this->getPrimeryKey($rel)['attname']) . '"';
        $rolesJson = json_encode((object)$this->roles($schema, $table));

        $this->withTransaction(function () use ($schema, $table, $gids, $user, $role, $level, $name, $pk, $rolesJson) {
            foreach ($gids as $gid) {
                $res = $this->prepare("SELECT * FROM $name WHERE $pk = :gid FOR UPDATE");
                $this->execute($res, ['gid' => $gid]);
                $feature = $this->fetchRow($res, 'assoc');
                if (!$feature) {
                    throw new GC2Exception("Feature not found: $gid", 404, null, 'FEATURE_NOT_FOUND');
                }
                if (!empty($feature['gc2_version_end_date'])) {
                    throw new GC2Exception("Feature $gid is an ended version; history cannot be changed", 409, null, 'VERSION_ENDED');
                }
                $status = (int)$feature['gc2_status'];
                if ($status >= $level) {
                    $is = self::STATUS_NAMES[$status] ?? "at status $status";
                    throw new GC2Exception("Feature $gid is already $is; a $role cannot move it further", 409, null, 'WORKFLOW_STATUS_CONFLICT');
                }

                // Keep the state before this step as an ended version
                $columns = [];
                $values = [];
                foreach (array_keys($feature) as $column) {
                    $quoted = '"' . str_replace('"', '""', $column) . '"';
                    if ($quoted === $pk) {
                        continue;
                    }
                    $columns[] = $quoted;
                    $values[] = $column === 'gc2_version_end_date' ? 'now()' : $quoted;
                }
                $res = $this->prepare("INSERT INTO $name (" . implode(',', $columns) . ") SELECT " . implode(',', $values) . " FROM $name WHERE $pk = :gid");
                $this->execute($res, ['gid' => $gid]);

                $res = $this->prepare("UPDATE $name SET gc2_version_user = :user, gc2_status = :status,
                                              gc2_workflow = COALESCE(gc2_workflow, ''::hstore) || hstore(:role, :signer)
                                       WHERE $pk = :gid");
                $this->execute($res, ['user' => $user, 'status' => $level, 'role' => $role, 'signer' => $user, 'gid' => $gid]);

                $res = $this->prepare("INSERT INTO settings.workflow (f_schema_name, f_table_name, gid, status, gc2_user, roles, workflow, version_gid, operation)
                                       SELECT :schema, :table, :gid, :status, :user,
                                              (SELECT hstore(COALESCE(array_agg(key), '{}'), COALESCE(array_agg(value), '{}')) FROM jsonb_each_text(CAST(:roles AS jsonb))),
                                              f.gc2_workflow, f.gc2_version_gid, 'update'
                                       FROM $name f WHERE f.$pk = :gid2");
                $this->execute($res, [
                    'schema' => $schema, 'table' => $table, 'gid' => (int)$gid, 'status' => $level, 'user' => $user,
                    'roles' => $rolesJson, 'gid2' => $gid,
                ]);
            }
        });

        // What is published changed, so cached tiles of the layer are stale. Best effort.
        try {
            Tilecache::bust($rel, $this->connection);
        } catch (Throwable $e) {
            error_log("Workflow: could not clear the tile cache of $rel: " . $e->getMessage());
        }
    }
}
