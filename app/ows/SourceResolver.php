<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\inc\Model;

/**
 * Resolves a layer's backend source (QGIS project path or external WMS server)
 * from the settings.geometry_columns_join.wmssource column. Replaces the legacy
 * mapObj (php-mapscript) reads, which parsed the same value out of the mapfile
 * CONNECTION written verbatim from this column.
 */
final class SourceResolver
{
    /**
     * @param array<string,?string> $wmssourceByLayer keyed by "schema.table"
     */
    public function __construct(private array $wmssourceByLayer) {}

    public static function fromLayers(Model $model, string $schema, array $layers): self
    {
        $map = [];
        foreach ($layers as $layer) {
            // $layer is "schema.table"; column key is _key_ = schema.table.geom
            $bits = explode('.', $layer);
            $table = $bits[1] ?? $bits[0];
            // Match on the schema/table segments explicitly (not a LIKE pattern) so
            // "_" and "%" in a schema/table name aren't treated as SQL wildcards.
            $sql = "SELECT _key_, wmssource FROM settings.geometry_columns_join
                    WHERE split_part(_key_, '.', 1) = :schema AND split_part(_key_, '.', 2) = :table
                    ORDER BY _key_";
            $res = $model->prepare($sql);
            $model->execute($res, ['schema' => $schema, 'table' => $table]);
            $map[$layer] = self::pickWmssource($model->fetchAll($res));
        }
        return new self($map);
    }

    /**
     * The wmssource of the settings row a layer is served from, given all rows of its table
     * ordered by _key_. Next to the row of its geometry column ("schema.table.the_geom") a table
     * can have rows that are not a layer: one keyed without a column ("schema.table") and one for
     * a non-spatial registration ("schema.table.gc2_non_postgis"). They have no source and sort
     * before the geometry row, so taking simply the first row made a QGIS or external WMS layer
     * look like an ordinary one. The first geometry row wins; another row only counts when the
     * table has no geometry row.
     *
     * @param list<array{_key_: string, wmssource: ?string}> $rows
     */
    public static function pickWmssource(array $rows): ?string
    {
        foreach ($rows as $row) {
            $column = explode('.', (string)($row['_key_'] ?? ''))[2] ?? '';
            if ($column !== '' && $column !== 'gc2_non_postgis') {
                return $row['wmssource'] ?? null;
            }
        }
        return $rows[0]['wmssource'] ?? null;
    }

    public function qgsFilePath(): ?string
    {
        foreach ($this->wmssourceByLayer as $wmssource) {
            $path = self::parseQgsPath($wmssource);
            if ($path !== null) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Only for a single requested layer (legacy restriction).
     */
    public function wmsSource(int $layerCount): ?array
    {
        if ($layerCount !== 1) {
            return null;
        }
        $wmssource = reset($this->wmssourceByLayer) ?: null;
        return self::parseWmsSource($wmssource);
    }

    public static function parseQgsPath(?string $wmssource): ?string
    {
        if (empty($wmssource) || !str_contains($wmssource, 'map=')) {
            return null;
        }
        $par = parse_url($wmssource);
        if (empty($par['query'])) {
            return null;
        }
        parse_str($par['query'], $result);
        if (!empty($result['map']) && str_ends_with($result['map'], '.qgs')) {
            return $result['map'];
        }
        return null;
    }

    public static function parseWmsSource(?string $wmssource): ?array
    {
        if (empty($wmssource) || !str_starts_with($wmssource, 'http')) {
            return null;
        }
        $par = parse_url($wmssource);
        parse_str($par['query'] ?? '', $result);
        $par['query'] = array_change_key_case($result, CASE_UPPER);
        return $par;
    }
}
