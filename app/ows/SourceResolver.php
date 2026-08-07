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
            $sql = "SELECT wmssource FROM settings.geometry_columns_join
                    WHERE _key_ LIKE :key ORDER BY _key_ LIMIT 1";
            $res = $model->prepare($sql);
            $model->execute($res, ['key' => "$schema.$table.%"]);
            $row = $model->fetchRow($res);
            $map[$layer] = $row['wmssource'] ?? null;
        }
        return new self($map);
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
