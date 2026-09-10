<?php
namespace app\tests\unit\wfs;

use app\models\Table;
use app\wfs\Context;
use app\wfs\output\GeoJsonWriter;
use app\wfs\Request;
use Codeception\Test\Unit;

class GeoJsonWriterTest extends Unit
{
    private function ctx(): Context
    {
        return new Context(
            connection: new \app\inc\Connection(database: 'mydb'),
            database: 'mydb', schema: 'public', user: 'alice', userGroup: null, parentUser: false,
            trusted: true, host: 'http://example.com', thePath: '/x', startTime: 0.0,
        );
    }

    private function req(): Request
    {
        return new Request(
            operation: 'GETFEATURE', version: '1.1.0', service: 'WFS', outputFormat: 'GEOJSON',
            typeNames: ['poi'], properties: null, featureIds: null, bbox: null, resultType: null,
            srsName: null, srs: 4326, maxFeatures: 10, timeSlice: null, filter: null,
            transactionBody: null, rawPostBody: null, startIndex: 0,
        );
    }

    /** A Table without a database: metaData/primaryKey are set directly. */
    private function table(): Table
    {
        $t = new \ReflectionClass(Table::class)->newInstanceWithoutConstructor();
        $t->metaData = [
            'gid' => ['type' => 'int4'],
            'name' => ['type' => 'varchar'],
            'height' => ['type' => 'numeric'],
            'active' => ['type' => 'bool'],
            'tags' => ['type' => 'jsonb'],
            'blob' => ['type' => 'bytea'],
            'the_geom' => ['type' => 'geometry'],
        ];
        $t->primaryKey = ['attname' => 'gid'];
        return $t;
    }

    private function row(int $gid, string $name): array
    {
        return [
            'gid' => (string)$gid, 'name' => $name, 'height' => '1.50', 'active' => 't',
            'tags' => '{"a":1}', 'blob' => 'xx', 'the_geom' => '{"type":"Point","coordinates":[9.5,55.7]}',
            'fid' => (string)$gid,
        ];
    }

    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    public function testFeatureCollectionWithTypedPropertiesAndNextLink(): void
    {
        $w = new GeoJsonWriter(
            crsUri: 'http://www.opengis.net/def/crs/OGC/1.3/CRS84',
            links: [['rel' => 'self', 'type' => 'application/geo+json', 'href' => 'http://x/items?limit=2']],
            pageHref: 'http://x/items?limit=2', offset: 0, limit: 2, suppressFlush: true,
        );
        $out = $this->capture(function () use ($w) {
            $w->writeXmlProlog();
            $w->writeFeatureCollectionOpen($this->req(), $this->ctx(), 5);
            $w->writeFeatureMembersOpen('1.1.0');
            $w->writeFeature($this->row(1, 'alpha'), 'poi', $this->table(), $this->req(), $this->ctx());
            $w->writeFeature($this->row(2, 'bravo'), 'poi', $this->table(), $this->req(), $this->ctx());
            $w->writeFeatureMembersClose('1.1.0');
            $w->writeFeatureCollectionClose();
        });
        $doc = json_decode($out, true);
        $this->assertNotNull($doc, 'writer must emit valid JSON: ' . $out);
        $this->assertSame('FeatureCollection', $doc['type']);
        $this->assertSame(5, $doc['numberMatched']);
        $this->assertSame(2, $doc['numberReturned']);
        $this->assertSame(2, $w->numberReturned());
        $f = $doc['features'][0];
        $this->assertSame(1, $f['id']);                       // int pkey → int id
        $this->assertSame('Point', $f['geometry']['type']);
        $this->assertSame(1, $f['properties']['gid']);        // pkey present and int-converted
        $this->assertSame('alpha', $f['properties']['name']);
        $this->assertSame(1.5, $f['properties']['height']);
        $this->assertTrue($f['properties']['active']);
        $this->assertSame(['a' => 1], $f['properties']['tags']);
        $this->assertArrayNotHasKey('blob', $f['properties']);    // bytea skipped
        $this->assertArrayNotHasKey('the_geom', $f['properties']);
        $this->assertArrayNotHasKey('fid', $f['properties']);
        $rels = array_column($doc['links'], 'href', 'rel');
        $this->assertSame('http://x/items?limit=2&offset=2', $rels['next']);
        $this->assertArrayNotHasKey('prev', $rels);
    }

    public function testPrevLinkAndNoNextOnLastPage(): void
    {
        $w = new GeoJsonWriter(crsUri: 'x', links: [], pageHref: 'http://x/items', offset: 4, limit: 2, suppressFlush: true);
        $out = $this->capture(function () use ($w) {
            $w->writeFeatureCollectionOpen($this->req(), $this->ctx(), 5);
            $w->writeFeature($this->row(5, 'echo'), 'poi', $this->table(), $this->req(), $this->ctx());
            $w->writeFeatureCollectionClose();
        });
        $rels = array_column(json_decode($out, true)['links'], 'href', 'rel');
        $this->assertSame('http://x/items?offset=2', $rels['prev']);
        $this->assertArrayNotHasKey('next', $rels);
    }

    public function testEmptyCollectionHasEmptyFeaturesArray(): void
    {
        $w = new GeoJsonWriter(crsUri: 'x', suppressFlush: true);
        $out = $this->capture(function () use ($w) {
            $w->writeFeatureCollectionOpen($this->req(), $this->ctx(), 0);
            $w->writeFeatureCollectionClose();
        });
        $doc = json_decode($out, true);
        $this->assertSame([], $doc['features']);
        $this->assertSame(0, $doc['numberReturned']);
    }

    public function testSingleModeEmitsBareFeatureWithLinks(): void
    {
        $links = [['rel' => 'collection', 'type' => 'application/json', 'href' => 'http://x/collections/public.poi']];
        $w = new GeoJsonWriter(crsUri: 'x', links: $links, single: true, suppressFlush: true);
        $out = $this->capture(function () use ($w) {
            $w->writeFeatureCollectionOpen($this->req(), $this->ctx(), 1);
            $w->writeFeature($this->row(7, 'golf'), 'poi', $this->table(), $this->req(), $this->ctx());
            $w->writeFeatureCollectionClose();
        });
        $doc = json_decode($out, true);
        $this->assertSame('Feature', $doc['type']);
        $this->assertSame(7, $doc['id']);
        $this->assertSame('collection', $doc['links'][0]['rel']);
        $this->assertSame(1, $w->numberReturned());
    }

    public function testNullGeometryAndEmptyPropertiesAreObjects(): void
    {
        $t = new \ReflectionClass(Table::class)->newInstanceWithoutConstructor();
        $t->metaData = ['id' => ['type' => 'text'], 'the_geom' => ['type' => 'geometry']];
        $t->primaryKey = ['attname' => 'id'];
        $w = new GeoJsonWriter(crsUri: 'x', suppressFlush: true);
        $out = $this->capture(function () use ($w, $t) {
            $w->writeFeatureCollectionOpen($this->req(), $this->ctx(), 1);
            $w->writeFeature(['id' => null, 'the_geom' => null, 'fid' => 'abc'], 'poi', $t, $this->req(), $this->ctx());
            $w->writeFeatureCollectionClose();
        });
        $this->assertStringContainsString('"geometry":null', $out);
        $this->assertStringContainsString('"properties":{"id":null}', $out);
        $this->assertSame('abc', json_decode($out, true)['features'][0]['id']);   // text pkey → string id
    }

    public function testConvert(): void
    {
        $this->assertSame(42, GeoJsonWriter::convert('42', 'int8'));
        $this->assertSame(2.5, GeoJsonWriter::convert('2.5', 'float8'));
        $this->assertFalse(GeoJsonWriter::convert('f', 'bool'));
        $this->assertSame('{1,2}', GeoJsonWriter::convert('{1,2}', '_int4'));   // arrays stay strings
        $this->assertSame('not json', GeoJsonWriter::convert('not json', 'json')); // invalid JSON stays a string
        $this->assertNull(GeoJsonWriter::convert(null, 'int4'));
    }
}
