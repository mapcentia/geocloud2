<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Model;
use app\ows\SourceResolver;
use Throwable;

/**
 * A layer's QML (QGIS style) and the switch between drawing with it and drawing with the
 * layer's MapServer classes.
 *
 * Two things are stored on the layer's settings row. `qml` is the style text. `wmssource` is
 * what decides who draws: when it points at the layer's own generated QGIS project, QGIS Server
 * draws the layer with the QML and the MapServer classes are ignored; when it is empty,
 * MapServer draws with the classes. Keeping the two apart is what lets a layer be switched to
 * MapServer and back without losing its QML.
 *
 * A layer whose `wmssource` is something else (an external WMS, an uploaded QGIS project) is
 * neither: its QML can be stored, but it cannot be switched on until that source is removed.
 */
class LayerQml extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * @param string $key schema.table.geometry_column
     * @return array{qml: ?string, use_qml: bool}
     */
    public function read(string $key): array
    {
        $row = $this->row($key);
        return [
            'qml' => self::textOrNull($row['qml'] ?? null),
            'use_qml' => self::isOwnProject($row['wmssource'] ?? null, $this->connection->database, $key),
        ];
    }

    /**
     * Applies a change of `qml` and/or `use_qml`. The mapfiles must be rewritten afterwards
     * (the caller does, as for any layer update).
     *
     * @param string $key schema.table.geometry_column
     * @param bool $hasQml whether the request carries `qml` at all (null is a value: it clears)
     * @throws GC2Exception
     */
    public function update(string $key, bool $hasQml, ?string $qml, ?bool $useQml): void
    {
        $row = $this->row($key);
        $stored = self::textOrNull($row['qml'] ?? null);
        $source = self::textOrNull($row['wmssource'] ?? null);
        $inUse = self::isOwnProject($source, $this->connection->database, $key);
        $plan = self::plan($stored, $inUse, $source !== null && !$inUse, $hasQml, self::textOrNull($qml), $useQml);

        [$schema, $table] = explode('.', $key);
        $set = ['qml' => $plan['qml']];
        if ($plan['use']) {
            if ($plan['write']) {
                [$set['wmssource'], $set['wmsclientepsgs']] = $this->writeProject($plan['qml'], $schema, $table);
            }
        } elseif ($inUse) {
            $set['wmssource'] = null;
            $set['wmsclientepsgs'] = null;
            $file = SourceResolver::parseQgsPath($source);
            if ($file !== null && is_file($file)) {
                @unlink($file);
            }
        }
        $assignments = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($set)));
        $res = $this->prepare("UPDATE settings.geometry_columns_join SET $assignments WHERE _key_ = :key");
        $this->execute($res, $set + ['key' => $key]);

        // Everything that remembers how the layer is drawn or where its source is
        Cache::deleteByPatterns([
            $this->connection->database . '_' . md5("$schema.$table") . '*',
            $this->connection->database . '*_meta_*',
            $this->connection->database . '*_legend_*',
            $this->connection->database . '*_geometryColumns',
        ]);
    }

    /**
     * What a request means, given the layer's state. Pure, so the rules are testable.
     *
     * @param ?string $stored the stored QML
     * @param bool $inUse the layer is drawn with its QML now
     * @param bool $external the layer has another source (external WMS, uploaded project)
     * @param bool $hasQml the request carries `qml`
     * @param ?string $qml its value (null clears)
     * @param ?bool $useQml the requested switch, null when the request does not carry it
     * @return array{qml: ?string, use: bool, write: bool} the QML to store, whether the layer
     *         is drawn with it afterwards, and whether the QGIS project must be (re)written
     * @throws GC2Exception
     */
    public static function plan(?string $stored, bool $inUse, bool $external, bool $hasQml, ?string $qml, ?bool $useQml): array
    {
        $next = $hasQml ? $qml : $stored;
        if ($hasQml && $next !== null) {
            self::assertWellFormed($next);
        }
        // Not asked: stay as is, except that a layer cannot go on using a QML that was removed
        $use = $useQml ?? ($inUse && $next !== null);
        if ($use && $next === null) {
            throw new GC2Exception("The layer has no QML to use. Send qml, or set use_qml to false", 400, null, 'QML_MISSING');
        }
        if ($use && $external) {
            throw new GC2Exception("The layer is drawn from an external source and cannot use a QML", 409, null, 'EXTERNAL_SOURCE');
        }
        return [
            'qml' => $next,
            'use' => $use,
            // Switched on, or already on with a changed QML
            'write' => $use && (!$inUse || ($hasQml && $next !== $stored)),
        ];
    }

    /**
     * Whether a layer's source is the QGIS project GC2 generates for it from its QML
     * (app/wms/qgsfiles/<database>_<schema>_<table>_<geometry column>.qgs).
     *
     * @param string $key schema.table.geometry_column
     */
    public static function isOwnProject(?string $wmssource, string $database, string $key): bool
    {
        $path = SourceResolver::parseQgsPath($wmssource);
        if ($path === null) {
            return false;
        }
        [$schema, $table] = explode('.', $key);
        return str_contains($path, '/qgsfiles/')
            && str_starts_with(basename($path), "{$database}_{$schema}_{$table}_");
    }

    /**
     * @throws GC2Exception
     */
    private static function assertWellFormed(string $qml): void
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($qml, options: LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new GC2Exception("The QML is not well-formed XML", 400, null, 'INVALID_QML');
        }
    }

    /**
     * @return array{0: string, 1: string} the layer's new wmssource and wmsclientepsgs
     * @throws GC2Exception
     */
    private function writeProject(string $qml, string $schema, string $table): array
    {
        $connection = clone $this->connection;
        $connection->schema = $schema;
        try {
            return new Qgisfile($connection)->project(qml: $qml, schema: $schema, rel: $table);
        } catch (\UnhandledMatchError) {
            // Qgisfile knows point, line and polygon layers
            throw new GC2Exception("A QML can only be used on a point, line or polygon layer", 400, null, 'QML_UNSUPPORTED_GEOMETRY');
        } catch (GC2Exception $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log((string)$e);
            throw new GC2Exception("The QGIS project for the layer could not be written", 500, null, 'QML_PROJECT_ERROR');
        }
    }

    /**
     * @throws GC2Exception
     */
    private function row(string $key): array
    {
        $res = $this->prepare("SELECT qml, wmssource FROM settings.geometry_columns_join WHERE _key_ = :key");
        $this->execute($res, ['key' => $key]);
        $row = $this->fetchRow($res);
        if (!$row) {
            throw new GC2Exception("Layer not found", 404, null, 'LAYER_NOT_FOUND');
        }
        return $row;
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
