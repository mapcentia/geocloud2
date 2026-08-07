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
    private $subUserName;
    private $subUserAccessToken;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Layer api test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'layerapitest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'layer_api_test_' . $this->date->getTimestamp();
        $this->subUserName = 'layerapisub' . $this->date->getTimestamp();
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

        // Mutations must regenerate the mapfile of the layer's schema (not just public)
        $mapfile = '/var/www/geocloud2/app/wms/mapfiles/' . $this->userId . '_' . $this->schemaName . '_wms.map';
        $I->assertFileExists($mapfile);
        $I->assertStringContainsString('Main roads', file_get_contents($mapfile));
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

    public function shouldCrudClasses(ApiTester $I)
    {
        $this->auth($I);
        // Collection GET
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(2, $response);

        // Single GET
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('Main roads', $response['name']);

        // POST a new class with nested style
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode([
            'name' => 'Paths',
            'styles' => [['color' => '#0000ff']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $newClassId = basename($location);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newClassId);

        // sortid defaulted to highest existing + 10 (10 and 20 exist)
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(30, $response['sortid']);
        $I->assertCount(1, $response['styles']);

        // PATCH (key-merge)
        $I->stopFollowingRedirects();
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId, json_encode([
            'sortid' => 5,
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(5, $response['sortid']);
        $I->assertEquals('Paths', $response['name']); // merge keeps other keys

        // DELETE
        $I->sendDELETE('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/' . $newClassId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadClassRequests(ApiTester $I)
    {
        $this->auth($I);
        // Unknown class id
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Client-supplied id
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode([
            'name' => 'X', 'id' => 'cafebabe',
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // styles in PATCH body
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId, json_encode([
            'styles' => [],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // PATCH on collection
        $I->sendPatch('/api/v4/layers/' . $this->layerKey . '/classes', json_encode(['name' => 'X']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // POST without name
        $I->sendPOST('/api/v4/layers/' . $this->layerKey . '/classes', json_encode(['sortid' => 1]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldCrudStyles(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/styles';

        // Collection GET — one style from the layer POST
        $I->sendGET($base);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $response);
        $I->assertEquals($this->styleId, $response[0]['id']);

        // Single GET
        $I->sendGET($base . '/' . $this->styleId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals('#008000', $response['color']);

        // POST without sortid → defaults to highest + 10 (10 exists)
        $I->sendPOST($base, json_encode(['color' => '#ff0000', 'width' => '4']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $newStyleId = basename($I->grabHttpHeader('Location'));
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newStyleId);
        $I->sendGET($base . '/' . $newStyleId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(20, $response['sortid']);

        // PATCH sortid to reorder
        $I->stopFollowingRedirects();
        $I->sendPatch($base . '/' . $newStyleId, json_encode(['sortid' => 5]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET($base . '/' . $newStyleId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(5, $response['sortid']);
        $I->assertEquals('#ff0000', $response['color']); // merge keeps other keys

        // DELETE
        $I->sendDELETE($base . '/' . $newStyleId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET($base . '/' . $newStyleId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadStyleRequests(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/styles';
        // Unknown style id
        $I->sendGET($base . '/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown class id in path
        $I->sendGET('/api/v4/layers/' . $this->layerKey . '/classes/deadbeef/styles');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown property key
        $I->sendPOST($base, json_encode(['no_such_key' => '1']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Client-supplied id
        $I->sendPOST($base, json_encode(['id' => 'cafebabe', 'color' => '#fff']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldCrudLabels(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/labels';

        // Collection GET — one label from the layer POST
        $I->sendGET($base);
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertCount(1, $response);
        $I->assertEquals($this->labelId, $response[0]['id']);
        $I->assertTrue($response[0]['on']);

        // POST
        $I->sendPOST($base, json_encode(['on' => false, 'text' => '[name]', 'color' => '#000000']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $newLabelId = basename($I->grabHttpHeader('Location'));
        $I->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $newLabelId);
        $I->sendGET($base . '/' . $newLabelId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(20, $response['sortid']); // 10 exists → default 20
        $I->assertFalse($response['on']);

        // PATCH — toggle on
        $I->stopFollowingRedirects();
        $I->sendPatch($base . '/' . $newLabelId, json_encode(['on' => true]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET($base . '/' . $newLabelId);
        $response = json_decode($I->grabResponse(), true);
        $I->assertTrue($response['on']);
        $I->assertEquals('[name]', $response['text']);

        // DELETE
        $I->sendDELETE($base . '/' . $newLabelId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET($base . '/' . $newLabelId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectBadLabelRequests(ApiTester $I)
    {
        $this->auth($I);
        $base = '/api/v4/layers/' . $this->layerKey . '/classes/' . $this->classId . '/labels';
        // Unknown label id
        $I->sendGET($base . '/deadbeef');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        // Unknown property key
        $I->sendPOST($base, json_encode(['no_such_key' => '1']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        // Wrong type for on
        $I->sendPOST($base, json_encode(['on' => 'yes']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldForbidSubUserOnForeignSchema(ApiTester $I)
    {
        // Create a sub-user under the super user (session cookie flow)
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => $this->password,
            'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->subUserName,
            'email' => 'sub' . $this->userEmail,
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;

        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $subUserId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subUserAccessToken = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subUserAccessToken);
        $I->sendGET('/api/v4/layers/' . $this->layerKey);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
