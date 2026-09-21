<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Builds the static STAC 1.1.0 catalog of a database's published snapshots:
 * one Catalog, one Collection per relation and one Item per snapshot — with
 * one asset per produced output format — laid out alongside the data files
 * themselves
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

    /**
     * Projection extension v2, which spells the CRS `proj:code` ("EPSG:25832")
     * rather than the v1 `proj:epsg`. Declared only on the documents that
     * actually carry it.
     */
    public const string PROJECTION_EXTENSION = 'https://stac-extensions.github.io/projection/v2.0.0/schema.json';

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
     *     relative to the database root, `catalog.json` last: it is the entry
     *     point, so whoever writes these documents writes it once everything
     *     it links to is in place.
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

        return $documents + ['catalog.json' => $this->catalog($children)];
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
            // No self link: these documents are relative and do not know the
            // URL they are served from.
            'links' => array_merge([
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
        $codes = [];
        foreach ($rows as $row) {
            $version = $row['schema_version'] ?? null;
            if ($version !== null && !in_array((string)$version, $versions, true)) {
                $versions[] = (string)$version;
            }
            $code = $this->projCode($row);
            if ($code !== null && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        // Only summarise what there is something to say about; an empty
        // summaries object says nothing and an empty list is invalid.
        $summaries = array_filter([
            'gc2:schema_version' => $versions,
            'proj:code' => $codes,
        ], fn(array $values) => $values !== []);

        $links = [
            ['rel' => 'root', 'href' => '../../catalog.json', 'type' => 'application/json'],
            ['rel' => 'parent', 'href' => '../../catalog.json', 'type' => 'application/json'],
        ];
        foreach ($dates as $date) {
            $links[] = ['rel' => 'item', 'href' => "./_gc2_snapshot_date=$date/item.json", 'type' => 'application/geo+json'];
        }

        $collection = [
            'type' => 'Collection',
            'stac_version' => self::STAC_VERSION,
        ];
        if ($codes !== []) {
            $collection['stac_extensions'] = [self::PROJECTION_EXTENSION];
        }
        $collection += [
            'id' => $id,
            'title' => $title,
            'description' => $this->text($meta['description'] ?? null) ?? "Snapshots of $id",
            'keywords' => array_values(array_filter($meta['keywords'] ?? [], 'is_string')),
            // GC2 knows nothing about the licence of the data it snapshots.
            'license' => 'other',
            'extent' => [
                'spatial' => ['bbox' => [$this->union($items)]],
                'temporal' => ['interval' => [[min($dates) . 'T00:00:00Z', max($dates) . 'T00:00:00Z']]],
            ],
        ];
        if ($summaries !== []) {
            $collection['summaries'] = $summaries;
        }
        $collection['links'] = $links;
        return $collection;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function item(string $collectionId, array $row): array
    {
        $bbox = $this->bbox($row['bbox'] ?? null);
        $uuid = (string)$row['uuid'];

        $properties = ['datetime' => $row['snapshot_date'] . 'T00:00:00Z'];
        if (($row['row_count'] ?? null) !== null) {
            $properties['gc2:row_count'] = (int)$row['row_count'];
        }
        if (($row['schema_version'] ?? null) !== null) {
            $properties['gc2:schema_version'] = (string)$row['schema_version'];
        }
        $properties['gc2:snapshot_id'] = $uuid;
        $code = $this->projCode($row);
        if ($code !== null) {
            $properties['proj:code'] = $code;
        }

        $item = [
            'type' => 'Feature',
            'stac_version' => self::STAC_VERSION,
        ];
        if ($code !== null) {
            $item['stac_extensions'] = [self::PROJECTION_EXTENSION];
        }
        $item += [
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
            ['rel' => 'root', 'href' => '../../../catalog.json', 'type' => 'application/json'],
            ['rel' => 'parent', 'href' => '../collection.json', 'type' => 'application/json'],
            ['rel' => 'collection', 'href' => '../collection.json', 'type' => 'application/json'],
        ];
        $item['assets'] = $this->dataAssets($row, $this->isSpatial($row, $bbox)) + [
            'metadata' => [
                'href' => "./metadata-$uuid.json",
                'type' => 'application/json',
                'roles' => ['metadata'],
            ],
        ];
        return $item;
    }

    /**
     * One asset per produced output format, keyed by the format's STAC asset
     * key (`data` for Parquet, the format id for the rest) and in the order the
     * formats were produced. A skipped format has no file and therefore no
     * asset.
     *
     * @param array<string, mixed> $row
     * @param bool $spatial Whether the snapshot has a geometry column, which is
     *     what makes Parquet *Geo*Parquet.
     * @return array<string, array<string, mixed>>
     */
    private function dataAssets(array $row, bool $spatial): array
    {
        $assets = [];
        foreach ($this->producedFormats($row) as [$format, $file]) {
            $assets[$format->stacAssetKey] = [
                'href' => "./$file",
                'type' => $format->mediaType,
                'roles' => ['data'],
                'title' => $format->stacTitle($spatial),
            ];
        }
        return $assets;
    }

    /**
     * The produced formats of a snapshot as [format, file name] pairs.
     *
     * Read from the row's `formats` results, which is what the worker writes on
     * publish. A row published before that column existed — or one whose
     * `formats` still holds the *requested* ids rather than results — is
     * described by its files instead, every data file a produced format; and a
     * row with neither falls back to the Parquet the file names always had, so
     * an old item keeps exactly the asset it has always had.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{0:SnapshotFormat, 1:string}>
     */
    private function producedFormats(array $row): array
    {
        $produced = [];
        foreach ($this->decode($row['formats'] ?? null) as $entry) {
            if (!is_array($entry) || ($entry['status'] ?? 'produced') !== 'produced') {
                continue;
            }
            $id = (string)($entry['format'] ?? '');
            $file = (string)($entry['file'] ?? '');
            if ($file === '' || !SnapshotFormat::has($id)) {
                continue;
            }
            $produced[] = [SnapshotFormat::get($id), $file];
        }
        if ($produced !== []) {
            return $produced;
        }
        foreach ($this->decode($row['files'] ?? null) as $file) {
            $name = is_array($file) ? (string)($file['name'] ?? '') : '';
            $format = $name === '' ? null : SnapshotFormat::fromExtension($name);
            if ($format !== null) {
                $produced[] = [$format, $name];
            }
        }
        if ($produced !== []) {
            return $produced;
        }
        $parquet = SnapshotFormat::get('parquet');
        return [[$parquet, $parquet->fileName((string)$row['uuid'])]];
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
     * The CRS of the snapshot's data as "EPSG:<n>", or null when nothing
     * declares one.
     *
     * The row's `srs` is only the *requested* reprojection and is null for the
     * common case, so the fallback is the SRID the geometry (or geography)
     * column itself declares — `geometry(Point,25832)` gives 25832. A column
     * typed without an SRID (a bare `geometry`) declares nothing, and the item
     * then carries no CRS rather than a guessed one.
     *
     * @param array<string, mixed> $row
     */
    private function projCode(array $row): ?string
    {
        $srs = $row['srs'] ?? null;
        if ($srs !== null) {
            return 'EPSG:' . (int)$srs;
        }
        // Same column choice as SnapshotWorker::nativeSrid()/geometryColumn()
        // (geometry_columns ORDER BY f_geometry_column): alphabetical by name,
        // so the CRS stated here is the one the bbox and metadata.json used.
        $columns = array_values(array_filter($this->decode($row['relation_schema'] ?? null), 'is_array'));
        usort($columns, fn(array $a, array $b) => strcmp((string)($a['column_name'] ?? ''), (string)($b['column_name'] ?? '')));
        foreach ($columns as $column) {
            $type = strtolower(trim((string)($column['data_type'] ?? '')));
            if (!str_starts_with($type, 'geometry') && !str_starts_with($type, 'geography')) {
                continue;
            }
            if (preg_match('/\((?:[^()]*,)?\s*(\d+)\s*\)$/', $type, $m)) {
                return 'EPSG:' . (int)$m[1];
            }
        }
        return null;
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
