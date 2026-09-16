<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Authorization;

/**
 * May the JWT identity read snapshots of a relation? Mirrors the SQL API's
 * read rule: super-users always; sub-users when they own the schema or hold
 * a read/read-write privilege on the relation. Every relation GC2 knows has
 * a privilege row (settings.geometry_columns_view unions non-spatial tables,
 * views and matviews with geometry_columns); a dropped relation has none,
 * so only super-users and schema owners can still read its history.
 */
final class SnapshotAuthorizer
{
    private Authorization $authorization;

    public function __construct(Connection $connection)
    {
        $this->authorization = new Authorization($connection);
    }

    /**
     * @param array<string,mixed> $jwtData the "data" part of the JWT (uid, superUser, userGroup)
     * @throws GC2Exception 403 INSUFFICIENT_PRIVILEGES
     */
    public function assertCanRead(array $jwtData, string $schema, string $relation): void
    {
        if (!empty($jwtData['superUser'])) {
            return;
        }
        $uid = (string)($jwtData['uid'] ?? '');
        $groups = $jwtData['userGroup'] ?? null;
        $groups = is_array($groups) ? $groups : ($groups === null ? null : [$groups]);
        if ($this->authorization->isOwner($uid, $groups, $schema)) {
            return;
        }
        $raw = $this->authorization->getGeometryColumns("$schema.$relation", 'privileges');
        $privileges = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $privilege = $privileges === [] ? 'none' : $this->authorization->extractHighestPrivilege($privileges, $uid, $groups);
        if ($privilege === 'read' || $privilege === 'read/write' || $privilege === 'write') {
            return;
        }
        throw new GC2Exception("Insufficient privileges to read snapshots of $schema.$relation", 403, null, "INSUFFICIENT_PRIVILEGES");
    }
}
