<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\StacCatalogWriter;
use Codeception\Test\Unit;

/**
 * The STAC document builder is pure: rows in, documents out. It trusts its
 * input to be published rows only (the model filters) and never touches
 * storage, so every shape and every href can be asserted here.
 */
class StacCatalogWriterTest extends Unit
{
    protected UnitTester $tester;

    private function row(array $over = []): array
    {
        return array_merge([
            'schema_name' => 'snap',
            'relation_name' => 'points',
            'snapshot_date' => '2026-09-16',
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'row_count' => 3,
            'schema_version' => 'ver1',
            'srs' => 25832,
            'files' => [
                ['name' => 'data-11111111-1111-1111-1111-111111111111.parquet', 'size_bytes' => 10],
            ],
            'bbox' => [8.0, 55.0, 9.0, 56.0],
            'relation_schema' => [
                ['column_name' => 'gid', 'data_type' => 'integer'],
                ['column_name' => 'the_geom', 'data_type' => 'geometry(Point,25832)'],
            ],
        ], $over);
    }

    private function writer(): StacCatalogWriter
    {
        return new StacCatalogWriter('mydb');
    }

    public function testCatalogHasRelativeSelfRootAndOneChildPerRelationSortedBySchemaAndRelation(): void
    {
        $docs = $this->writer()->build([
            $this->row(['schema_name' => 'snap', 'relation_name' => 'points']),
            $this->row(['schema_name' => 'geo', 'relation_name' => 'roads']),
            $this->row(['schema_name' => 'geo', 'relation_name' => 'areas']),
        ], ['geo.roads' => ['title' => 'Roads', 'description' => null, 'keywords' => []]]);

        $catalog = $docs['catalog.json'];
        $this->assertSame('Catalog', $catalog['type']);
        $this->assertSame('1.1.0', $catalog['stac_version']);
        $this->assertSame('mydb', $catalog['id']);
        $this->assertSame('mydb', $catalog['title']);
        $this->assertSame('Parquet snapshots of database mydb published by GC2', $catalog['description']);

        $this->assertSame(['root', 'child', 'child', 'child'], array_column($catalog['links'], 'rel'), 'no self link: the catalog does not know its own URL');
        $this->assertSame('./catalog.json', $catalog['links'][0]['href']);
        $this->assertSame(
            [
                './schema=geo/relation=areas/collection.json',
                './schema=geo/relation=roads/collection.json',
                './schema=snap/relation=points/collection.json',
            ],
            array_column(array_slice($catalog['links'], 1), 'href'),
            'children are sorted by schema then relation and addressed relatively'
        );
        $child = $catalog['links'][2];
        $this->assertSame('application/json', $child['type']);
        $this->assertSame('Roads', $child['title'], 'child link carries the collection title');
        $this->assertSame('geo.areas', $catalog['links'][1]['title'], 'without metadata the qualified name is the title');
        $this->assertArrayNotHasKey('stac_extensions', $catalog, 'the catalog carries no extension of its own');
    }

    public function testCollectionUsesMetadataTitleDescriptionAndKeywords(): void
    {
        $docs = $this->writer()->build([$this->row()], [
            'snap.points' => ['title' => 'Nice points', 'description' => 'All the points', 'keywords' => ['points', 'test']],
        ]);

        $collection = $docs['schema=snap/relation=points/collection.json'];
        $this->assertSame('Collection', $collection['type']);
        $this->assertSame('1.1.0', $collection['stac_version']);
        $this->assertSame('snap.points', $collection['id']);
        $this->assertSame('Nice points', $collection['title']);
        $this->assertSame('All the points', $collection['description']);
        $this->assertSame(['points', 'test'], $collection['keywords']);
        $this->assertSame('other', $collection['license']);
    }

    public function testCollectionFallsBackWhenMetadataIsMissingOrBlank(): void
    {
        $docs = $this->writer()->build([$this->row()], []);
        $collection = $docs['schema=snap/relation=points/collection.json'];
        $this->assertSame('snap.points', $collection['title']);
        $this->assertSame('Snapshots of snap.points', $collection['description']);
        $this->assertSame([], $collection['keywords']);

        $blank = $this->writer()->build([$this->row()], [
            'snap.points' => ['title' => "  \n ", 'description' => '', 'keywords' => []],
        ])['schema=snap/relation=points/collection.json'];
        $this->assertSame('snap.points', $blank['title'], 'a whitespace-only title is no title');
        $this->assertSame('Snapshots of snap.points', $blank['description']);
    }

    public function testCollectionLinksEveryItemNewestFirstAndPointsAtRootAndParent(): void
    {
        $docs = $this->writer()->build([
            $this->row(['snapshot_date' => '2026-09-16', 'uuid' => 'c']),
            $this->row(['snapshot_date' => '2026-09-14', 'uuid' => 'a']),
            $this->row(['snapshot_date' => '2026-09-15', 'uuid' => 'b']),
        ], []);
        $collection = $docs['schema=snap/relation=points/collection.json'];

        $this->assertSame(['root', 'parent', 'item', 'item', 'item'], array_column($collection['links'], 'rel'));
        $this->assertSame('../../catalog.json', $collection['links'][0]['href']);
        $this->assertSame('../../catalog.json', $collection['links'][1]['href']);
        $this->assertSame(
            [
                './_gc2_snapshot_date=2026-09-16/item.json',
                './_gc2_snapshot_date=2026-09-15/item.json',
                './_gc2_snapshot_date=2026-09-14/item.json',
            ],
            array_column(array_slice($collection['links'], 2), 'href'),
            'items are linked newest first'
        );
        $this->assertSame('application/geo+json', $collection['links'][2]['type']);
        $this->assertCount(3, array_filter(array_keys($docs), fn($p) => str_ends_with($p, 'item.json')));
    }

    public function testCollectionExtentUnionsBboxesAndSpansOldestToNewestDate(): void
    {
        $docs = $this->writer()->build([
            $this->row(['snapshot_date' => '2026-09-16', 'uuid' => 'b', 'bbox' => [7.0, 54.5, 8.5, 55.5], 'schema_version' => 'ver2']),
            $this->row(['snapshot_date' => '2026-09-10', 'uuid' => 'a', 'bbox' => [8.0, 55.0, 9.0, 56.0]]),
        ], []);
        $collection = $docs['schema=snap/relation=points/collection.json'];

        $this->assertSame([[7.0, 54.5, 9.0, 56.0]], $collection['extent']['spatial']['bbox']);
        $this->assertSame(
            [['2026-09-10T00:00:00Z', '2026-09-16T00:00:00Z']],
            $collection['extent']['temporal']['interval'],
            'interval runs oldest to newest'
        );
        $this->assertSame(['ver2', 'ver1'], $collection['summaries']['gc2:schema_version'], 'distinct schema versions');
        $this->assertSame(['EPSG:25832'], $collection['summaries']['proj:code'], 'distinct CRS codes');
        $this->assertSame([StacCatalogWriter::PROJECTION_EXTENSION], $collection['stac_extensions']);
    }

    /**
     * The row's srs is only the *requested* reprojection, so it is usually
     * null; the CRS a reader needs is then the one the geometry column itself
     * declares.
     */
    public function testCrsFallsBackToTheSridOfTheGeometryColumn(): void
    {
        $docs = $this->writer()->build([$this->row(['srs' => null])], []);
        $item = $docs['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertSame('EPSG:25832', $item['properties']['proj:code'], 'read from geometry(Point,25832)');
        $this->assertArrayNotHasKey('proj:epsg', $item['properties'], 'projection v2 spells it proj:code');
        $this->assertSame([StacCatalogWriter::PROJECTION_EXTENSION], $item['stac_extensions']);

        $requested = $this->writer()->build([$this->row(['srs' => 4326])], [])['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertSame('EPSG:4326', $requested['properties']['proj:code'], 'a requested reprojection wins over the column');

        $geography = $this->writer()->build([$this->row([
            'srs' => null,
            'relation_schema' => [['column_name' => 'geog', 'data_type' => 'geography(Point,4326)']],
        ])], [])['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertSame('EPSG:4326', $geography['properties']['proj:code']);

        $typeless = $this->writer()->build([$this->row([
            'srs' => null,
            'relation_schema' => [['column_name' => 'gid', 'data_type' => 'integer'], ['column_name' => 'geom', 'data_type' => 'geometry']],
        ])], [])['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertArrayNotHasKey('proj:code', $typeless['properties'], 'an untyped geometry column declares no SRID');
        $this->assertArrayNotHasKey('stac_extensions', $typeless, 'no projection extension when nothing uses it');
    }

    public function testCollectionOmitsSummariesItCannotFill(): void
    {
        $collection = $this->writer()->build([$this->row([
            'srs' => null,
            'schema_version' => null,
            'relation_schema' => [['column_name' => 'id', 'data_type' => 'integer']],
        ])], [])['schema=snap/relation=points/collection.json'];
        $this->assertArrayNotHasKey('summaries', $collection, 'no summaries at all rather than empty lists');
        $this->assertArrayNotHasKey('stac_extensions', $collection);
    }

    public function testItemCarriesBboxPolygonPropertiesAssetsAndRelativeLinks(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $docs = $this->writer()->build([$this->row()], []);
        $item = $docs['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];

        $this->assertSame('Feature', $item['type']);
        $this->assertSame('1.1.0', $item['stac_version']);
        $this->assertSame('snap.points/2026-09-16', $item['id']);
        $this->assertSame('snap.points', $item['collection']);
        $this->assertSame([8.0, 55.0, 9.0, 56.0], $item['bbox']);
        $this->assertSame('Polygon', $item['geometry']['type']);
        $this->assertSame(
            [[[8.0, 55.0], [9.0, 55.0], [9.0, 56.0], [8.0, 56.0], [8.0, 55.0]]],
            $item['geometry']['coordinates'],
            'geometry is the bbox as a closed ring in WGS84'
        );
        $this->assertSame([
            'datetime' => '2026-09-16T00:00:00Z',
            'gc2:row_count' => 3,
            'gc2:schema_version' => 'ver1',
            'gc2:snapshot_id' => $uuid,
            'proj:code' => 'EPSG:25832',
        ], $item['properties']);

        $this->assertSame(['root', 'parent', 'collection'], array_column($item['links'], 'rel'));
        $this->assertSame(
            ['../../../catalog.json', '../collection.json', '../collection.json'],
            array_column($item['links'], 'href')
        );

        $this->assertSame("./data-$uuid.parquet", $item['assets']['data']['href']);
        $this->assertSame('application/vnd.apache.parquet', $item['assets']['data']['type']);
        $this->assertSame(['data'], $item['assets']['data']['roles']);
        $this->assertSame('GeoParquet', $item['assets']['data']['title']);
        $this->assertSame("./metadata-$uuid.json", $item['assets']['metadata']['href']);
        $this->assertSame('application/json', $item['assets']['metadata']['type']);
        $this->assertSame(['metadata'], $item['assets']['metadata']['roles']);
    }

    public function testNonSpatialRelationHasNullGeometryNoBboxWorldExtentAndPlainParquetAsset(): void
    {
        $docs = $this->writer()->build([
            $this->row([
                'schema_name' => 'snap',
                'relation_name' => 'plain',
                'srs' => null,
                'bbox' => null,
                'uuid' => 'plain-1',
                'files' => [['name' => 'data-plain-1.parquet', 'size_bytes' => 4]],
                'relation_schema' => [['column_name' => 'id', 'data_type' => 'integer']],
            ]),
        ], []);

        $item = $docs['schema=snap/relation=plain/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertNull($item['geometry']);
        $this->assertArrayNotHasKey('bbox', $item, 'no bbox key at all when there is no extent');
        $this->assertArrayNotHasKey('proj:code', $item['properties']);
        $this->assertSame('Parquet', $item['assets']['data']['title']);

        $collection = $docs['schema=snap/relation=plain/collection.json'];
        $this->assertSame([[-180, -90, 180, 90]], $collection['extent']['spatial']['bbox'], 'world extent when nothing has a bbox');
    }

    public function testFileNamesComeFromTheRowNotFromTheUuid(): void
    {
        $docs = $this->writer()->build([
            $this->row(['uuid' => 'zz', 'files' => [['name' => 'data-legacy.parquet', 'size_bytes' => 1]]]),
        ], []);
        $item = $docs['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertSame('./data-legacy.parquet', $item['assets']['data']['href']);
        $this->assertSame('./metadata-zz.json', $item['assets']['metadata']['href']);
    }

    public function testRowsWithStringJsonColumnsAreAccepted(): void
    {
        $docs = $this->writer()->build([
            $this->row([
                'files' => json_encode([['name' => 'data-x.parquet', 'size_bytes' => 2]]),
                'bbox' => json_encode([1, 2, 3, 4]),
                'relation_schema' => json_encode([['column_name' => 'geom', 'data_type' => 'geometry(Point,4326)']]),
            ]),
        ], []);
        $item = $docs['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertSame('./data-x.parquet', $item['assets']['data']['href']);
        $this->assertSame([1.0, 2.0, 3.0, 4.0], $item['bbox']);
    }

    public function testNoPublishedRowsGivesAChildlessCatalogOnly(): void
    {
        $docs = $this->writer()->build([], []);
        $this->assertSame(['catalog.json'], array_keys($docs));
        $this->assertSame(['root'], array_column($docs['catalog.json']['links'], 'rel'));
    }

    public function testItemOmitsPropertiesItHasNoValueFor(): void
    {
        $item = $this->writer()->build([$this->row(['row_count' => null, 'schema_version' => null])], [])
        ['schema=snap/relation=points/_gc2_snapshot_date=2026-09-16/item.json'];
        $this->assertArrayNotHasKey('gc2:row_count', $item['properties']);
        $this->assertArrayNotHasKey('gc2:schema_version', $item['properties']);
        $this->assertSame('2026-09-16T00:00:00Z', $item['properties']['datetime']);
    }

    public function testKeywordsKeepOnlyStrings(): void
    {
        $collection = $this->writer()->build([$this->row()], [
            'snap.points' => ['title' => null, 'description' => null, 'keywords' => ['poi', 42, null, ['nested'], 'ok']],
        ])['schema=snap/relation=points/collection.json'];
        $this->assertSame(['poi', 'ok'], $collection['keywords']);
    }

    /**
     * The catalog is the entry point, so it is written last: a reader that
     * follows a child link must not reach a collection that is not there yet.
     */
    public function testCatalogIsTheLastDocumentInBuildOrder(): void
    {
        $paths = array_keys($this->writer()->build([$this->row(), $this->row(['relation_name' => 'other'])], []));
        $this->assertSame('catalog.json', end($paths));
        $this->assertCount(5, $paths, 'two collections, two items and the catalog');
    }

    public function testEncodeIsPrettyPrintedWithUnescapedSlashes(): void
    {
        $json = StacCatalogWriter::encode(['href' => './a/b.json']);
        $this->assertStringContainsString('"href": "./a/b.json"', $json);
        $this->assertStringNotContainsString('\\/', $json);
        $this->assertSame(['href' => './a/b.json'], json_decode($json, true));
    }
}
