<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\inc\Cache;
use app\inc\Globals;
use app\inc\Model;
use app\inc\PublicIdentity;

/**
 * Legends of layers, for GET /api/v4/legends: per layer its title and classes, each class with
 * a small PNG icon.
 *
 * The icons are drawn by MapServer over HTTP (`mode=legendicon`) on the node App.php
 * `mapServer.host` points at, from the schema's WMS mapfile. PHP MapScript is not used, so this
 * works when MapServer runs on another node. Class names and expressions come from GC2's own
 * class data; a class is matched to its icon by position, in the sortid order the mapfile is
 * written in.
 *
 * A layer the identity may not read is left out of the result. Geofence rules are not consulted:
 * a legend shows how a layer is drawn, not its rows.
 */
final class Legend
{
    /** Upper bound on a fetched legend picture. */
    private const int MAX_IMAGE_BYTES = 2097152;

    public function __construct(private readonly PublicIdentity $id) {}

    /**
     * @param list<string> $names "schema.table" or "schema" (all layers of the schema)
     * @return list<array{id: string, title: string, classes: list<array{name: ?string, expression: ?string, img: ?string}>}>
     */
    public function forLayers(array $names): array
    {
        $model = new Model(connection: $this->id->connection);
        $gate = new LayerGate($this->id);
        $out = [];
        $seen = [];
        foreach ($this->expand($model, $names) as $rel) {
            if (isset($seen[$rel])) {
                continue;
            }
            $seen[$rel] = true;
            if (!$gate->canRead($rel)) {
                continue;
            }
            $legend = $this->legendOf($model, $rel);
            if ($legend !== null) {
                $out[] = $legend;
            }
        }
        return $out;
    }

    /**
     * Splits a comma separated list of layer names. Null when a name is malformed.
     *
     * @return list<string>|null
     */
    public static function parseNames(string $list): ?array
    {
        $names = explode(',', $list);
        foreach ($names as $name) {
            if (!preg_match('/^[\p{L}\p{N}_\-]+(\.[\p{L}\p{N}_\-]+)?$/u', $name)) {
                return null;
            }
        }
        return $names;
    }

    /**
     * Classes in the order the mapfile has them: ascending sortid, ties in stored order
     * (as Mapfile::sortClasses). The array index is MapServer's class index.
     *
     * @return list<array<string, mixed>>
     */
    public static function sortClasses(?string $classJson): array
    {
        $classes = !empty($classJson) ? json_decode($classJson, true) : null;
        if (!is_array($classes)) {
            return [];
        }
        $classes = array_values(array_filter($classes, 'is_array'));
        usort($classes, fn($a, $b) => (int)($a['sortid'] ?? 0) <=> (int)($b['sortid'] ?? 0));
        return $classes;
    }

    /** MapServer CGI call that returns only the icon of one class of one layer. */
    public static function iconUrl(string $database, string $rel, int $classIndex): string
    {
        $schema = explode('.', $rel)[0];
        return Proxy::mapservUrl() . '?' . http_build_query([
                'map' => Proxy::wmsMapfilePath($database, $schema),
                'mode' => 'legendicon',
                'icon' => "$rel,$classIndex",
            ]);
    }

    /**
     * Where to get the one legend picture of a layer that is not drawn from GC2 classes: its own
     * legend_url, QGIS Server (on the MapServer node) for a QGIS project, or the external WMS.
     * Null for an ordinary layer.
     */
    public static function pictureUrl(?string $wmssource, ?string $legendUrl): ?string
    {
        if (!empty($legendUrl)) {
            return $legendUrl;
        }
        if (empty($wmssource)) {
            return null;
        }
        $qgs = SourceResolver::parseQgsPath($wmssource);
        if ($qgs !== null) {
            parse_str(parse_url($wmssource, PHP_URL_QUERY) ?? '', $query);
            $query = array_change_key_case($query, CASE_UPPER);
            return Proxy::qgisUrl() . '?' . http_build_query([
                    'map' => $qgs,
                    'SERVICE' => 'WMS',
                    'VERSION' => $query['VERSION'] ?? '1.1.1',
                    'REQUEST' => 'GetLegendGraphic',
                    'FORMAT' => 'image/png',
                    'LAYER' => $query['LAYER'] ?? $query['LAYERS'] ?? '',
                ]);
        }
        if (SourceResolver::parseWmsSource($wmssource) === null) {
            return null;
        }
        // Same URL the mapfile advertises as wms_get_legend_url
        return str_replace(['layers', 'LAYERS'], 'LAYER', $wmssource) . '&REQUEST=getlegendgraphic';
    }

    /**
     * @param list<string> $names
     * @return list<string> "schema.table"
     */
    private function expand(Model $model, array $names): array
    {
        $rels = [];
        foreach ($names as $name) {
            if (str_contains($name, '.')) {
                $rels[] = $name;
                continue;
            }
            $sql = "SELECT DISTINCT split_part(_key_, '.', 2) AS t FROM settings.geometry_columns_join
                    WHERE split_part(_key_, '.', 1) = :schema ORDER BY 1";
            $res = $model->prepare($sql);
            $model->execute($res, ['schema' => $name]);
            while ($row = $model->fetchRow($res)) {
                $rels[] = $name . '.' . $row['t'];
            }
        }
        return $rels;
    }

    private function legendOf(Model $model, string $rel): ?array
    {
        // Per layer, not per user: what a legend contains does not depend on who asks, and access
        // is decided before this. Cleared with the layer's other caches ("<db>*_legend_*").
        $item = Cache::getItem($this->id->database . '_legend_v4_' . md5($rel));
        if ($item !== null && $item->isHit()) {
            return $item->get();
        }
        [$schema, $table] = explode('.', $rel, 2);
        $row = self::layerRow($model->getColumns($schema, $table), $schema, $table);
        if ($row === null) {
            return null;
        }
        $classes = [];
        $complete = true;
        $picture = self::pictureUrl($row['wmssource'] ?? null, $row['legend_url'] ?? null);
        if ($picture !== null) {
            $img = self::toPng(self::fetchImage($picture));
            $complete = $img !== null;
            $classes[] = ['name' => null, 'expression' => null, 'img' => $img];
        } else {
            foreach (self::sortClasses($row['class'] ?? null) as $i => $class) {
                // As MapServer's own legends: a class without a name has no legend entry
                if (!isset($class['name']) || trim((string)$class['name']) === '') {
                    continue;
                }
                $bytes = self::fetchImage(self::iconUrl($this->id->database, $rel, $i));
                $complete = $complete && $bytes !== null;
                $classes[] = [
                    'name' => (string)$class['name'],
                    'expression' => !empty($class['expression']) ? (string)$class['expression'] : null,
                    'img' => $bytes !== null ? base64_encode($bytes) : null,
                ];
            }
        }
        $legend = [
            'id' => $rel,
            'title' => !empty($row['f_table_title']) ? (string)$row['f_table_title'] : $table,
            'classes' => $classes,
        ];
        // A legend with a missing picture (MapServer down, mapfile not written yet) is not kept
        if ($item !== null && $complete) {
            $item->set($legend)->expiresAfter(Globals::$cacheTtl);
            Cache::save($item);
        }
        return $legend;
    }

    /**
     * The settings row the mapfile layer "schema.table" is written from. A table can also have a
     * left-over row for a non-spatial registration (gc2_non_postgis) without classes; the row of
     * a real geometry or raster column wins.
     *
     * @param list<array<string, mixed>> $rows from Model::getColumns
     */
    public static function layerRow(array $rows, string $schema, string $table): ?array
    {
        $fallback = null;
        foreach ($rows as $row) {
            if (($row['f_table_schema'] ?? null) !== $schema || ($row['f_table_name'] ?? null) !== $table) {
                continue;
            }
            if (($row['f_geometry_column'] ?? null) !== 'gc2_non_postgis') {
                return $row;
            }
            $fallback ??= $row;
        }
        return $fallback;
    }

    /** The body of an image response, or null: MapServer reports errors as 200 text/html. */
    private static function fetchImage(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_MAXFILESIZE => self::MAX_IMAGE_BYTES,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if (!is_string($body) || $body === '' || $status !== 200 || !str_starts_with(strtolower($type), 'image/')
            || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }
        return $body;
    }

    /** Any image an external source answers with, as base64 PNG with its transparency kept. */
    private static function toPng(?string $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            return null;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        return base64_encode(ob_get_clean());
    }
}
