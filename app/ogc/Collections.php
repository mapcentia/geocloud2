<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Model;
use app\inc\PublicIdentity;
use app\models\Authorization;
use app\models\Layer;
use Throwable;

/**
 * OGC API collections = OWS-enabled layers (settings.getColumns with enableows=true), visible by
 * the same rules as WMS GetCapabilities: anonymous clients see 'None'/'Write' layers, identified
 * clients see everything their privileges allow. One collection per relation; id = "schema.table".
 */
final class Collections
{
    public const int DEFAULT_LIMIT = 100;
    public const int MAX_LIMIT = 1000;
    private const array WORLD = [-180.0, -90.0, 180.0, 90.0];
    private const int EXTENT_TTL = 86400;
    private const int EXTENT_TIMEOUT_MS = 5000;

    public function __construct(
        private readonly PublicIdentity $id,
        private readonly string $baseHref,
    ) {}

    /** @return array{collections: list<array<string,mixed>>, numberMatched: int} */
    public function list(int $limit, int $offset): array
    {
        $rows = array_values(array_filter($this->rows(), fn(array $r) => $this->visible($r)));
        $page = array_slice($rows, $offset, $limit);
        return [
            'collections' => array_map(fn(array $r) => $this->toCollection($r), $page),
            'numberMatched' => count($rows),
        ];
    }

    /**
     * The raw layer row for a collection the caller may read.
     *
     * @throws GC2Exception 404 when no such OWS-enabled layer exists; 401 (with a Basic challenge)
     *                      when it exists but the anonymous caller needs credentials; 403 when the
     *                      identified caller lacks the privilege
     */
    public function get(string $collectionId): array
    {
        $bits = explode('.', $collectionId, 2);
        if (count($bits) !== 2 || $bits[0] === '' || $bits[1] === '' || preg_match('/[\'\\\\]/', $collectionId)) {
            throw new GC2Exception("Collection $collectionId not found", 404, null, 'COLLECTION_NOT_FOUND');
        }
        $rows = $this->rows($bits[0], $bits[1], visibleOnly: false);
        if ($rows === []) {
            throw new GC2Exception("Collection $collectionId not found", 404, null, 'COLLECTION_NOT_FOUND');
        }
        $row = $rows[0];
        if ($this->visible($row)) {
            return $row;
        }
        if ($this->id->anonymous) {
            header('WWW-Authenticate: Basic realm="' . $this->id->database . '"');
            throw new GC2Exception("Authentication required for collection $collectionId", 401, null, 'UNAUTHORIZED');
        }
        throw new GC2Exception("Insufficient privileges for collection $collectionId", 403, null, 'INSUFFICIENT_PRIVILEGES');
    }

    /**
     * @param bool $visibleOnly narrow the SQL to anonymously readable layers for anonymous callers
     *                          (listing); false returns every OWS-enabled row so get() can tell
     *                          "unknown" from "needs credentials"
     * @return list<array<string,mixed>>
     */
    private function rows(?string $schema = null, ?string $table = null, bool $visibleOnly = true): array
    {
        $model = new Model(connection: $this->id->connection);
        // settings.getColumns() inlines its arguments into SQL, hence the doubled quotes.
        $auth = $visibleOnly && $this->id->anonymous ? " AND (authentication=''Write'' OR authentication=''None'')" : '';
        $vector = 'enableows=true' . $auth;
        $raster = 'enableows=true' . $auth;
        if ($schema !== null && $table !== null) {
            $vector .= " AND f_table_schema=''$schema'' AND f_table_name=''$table''";
            $raster .= " AND raster_columns.r_table_schema=''$schema'' AND raster_columns.r_table_name=''$table''";
        }
        $res = $model->prepare("SELECT * FROM settings.getColumns('$vector','$raster') ORDER BY f_table_schema, f_table_name, sort_id");
        $model->execute($res);
        $rows = [];
        $seen = [];
        while ($row = $model->fetchRow($res)) {
            $key = $row['f_table_schema'] . '.' . $row['f_table_name'];
            if (isset($seen[$key])) {
                continue; // one collection per relation, even with several geometry columns
            }
            $seen[$key] = true;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Anonymous callers may read layers below 'Read/write'; the parent user and trusted
     * addresses read everything; sub-users read 'Read/write' layers they hold a privilege on.
     */
    private function visible(array $row): bool
    {
        if (($row['authentication'] ?? null) !== 'Read/write') {
            return true;
        }
        // Anonymous first: the connection identity of an anonymous request is the database
        // owner, so parentUser is true for it as well.
        if ($this->id->anonymous) {
            return false;
        }
        if ($this->id->parentUser || $this->id->trusted) {
            return true;
        }
        try {
            new Authorization(connection: $this->id->connection)->check(
                relName: $row['f_table_schema'] . '.' . $row['f_table_name'], transaction: false, isAuth: true,
                subUser: $this->id->user, userGroup: $this->id->userGroup, rels: []
            );
            return true;
        } catch (GC2Exception) {
            return false;
        }
    }

    public function toCollection(array $row): array
    {
        $schema = $row['f_table_schema'];
        $table = $row['f_table_name'];
        $cid = "$schema.$table";
        $href = Links::collection($this->baseHref, $cid);
        $isRaster = ($row['type'] ?? null) === 'RASTER';
        $ext = $this->extent($row);
        $c = [
            'id' => $cid,
            'title' => !empty($row['f_table_title']) ? $row['f_table_title'] : $table,
            'description' => (string)($row['f_table_abstract'] ?? ''),
            'extent' => ['spatial' => ['bbox' => [$ext['bbox']], 'crs' => Crs::CRS84]],
            'links' => [
                ['rel' => 'self', 'type' => 'application/json', 'title' => 'This collection', 'href' => $href],
            ],
        ];
        if ($ext['temporal'] !== null) {
            $c['extent']['temporal'] = [
                'interval' => [[$ext['temporal'], null]],
                'trs' => 'http://www.opengis.net/def/uom/ISO-8601/0/Gregorian',
            ];
        }
        if (!$isRaster) {
            $c['itemType'] = 'feature';
            $c['crs'] = self::crsList((int)$row['srid']);
            $c['storageCrs'] = Crs::uri((int)$row['srid']);
            $c['links'][] = ['rel' => 'items', 'type' => 'application/geo+json', 'title' => 'Items as GeoJSON', 'href' => "$href/items"];
        } else {
            $c['crs'] = self::crsList(null);
        }
        $c['links'][] = ['rel' => 'http://www.opengis.net/def/rel/ogc/1.0/map', 'type' => 'image/png', 'title' => 'Map', 'href' => "$href/map"];
        return $c;
    }

    /** CRS84, EPSG:4326, the storage CRS and the advertised WMS SRS list, deduplicated. */
    public static function crsList(?int $storageSrid): array
    {
        $list = [Crs::CRS84, Crs::uri(4326)];
        if ($storageSrid) {
            $list[] = Crs::uri($storageSrid);
        }
        $advertised = App::$param['advertisedSrs'] ?? ['EPSG:4326', 'EPSG:3857', 'EPSG:3044', 'EPSG:25832'];
        foreach ($advertised as $s) {
            $code = (int)(explode(':', (string)$s)[1] ?? 0);
            if ($code > 0) {
                $list[] = Crs::uri($code);
            }
        }
        return array_values(array_unique($list));
    }

    /** @return list<float> [minx,miny,maxx,maxy] in EPSG:4326 x/y */
    public function extentBbox(array $row): array
    {
        return $this->extent($row)['bbox'];
    }

    /**
     * Estimated extent (ST_EstimatedExtent via Layer::getEstExtent), falling back to one full
     * ST_Extent under a statement_timeout, then to the world. Cached per relation; the key ends in
     * _geometryColumns so Table/Layer::clearCacheOnSchemaChanges() (pattern <db>*_geometryColumns)
     * busts it with the layer metadata.
     *
     * @return array{bbox: list<float>, temporal: ?string}
     */
    private function extent(array $row): array
    {
        if (($row['type'] ?? null) === 'RASTER') {
            return ['bbox' => self::WORLD, 'temporal' => null];
        }
        $schema = $row['f_table_schema'];
        $table = $row['f_table_name'];
        $rel = "$schema.$table";
        $key = $this->id->database . '_' . md5($rel . '_ogcExtent') . '_geometryColumns';
        $item = Cache::getItem($key);
        if ($item !== null && $item->isHit()) {
            return $item->get();
        }
        $model = new Model(connection: $this->id->connection);
        $bbox = null;
        try {
            $est = new Layer(connection: $this->id->connection)->getEstExtent($row['_key_'], 4326)['extent'];
            if ($est['xmin'] !== null) {
                $bbox = [(float)$est['xmin'], (float)$est['ymin'], (float)$est['xmax'], (float)$est['ymax']];
            }
        } catch (Throwable) {
            // views and unanalysed tables: fall through to the full extent
        }
        if ($bbox === null) {
            try {
                $model->execute($model->prepare('SET statement_timeout = ' . self::EXTENT_TIMEOUT_MS));
                $res = $model->prepare("SELECT ST_XMin(e) AS xmin, ST_YMin(e) AS ymin, ST_XMax(e) AS xmax, ST_YMax(e) AS ymax "
                    . "FROM (SELECT ST_Extent(ST_Transform(\"{$row['f_geometry_column']}\", 4326)) AS e FROM \"$schema\".\"$table\") s");
                $model->execute($res);
                $r = $model->fetchRow($res);
                if (!empty($r) && $r['xmin'] !== null) {
                    $bbox = [(float)$r['xmin'], (float)$r['ymin'], (float)$r['xmax'], (float)$r['ymax']];
                }
            } catch (Throwable) {
                // timeout or error: world
            } finally {
                try {
                    $model->execute($model->prepare('RESET statement_timeout'));
                } catch (Throwable) {
                }
            }
        }
        $temporal = null;
        if (!empty($model->doesColumnExist($rel, 'gc2_version_gid')['exists'])) {
            try {
                $res = $model->prepare("SELECT to_char(min(gc2_version_start_date) AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS t FROM \"$schema\".\"$table\"");
                $model->execute($res);
                $temporal = $model->fetchRow($res)['t'] ?: null;
            } catch (Throwable) {
            }
        }
        $data = ['bbox' => $bbox ?? self::WORLD, 'temporal' => $temporal];
        if ($item !== null) {
            $item->set($data)->expiresAfter(self::EXTENT_TTL);
            Cache::save($item);
        }
        return $data;
    }
}
