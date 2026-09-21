<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\App;
use app\inc\snapshot\SnapshotFormat;
use Codeception\Test\Unit;

/**
 * The format registry is the only place that knows which output formats a
 * snapshot can be produced in, so these tests pin its table down: the ids, the
 * per-format fields the worker and the read API read, and how a configured
 * default list is validated.
 */
class SnapshotFormatTest extends Unit
{
    protected UnitTester $tester;

    /** @var array<string, mixed>|null */
    private mixed $savedSnapshotParam = null;
    private bool $hadSnapshotParam = false;

    protected function _before(): void
    {
        $this->hadSnapshotParam = array_key_exists('snapshot', App::$param);
        $this->savedSnapshotParam = App::$param['snapshot'] ?? null;
    }

    protected function _after(): void
    {
        if ($this->hadSnapshotParam) {
            App::$param['snapshot'] = $this->savedSnapshotParam;
        } else {
            unset(App::$param['snapshot']);
        }
    }

    public function testIdsAreTheRegisteredFormatsInOrder(): void
    {
        $this->assertSame(['parquet', 'flatgeobuf'], SnapshotFormat::ids());
    }

    public function testGetReturnsTheParquetEntry(): void
    {
        $f = SnapshotFormat::get('parquet');
        $this->assertSame('parquet', $f->id);
        $this->assertSame('Parquet', $f->driver);
        $this->assertSame('parquet', $f->extension);
        $this->assertSame('application/vnd.apache.parquet', $f->mediaType);
        $this->assertFalse($f->requiresGeometry);
        $this->assertSame('data', $f->stacAssetKey);
        $this->assertSame(['-mapFieldType', 'Time=String,Binary=String'], $f->ogrArgs);
        $this->assertSame('GeoParquet', $f->stacTitle(true));
        $this->assertSame('Parquet', $f->stacTitle(false), 'a non-spatial snapshot is plain Parquet');
    }

    public function testGetReturnsTheFlatgeobufEntry(): void
    {
        $f = SnapshotFormat::get('flatgeobuf');
        $this->assertSame('flatgeobuf', $f->id);
        $this->assertSame('FlatGeobuf', $f->driver);
        $this->assertSame('fgb', $f->extension);
        $this->assertSame('application/flatgeobuf', $f->mediaType);
        $this->assertTrue($f->requiresGeometry, 'FlatGeobuf cannot describe a relation without geometry');
        $this->assertSame('flatgeobuf', $f->stacAssetKey);
        $this->assertSame(['-mapFieldType', 'Time=String,Binary=String'], $f->ogrArgs);
        $this->assertSame('FlatGeobuf', $f->stacTitle(true));
        $this->assertSame('FlatGeobuf', $f->stacTitle(false), 'the title does not depend on the footprint');
    }

    public function testGetIsTheSameInstanceEveryTime(): void
    {
        $this->assertSame(SnapshotFormat::get('parquet'), SnapshotFormat::get('parquet'));
    }

    public function testGetUnknownIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SnapshotFormat::get('geojson');
    }

    public function testHas(): void
    {
        $this->assertTrue(SnapshotFormat::has('parquet'));
        $this->assertTrue(SnapshotFormat::has('flatgeobuf'));
        $this->assertFalse(SnapshotFormat::has('geojson'));
        $this->assertFalse(SnapshotFormat::has(''), 'the empty id is not a format');
        $this->assertFalse(SnapshotFormat::has('PARQUET'), 'ids are the lower case ones in the registry');
    }

    public function testFromExtensionReadsTheFileName(): void
    {
        $this->assertSame('flatgeobuf', SnapshotFormat::fromExtension('data-x.fgb')?->id);
        $this->assertSame('parquet', SnapshotFormat::fromExtension('data-11112222-3333-4444-5555-666677778888.parquet')?->id);
        $this->assertSame('parquet', SnapshotFormat::fromExtension('/some/dir/data-x.PARQUET')?->id, 'the extension is matched case insensitively');
        $this->assertNull(SnapshotFormat::fromExtension('metadata-x.json'));
        $this->assertNull(SnapshotFormat::fromExtension('data-x'), 'a name without an extension matches nothing');
        $this->assertNull(SnapshotFormat::fromExtension(''));
    }

    public function testFileNameIsDataUuidDotExtension(): void
    {
        $uuid = '11112222-3333-4444-5555-666677778888';
        $this->assertSame("data-$uuid.parquet", SnapshotFormat::get('parquet')->fileName($uuid));
        $this->assertSame("data-$uuid.fgb", SnapshotFormat::get('flatgeobuf')->fileName($uuid));
    }

    public function testDefaultsIsParquetWhenNotConfigured(): void
    {
        App::$param['snapshot'] = ['storage' => 'local'];
        $this->assertSame(['parquet'], SnapshotFormat::defaults());

        unset(App::$param['snapshot']);
        $this->assertSame(['parquet'], SnapshotFormat::defaults(), 'no snapshot block at all still yields the built-in default');
    }

    public function testDefaultsReadsTheConfiguredList(): void
    {
        App::$param['snapshot'] = ['formats' => ['parquet', 'flatgeobuf']];
        $this->assertSame(['parquet', 'flatgeobuf'], SnapshotFormat::defaults());

        App::$param['snapshot'] = ['formats' => ['flatgeobuf']];
        $this->assertSame(['flatgeobuf'], SnapshotFormat::defaults(), 'the configured order is kept');
    }

    public function testDefaultsEmptyListFallsBackToParquet(): void
    {
        App::$param['snapshot'] = ['formats' => []];
        $this->assertSame(['parquet'], SnapshotFormat::defaults(), 'an empty list says nothing, so the built-in default applies');
    }

    public function testDefaultsThrowsOnAnUnknownConfiguredId(): void
    {
        App::$param['snapshot'] = ['formats' => ['parquet', 'geopackage']];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/geopackage/');
        SnapshotFormat::defaults();
    }

    public function testDefaultsThrowsWhenTheConfiguredValueIsNotAList(): void
    {
        App::$param['snapshot'] = ['formats' => 'parquet'];
        $this->expectException(RuntimeException::class);
        SnapshotFormat::defaults();
    }

    /**
     * The OpenAPI enums on `formats` and `/data/{format}` are PHP attribute
     * arguments and can only be constant expressions, so they read the IDS
     * constant instead of ids(). The registry stays the source of truth: a
     * format added there without extending IDS fails here rather than shipping
     * a stale enum to the SDK and MCP generators.
     */
    public function testTheIdsConstantMatchesTheRegistry(): void
    {
        $this->assertSame(SnapshotFormat::ids(), SnapshotFormat::IDS);
    }
}
