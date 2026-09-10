<?php

use Codeception\Util\HttpCode;

/**
 * Tests the OGC API Features/Maps endpoints (app/api/v4/controllers/Ogc.php, OgcFeatures.php,
 * OgcMaps.php) for token-less clients: anonymous and HTTP Basic. Layers default to
 * authentication 'Write' (anonymously readable); a 'Read/write' layer must be invisible
 * anonymously and readable with Basic auth. Token flows are covered by OgcApiCest.
 *
 * Setup creates: poi (plain), poi_v (versioned), secret (Read/write) — all Point/4326 in one schema.
 */
class OgcNoTokenApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;
    private $poiKey1;
    private $poiKey2;
    private $versionedKey;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Ogc no token test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'ogcnotokentest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'ogc_no_token_test_' . $this->date->getTimestamp();
    }

    private function base(): string
    {
        return '/api/v4/ogc/database/' . $this->userId;
    }

    private function collection(string $table): string
    {
        return $this->base() . '/collections/' . $this->schemaName . '.' . $table;
    }

    private function featureCollection(string $name, float $lon, float $lat): string
    {
        return json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['name' => $name],
            'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
        ]]]);
    }

    private function createTable(ApiTester $I, string $name): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => $name,
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/' . $name . '/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // Assign a class: registers the layer for WMS and regenerates the mapfiles.
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->schemaName . '.' . $name . '.the_geom',
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'size' => '6']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    /** Inserts through the v4 Feature API (WFS-T) and returns the new primary key. */
    private function insert(ApiTester $I, string $table, string $name, float $lon, float $lat): string
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/' . $table . '/features', $this->featureCollection($name, $lon, $lat));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $links = json_decode($I->grabResponse(), true);
        $I->deleteHeader('Authorization');
        return basename($links[0]['_links']['self']);
    }

    private function startSession(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($cookie));
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
    }

    private function createRule(ApiTester $I, array $rule): string
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/rules', json_encode($rule));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $id = basename($I->grabHttpHeader('Location'));
        $I->deleteHeader('Authorization');
        return $id;
    }

    private function deleteRule(ApiTester $I, string $id): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE('/api/v4/rules/' . $id);
        $I->deleteHeader('Authorization');
    }

    public function shouldPrepareUserSchemaAndLayers(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName, 'email' => $this->userEmail, 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $this->createTable($I, 'poi');
        $this->createTable($I, 'poi_v');
        $this->createTable($I, 'secret');
        $I->deleteHeader('Authorization');

        // Versioning on poi_v and Read/write on secret go through the legacy session endpoints
        // (there is no v4 endpoint for either), same pattern as OwsNoTokenApiCest.
        $this->startSession($I);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPUT('/controllers/table/versions/' . $this->schemaName . '.poi_v/' . $this->schemaName . '.poi_v.the_geom');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.secret.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->schemaName . '.secret.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        // The HTTP Basic password for OWS/OGC is the separate "viewer" password.
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');

        $this->poiKey1 = $this->insert($I, 'poi', 'alpha', 9.5, 55.7);
        $this->poiKey2 = $this->insert($I, 'poi', 'bravo', 10.1, 56.1);
        $this->insert($I, 'poi', 'charlie', 12.5, 55.6);
        $this->versionedKey = $this->insert($I, 'poi_v', 'v1', 9.0, 56.0);
        $this->insert($I, 'secret', 'hidden', 9.0, 56.0);
    }

    public function shouldServeLandingPageAnonymously(ApiTester $I)
    {
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/json; charset=utf-8');
        $doc = json_decode($I->grabResponse(), true);
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertStringEndsWith($this->base(), $rels['self']);
        $I->assertStringEndsWith($this->base() . '/conformance', $rels['conformance']);
        $I->assertStringEndsWith($this->base() . '/collections', $rels['data']);
        $I->assertStringContainsString('/swagger/api.php?v=4', $rels['service-desc']);
        $I->assertArrayHasKey('service-doc', $rels);
    }

    public function shouldServeConformance(ApiTester $I)
    {
        $I->sendGET($this->base() . '/conformance');
        $I->seeResponseCodeIs(HttpCode::OK);
        $doc = json_decode($I->grabResponse(), true);
        $I->assertContains('http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/core', $doc['conformsTo']);
        $I->assertContains('http://www.opengis.net/spec/ogcapi-features-2/1.0/conf/crs', $doc['conformsTo']);
        $I->assertContains('http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/core', $doc['conformsTo']);
    }

    public function shouldRejectUnknownQueryParameter(ApiTester $I)
    {
        $I->sendGET($this->base() . '/collections?foo=1');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertSame('UNKNOWN_PARAMETER', json_decode($I->grabResponse(), true)['errorCode']);
        $I->sendGET($this->base() . '?f=xml');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldListOnlyAnonymouslyReadableCollections(ApiTester $I)
    {
        $I->sendGET($this->base() . '/collections');
        $I->seeResponseCodeIs(HttpCode::OK);
        $doc = json_decode($I->grabResponse(), true);
        $ids = array_column($doc['collections'], 'id');
        $I->assertContains($this->schemaName . '.poi', $ids);
        $I->assertContains($this->schemaName . '.poi_v', $ids);
        $I->assertNotContains($this->schemaName . '.secret', $ids);   // Read/write is invisible anonymously
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertArrayHasKey('self', $rels);
    }

    public function shouldPaginateCollections(ApiTester $I)
    {
        $I->sendGET($this->base() . '/collections?limit=1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $doc = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $doc['collections']);
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertStringContainsString('offset=1', $rels['next']);
        $I->assertGreaterThanOrEqual(2, $doc['numberMatched']);
    }

    public function shouldDescribeCollection(ApiTester $I)
    {
        $I->sendGET($this->collection('poi'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $c = json_decode($I->grabResponse(), true);
        $I->assertSame($this->schemaName . '.poi', $c['id']);
        $I->assertSame('feature', $c['itemType']);
        $bbox = $c['extent']['spatial']['bbox'][0];
        // Three points between lon 9.5..12.5, lat 55.6..56.1 (estimated extent may be slightly wider)
        $I->assertLessThanOrEqual(9.6, $bbox[0]);
        $I->assertGreaterThanOrEqual(12.4, $bbox[2]);
        $I->assertLessThanOrEqual(55.7, $bbox[1]);
        $I->assertGreaterThanOrEqual(56.0, $bbox[3]);
        $I->assertContains('http://www.opengis.net/def/crs/OGC/1.3/CRS84', $c['crs']);
        $I->assertContains('http://www.opengis.net/def/crs/EPSG/0/4326', $c['crs']);
        $I->assertContains('http://www.opengis.net/def/crs/EPSG/0/25832', $c['crs']);
        $I->assertSame('http://www.opengis.net/def/crs/EPSG/0/4326', $c['storageCrs']);
        $rels = array_column($c['links'], 'href', 'rel');
        $I->assertStringEndsWith($this->collection('poi') . '/items', $rels['items']);
        $I->assertStringEndsWith($this->collection('poi') . '/map', $rels['http://www.opengis.net/def/rel/ogc/1.0/map']);
    }

    public function shouldExposeTemporalExtentForVersionedCollection(ApiTester $I)
    {
        $I->sendGET($this->collection('poi_v'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $c = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('temporal', $c['extent']);
        $I->assertNotEmpty($c['extent']['temporal']['interval'][0][0]);
        $I->assertNull($c['extent']['temporal']['interval'][0][1]);
    }

    public function shouldReturnNotFoundForUnknownOrHiddenCollection(ApiTester $I)
    {
        $I->sendGET($this->collection('nope'));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET($this->collection('secret'));   // exists, but not visible anonymously → 404, not 403
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET($this->base() . '/nope');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldStreamItemsAsGeoJson(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/geo+json');
        $I->seeHttpHeader('Content-Crs', '<http://www.opengis.net/def/crs/OGC/1.3/CRS84>');
        $doc = json_decode($I->grabResponse(), true);
        $I->assertNotNull($doc, 'valid JSON expected: ' . $I->grabResponse());
        $I->assertSame('FeatureCollection', $doc['type']);
        $I->assertSame(3, $doc['numberMatched']);
        $I->assertSame(3, $doc['numberReturned']);
        $I->assertCount(3, $doc['features']);
        $f = $doc['features'][0];
        $I->assertSame('Feature', $f['type']);
        $I->assertIsInt($f['id']);
        $I->assertSame('Point', $f['geometry']['type']);
        $I->assertArrayHasKey('name', $f['properties']);
        $I->assertArrayNotHasKey('the_geom', $f['properties']);
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertArrayHasKey('self', $rels);
        $I->assertArrayHasKey('collection', $rels);
        $I->assertArrayNotHasKey('next', $rels);
    }

    public function shouldPaginateItems(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items?limit=2');
        $I->seeResponseCodeIs(HttpCode::OK);
        $doc = json_decode($I->grabResponse(), true);
        $I->assertSame(3, $doc['numberMatched']);
        $I->assertSame(2, $doc['numberReturned']);
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertStringContainsString('limit=2', $rels['next']);
        $I->assertStringContainsString('offset=2', $rels['next']);
        $I->assertArrayNotHasKey('prev', $rels);

        $I->sendGET($this->collection('poi') . '/items?limit=2&offset=2');
        $doc = json_decode($I->grabResponse(), true);
        $I->assertSame(1, $doc['numberReturned']);
        $rels = array_column($doc['links'], 'href', 'rel');
        $I->assertArrayNotHasKey('next', $rels);
        $I->assertStringContainsString('offset=0', $rels['prev']);
    }

    public function shouldFilterItemsByBbox(ApiTester $I)
    {
        // Only alpha (9.5,55.7) lies in lon 9..10 / lat 55.5..56
        $I->sendGET($this->collection('poi') . '/items?bbox=9,55.5,10,56');
        $I->seeResponseCodeIs(HttpCode::OK);
        $doc = json_decode($I->grabResponse(), true);
        $I->assertSame(1, $doc['numberMatched']);
        $I->assertSame('alpha', $doc['features'][0]['properties']['name']);

        // Same box expressed lat/lon through the EPSG:4326 URI
        $I->sendGET($this->collection('poi') . '/items?bbox=55.5,9,56,10&bbox-crs=' . urlencode('http://www.opengis.net/def/crs/EPSG/0/4326'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(1, json_decode($I->grabResponse(), true)['numberMatched']);

        // Empty box → empty, valid collection
        $I->sendGET($this->collection('poi') . '/items?bbox=0,0,1,1');
        $doc = json_decode($I->grabResponse(), true);
        $I->assertSame(0, $doc['numberMatched']);
        $I->assertSame([], $doc['features']);
    }

    public function shouldReprojectItemsWithCrs(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items?bbox=9,55.5,10,56&crs=' . urlencode('http://www.opengis.net/def/crs/EPSG/0/25832'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Crs', '<http://www.opengis.net/def/crs/EPSG/0/25832>');
        $c = json_decode($I->grabResponse(), true)['features'][0]['geometry']['coordinates'];
        // 9.5E 55.7N ≈ 531 400 E, 6 172 000 N in UTM 32N
        $I->assertEqualsWithDelta(531400, $c[0], 5000);
        $I->assertEqualsWithDelta(6173000, $c[1], 5000);

        // EPSG:4326 URI: lat/lon axis order
        $I->sendGET($this->collection('poi') . '/items?bbox=9,55.5,10,56&crs=' . urlencode('http://www.opengis.net/def/crs/EPSG/0/4326'));
        $c = json_decode($I->grabResponse(), true)['features'][0]['geometry']['coordinates'];
        $I->assertEqualsWithDelta(55.7, $c[0], 0.0001);
        $I->assertEqualsWithDelta(9.5, $c[1], 0.0001);
    }

    public function shouldRejectBadItemsParameters(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items?foo=bar');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendGET($this->collection('poi') . '/items?crs=' . urlencode('http://www.opengis.net/def/crs/EPSG/0/2000'));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertSame('INVALID_CRS', json_decode($I->grabResponse(), true)['errorCode']);
        $I->sendGET($this->collection('poi') . '/items?limit=0');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendGET($this->collection('poi') . '/items?datetime=2024-01-01/2024-02-01');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldGetSingleItem(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items/' . $this->poiKey1);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/geo+json');
        $f = json_decode($I->grabResponse(), true);
        $I->assertSame('Feature', $f['type']);
        $I->assertSame((int)$this->poiKey1, $f['id']);
        $I->assertSame('alpha', $f['properties']['name']);
        $rels = array_column($f['links'], 'href', 'rel');
        $I->assertStringEndsWith('/items/' . $this->poiKey1, $rels['self']);
        $I->assertStringEndsWith($this->collection('poi'), $rels['collection']);
    }

    public function shouldReturnNotFoundForUnknownItem(ApiTester $I)
    {
        $I->sendGET($this->collection('poi') . '/items/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET($this->collection('poi') . "/items/1'");
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendGET($this->collection('poi') . '/items/' . $this->poiKey1 . '?limit=1');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);   // limit is not a single-item parameter
        $I->sendGET($this->collection('secret') . '/items');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);    // hidden anonymously
    }
}
