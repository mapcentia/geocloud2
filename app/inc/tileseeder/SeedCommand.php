<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\tileseeder;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;
use SimpleXMLElement;
use Throwable;

/**
 * The mapcache_seed invocation for one seed job.
 *
 * argv() returns a list, never a string, so the shell never re-parses a value the
 * caller supplied: v3 built one string and a tileset containing `; rm -rf /` ran.
 * The database password is only ever in env(), because a command line is readable
 * with ps by everyone on the node.
 */
final class SeedCommand
{
    public function __construct(
        private readonly string  $database,
        private readonly string  $tileset,
        private readonly string  $grid,
        private readonly int     $zoomStart,
        private readonly int     $zoomEnd,
        private readonly ?string $extentLayer,
        private readonly int     $threads,
    )
    {
    }

    /**
     * Everything that can be known before a process starts. Throws 400 rather than
     * letting mapcache_seed fail minutes later in a log nobody reads.
     *
     * $connection addresses the job's own database and is only used when
     * $extentLayer is given (to prove that relation exists); it is a required
     * parameter rather than an optional one so that no caller can reach this
     * validator without it and silently skip that check — the v3 shim and the v4
     * controller must both get the same answers.
     *
     * @throws GC2Exception
     */
    public static function validate(string     $database, string $tileset, string $grid,
                                    int        $zoomStart, int $zoomEnd, ?string $extentLayer, int $threads,
                                    Connection $connection): void
    {
        foreach (['tileset' => $tileset, 'grid' => $grid, 'extent_layer' => $extentLayer] as $field => $value) {
            if ($value !== null && (str_contains($value, '..') || !preg_match('/^[A-Za-z0-9_.:\-]+$/', $value))) {
                throw new GC2Exception("Invalid $field", 400, null, 'INVALID_REQUEST');
            }
        }
        $config = self::configPath($database);
        if (!is_file($config)) {
            throw new GC2Exception('No tile cache configuration for this database', 404, null, 'NOT_FOUND');
        }
        $xml = self::loadConfig($config);
        if ($xml === null) {
            throw new GC2Exception('The tile cache configuration for this database could not be parsed', 500, null, 'INVALID_CACHE_CONFIG');
        }
        $element = self::findTileset($xml, $tileset);
        if ($element === null) {
            throw new GC2Exception('Tileset not found', 404, null, 'TILESET_NOT_FOUND');
        }
        // The authority for `grid` is the tileset's own <grid> children in this
        // database's generated config — not Mapcache::getGrids(), which lists
        // app/conf/grids/*.xml. Those two sets are not the same: GC2 writes an
        // inline grid called g20 into every config and every <tileset> declares
        // it (app/models/Mapcachefile.php), while app/conf/grids may hold other
        // names, none, or names a given tileset does not declare. Validating
        // against the wrong set rejected the only grid that works ("g20" -> 400)
        // and accepted one mapcache_seed then refused ("grid not configured for
        // tileset", exit 1) — and broke v3, which used to pass `grid` straight
        // to the binary. getGrids() is left alone; its other callers (the config
        // generator, Baselayerjs) are about what exists, not about what this
        // tileset can be seeded with.
        $declared = self::declaredGrids($element);
        if (!in_array($grid, $declared, true)) {
            throw new GC2Exception(sprintf('Unknown grid "%s" for tileset %s. That tileset declares: %s',
                $grid, $tileset, $declared === [] ? '(no grids at all)' : implode(', ', $declared)),
                400, null, 'UNKNOWN_GRID');
        }
        if ($zoomStart < 0 || $zoomEnd < 0 || $zoomStart > $zoomEnd) {
            throw new GC2Exception('zoom_start must be >= 0 and <= zoom_end', 400, null, 'INVALID_REQUEST');
        }
        // Spec §7: both zooms within the grid's levels. A grid has as many zoom
        // levels as it has resolutions, so zoom_end: 40 on a 28-resolution grid
        // is a request the binary can never satisfy, and is refused here instead
        // of reaching it. A grid definition without <resolutions> (not something
        // GC2 generates, but a hand-written app/conf/grids file could) leaves the
        // bound unknown, and an unknown bound must not refuse a valid request.
        $levels = self::gridLevels($xml, $grid);
        if ($levels !== null && $zoomEnd > $levels - 1) {
            throw new GC2Exception(sprintf('zoom_end must be within grid %s\'s zoom levels (0-%d)', $grid, $levels - 1),
                400, null, 'INVALID_REQUEST');
        }
        $maxThreads = App::$param['tileseeder']['maxThreads'] ?? 4;
        if ($threads < 1 || $threads > $maxThreads) {
            throw new GC2Exception("threads must be between 1 and $maxThreads", 400, null, 'INVALID_REQUEST');
        }
        // Spec §7: extent_layer must be a relation the caller may read. Existence
        // is checked here, so every caller of this validator (the v4 controller
        // and the v3 shim) gets a 404 now instead of an exit-1 seed run minutes
        // later in a log nobody reads; whether the *caller* may read it depends
        // on the request's identity, and is enforced by the controller.
        if ($extentLayer !== null && !self::relationExists($connection, $extentLayer)) {
            throw new GC2Exception("Extent layer not found: $extentLayer", 404, null, 'EXTENT_LAYER_NOT_FOUND');
        }
    }

    /**
     * Whether the database holds a relation mapcache_seed's OGR datasource could
     * read features from. Asked of the catalogs with bound parameters rather than
     * through Model::isTableOrView()/getGeometryColumns(): those interpolate the
     * name into SQL and memoise the answer in the shared cache, and the cache is
     * not initialised in every context this validator runs in (the CLI and the
     * unit suite have none, where those helpers fault on a null cache item).
     * Partitioned and foreign tables count, as OGR reads them like any other.
     */
    private static function relationExists(Connection $connection, string $relation): bool
    {
        $model = new Model(connection: $connection);
        $bits = explode('.', $relation);
        $schema = count($bits) > 1 ? $bits[0] : ($connection->schema ?: 'public');
        $name = count($bits) > 1 ? $bits[1] : $bits[0];
        $res = $model->prepare("SELECT EXISTS (
                                    SELECT 1
                                    FROM pg_catalog.pg_class c
                                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                                    WHERE n.nspname = :schema AND c.relname = :relation
                                      AND c.relkind IN ('r', 'v', 'm', 'f', 'p')
                                ) AS e");
        $model->execute($res, ['schema' => $schema, 'relation' => $name]);
        return (bool)($model->fetchRow($res)['e'] ?? false);
    }

    /**
     * @param array{host:string, port:string, user:string} $pgParams
     * @return list<string>
     */
    public function argv(array $pgParams): array
    {
        $argv = [
            App::$param['tileseeder']['seedBinary'] ?? '/usr/local/bin/mapcache_seed',
            '-c', self::configPath($this->database),
            '-t', $this->tileset,
            '-g', $this->grid,
            '-z', $this->zoomStart . ',' . $this->zoomEnd,
            '-n', (string)$this->threads,
            '-v',
        ];
        // -d and -l go together or not at all. mapcache_seed validates the OGR
        // datasource before it seeds anything: given -d without -l it answers
        // "ogr datastore contains more than one layer. please specify which one
        // to use with --ogr-layer", prints its usage and exits 1 — so passing -d
        // unconditionally made every seed without an extent_layer (the normal
        // case: the field is optional) fail. On a datasource with exactly one
        // visible layer it is worse than an error, because the seed is then
        // silently clipped to that one layer's extent. With no extent_layer the
        // seed covers the grid's own extent, which is what the API promises.
        if ($this->extentLayer !== null) {
            $argv[] = '-d';
            $argv[] = sprintf('PG:host=%s port=%s user=%s dbname=%s',
                $pgParams['host'], $pgParams['port'], $pgParams['user'], $this->database);
            $argv[] = '-l';
            $argv[] = $this->extentLayer;
        }
        return $argv;
    }

    /** @return array<string, string> */
    public function env(string $pgPassword): array
    {
        return ['PGPASSWORD' => $pgPassword];
    }

    /**
     * The database's mapcache configuration as a tree, or null if even libxml's
     * recovery cannot make one.
     *
     * LIBXML_RECOVER is load-bearing, not defensive: GC2's own generated config
     * is not well-formed XML. Mapcachefile writes the mapserv URL with a bare
     * trailing `&` ("…_wfs.map&"), which libxml reports as "xmlParseEntityRef: no
     * name" and refuses the whole document for — once per source, 200+ times in a
     * real config. MapCache itself parses with ezxml, which does not care, so the
     * file works for the thing that consumes it. A strict parse here would
     * therefore answer 500 for every seed request on every real install, which is
     * worse than the bug this parsing replaced. Recovery skips the offending
     * entity reference and builds the rest of the tree, which is all this needs:
     * the <tileset> elements and their <grid> children parse exactly as written
     * (verified against a 619-tileset config). libxml's error state is restored,
     * because it is process-global and this code also runs under a resident SAPI.
     */
    private static function loadConfig(string $config): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_file($config, SimpleXMLElement::class, LIBXML_NOERROR | LIBXML_RECOVER);
            if (!$xml instanceof SimpleXMLElement) {
                return null;
            }
            // LIBXML_RECOVER hands back an *uninitialised* element for a file with no
            // root element at all (one byte of junk, or plain prose), and every access
            // to it — including getName() — raises an Error rather than an exception.
            // Probe it here so the caller turns it into INVALID_CACHE_CONFIG rather
            // than a 500 carrying a PHP Error message.
            try {
                return $xml->getName() === '' ? null : $xml;
            } catch (Throwable) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * The <tileset> element of that name, or null. Iterated rather than looked up
     * with xpath(): $tileset is request input, and building an XPath string from
     * request input is the same class of mistake as building SQL from it.
     */
    private static function findTileset(SimpleXMLElement $xml, string $tileset): ?SimpleXMLElement
    {
        foreach ($xml->tileset as $candidate) {
            if ((string)$candidate['name'] === $tileset) {
                return $candidate;
            }
        }
        return null;
    }

    /** @return list<string> the grid names a tileset declares, in document order */
    private static function declaredGrids(SimpleXMLElement $tileset): array
    {
        $grids = [];
        foreach ($tileset->grid as $grid) {
            $name = trim((string)$grid);
            if ($name !== '') {
                $grids[] = $name;
            }
        }
        return array_values(array_unique($grids));
    }

    /** How many zoom levels a grid definition has (one per resolution), or null
     *  when the config does not say. */
    private static function gridLevels(SimpleXMLElement $xml, string $grid): ?int
    {
        foreach ($xml->grid as $candidate) {
            if ((string)$candidate['name'] !== $grid) {
                continue;
            }
            $resolutions = trim((string)$candidate->resolutions);
            if ($resolutions === '') {
                return null;
            }
            return count(preg_split('/\s+/', $resolutions) ?: []);
        }
        return null;
    }

    private static function configPath(string $database): string
    {
        return App::$param['path'] . 'app/wms/mapcache/' . $database . '.xml';
    }
}
