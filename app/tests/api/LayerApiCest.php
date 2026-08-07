<?php

use Codeception\Util\HttpCode;

class LayerApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $userAccessToken;
    private $schemaName;
    private $layerKey;
    private $classId;
    private $styleId;
    private $labelId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Layer api test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'layerapitest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'layer_api_test_' . $this->date->getTimestamp();
    }

    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
    }

    public function shouldPrepareUserAndToken(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $this->userId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->userAccessToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldCreateSchemaAndTableWithGeometry(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'roads',
            'columns' => [
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(LineString,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->layerKey = $this->schemaName . '.roads.the_geom';
    }

    public function shouldGetLayerWithDefaultProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals($this->layerKey, $response['name']);
        $I->assertArrayHasKey('theme_column', $response['properties']);
        $I->assertArrayHasKey('ttl', $response['properties']);
        $I->assertEquals([], $response['classes']);
    }

    public function shouldPostFullLayerResource(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->layerKey,
            'properties' => ['opacity' => '80', 'maxscaledenom' => '50000'],
            'classes' => [
                [
                    'name' => 'Main roads',
                    'sortid' => 10,
                    'expression' => "[name]='main'",
                    'styles' => [['sortid' => 10, 'color' => '#008000', 'width' => '2']],
                    'labels' => [['sortid' => 10, 'on' => true, 'text' => '[name]', 'size' => '10']],
                ],
                ['name' => 'Other roads', 'sortid' => 20],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $I->assertStringContainsString('/api/v4/layers/' . $this->layerKey, $location);

        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('80', $response['properties']['opacity']);
        $I->assertEquals('50000', $response['properties']['maxscaledenom']);
        $I->assertCount(2, $response['classes']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['id']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['styles'][0]['id']);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $response['classes'][0]['labels'][0]['id']);
        $this->classId = $response['classes'][0]['id'];
        $this->styleId = $response['classes'][0]['styles'][0]['id'];
        $this->labelId = $response['classes'][0]['labels'][0]['id'];

        // Ids are stable across requests
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals($this->classId, $response['classes'][0]['id']);
    }

    public function shouldPatchLayerProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->stopFollowingRedirects();
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'properties' => ['opacity' => '50'],
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('50', $response['properties']['opacity']);
        $I->assertEquals('50000', $response['properties']['maxscaledenom']); // key-merge keeps others
    }

    public function shouldListLayers(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/layers?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains($this->layerKey);
    }

    public function shouldRejectBadLayerRequests(ApiTester $I)
    {
        $this->auth($I);
        // Bad key format
        $I->sendGET('/api/v4/layers/not_a_layer_key');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Unknown table
        $I->sendGET('/api/v4/layers/' . $this->schemaName . '.nope.the_geom');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown geometry column on existing table
        $I->sendGET('/api/v4/layers/' . $this->schemaName . '.roads.wrong_geom');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown key in properties
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'properties' => ['no_such_key' => '1'],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // classes not allowed in PATCH
        $I->sendPatch('/api/v4/layers/' . $this->layerKey, json_encode([
            'classes' => [],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // POST to a resource id
        $I->sendPOST('/api/v4/layers/' . $this->layerKey, json_encode(['name' => $this->layerKey]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }
}
