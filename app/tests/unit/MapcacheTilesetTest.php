<?php
/**
 * Unit tests for the pure tileset→layer parsing in the MapCache proxy controller. These cover the
 * per-service extraction (WMS/WMTS KVP, WMTS RESTful, TMS, Google Maps), vector-suffix stripping,
 * the "is this a tile fetch" heuristic used to fail closed, and the service-path tail extraction.
 */

use app\api\v4\controllers\Mapcache;
use app\inc\Connection;
use Codeception\Test\Unit;

class MapcacheTilesetTest extends Unit
{
    /** @param array<string,mixed> $query keys as they arrive (upper-cased by the controller) */
    private function layers(string $service, array $segments, array $query = []): array
    {
        return Mapcache::extractLayers($service, $segments, array_change_key_case($query, CASE_UPPER));
    }

    public function testWmsKvpLayers(): void
    {
        $this->assertSame(['s.a', 's.b'], $this->layers('wms', ['wms'], ['LAYERS' => 's.a,s.b']));
    }

    public function testWmtsKvpLayer(): void
    {
        $this->assertSame(['s.a'], $this->layers('wmts', ['wmts'], ['LAYER' => 's.a']));
    }

    public function testWmtsRestfulTileset(): void
    {
        $segments = ['wmts', '1.0.0', 's.a', 'default', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('wmts', $segments));
    }

    public function testWmtsRestfulCapabilitiesHasNoTileset(): void
    {
        $segments = ['wmts', '1.0.0', 'WMTSCapabilities.xml'];
        $this->assertSame([], $this->layers('wmts', $segments));
    }

    public function testTmsTilesetStripsGrid(): void
    {
        $segments = ['tms', '1.0.0', 's.a@g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('tms', $segments));
    }

    /**
     * SECURITY. MapCache's gmaps URL is gmaps/{tileset}@{grid}/{z}/{x}/{y}.ext —
     * the same "@" form as TMS, not the gmaps/{tileset}/{grid}/… this parser's
     * comment used to claim. Taking the segment raw made the layer name
     * "dagi.x@g20", which matches no layer, so authorize()'s anonymous branch fell
     * through to "readable anonymously" and MapCache then served a Read/write
     * layer's tiles to an unauthenticated caller. Measured before the fix:
     *
     *   gmaps/dagi.dagi_politikreds2000@g20/10/540/320.png → 200 image/png, 7954 bytes
     *   tms/1.0.0/dagi.dagi_politikreds2000@g20/…          → 401
     *
     * Introduced with the proxy itself in 731bf91a (2026-08-18).
     */
    public function testGoogleMapsTilesetStripsTheGrid(): void
    {
        $this->assertSame(['s.a'], $this->layers('gmaps', ['gmaps', 's.a@g20', '8', '136', '78.png'], []));
        $this->assertSame(['geodk'], $this->layers('gmaps', ['gmaps', 'geodk@g20', '8', '136', '78.png'], []),
            'a schema tileset over gmaps too');
        $this->assertSame(['s.a'], $this->layers('gmaps', ['gmaps', 's.a.mvt@g20', '8', '136', '78.mvt'], []),
            'the vector suffix is stripped after the grid');
    }

    public function testGoogleMapsTileset(): void
    {
        $segments = ['gmaps', 's.a', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('gmaps', $segments));
    }

    public function testVectorSuffixIsStripped(): void
    {
        $this->assertSame(['s.a'], $this->layers('wms', ['wms'], ['LAYERS' => 's.a.mvt']));
        $segments = ['wmts', '1.0.0', 's.a.json', 'default', 'g20', '8', '136', '78.png'];
        $this->assertSame(['s.a'], $this->layers('wmts', $segments));
    }

    /**
     * A name without a dot is NOT dropped any more: it is the merged per-schema
     * tileset, <schema> and <schema>.mvt, which MapCache really serves and which
     * GetCapabilities really advertises. Dropping it meant extractLayers() came
     * back empty, and the fail-closed branch answered every tile fetch with
     * "Could not resolve tileset for authorization" — so a schema tileset could be
     * listed and seeded but never fetched through the authorizing proxy.
     *
     * The old assumption behind this test, "tilesets are always schema.table", was
     * true of layer tilesets only.
     */
    public function testAnUnqualifiedNameIsTheSchemaTileset(): void
    {
        $this->assertSame(['justname'], $this->layers('wms', ['wms'], ['LAYERS' => 'justname']));
        // Every service's path form reaches it, not just one.
        $this->assertSame(['geodk'], $this->layers('tms', ['tms', '1.0.0', 'geodk@g20', '16', '1', '2.png'], []));
        $this->assertSame(['geodk'], $this->layers('wmts', ['wmts', '1.0.0', 'geodk', 'default', 'g20', '16', '1', '2.png'], []));
        $this->assertSame(['geodk'], $this->layers('gmaps', ['gmaps', 'geodk', 'g20', '16', '1', '2.png'], []));
        // The vector variant resolves to the same schema.
        $this->assertSame(['geodk'], $this->layers('tms', ['tms', '1.0.0', 'geodk.mvt@g20', '16', '1', '2.mvt'], []));
    }

    /** An empty or whitespace-only name is still nothing. */
    public function testAnEmptyNameIsStillDropped(): void
    {
        $this->assertSame([], $this->layers('wms', ['wms'], ['LAYERS' => '']));
        $this->assertSame([], $this->layers('wms', ['wms'], ['LAYERS' => '  ,  ']));
    }

    public function testUnknownServiceHasNoTileset(): void
    {
        $this->assertSame([], $this->layers('demo', ['demo'], []));
    }

    public function testLooksLikeTileFetch(): void
    {
        $this->assertTrue(Mapcache::looksLikeTileFetch([], ['REQUEST' => 'GetTile']));
        $this->assertTrue(Mapcache::looksLikeTileFetch([], ['REQUEST' => 'GetMap']));
        $this->assertTrue(Mapcache::looksLikeTileFetch(['wmts', '1.0.0', 's.a', 'default', 'g20', '8', '136', '78.png'], []));
        $this->assertFalse(Mapcache::looksLikeTileFetch(['wms'], []));
        $this->assertFalse(Mapcache::looksLikeTileFetch(['wmts', '1.0.0', 'WMTSCapabilities.xml'], []));
    }

    public function testTail(): void
    {
        $this->assertSame('wmts/1.0.0/s.a/default/g20/8/136/78.png',
            Mapcache::tail('/api/v4/mapcache/database/mydb/wmts/1.0.0/s.a/default/g20/8/136/78.png', 'mydb'));
        $this->assertSame('wms', Mapcache::tail('/api/v4/mapcache/database/mydb/wms', 'mydb'));
        $this->assertSame('', Mapcache::tail('/api/v4/mapcache/database/mydb', 'mydb'));
    }

    /**
     * A merged per-schema tileset is drawn from every layer in the schema, so the
     * caller must be allowed to read every one of them — the same rule the WMS
     * path already applies when a request names several layers. Resolving the
     * schema to that list is what makes the rule enforceable.
     *
     * Measured before this existed: dagi.dagi_politikreds2000 is Read/write, so
     * anonymously it is challenged with 401 — while the dagi schema tileset, which
     * contains it, answered 200. Keeping the dotless name without expanding it
     * turns the merged tileset into a way around per-layer authorization.
     */
    public function testSchemaLayersListsTheSchemasOwsLayers(): void
    {
        $layers = Mapcache::schemaLayers('dagi', new Connection(database: 'mydb'));
        $this->assertNotEmpty($layers, 'dagi has OWS-enabled layers in this install');
        $this->assertContains('dagi.dagi_politikreds2000', $layers,
            'the protected layer must be in the list, or authorizing the schema cannot protect it');
        foreach ($layers as $l) {
            $this->assertStringStartsWith('dagi.', $l);
            $this->assertSame(2, count(explode('.', $l)), 'schema.table, without the geometry column');
        }
        $this->assertSame(array_values(array_unique($layers)), $layers, 'no duplicates per geometry column');
    }

    /**
     * Dropping a table leaves its row in settings.geometry_columns_join, and this
     * install has 81 such rows out of 285. The config generator does not see them —
     * it goes through settings.getColumns(), which joins the real catalog — so a
     * list built from the join table directly would authorize layers the merged
     * tileset does not contain, and, worse, would let a stale name pass the
     * existence check that makes an unresolvable tileset fail closed. Measured
     * before this: a stale name reached the upstream MapCache (400) where a name
     * with no row at all was correctly refused (403).
     */
    public function testSchemaLayersExcludesARowWhoseTableIsGone(): void
    {
        $conn = new Connection(database: 'mydb');
        $model = new app\inc\Model(connection: $conn);
        $schema = 'zz_ghost_' . substr(uniqid(), -6);
        try {
            $model->execQuery("CREATE SCHEMA \"$schema\"", 'PDO', 'transaction');
            $model->execQuery("CREATE TABLE \"$schema\".alive (gid serial primary key, the_geom geometry(Point,4326))", 'PDO', 'transaction');
            $model->execQuery("CREATE TABLE \"$schema\".doomed (gid serial primary key, the_geom geometry(Point,4326))", 'PDO', 'transaction');
            foreach (['alive', 'doomed'] as $t) {
                $res = $model->prepare("INSERT INTO settings.geometry_columns_join (_key_, enableows) VALUES (:k, true)
                                        ON CONFLICT (_key_) DO NOTHING");
                $model->execute($res, ['k' => "$schema.$t.the_geom"]);
            }
            $this->assertEqualsCanonicalizing(["$schema.alive", "$schema.doomed"],
                Mapcache::schemaLayers($schema, $conn), 'both are layers while both tables exist');

            // Drop the table but leave the row — exactly what GC2 does today.
            $model->execQuery("DROP TABLE \"$schema\".doomed", 'PDO', 'transaction');
            // No cache busting needed here: Cache::getItem() returns null under the
            // CLI, so schemaLayers()' cache is inert in this suite. Through the web
            // SAPI the list is cached for the endpoint's auth window and busted by
            // Table::clearCacheOnSchemaChanges().
            $this->assertSame(["$schema.alive"], Mapcache::schemaLayers($schema, $conn),
                'the row whose table is gone must not be in the list');
        } finally {
            $res = $model->prepare("DELETE FROM settings.geometry_columns_join WHERE _key_ LIKE :p");
            $model->execute($res, ['p' => $schema . '.%']);
            $model->execQuery("DROP SCHEMA IF EXISTS \"$schema\" CASCADE", 'PDO', 'transaction');
        }
    }

    /** A schema with no layers resolves to nothing, so the caller can fail closed. */
    public function testSchemaLayersIsEmptyForAnUnknownSchema(): void
    {
        $this->assertSame([], Mapcache::schemaLayers('zz_no_such_schema', new Connection(database: 'mydb')));
    }
}
