<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\conf\App;
use app\controllers\Mapcache;
use app\inc\Model;
use PDOStatement;
use stdClass;

/**
 * Generates the per-database MapCache XML configuration covering every
 * OWS-enabled geographic table. Worker-safe: all state comes from the
 * injected Connection and App config — no output buffering, no $_SERVER,
 * no process-global database.
 */
class Mapcachefile extends Model
{
    function __construct(?\app\inc\Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * Fetch all vector/raster layer rows, optionally restricted to the
     * schemas listed in App mapCache.include.
     */
    public function getLayerRows(): PDOStatement
    {
        $includeSchemasF = $includeSchemasR = '';
        if (!empty(App::$param['mapCache']['include'])) {
            $in = "'" . implode("','", App::$param['mapCache']['include']) . "'";
            $includeSchemasF = "AND f_table_schema in ($in)";
            $includeSchemasR = "AND r_table_schema in ($in)";
        }
        $sql = "SELECT * FROM settings.getColumns('f_table_schema NOTNULL AND f_table_name NOTNULL AND f_geometry_column NOTNULL $includeSchemasF', 'r_table_schema NOTNULL AND r_table_name NOTNULL AND r_raster_column NOTNULL $includeSchemasR')";
        return $this->execQuery($sql);
    }

    /**
     * Extract the per-layer tile settings from a getColumns row.
     * Pure — safe to unit test without a database.
     */
    public static function layerSettings(array $row, string $defaultCache): array
    {
        $def = !empty($row['def']) ? json_decode($row['def']) : null;
        $def = $def instanceof stdClass ? $def : new stdClass();

        $qgisLayers = null;
        if (!empty($row['wmssource']) && strpos($row['wmssource'], 'qgis_mapserv.fcgi')) {
            parse_str(parse_url($row['wmssource'])['query'] ?? '', $getArr);
            $qgisLayers = $getArr['LAYER'] ?? null;
        }

        return [
            'metaSize' => !empty($def->meta_size) ? (int)$def->meta_size : null,
            'metaBuffer' => !empty($def->meta_buffer) ? (int)$def->meta_buffer : 0,
            // It seems that auto expire makes the server hang, so it is only
            // set when explicitly configured on the layer.
            'expires' => !empty($def->ttl) ? max(30, (int)$def->ttl) : 30,
            'autoExpire' => !empty($def->auto_expire) ? (int)$def->auto_expire : null,
            'format' => !empty($def->format) ? $def->format : 'PNG',
            'cache' => !empty($def->cache) ? $def->cache : $defaultCache,
            'extraLayers' => !empty($def->layers) ? ',' . $def->layers : '',
            's3TileSet' => !empty($def->s3_tile_set) ? $def->s3_tile_set : null,
            'qgisLayers' => $qgisLayers,
            'title' => !empty($row['f_table_title']) ? $row['f_table_title'] : $row['f_table_name'],
            'abstract' => $row['f_table_abstract'] ?? '',
        ];
    }

    /**
     * A WMS source element. getfeatureinfo is only emitted when
     * $queryLayers is given (single-table PNG sources).
     */
    public static function renderWmsSource(string $name, string $format, string $layers, string $url, ?string $queryLayers = null): string
    {
        $s = "  <source name=\"$name\" type=\"wms\">\n";
        $s .= "    <getmap>\n";
        $s .= "      <params>\n";
        $s .= "        <FORMAT>$format</FORMAT>\n";
        $s .= "        <LAYERS>$layers</LAYERS>\n";
        $s .= "      </params>\n";
        $s .= "    </getmap>\n";
        $s .= "    <http>\n";
        $s .= "      <url>$url</url>\n";
        $s .= "    </http>\n";
        if ($queryLayers !== null) {
            $s .= "    <getfeatureinfo>\n";
            $s .= "      <info_formats>text/plain,application/vnd.ogc.gml</info_formats>\n";
            $s .= "      <params>\n";
            $s .= "        <QUERY_LAYERS>$queryLayers</QUERY_LAYERS>\n";
            $s .= "      </params>\n";
            $s .= "    </getfeatureinfo>\n";
        }
        $s .= "  </source>\n";
        return $s;
    }

    /**
     * A tileset element. Optional parts (metatile, metabuffer, auto_expire,
     * wgs84boundingbox) are only emitted when non-null.
     */
    public static function renderTileset(string $name, string $source, string $cache, array $extraGridNames, string $format,
                                         int    $expires, ?int $metaSize = null, ?int $metaBuffer = null, ?int $autoExpire = null,
                                         string $title = '', string $abstract = '', ?string $wgs84bbox = null): string
    {
        $s = "  <tileset name=\"$name\">\n";
        $s .= "    <source>$source</source>\n";
        $s .= "    <cache>$cache</cache>\n";
        $s .= "    <grid>g20</grid>\n";
        foreach ($extraGridNames as $gridName) {
            $s .= "    <grid>$gridName</grid>\n";
        }
        $s .= "    <format>$format</format>\n";
        if ($metaSize !== null) {
            $s .= "    <metatile>$metaSize $metaSize</metatile>\n";
        }
        if ($metaBuffer !== null) {
            $s .= "    <metabuffer>$metaBuffer</metabuffer>\n";
        }
        $s .= "    <expires>$expires</expires>\n";
        if ($autoExpire !== null) {
            $s .= "    <auto_expire>$autoExpire</auto_expire>\n";
        }
        $s .= "    <metadata>\n";
        $s .= "      <title><![CDATA[$title]]></title>\n";
        $s .= "      <abstract><![CDATA[$abstract]]></abstract>\n";
        if ($wgs84bbox !== null) {
            $s .= "      <wgs84boundingbox>$wgs84bbox</wgs84boundingbox>\n";
        }
        $s .= "    </metadata>\n";
        $s .= "  </tileset>\n";
        return $s;
    }

    /**
     * An s3 cache element. $tileSetPath is the first path segment of the
     * object url — the database name for the shared cache, or the layer's
     * s3_tile_set for a per-layer cache (the latter without {tileset}).
     */
    public static function renderS3Cache(string $name, string $tileSetPath, bool $perTileSet): string
    {
        $host = App::$param['s3']['host'] ?? '';
        $tileset = $perTileSet ? '{tileset}/' : '';
        $s = "  <cache name=\"$name\" type=\"s3\">\n";
        $s .= "    <url>https://$host/$tileSetPath/$tileset{grid}/{z}/{x}/{y}/{ext}</url>\n";
        $s .= "    <headers>\n";
        $s .= "      <Host>$host</Host>\n";
        $s .= "    </headers>\n";
        $s .= "    <id>" . (App::$param['s3']['id'] ?? '') . "</id>\n";
        $s .= "    <secret>" . (App::$param['s3']['secret'] ?? '') . "</secret>\n";
        $s .= "    <region>" . (App::$param['s3']['region'] ?? '') . "</region>\n";
        $s .= "    <operation type=\"put\">\n";
        $s .= "      <headers>\n";
        $s .= "        <x-amz-storage-class>REDUCED_REDUNDANCY</x-amz-storage-class>\n";
        $s .= "        <x-amz-acl>public-read</x-amz-acl>\n";
        $s .= "      </headers>\n";
        $s .= "    </operation>\n";
        $s .= "    <symlink_blank/>\n";
        $s .= "    <creation_retry>3</creation_retry>\n";
        $s .= "  </cache>\n";
        return $s;
    }

    private function renderHeader(): string
    {
        $db = $this->connection->database;
        $path = App::$param['mapCache']['path'] ?? App::$param['path'];
        $host = App::$param['host'] ?? '';

        $s = "<mapcache>\n";
        $s .= "  <locker type=\"disk\">\n";
        $s .= "    <directory>/tmp</directory>\n";
        $s .= "    <timeout>30</timeout>\n";
        $s .= "    <retry>0.6</retry>\n";
        $s .= "  </locker>\n";
        $s .= "  <metadata>\n";
        $s .= "    <title>my mapcache service</title>\n";
        $s .= "    <abstract>woot! this is a service abstract!</abstract>\n";
        $s .= "    <url>$host/mapcache/$db</url>\n";
        $s .= "  </metadata>\n";
        $s .= "  <cache name=\"sqlite\" type=\"sqlite3\">\n";
        $s .= "    <dbfile>{$path}app/wms/mapcache/sqlite/$db/{tileset}.sqlite3</dbfile>\n";
        $s .= "    <symlink_blank/>\n";
        $s .= "    <creation_retry>3</creation_retry>\n";
        $s .= "  </cache>\n";
        $s .= "  <cache name=\"disk\" type=\"disk\">\n";
        $s .= "    <base>{$path}app/wms/mapcache/disk/$db/</base>\n";
        $s .= "    <symlink_blank/>\n";
        $s .= "    <creation_retry>3</creation_retry>\n";
        $s .= "  </cache>\n";
        $s .= self::renderS3Cache('s3', $db, perTileSet: true);
        $s .= "  <cache name=\"memcache\" type=\"memcache\">\n";
        $s .= "    <server>\n";
        $s .= "      <host>memcached</host>\n";
        $s .= "      <port>11211</port>\n";
        $s .= "    </server>\n";
        $s .= "  </cache>\n";
        $s .= "  <format name=\"jpeg_low\" type=\"JPEG\">\n    <quality>60</quality>\n    <photometric>ycbcr</photometric>\n  </format>\n";
        $s .= "  <format name=\"jpeg_medium\" type=\"JPEG\">\n    <quality>75</quality>\n    <photometric>ycbcr</photometric>\n  </format>\n";
        $s .= "  <format name=\"jpeg_high\" type=\"JPEG\">\n    <quality>95</quality>\n    <photometric>ycbcr</photometric>\n  </format>\n";
        $s .= "  <format name=\"MVT\" type=\"RAW\">\n    <extension>mvt</extension>\n    <mime_type>application/vnd.mapbox-vector-tile</mime_type>\n  </format>\n";
        $s .= "  <format name=\"JSON\" type=\"RAW\">\n    <extension>json</extension>\n    <mime_type>application/json</mime_type>\n  </format>\n";
        $s .= "  <grid name=\"g20\">\n";
        $s .= "    <metadata>\n";
        $s .= "      <title>GoogleMapsCompatible</title>\n";
        $s .= "      <WellKnownScaleSet>urn:ogc:def:wkss:OGC:1.0:GoogleMapsCompatible</WellKnownScaleSet>\n";
        $s .= "    </metadata>\n";
        $s .= "    <extent>-20037508.3427892480 -20037508.3427892480 20037508.3427892480 20037508.3427892480</extent>\n";
        $s .= "    <srs>EPSG:3857</srs>\n";
        $s .= "    <srsalias>EPSG:900913</srsalias>\n";
        $s .= "    <units>m</units>\n";
        $s .= "    <size>256 256</size>\n";
        $s .= "    <resolutions>156543.0339280410 78271.51696402048 39135.75848201023 19567.87924100512 9783.939620502561\n";
        $s .= "      4891.969810251280 2445.984905125640 1222.992452562820 611.4962262814100 305.7481131407048\n";
        $s .= "      152.8740565703525 76.43702828517624 38.21851414258813 19.10925707129406 9.554628535647032\n";
        $s .= "      4.777314267823516 2.388657133911758 1.194328566955879 0.5971642834779395 0.298582141739\n";
        $s .= "      0.149291070869 0.074645535435 0.0373227677175 0.018661384 0.009330692 0.004665346 0.002332673\n";
        $s .= "      0.001166337\n";
        $s .= "    </resolutions>\n";
        $s .= "  </grid>\n";
        return $s;
    }

    private function renderFooter(): string
    {
        $s = "  <default_format>PNG</default_format>\n";
        $s .= "  <service type=\"wms\" enabled=\"true\">\n";
        $s .= "    <full_wms>assemble</full_wms>\n";
        $s .= "    <resample_mode>bilinear</resample_mode>\n";
        $s .= "    <format allow_client_override=\"true\">PNG</format>\n";
        $s .= "    <maxsize>16384</maxsize>\n";
        $s .= "  </service>\n";
        $s .= "  <service type=\"wmts\" enabled=\"true\"/>\n";
        $s .= "  <service type=\"tms\" enabled=\"true\"/>\n";
        $s .= "  <service type=\"kml\" enabled=\"true\"/>\n";
        $s .= "  <service type=\"gmaps\" enabled=\"true\"/>\n";
        $s .= "  <service type=\"ve\" enabled=\"true\"/>\n";
        $s .= "  <errors>report</errors>\n";
        $s .= "  <lock_dir>/tmp</lock_dir>\n";
        $s .= "  <lock_retry>10000</lock_retry>\n";
        $s .= "  <log_level>warn</log_level>\n";
        $s .= "  <!-- start extra -->\n";
        foreach (Mapcache::getSources() as $source) {
            $s .= $source . "\n";
        }
        foreach (Mapcache::getTileSets() as $tileSet) {
            $s .= $tileSet . "\n";
        }
        $s .= "  <!-- end extra -->\n";
        $s .= "</mapcache>\n";
        return $s;
    }

    private function mapserverUrl(string $schema, string $type, bool $trailingAmp = true): string
    {
        return App::$param['mapCache']['wmsHost'] . "/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/"
            . $this->connection->database . "_" . $schema . "_$type.map" . ($trailingAmp ? "&" : "");
    }

    /**
     * Build the complete MapCache XML document as a string.
     */
    public function generate(): string
    {
        $db = $this->connection->database;
        $formats = App::$param['mapCache']['formats'] ?? null;
        $mvtEnabled = empty($formats) || (is_array($formats) && in_array('mvt', $formats));
        $jsonEnabled = empty($formats) || (is_array($formats) && in_array('json', $formats));
        $defaultCache = !empty(App::$param['mapCache']['type']) ? App::$param['mapCache']['type'] : 'sqlite';
        $wgs84bbox = !empty(App::$param['wgs84boundingbox']) ? implode(' ', App::$param['wgs84boundingbox']) : '-180 -90 180 90';
        $grids = Mapcache::getGrids();
        $gridNames = array_keys($grids);

        $s = $this->renderHeader();
        foreach ($grids as $grid) {
            $s .= $grid . "\n";
        }

        $layersPerSchema = [];
        $seen = [];
        $result = $this->getLayerRows();
        while ($row = $this->fetchRow($result)) {
            if ($row['f_table_schema'] == 'sqlapi' || !$row['enableows']) {
                continue;
            }
            $table = $row['f_table_schema'] . '.' . $row['f_table_name'];
            $layersPerSchema[$row['f_table_schema']][] = $table;
            if (in_array($table, $seen)) {
                continue;
            }
            $seen[] = $table;
            $set = self::layerSettings($row, $defaultCache);
            $cache = $set['cache'];

            $s .= "\n  <!-- $table -->\n";

            if ($cache == 's3' && $set['s3TileSet']) {
                $cache = "s3_" . $table;
                $s .= self::renderS3Cache($cache, $set['s3TileSet'], perTileSet: false);
            }

            // PNG source/tileset — either directly against qgis_mapserv or
            // against the schema's WMS mapfile.
            $pngUrl = $set['qgisLayers']
                ? explode('&', $row['wmssource'])[0] . "&transparent=true&DPI_=96&"
                : $this->mapserverUrl($row['f_table_schema'], 'wms', trailingAmp: false);
            $s .= self::renderWmsSource($table, 'PNG', ($set['qgisLayers'] ?: $table) . $set['extraLayers'], $pngUrl, queryLayers: $table);
            $s .= self::renderTileset($table, $table, $cache, $gridNames, $set['format'], $set['expires'],
                metaSize: $set['metaSize'], metaBuffer: $set['metaBuffer'], autoExpire: $set['autoExpire'],
                title: $set['title'], abstract: $set['abstract'], wgs84bbox: $wgs84bbox);

            // MVT and JSON tiles come from the schema's WFS mapfile. The
            $vectorCache =  $set['cache'];
            foreach ([['mvt', 'MVT', $mvtEnabled], ['json', 'JSON', $jsonEnabled]] as [$ext, $format, $enabled]) {
                if (!$enabled) {
                    continue;
                }
                $s .= self::renderWmsSource("$table.$ext", $ext, $table . $set['extraLayers'], $this->mapserverUrl($row['f_table_schema'], 'wfs'));
                $s .= self::renderTileset("$table.$ext", "$table.$ext", $vectorCache, $gridNames, $format, $set['expires'],
                    autoExpire: $set['autoExpire'], title: $set['title'], abstract: $set['abstract'], wgs84bbox: $wgs84bbox);
            }
        }

        // Merged per-schema source/tileset with all the schema's layers.
        foreach ($layersPerSchema as $schema => $layers) {
            $s .= "\n  <!-- $schema -->\n";
            $schemaUrl = empty(App::$param['useQgisForMergedLayers'][$schema])
                ? $this->mapserverUrl($schema, 'wms')
                : App::$param['mapCache']['wmsHost'] . "/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2/app/wms/qgsfiles/parsed_"
                . App::$param['useQgisForMergedLayers'][$schema] . "&transparent=true";
            $s .= self::renderWmsSource($schema, 'image/png', implode(',', $layers), $schemaUrl);
            $s .= self::renderTileset($schema, $schema, $defaultCache, $gridNames, 'PNG', 60,
                metaSize: 3, metaBuffer: 0, title: $schema);
            if ($mvtEnabled) {
                $s .= self::renderWmsSource("$schema.mvt", 'mvt', implode(',', $layers), $this->mapserverUrl($schema, 'wfs'));
                $s .= self::renderTileset("$schema.mvt", "$schema.mvt", $defaultCache, $gridNames, 'MVT', 60, title: $schema);
            }
        }

        $s .= $this->renderFooter();
        return $s;
    }

    /**
     * Write the config to app/wms/mapcache/<database>.xml, but only when its
     * content changed.
     */
    public function write(string $content): array
    {
        $file = App::$param['path'] . "app/wms/mapcache/" . $this->connection->database . ".xml";
        $changed = !file_exists($file) || md5_file($file) != md5($content);
        if ($changed) {
            @unlink($file);
            file_put_contents($file, $content);
        }
        return ["success" => true, "message" => "MapCache file written", "changed" => $changed, "ch" => $file];
    }
}
