<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\wfs\output;

use app\models\Table;
use app\wfs\Context;
use app\wfs\Request;
use stdClass;

/**
 * GeoJSON output for the GetFeature handler (OGC API Features). Streams a FeatureCollection one
 * feature at a time; numberReturned and the paging links are only known when the cursor is
 * drained, so they go in the footer. In single mode the one feature IS the document.
 *
 * The geometry column arrives as JSON text from ST_AsGeoJSON and is inlined verbatim. Properties
 * are converted from PDO strings by the column's udt_name (Table::$metaData); unknown types stay
 * strings, arrays stay PostgreSQL literals, bytea is skipped.
 */
final class GeoJsonWriter implements FeatureWriterInterface
{
    private const array INT_TYPES   = ['int2', 'int4', 'int8', 'smallint', 'integer', 'bigint', 'serial', 'bigserial', 'oid'];
    private const array FLOAT_TYPES = ['float4', 'float8', 'numeric', 'real', 'double precision', 'decimal', 'money'];
    private const array BOOL_TYPES  = ['bool', 'boolean'];
    private const array JSON_TYPES  = ['json', 'jsonb'];
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    private int $numberReturned = 0;
    private ?int $numberMatched = null;

    /**
     * @param string $crsUri CRS of the geometry (informational; the Content-Crs header is set by the controller)
     * @param list<array{rel:string,type:string,href:string,title?:string}> $links static links (self, collection)
     * @param string $pageHref items URL carrying every query parameter except offset; next/prev append offset
     * @param bool $single emit one bare Feature (with $links) instead of a FeatureCollection
     * @param bool $suppressFlush skip the physical flush (output captured by the caller or a test)
     */
    public function __construct(
        private readonly string $crsUri,
        private readonly array  $links = [],
        private readonly string $pageHref = '',
        private readonly int    $offset = 0,
        private readonly int    $limit = 10,
        private readonly bool   $single = false,
        private readonly bool   $suppressFlush = false,
    ) {}

    public function numberReturned(): int
    {
        return $this->numberReturned;
    }

    public function wantsBoundedBy(): bool
    {
        return false;
    }

    // XML-only hooks of the interface: nothing to emit for GeoJSON.
    public function writeXmlProlog(): void {}
    public function writeFeatureMembersOpen(string $version): void {}
    public function writeFeatureMembersClose(string $version): void {}
    public function writeTag(string $type, ?string $ns, string $tag, ?array $atts = null, bool $newline = true): void {}

    public function write(string $s): void
    {
        echo $s;
    }

    public function flush(): void
    {
        if ($this->suppressFlush) return;
        flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }

    public function writeFeatureCollectionOpen(Request $req, Context $ctx, ?int $numberMatched = null): void
    {
        $this->numberMatched = $numberMatched;
        if ($this->single) return;
        $this->write('{"type":"FeatureCollection"'
            . ($numberMatched !== null ? ',"numberMatched":' . $numberMatched : '')
            . ',"timeStamp":"' . gmdate('Y-m-d\TH:i:s\Z') . '","features":[');
    }

    public function writeFeature(array $row, string $table, Table $tableObj, Request $req, Context $ctx): void
    {
        $geometry = 'null';
        $props = [];
        $pkey = $tableObj->primaryKey['attname'] ?? null;
        foreach ($row as $field => $value) {
            if ($field === 'fid' || $field === 'FID' || $field === 'oid') {
                continue;
            }
            if ($field === $pkey) {
                continue;
            }
            $info = $tableObj->metaData[$field] ?? null;
            if ($info === null) {
                continue;
            }
            $type = (string)($info['type'] ?? '');
            if ($type === 'geometry') {
                if ($value !== null && $value !== '') {
                    $geometry = (string)$value;
                }
                continue;
            }
            if ($type === 'bytea') {
                continue;
            }
            $props[$field] = self::convert($value, $type);
        }
        $id = $row['fid'] ?? null;
        if ($id !== null && $pkey !== null && in_array($tableObj->metaData[$pkey]['type'] ?? '', self::INT_TYPES, true)) {
            $id = (int)$id;
        }
        $feature = '{"type":"Feature","id":' . json_encode($id, self::JSON_FLAGS)
            . ',"geometry":' . $geometry
            . ',"properties":' . json_encode($props === [] ? new stdClass() : $props, self::JSON_FLAGS);
        if ($this->single) {
            $feature .= ',"links":' . json_encode($this->links, self::JSON_FLAGS);
        }
        $feature .= '}';
        $this->write(($this->numberReturned > 0 && !$this->single ? ',' : '') . $feature);
        $this->numberReturned++;
        $this->flush();
    }

    public function writeFeatureCollectionClose(): void
    {
        if ($this->single) return;
        $links = $this->links;
        $sep = str_contains($this->pageHref, '?') ? '&' : '?';
        if ($this->numberMatched !== null && $this->offset + $this->numberReturned < $this->numberMatched) {
            $links[] = ['rel' => 'next', 'type' => 'application/geo+json', 'title' => 'Next page',
                'href' => $this->pageHref . $sep . 'offset=' . ($this->offset + $this->limit)];
        }
        if ($this->offset > 0) {
            $links[] = ['rel' => 'prev', 'type' => 'application/geo+json', 'title' => 'Previous page',
                'href' => $this->pageHref . $sep . 'offset=' . max(0, $this->offset - $this->limit)];
        }
        $this->write('],"numberReturned":' . $this->numberReturned
            . ',"links":' . json_encode($links, self::JSON_FLAGS) . '}');
        $this->flush();
    }

    /** PDO string → JSON scalar by PostgreSQL udt_name. Unknown types are returned unchanged. */
    public static function convert(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($type, self::INT_TYPES, true)) {
            return is_numeric($value) && (string)(int)$value === (string)$value ? (int)$value : $value;
        }
        if (in_array($type, self::FLOAT_TYPES, true)) {
            return is_numeric($value) ? (float)$value : $value;
        }
        if (in_array($type, self::BOOL_TYPES, true)) {
            return $value === true || $value === 't' || $value === 'true' || $value === '1' || $value === 1;
        }
        if (in_array($type, self::JSON_TYPES, true) && is_string($value)) {
            $decoded = json_decode($value, true);
            return ($decoded === null && trim($value) !== 'null') ? $value : $decoded;
        }
        return $value;
    }
}
