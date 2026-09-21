<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Builds the static STAC 1.1.0 catalog of a database's published Parquet
 * snapshots: one Catalog, one Collection per relation and one Item per
 * snapshot, laid out alongside the data files themselves
 * ({database}/catalog.json, {database}/schema=X/relation=Y/collection.json,
 * .../_gc2_snapshot_date=D/item.json).
 *
 * Pure: rows in, documents out. Every href is *relative* to the document that
 * carries it, so the same files work under an S3 bucket, behind the read API
 * and on a local disk without knowing any base URL.
 *
 * The rows are trusted to be the published ones (the model filters); this
 * class only shapes them.
 */
final class StacCatalogWriter
{
    public const string STAC_VERSION = '1.1.0';

    /** STAC's own default extent when nothing is known about a collection's footprint. */
    private const array WORLD_BBOX = [-180, -90, 180, 90];

    public function __construct(private readonly string $database)
    {
    }

    /** The one JSON flavour every catalog document is written in. */
    public static function encode(array $document): string
    {
        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<int, array<string, mixed>> $publishedRows Published rows of
     *     settings.snapshots (Snapshot::listAllPublished()). `files`, `bbox`
     *     and `relation_schema` may be arrays or the raw JSON strings.
     * @param array<string, array{title?:?string, description?:?string, keywords?:array}> $relationMeta
     *     Layer metadata keyed "schema.relation"; missing or blank entries fall back.
     * @return array<string, array<string, mixed>> Documents keyed by path
     *     relative to the database root.
     */
    public function build(array $publishedRows, array $relationMeta): array
    {
        $byRelation = [];
        foreach ($publishedRows as $row) {
            $byRelation[$row['schema_name'] . '.' . $row['relation_name']][] = $row;
        }
        uksort($byRelation, function (string $a, string $b) use ($byRelation) {
            $ra = $byRelation[$a][0];
            $rb = $byRelation[$b][0];
            return [$ra['schema_name'], $ra['relation_name']] <=> [$rb['schema_name'], $rb['relation_name']];
        });

        $documents = [];
        $children = [];
        foreach ($byRelation as $id => $rows) {
            $schema = $rows[0]['schema_name'];
            $relation = $rows[0]['relation_name'];
            $dir = "schema=$schema/relation=$relation";
            $meta = $relationMeta[$id] ?? [];
            $title = $this->text($meta['title'] ?? null) ?? $id;

            // Newest first: the collection's item links and the summaries follow
            // this order, and the newest snapshot is what a reader usually wants.
            usort($rows, fn($a, $b) => strcmp((string)$b['snapshot_date'], (string)$a['snapshot_date']));

            $items = [];
            foreach ($rows as $row) {
                $items[$row['snapshot_date']] = $this->item($id, $row);
                $documents["$dir/_gc2_snapshot_date={$row['snapshot_date']}/item.json"] = $items[$row['snapshot_date']];
            }
            $documents["$dir/collection.json"] = $this->collection($id, $title, $meta, $rows, $items);
            $children[] = [
                'rel' => 'child',
                'href' => "./$dir/collection.json",
                'type' => 'application/json',
                'title' => $title,
            ];
        }

        return ['catalog.json' => $this->catalog($children)] + $documents;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function catalog(array $children): array
    {
        return [
            'type' => 'Catalog',
            'stac_version' => self::STAC_VERSION,
            'id' => $this->database,
            'title' => $this->database,
            'description' => "Parquet snapshots of database {$this->database} published by GC2",
            'links' => array_merge([
                ['rel' => 'self', 'href' => './catalog.json', 'type' => 'application/json'],
                ['rel' => 'root', 'href' => './catalog.json', 'type' => 'application/json'],
            ], $children),
        ];
    }

    /**
     * @param array{title?:?string, description?:?string, keywords?:array} $meta
     * @param array<int, array<string, mixed>> $rows Newest first.
     * @param array<string, array<string, mixed>> $items Items by snapshot date.
     * @return array<string, mixed>
     */
    private function collection(string $id, string $title, array $meta, array $rows, array $items): array
    {
        $dates = array_map(fn($r) => (string)$r['snapshot_date'], $rows);
        $versions = [];
        $epsgs = [];
        foreach ($rows as $row) {
            $version = $row['schema_version'] ?? null;
            if ($version !== null && !in_array($version, $versions, true)) {
                $versions[] = (string)$version;
            }
            if ($row['srs'] !== null && !in_array((int)$row['srs'], $epsgs, true)) {
                $epsgs[] = (int)$row['srs'];
            }
        }

        $summaries = ['gc2:schema_version' => $versions];
        if ($epsgs !== []) {
            $summaries['proj:epsg'] = $epsgs;
        }

        $links = [
            ['rel' => 'self', 'href' => './collection.json', 'type' => 'application/json'],
            ['rel' => 'root', 'href' => '../../catalog.json', 'type' => 'application/json'],
            ['rel' => 'parent', 'href' => '../../catalog.json', 'type' => 'application/json'],
        ];
        foreach ($dates as $date) {
            $links[] = ['rel' => 'item', 'href' => "./_gc2_snapshot_date=$date/item.json", 'type' => 'application/geo+json'];
        }

        return [
            'type' => 'Collection',
            'stac_version' => self::STAC_VERSION,
            'id' => $id,
            'title' => $title,
            'description' => $this->text($meta['description'] ?? null) ?? "Snapshots of $id",
            'keywords' => array_values(array_filter($meta['keywords'] ?? [], 'is_scalar')),
            'license' => 'proprietary',
            'extent' => [
                'spatial' => ['bbox' => [$this->union($items)]],
                'temporal' => ['interval' => [[min($dates) . 'T00:00:00Z', max($dates) . 'T00:00:00Z']]],
            ],
            'summaries' => $summaries,
            'links' => $links,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function item(string $collectionId, array $row): array
    {
        $bbox = $this->bbox($row['bbox'] ?? null);
        $files = $this->decode($row['files'] ?? null);
        $uuid = (string)$row['uuid'];
        $dataFile = null;
        foreach ($files as $file) {
            if (is_array($file) && isset($file['name']) && str_ends_with((string)$file['name'], '.parquet')) {
                $dataFile = (string)$file['name'];
                break;
            }
        }
        $dataFile ??= "data-$uuid.parquet";

        $properties = [
            'datetime' => $row['snapshot_date'] . 'T00:00:00Z',
            'gc2:row_count' => $row['row_count'] !== null ? (int)$row['row_count'] : null,
            'gc2:schema_version' => $row['schema_version'] !== null ? (string)$row['schema_version'] : null,
            'gc2:snapshot_id' => $uuid,
        ];
        if ($row['srs'] !== null) {
            $properties['proj:epsg'] = (int)$row['srs'];
        }

        $item = [
            'type' => 'Feature',
            'stac_version' => self::STAC_VERSION,
            'id' => $collectionId . '/' . $row['snapshot_date'],
            'collection' => $collectionId,
            'geometry' => $bbox === null ? null : [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$bbox[0], $bbox[1]],
                    [$bbox[2], $bbox[1]],
                    [$bbox[2], $bbox[3]],
                    [$bbox[0], $bbox[3]],
                    [$bbox[0], $bbox[1]],
                ]],
            ],
        ];
        // A STAC Item must not carry a bbox when its geometry is null.
        if ($bbox !== null) {
            $item['bbox'] = $bbox;
        }
        $item['properties'] = $properties;
        $item['links'] = [
            ['rel' => 'self', 'href' => './item.json', 'type' => 'application/geo+json'],
            ['rel' => 'root', 'href' => '../../../catalog.json', 'type' => 'application/json'],
            ['rel' => 'parent', 'href' => '../collection.json', 'type' => 'application/json'],
            ['rel' => 'collection', 'href' => '../collection.json', 'type' => 'application/json'],
        ];
        $item['assets'] = [
            'data' => [
                'href' => "./$dataFile",
                'type' => 'application/vnd.apache.parquet',
                'roles' => ['data'],
                'title' => $this->isSpatial($row, $bbox) ? 'GeoParquet' : 'Parquet',
            ],
            'metadata' => [
                'href' => "./metadata-$uuid.json",
                'type' => 'application/json',
                'roles' => ['metadata'],
            ],
        ];
        return $item;
    }

    /**
     * Union of the items' footprints, or the whole world when no snapshot of
     * the relation has one (a non-spatial or empty relation).
     *
     * @param array<string, array<string, mixed>> $items
     * @return array{0:float|int, 1:float|int, 2:float|int, 3:float|int}
     */
    private function union(array $items): array
    {
        $union = null;
        foreach ($items as $item) {
            $bbox = $item['bbox'] ?? null;
            if ($bbox === null) {
                continue;
            }
            $union = $union === null ? $bbox : [
                min($union[0], $bbox[0]),
                min($union[1], $bbox[1]),
                max($union[2], $bbox[2]),
                max($union[3], $bbox[3]),
            ];
        }
        return $union ?? self::WORLD_BBOX;
    }

    /**
     * ogr2ogr writes GeoParquet whenever the relation has a geometry column,
     * also when the table is empty and there is no footprint to show.
     *
     * @param array<string, mixed> $row
     */
    private function isSpatial(array $row, ?array $bbox): bool
    {
        if ($bbox !== null) {
            return true;
        }
        foreach ($this->decode($row['relation_schema'] ?? null) as $column) {
            $type = is_array($column) ? strtolower((string)($column['data_type'] ?? '')) : '';
            if (str_starts_with($type, 'geometry') || str_starts_with($type, 'geography')) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0:float, 1:float, 2:float, 3:float}|null */
    private function bbox(mixed $bbox): ?array
    {
        $bbox = is_string($bbox) ? $this->decode($bbox) : $bbox;
        if (!is_array($bbox) || count($bbox) !== 4) {
            return null;
        }
        foreach ($bbox as $v) {
            if (!is_numeric($v)) {
                return null;
            }
        }
        return array_map(fn($v) => (float)$v, array_values($bbox));
    }

    /** JSONB columns reach us as arrays (already decoded) or as raw strings. */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** Trimmed text, or null when there is nothing left to show. */
    private function text(?string $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
