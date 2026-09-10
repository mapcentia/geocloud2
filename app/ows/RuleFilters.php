<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\exceptions\ServiceException;
use app\inc\Model;
use app\inc\PublicIdentity;
use app\inc\UserFilter;
use app\models\Geofence;
use app\models\Rule;

/**
 * Geofence rules (service "ows") and the versioning filter per requested layer, merged with
 * client filters, as WHERE fragments for MapfilePatcher. Extracted from Ows::applyRules so the
 * OGC API Maps endpoint applies exactly the same rules as the WMS endpoint.
 */
final class RuleFilters
{
    /** ISO 8601 instant: date, optional time, optional fraction, optional Z/offset. */
    private const string INSTANT = '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    public function __construct(private readonly PublicIdentity $id) {}

    public static function isInstant(string $value): bool
    {
        return preg_match(self::INSTANT, $value) === 1;
    }

    /**
     * WHERE fragment selecting the current version, or the version valid at $timeSlice.
     * The time slice is validated before it is inlined, so it cannot inject SQL.
     *
     * @throws ServiceException on a malformed time slice
     */
    public static function versionFilter(?string $timeSlice): string
    {
        if ($timeSlice === null || $timeSlice === '') {
            return 'gc2_version_end_date IS NULL';
        }
        if (!self::isInstant($timeSlice)) {
            throw new ServiceException('Invalid datetime');
        }
        return "(gc2_version_start_date <= '$timeSlice' AND (gc2_version_end_date > '$timeSlice' OR gc2_version_end_date IS NULL))";
    }

    public static function tableOf(string $layer): string
    {
        $bits = explode('.', $layer);
        return $bits[1] ?? $bits[0];
    }

    /**
     * @param list<string> $layers layer names as requested ("schema.table" or "table"); the
     *                             returned array is keyed by these raw names, which is what
     *                             MapfilePatcher looks up
     * @param string $schema the route schema, used to resolve unqualified names
     * @param array<string,list<string>> $clientFilters filters from the FILTERS parameter
     * @return array<string,list<string>>
     * @throws ServiceException 'DENY' when a deny rule matches
     */
    public function forLayers(array $layers, string $schema, array $clientFilters = [], ?string $timeSlice = null): array
    {
        $filters = $clientFilters;
        $rules = new Rule(connection: $this->id->connection)->get();
        $model = new Model(connection: $this->id->connection);
        // Anonymous requests must reach the geofence as "*", never as the database name
        // (the connection identity), or a parent-user rule would match unauthenticated traffic.
        $geofenceUser = $this->id->geofenceUser();
        foreach ($layers as $layer) {
            $table = self::tableOf($layer);
            $userFilter = new UserFilter($geofenceUser, 'ows', 'select', '*', $schema, $table);
            $auth = new Geofence($userFilter)->authorize($rules);
            if (isset($auth['access'])) {
                if ($auth['access'] === 'deny') {
                    throw new ServiceException('DENY');
                }
                if ($auth['access'] === 'limit' && !empty($auth['filters']['filter'])) {
                    $filters[$layer][] = "({$auth['filters']['filter']})";
                }
            }
            $versioning = $model->doesColumnExist("$schema.$table", 'gc2_version_gid');
            if (!empty($versioning['exists'])) {
                $filters[$layer][] = self::versionFilter($timeSlice);
            }
        }
        return $filters;
    }
}
