<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v4/controllers/Feature.php — the v4 Feature API (GeoJSON CRUD on
 * single features), which drives the in-process WFS engine instead of v2's
 * internal http://localhost/wfs/... call.
 */
class FeatureV4ApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;
    private $insertedKey;
    private $insertedKey2;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Feature v4 test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'featurev4test' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'feature_v4_test_' . $this->date->getTimestamp();
    }

    private function endpoint(): string
    {
        return '/api/v4/schemas/' . $this->schemaName . '/tables/poi/features';
    }

    private function featureCollection(string $name, float $lon = 9.5, float $lat = 55.7): string
    {
        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['name' => $name],
                'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
            ]],
        ]);
    }

    public function shouldPrepareUserSchemaAndLayer(ApiTester $I)
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
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // The WFS engine needs a primary key to expose the layer
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function shouldInsertFeatureFromFeatureCollection(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($this->endpoint(), $this->featureCollection('alpha'));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeHttpHeader('Location');
        $links = json_decode($I->grabResponse(), true);
        $self = $links[0]['_links']['self'];
        $this->insertedKey = basename($self);
        $I->assertNotEmpty($this->insertedKey);
    }

    // A single matched key returns a bare GeoJSON Feature (not a FeatureCollection)
    public function shouldGetFeatureAsGeoJson(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '/' . $this->insertedKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $feature = json_decode($I->grabResponse(), true);
        $I->assertSame('Feature', $feature['type']);
        $I->assertSame('alpha', $feature['properties']['name']);
        $I->assertSame('Point', $feature['geometry']['type']);
        $I->assertEqualsWithDelta(9.5, $feature['geometry']['coordinates'][0], 0.0001);
        $I->assertEqualsWithDelta(55.7, $feature['geometry']['coordinates'][1], 0.0001);
    }

    // A comma-separated key list returns a FeatureCollection with every match
    public function shouldGetMultipleFeaturesAsFeatureCollection(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($this->endpoint(), $this->featureCollection('bravo', 10.1, 56.1));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $links = json_decode($I->grabResponse(), true);
        $this->insertedKey2 = basename($links[0]['_links']['self']);

        $I->sendGET($this->endpoint() . '/' . $this->insertedKey . ',' . $this->insertedKey2);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame('FeatureCollection', $body['type']);
        $I->assertCount(2, $body['features']);
        $names = array_map(static fn($f) => $f['properties']['name'], $body['features']);
        $I->assertContains('alpha', $names);
        $I->assertContains('bravo', $names);
        foreach ($body['features'] as $feature) {
            $I->assertSame('Feature', $feature['type']);
            $I->assertSame('Point', $feature['geometry']['type']);
        }
    }

    public function shouldReturnNotFoundForUnknownFeature(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldUpdateFeatureWithPatch(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH($this->endpoint() . '/' . $this->insertedKey, json_encode([
            'type' => 'Feature',
            'properties' => ['name' => 'beta'],
            'geometry' => ['type' => 'Point', 'coordinates' => [10.0, 56.0]],
        ]));
        // 303 See Other is followed to the GET of the updated feature,
        // which returns a bare GeoJSON Feature for a single key
        $I->seeResponseCodeIs(HttpCode::OK);
        $feature = json_decode($I->grabResponse(), true);
        $I->assertSame('Feature', $feature['type']);
        $I->assertSame('beta', $feature['properties']['name']);
        $I->assertEqualsWithDelta(10.0, $feature['geometry']['coordinates'][0], 0.0001);
    }

    public function shouldRejectPatchWithoutPrimaryKey(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH($this->endpoint(), json_encode([
            'type' => 'Feature',
            'properties' => ['name' => 'gamma'],
            'geometry' => null,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldRejectNonGeoJsonBody(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($this->endpoint(), json_encode(['foo' => 'bar']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    // The v4 dispatcher rejects token-less requests to non-public routes with
    // 400 "No token in header" (Jwt::validate) — same as every other v4 route.
    public function shouldRejectRequestWithoutToken(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->sendGET($this->endpoint() . '/1');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertStringContainsString('No token in header', $I->grabResponse());
    }

    public function shouldDeleteFeature(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE($this->endpoint() . '/' . $this->insertedKey);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function shouldReturnNotFoundAfterDelete(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '/' . $this->insertedKey);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldReturnNotFoundWhenDeletingUnknownFeature(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE($this->endpoint() . '/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
}
