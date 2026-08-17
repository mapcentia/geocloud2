<?php

use Codeception\Util\HttpCode;

/**
 * Tests the v4 Map API (api/v4/map/schema/{schema}), the port of the old GUI
 * /controllers/setting/extent endpoint. Proves GET returns the per-schema center/zoom/extent
 * (null when unset), PATCH sets any subset of them (partial update leaves the others intact),
 * null clears a value, and malformed shapes / unknown properties are rejected.
 */
class MapApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->schemaName = 'map_' . $this->date->getTimestamp();
    }

    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function getMap(ApiTester $I): array
    {
        $this->auth($I);
        $I->sendGET('/api/v4/map/schema/' . $this->schemaName);
        $I->seeResponseCodeIs(HttpCode::OK);
        return json_decode($I->grabResponse(), true);
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'map_owner_' . $ts, 'email' => 'mapowner' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->auth($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // A freshly created schema has no map configuration yet.
    public function shouldReturnNullsWhenUnset(ApiTester $I)
    {
        $map = $this->getMap($I);
        $I->assertNull($map['center']);
        $I->assertNull($map['zoom']);
        $I->assertNull($map['extent']);
    }

    // PATCH all three properties, then GET returns them.
    public function shouldSetAllProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode([
            'center' => [1386651.0, 7503372.0],
            'zoom' => 12,
            'extent' => [1354000.0, 7478000.0, 1419000.0, 7528000.0],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK); // 303 followed to the GET

        $map = $this->getMap($I);
        $I->assertEquals([1386651.0, 7503372.0], $map["center"]);
        $I->assertEquals(12, $map["zoom"]);
        $I->assertEquals([1354000.0, 7478000.0, 1419000.0, 7528000.0], $map["extent"]);
    }

    // PATCH only zoom — center and extent are left untouched.
    public function shouldPartiallyUpdate(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['zoom' => 7]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $map = $this->getMap($I);
        $I->assertEquals(7, $map["zoom"]);
        $I->assertEquals([1386651.0, 7503372.0], $map["center"]);
        $I->assertEquals([1354000.0, 7478000.0, 1419000.0, 7528000.0], $map["extent"]);
    }

    // null clears a single value without disturbing the others.
    public function shouldClearWithNull(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['center' => null]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $map = $this->getMap($I);
        $I->assertNull($map['center']);
        $I->assertEquals(7, $map["zoom"]);
    }

    public function shouldRejectWrongExtentLength(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['extent' => [1, 2]]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldRejectNonNumericCenter(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['center' => ['a', 'b']]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldRejectUnknownProperty(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['foo' => 1]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldReturn404ForMissingSchema(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/map/schema/does_not_exist_' . $this->date->getTimestamp());
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldCleanup(ApiTester $I)
    {
        $this->auth($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schemaName);
    }
}
