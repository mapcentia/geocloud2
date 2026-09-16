<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\UserFilter;
use app\models\Authorization;
use app\models\Geofence;
use app\models\Rule;

/**
 * May the JWT identity read snapshots of a relation? Mirrors the SQL API's
 * read rule: super-users always; sub-users when they own the schema or hold
 * a read/read-write privilege on the relation. Every relation GC2 knows has
 * a privilege row (settings.geometry_columns_view unions non-spatial tables,
 * views and matviews with geometry_columns); a dropped relation has none,
 * so only super-users and schema owners can still read its history.
 *
 * On top of that, a sub-user any geofence rule applies to is refused: a
 * snapshot is a whole-table Parquet file and cannot carry a row filter, so
 * serving it would hand a geofenced user the rows their SQL/OWS access is
 * filtered away from.
 */
final class SnapshotAuthorizer
{
    private Authorization $authorization;

    public function __construct(private readonly Connection $connection)
    {
        $this->authorization = new Authorization($connection);
    }

    /**
     * @param array<string,mixed> $jwtData the "data" part of the JWT (uid, superUser, userGroup)
     * @throws GC2Exception 403 INSUFFICIENT_PRIVILEGES or 403 GEOFENCE_RULES_APPLY
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
        if ($privilege !== 'read' && $privilege !== 'read/write' && $privilege !== 'write') {
            throw new GC2Exception("Insufficient privileges to read snapshots of $schema.$relation", 403, null, "INSUFFICIENT_PRIVILEGES");
        }
        if ($this->isGeofenced($uid, $groups, $schema, $relation)) {
            throw new GC2Exception("Geofence rules apply to $schema.$relation; snapshots are not available to this user", 403, null, "GEOFENCE_RULES_APPLY");
        }
    }

    /**
     * True when any rule in settings.geofence matches this sub-user (or one of
     * their groups) on this schema and relation.
     *
     * Matching is not re-implemented here: each rule is handed to
     * Geofence::authorize() on its own, with a UserFilter carrying the rule's
     * own service and request, so the only axes that can decide the match are
     * the ones that matter for a snapshot — the identity (fnmatch on
     * username), the schema and the layer (both fnmatch), and the client IP
     * range, exactly as they are matched for SQL and OWS. A rule of any
     * access level counts: `deny` and `limit` obviously, and `allow` because
     * a rule set that mentions this user and relation at all is a filtered
     * relationship a whole-table file cannot express.
     *
     * @param list<string>|null $groups
     */
    private function isGeofenced(string $uid, ?array $groups, string $schema, string $relation): bool
    {
        $rules = new Rule(connection: $this->connection)->get();
        if ($rules === []) {
            return false;
        }
        $identities = array_unique(array_filter(array_merge([$uid], $groups ?? []), fn($n) => is_string($n) && $n !== ''));
        foreach ($identities as $identity) {
            foreach ($rules as $rule) {
                $userFilter = new UserFilter(
                    $identity,
                    (string)($rule['service'] ?? '*'),
                    (string)($rule['request'] ?? '*'),
                    '*',
                    $schema,
                    $relation,
                );
                if (!empty(new Geofence($userFilter, $this->connection)->authorize([$rule])['access'])) {
                    return true;
                }
            }
        }
        return false;
    }
}
