<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\App;
use app\models\Mapcachefile;
use Codeception\Test\Unit;

class MapcachefileRenderTest extends Unit
{
    protected UnitTester $tester;

    public function testLayerSettingsDefaults(): void
    {
        $set = Mapcachefile::layerSettings(['def' => null, 'f_table_title' => '', 'f_table_name' => 't', 'f_table_abstract' => null], 'sqlite');
        $this->assertNull($set['metaSize']);
        $this->assertSame(0, $set['metaBuffer']);
        $this->assertSame(30, $set['expires']);
        $this->assertNull($set['autoExpire']);
        $this->assertSame('PNG', $set['format']);
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('', $set['extraLayers']);
        $this->assertNull($set['s3TileSet']);
        $this->assertNull($set['qgisLayers']);
        $this->assertSame('t', $set['title']);
        $this->assertSame('', $set['abstract']);
    }

    public function testLayerSettingsFromDef(): void
    {
        $def = json_encode(['meta_size' => 3, 'meta_buffer' => 10, 'ttl' => 600, 'auto_expire' => 86400,
            'format' => 'jpeg_high', 'cache' => 'disk', 'layers' => 'other.layer', 's3_tile_set' => 'mytiles']);
        $set = Mapcachefile::layerSettings(['def' => $def, 'f_table_title' => 'My title', 'f_table_name' => 't', 'f_table_abstract' => 'Abs'], 'sqlite');
        $this->assertSame(3, $set['metaSize']);
        $this->assertSame(10, $set['metaBuffer']);
        $this->assertSame(600, $set['expires']);
        $this->assertSame(86400, $set['autoExpire']);
        $this->assertSame('jpeg_high', $set['format']);
        $this->assertSame('disk', $set['cache']);
        $this->assertSame(',other.layer', $set['extraLayers']);
        $this->assertSame('mytiles', $set['s3TileSet']);
        $this->assertSame('My title', $set['title']);
        $this->assertSame('Abs', $set['abstract']);
    }

    public function testLayerSettingsTtlFloorsAt30(): void
    {
        $set = Mapcachefile::layerSettings(['def' => json_encode(['ttl' => 5]), 'f_table_title' => null, 'f_table_name' => 't', 'f_table_abstract' => ''], 'sqlite');
        $this->assertSame(30, $set['expires']);
    }

    public function testLayerSettingsDetectsQgisSource(): void
    {
        $row = ['def' => null, 'f_table_title' => null, 'f_table_name' => 't', 'f_table_abstract' => '',
            'wmssource' => 'http://qgis:8080/cgi-bin/qgis_mapserv.fcgi?map=/foo.qgs&LAYER=roads'];
        $set = Mapcachefile::layerSettings($row, 'sqlite');
        $this->assertSame('roads', $set['qgisLayers']);

        $row['wmssource'] = 'http://example.com/wms?LAYER=roads';
        $set = Mapcachefile::layerSettings($row, 'sqlite');
        $this->assertNull($set['qgisLayers']);
    }

    public function testRenderWmsSourceWithFeatureInfo(): void
    {
        $s = Mapcachefile::renderWmsSource('s.t', 'PNG', 's.t,extra.layer', 'http://wms/cgi-bin/mapserv.fcgi?map=x.map', queryLayers: 's.t');
        $this->assertStringContainsString('<source name="s.t" type="wms">', $s);
        $this->assertStringContainsString('<FORMAT>PNG</FORMAT>', $s);
        $this->assertStringContainsString('<LAYERS>s.t,extra.layer</LAYERS>', $s);
        $this->assertStringContainsString('<url>http://wms/cgi-bin/mapserv.fcgi?map=x.map</url>', $s);
        $this->assertStringContainsString('<QUERY_LAYERS>s.t</QUERY_LAYERS>', $s);
        $this->assertStringContainsString('<info_formats>text/plain,application/vnd.ogc.gml</info_formats>', $s);
    }

    public function testRenderWmsSourceWithoutFeatureInfo(): void
    {
        $s = Mapcachefile::renderWmsSource('s.t.mvt', 'mvt', 's.t', 'http://wms/x.map&');
        $this->assertStringNotContainsString('getfeatureinfo', $s);
        $this->assertStringNotContainsString('QUERY_LAYERS', $s);
    }

    public function testRenderTilesetFull(): void
    {
        $s = Mapcachefile::renderTileset('s.t', 's.t', 'sqlite', ['dtk25', 'utm'], 'jpeg_high', 600,
            metaSize: 3, metaBuffer: 10, autoExpire: 86400, title: 'My title', abstract: 'Abs', wgs84bbox: '-180 -90 180 90');
        $this->assertStringContainsString('<tileset name="s.t">', $s);
        $this->assertStringContainsString('<source>s.t</source>', $s);
        $this->assertStringContainsString('<cache>sqlite</cache>', $s);
        $this->assertStringContainsString('<grid>g20</grid>', $s);
        $this->assertStringContainsString('<grid>dtk25</grid>', $s);
        $this->assertStringContainsString('<grid>utm</grid>', $s);
        $this->assertStringContainsString('<format>jpeg_high</format>', $s);
        $this->assertStringContainsString('<metatile>3 3</metatile>', $s);
        $this->assertStringContainsString('<metabuffer>10</metabuffer>', $s);
        $this->assertStringContainsString('<expires>600</expires>', $s);
        $this->assertStringContainsString('<auto_expire>86400</auto_expire>', $s);
        $this->assertStringContainsString('<title><![CDATA[My title]]></title>', $s);
        $this->assertStringContainsString('<abstract><![CDATA[Abs]]></abstract>', $s);
        $this->assertStringContainsString('<wgs84boundingbox>-180 -90 180 90</wgs84boundingbox>', $s);
    }

    public function testRenderTilesetOmitsOptionalParts(): void
    {
        $s = Mapcachefile::renderTileset('myschema', 'myschema', 'sqlite', [], 'MVT', 60, title: 'myschema');
        $this->assertStringNotContainsString('<metatile>', $s);
        $this->assertStringNotContainsString('<metabuffer>', $s);
        $this->assertStringNotContainsString('<auto_expire>', $s);
        $this->assertStringNotContainsString('<wgs84boundingbox>', $s);
    }

    public function testRenderBdbCache(): void
    {
        $s = Mapcachefile::renderBdbCache('bdb_s.t', '/var/www/geocloud2/app/wms/mapcache/bdb/mydb/s.t');
        $this->assertStringContainsString('<cache name="bdb_s.t" type="bdb">', $s);
        $this->assertStringContainsString('<base>/var/www/geocloud2/app/wms/mapcache/bdb/mydb/s.t</base>', $s);
        $this->assertStringContainsString('<symlink_blank/>', $s);
        $this->assertStringContainsString('<creation_retry>3</creation_retry>', $s);
    }

    public function testRenderS3Cache(): void
    {
        $orig = App::$param['s3'] ?? null;
        App::$param['s3'] = ['host' => 's3.example.com', 'id' => 'ID', 'secret' => 'SECRET', 'region' => 'eu-west-1'];
        try {
            // Shared cache: db as first segment, {tileset} placeholder in url
            $s = Mapcachefile::renderS3Cache('s3', 'mydb', perTileSet: true);
            $this->assertStringContainsString('<cache name="s3" type="s3">', $s);
            $this->assertStringContainsString('<url>https://s3.example.com/mydb/{tileset}/{grid}/{z}/{x}/{y}/{ext}</url>', $s);
            $this->assertStringContainsString('<Host>s3.example.com</Host>', $s);
            $this->assertStringContainsString('<id>ID</id>', $s);
            $this->assertStringContainsString('<secret>SECRET</secret>', $s);
            $this->assertStringContainsString('<region>eu-west-1</region>', $s);
            $this->assertStringContainsString('<x-amz-acl>public-read</x-amz-acl>', $s);

            // Per-layer cache: fixed tile set, no {tileset} placeholder
            $s = Mapcachefile::renderS3Cache('s3_s.t', 'mytiles', perTileSet: false);
            $this->assertStringContainsString('<url>https://s3.example.com/mytiles/{grid}/{z}/{x}/{y}/{ext}</url>', $s);
        } finally {
            App::$param['s3'] = $orig;
        }
    }
}
