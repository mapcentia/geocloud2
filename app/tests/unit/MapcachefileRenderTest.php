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

    /**
     * The fallbacks are the per-schema loop's current literals — expires 60,
     * metatile 3, metabuffer 0, PNG and MVT — and NOT layerSettings()' 30 and
     * null. Getting this wrong changes every existing install's config silently.
     */
    public function testSchemaSettingsDefaults(): void
    {
        $set = Mapcachefile::schemaSettings(null, 'sqlite', 'dagi');
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('PNG', $set['imageFormat']);
        $this->assertSame('MVT', $set['vectorFormat']);
        $this->assertSame(60, $set['expires']);
        $this->assertSame(3, $set['metaSize']);
        $this->assertSame(0, $set['metaBuffer']);
        $this->assertNull($set['autoExpire']);
        $this->assertNull($set['s3TileSet']);
        $this->assertSame('dagi', $set['title']);
        $this->assertSame('', $set['abstract']);
    }

    public function testSchemaSettingsFromDef(): void
    {
        $def = json_encode(['cache' => 'disk', 'ttl' => 86400, 'meta_size' => 5, 'meta_buffer' => 10,
            'auto_expire' => 3600, 'title' => 'Danmarks administrative geografi', 'abstract' => 'DAGI']);
        $set = Mapcachefile::schemaSettings(['schema' => 'dagi', 'def' => $def], 'sqlite', 'dagi');
        $this->assertSame('disk', $set['cache']);
        $this->assertSame(86400, $set['expires']);
        $this->assertSame(5, $set['metaSize']);
        $this->assertSame(10, $set['metaBuffer']);
        $this->assertSame(3600, $set['autoExpire']);
        $this->assertSame('Danmarks administrative geografi', $set['title']);
        $this->assertSame('DAGI', $set['abstract']);
    }

    /**
     * `format` configures the image tileset only. The vector tileset has nothing
     * to choose from — MVT is its one possible value — so a stored 'MVT' was a
     * provable no-op, and accepting a value that cannot change anything is the
     * same defect JSON was rejected for. The resolver therefore reports
     * vectorFormat as a constant and never lets a stored format reach it.
     */
    public function testSchemaFormatConfiguresTheImageTilesetOnly(): void
    {
        $jpeg = Mapcachefile::schemaSettings(['def' => json_encode(['format' => 'jpeg_medium'])], 'sqlite', 'dagi');
        $this->assertSame('jpeg_medium', $jpeg['imageFormat']);
        $this->assertSame('MVT', $jpeg['vectorFormat']);

        // A vector format stored by hand must not become the image tileset's.
        $mvt = Mapcachefile::schemaSettings(['def' => json_encode(['format' => 'MVT'])], 'sqlite', 'dagi');
        $this->assertSame('PNG', $mvt['imageFormat'], 'MVT is not an image format');
        $this->assertSame('MVT', $mvt['vectorFormat']);

        // MVT is no longer an accepted input value, precisely because it could
        // never change anything.
        $this->assertNotContains('MVT', Mapcachefile::SCHEMA_INPUT_FORMATS);
        $this->assertSame(Mapcachefile::SCHEMA_IMAGE_FORMATS, Mapcachefile::SCHEMA_INPUT_FORMATS,
            'the configurable formats are exactly the image ones');
    }

    /** Review Focus 4: ttl is floored at 30, as layerSettings() floors it. */
    public function testSchemaTtlIsFloored(): void
    {
        foreach ([0, 5, -100] as $ttl) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['ttl' => $ttl])], 'sqlite', 'dagi');
            $this->assertSame($ttl === 0 ? 60 : 30, $set['expires'], "ttl $ttl");
        }
    }

    /**
     * Review Focus 1: the config is ONE file for the whole database, so
     * <metatile>0 0</metatile> from a hand-edited row would make MapCache reject
     * the document and take down every tileset in that database. The resolver is
     * the second line of defence behind the API's validation.
     */
    public function testSchemaMetaSizeBelowOneFallsBack(): void
    {
        foreach ([0, -1] as $bad) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['meta_size' => $bad])], 'sqlite', 'dagi');
            $this->assertSame(3, $set['metaSize'], "meta_size $bad must fall back to 3");
        }
        $this->assertSame(1, Mapcachefile::schemaSettings(['def' => json_encode(['meta_size' => 1])], 'sqlite', 'dagi')['metaSize']);
    }

    /**
     * Review Focus 2: s3_tile_set is interpolated into the cache's object URL,
     * so a value with a slash, ".." or whitespace could redirect writes to
     * another prefix. Refuse it in the resolver as well as at the API.
     */
    public function testSchemaS3TileSetRejectsPathCharacters(): void
    {
        // '..' and '.' pass a character-class check but are exactly the
        // "redirect writes to another prefix" case: libcurl normalises them away,
        // so tiles land at the bucket ROOT and collide with other schemas' objects
        // in the shared bucket, which is the separation the setting exists for.
        foreach (['other/prefix', '../escape', 'with space', 'semi;colon', '..', '.', '...', '....'] as $bad) {
            $set = Mapcachefile::schemaSettings(['def' => json_encode(['s3_tile_set' => $bad])], 'sqlite', 'dagi');
            $this->assertNull($set['s3TileSet'], "s3_tile_set '$bad' must be ignored");
        }
        $this->assertSame('dagi-tiles', Mapcachefile::schemaSettings(['def' => json_encode(['s3_tile_set' => 'dagi-tiles'])], 'sqlite', 'dagi')['s3TileSet']);
    }

    /**
     * Review Focus 3: a hand-edited row can hold a def that is not an object.
     * Every one of these must give the plain defaults rather than raise.
     */
    public function testSchemaSettingsSurvivesANonObjectDef(): void
    {
        foreach ([null, '', 'null', '[]', '[1,2]', '"a string"', '42', 'not json at all'] as $def) {
            $set = Mapcachefile::schemaSettings(['def' => $def], 'sqlite', 'dagi');
            $this->assertSame(60, $set['expires'], 'def ' . var_export($def, true) . ' must give the defaults');
            $this->assertSame('sqlite', $set['cache']);
            $this->assertSame(3, $set['metaSize']);
        }
    }

    /** An unknown cache or format in a hand-edited row falls back rather than reaching the file. */
    public function testSchemaUnknownCacheAndFormatFallBack(): void
    {
        $set = Mapcachefile::schemaSettings(['def' => json_encode(['cache' => 'redis', 'format' => 'WEBP'])], 'sqlite', 'dagi');
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('PNG', $set['imageFormat']);
        $this->assertSame('MVT', $set['vectorFormat']);
    }

    /**
     * A title or abstract containing "]]>" closes the CDATA section it is written
     * into, and everything after it is parsed by MapCache as configuration rather
     * than as text. A tenant super-user could inject a <cache> element — a
     * filesystem write primitive as www-data, or an attacker-controlled S3 URL —
     * and, because the resulting document fails `apachectl configtest`,
     * mapcache_conf.php then reverts and never reloads Apache again, so no tile
     * configuration takes effect for ANY database on the node until someone finds
     * the row.
     *
     * The same hole exists for a layer's f_table_title, so the guard belongs here
     * in renderTileset() rather than in either caller's validation.
     */
    public function testATitleCannotCloseItsCdataSection(): void
    {
        $xml = Mapcachefile::renderTileset('t', 't', 'sqlite', [], 'PNG', 60,
            title: 'a]]></title><cache>evil</cache><title>b',
            abstract: 'x]]></abstract><tileset name="evil">');
        // The payload must survive as TEXT, not as markup: parse the document and
        // compare the title to what was put in. That is a stronger check than
        // looking for forbidden substrings, because the standard CDATA split
        // (']]>' -> ']]]]><![CDATA[>') legitimately introduces more CDATA
        // sections, and a parser reassembles them into the original characters.
        $doc = simplexml_load_string("<mapcache>\n" . $xml . "</mapcache>\n");
        $this->assertNotFalse($doc, 'the document must parse');
        $this->assertSame('a]]></title><cache>evil</cache><title>b', (string)$doc->tileset->metadata->title,
            'the title must come back exactly, as text');
        $this->assertSame('x]]></abstract><tileset name="evil">', (string)$doc->tileset->metadata->abstract);
        $this->assertSame(1, $doc->tileset->count(), 'no second tileset may have been injected');
        $this->assertSame(0, $doc->cache->count(), 'no cache element may have been injected');
    }

    /** The generated document must parse as XML even with a hostile title. */
    public function testADocumentWithAHostileTitleStillParses(): void
    {
        $xml = "<mapcache>\n" . Mapcachefile::renderTileset('t', 't', 'sqlite', [], 'PNG', 60,
                title: ']]></title></metadata></tileset><cache name="x" type="disk"><base>/tmp/x/</base></cache>',
                abstract: '') . "</mapcache>\n";
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->assertNotFalse($parsed, 'the document must still parse');
        $this->assertSame([], $errors, 'and without libxml errors');
        $this->assertSame(1, $parsed->tileset->count(), 'no second tileset may have been injected');
        $this->assertSame(0, $parsed->cache->count(), 'no cache element may have been injected');
    }

    /**
     * A hand-edited def can hold an object or array where a scalar belongs. The
     * resolver's contract is that anything unrecognised falls back; without the
     * cast guards, (string)$def->title raised "Object of class stdClass could not
     * be converted to string" out of generate(), so NO config was written for that
     * whole database — one bad row silencing every tileset in it.
     */
    public function testSchemaSettingsSurvivesNonScalarMembers(): void
    {
        $def = json_encode([
            'title' => ['a' => 1], 'abstract' => [1, 2], 's3_tile_set' => ['x' => 'y'],
            'cache' => ['disk'], 'format' => ['PNG'], 'meta_size' => ['x' => 1],
            'meta_buffer' => [2], 'ttl' => ['t' => 3], 'auto_expire' => [4],
        ]);
        $set = Mapcachefile::schemaSettings(['def' => $def], 'sqlite', 'dagi');
        $this->assertSame('dagi', $set['title'], 'a non-scalar title falls back to the schema name');
        $this->assertSame('', $set['abstract']);
        $this->assertNull($set['s3TileSet']);
        $this->assertSame('sqlite', $set['cache']);
        $this->assertSame('PNG', $set['imageFormat']);
        $this->assertSame('MVT', $set['vectorFormat']);
        $this->assertSame(3, $set['metaSize']);
        $this->assertSame(0, $set['metaBuffer']);
        $this->assertSame(60, $set['expires']);
        $this->assertNull($set['autoExpire']);
    }
}
