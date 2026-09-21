<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\conf\App;
use InvalidArgumentException;
use RuntimeException;

/**
 * The output formats a snapshot can be produced in. One entry per format is
 * the whole knowledge of the format in GC2: the worker builds its ogr2ogr line
 * from it, the model and the read API take file name and media type from it,
 * and the STAC writer takes the asset key and title. Adding a format is an
 * entry here plus tests — no other code carries a list of formats.
 *
 * Entries are immutable and shared (get() returns the same instance), so they
 * are safe to hold under a resident SAPI: nothing request-scoped lives here.
 */
final readonly class SnapshotFormat
{
    /**
     * The built-in default when nothing is configured: the format snapshots
     * were produced in before formats were selectable.
     */
    public const array DEFAULT_FORMATS = ['parquet'];

    /**
     * Type mapping ogr2ogr needs for both drivers: PostgreSQL `time` and
     * `bytea` columns have no target type of their own and would otherwise
     * abort the export.
     */
    private const array COMMON_OGR_ARGS = ['-mapFieldType', 'Time=String,Binary=String'];

    /**
     * @param string $id Format id used in the API and stored on the row.
     * @param string $driver ogr2ogr `-f` driver name.
     * @param string $extension File name extension, without the dot.
     * @param string $mediaType Content-Type the read API serves the file as.
     * @param bool $requiresGeometry True when the driver cannot write a
     *     relation without a geometry column; such a format is skipped with a
     *     reason rather than failing the snapshot.
     * @param string $stacAssetKey Key of the file's asset in the STAC Item.
     * @param array<int, string> $ogrArgs Extra ogr2ogr arguments, each one
     *     escaped separately by the worker.
     * @param string $title Asset title, read through stacTitle().
     * @param string|null $spatialTitle Asset title used instead of $title when
     *     the snapshot has a geometry column.
     */
    private function __construct(
        public string   $id,
        public string   $driver,
        public string   $extension,
        public string   $mediaType,
        public bool     $requiresGeometry,
        public string   $stacAssetKey,
        public array    $ogrArgs,
        private string  $title,
        private ?string $spatialTitle = null,
    )
    {
    }

    /**
     * The registry itself. Built once and kept in a function static (a readonly
     * class has no static properties), so every get() of a format hands out the
     * same immutable instance.
     *
     * @return array<string, self>
     */
    private static function registry(): array
    {
        static $registry = null;
        return $registry ??= [
            'parquet' => new self(
                id: 'parquet',
                driver: 'Parquet',
                extension: 'parquet',
                mediaType: 'application/vnd.apache.parquet',
                requiresGeometry: false,
                stacAssetKey: 'data',
                ogrArgs: self::COMMON_OGR_ARGS,
                title: 'Parquet',
                spatialTitle: 'GeoParquet',
            ),
            'flatgeobuf' => new self(
                id: 'flatgeobuf',
                driver: 'FlatGeobuf',
                extension: 'fgb',
                mediaType: 'application/flatgeobuf',
                requiresGeometry: true,
                stacAssetKey: 'flatgeobuf',
                ogrArgs: self::COMMON_OGR_ARGS,
                title: 'FlatGeobuf',
            ),
        ];
    }

    /**
     * Every known format id, in registry order — the order a request and the
     * OpenAPI enum present them in.
     *
     * @return array<int, string>
     */
    public static function ids(): array
    {
        return array_keys(self::registry());
    }

    /**
     * @throws InvalidArgumentException when $id is not a known format. Callers
     *     that answer a request validate with has() first and turn a miss into
     *     400 INVALID_REQUEST; reaching this exception is a bug.
     */
    public static function get(string $id): self
    {
        $format = self::registry()[$id] ?? null;
        if ($format === null) {
            throw new InvalidArgumentException("Unknown snapshot format '$id'; known formats are " . implode(', ', self::ids()));
        }
        return $format;
    }

    public static function has(string $id): bool
    {
        return isset(self::registry()[$id]);
    }

    /**
     * The format of a file, by its name — how a row written before the
     * `formats` column existed is read back, and how `/files/{name}` picks a
     * media type. Null when the extension belongs to no format (metadata.json,
     * or a name without an extension at all).
     */
    public static function fromExtension(string $fileName): ?self
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }
        foreach (self::registry() as $format) {
            if ($format->extension === $extension) {
                return $format;
            }
        }
        return null;
    }

    /**
     * The server default: the formats produced when a request names none.
     * Read from App.php on every call, so a config change needs no restart
     * reasoning, and validated loudly — a typo in App.php must not silently
     * produce fewer formats than the operator asked for.
     *
     * @return array<int, string>
     * @throws RuntimeException when snapshot.formats is not a list of known ids.
     */
    public static function defaults(): array
    {
        $configured = App::$param['snapshot']['formats'] ?? null;
        if ($configured === null) {
            return self::DEFAULT_FORMATS;
        }
        if (!is_array($configured) || !array_is_list($configured)) {
            throw new RuntimeException("snapshot.formats in App.php must be a list of format ids; known formats are " . implode(', ', self::ids()));
        }
        if ($configured === []) {
            return self::DEFAULT_FORMATS;
        }
        foreach ($configured as $id) {
            if (!is_string($id) || !self::has($id)) {
                throw new RuntimeException("Unknown snapshot format '" . (is_string($id) ? $id : gettype($id)) . "' in snapshot.formats in App.php; known formats are " . implode(', ', self::ids()));
            }
        }
        return array_values(array_unique($configured));
    }

    /** Name of this format's data file inside a snapshot directory. */
    public function fileName(string $uuid): string
    {
        return "data-$uuid.$this->extension";
    }

    /**
     * Title of the file's STAC asset. Parquet is called GeoParquet when the
     * relation has a geometry column; the other formats read the same either
     * way.
     */
    public function stacTitle(bool $spatial): string
    {
        return $spatial ? ($this->spatialTitle ?? $this->title) : $this->title;
    }
}
