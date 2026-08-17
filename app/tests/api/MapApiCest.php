<?php

use Codeception\Util\HttpCode;

/**
 * Tests the v4 Map API (api/v4/map/schema/{schema}), the port of the old GUI
 * /controllers/setting/extent endpoint. The API surface speaks EPSG:4326 (lon/lat), while the
 * values are stored in EPSG:3857 so the legacy GUI endpoint keeps working. Proves GET returns
 * the per-schema center/zoom/extent in 4326 (null when unset), PATCH sets any subset of them
 * (partial update leaves the others intact), null clears a value, malformed shapes / unknown
 * properties are rejected, and the stored representation is 3857.
 */
class MapApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $cookie;
    private $schemaName;

    // API surface values are EPSG:4326.
    private $center = [12.5, 55.8];
    private $extent = [8.0, 54.5, 15.5, 57.5];

    public function __construct()
    {
        $this->date = new DateTime();
        $this->schemaName = 'map_' . $this->date->getTimestamp();
    }

    private function auth(ApiTester $I): void
    {
        $I->deleteHeader('Cookie');
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

        // Session cookie for the legacy /controllers/setting read (used to inspect raw storage).
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->cookie = $I->capturePHPSESSID();
    }

    // A freshly created schema has no map configuration yet.
    public function shouldReturnNullsWhenUnset(ApiTester $I)
    {
        $map = $this->getMap($I);
        $I->assertNull($map['center']);
        $I->assertNull($map['zoom']);
        $I->assertNull($map['extent']);
    }

    // PATCH all three properties (in 4326), then GET returns them (in 4326).
    public function shouldSetAllProperties(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode([
            'center' => $this->center,
            'zoom' => 12,
            'extent' => $this->extent,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK); // 303 followed to the GET

        $map = $this->getMap($I);
        // Round-trips through 3857 storage, so compare with a small delta.
        $I->assertEqualsWithDelta($this->center, $map['center'], 1e-4);
        $I->assertEquals(12, $map['zoom']);
        $I->assertEqualsWithDelta($this->extent, $map['extent'], 1e-4);
    }

    // The values are stored in EPSG:3857 (so the legacy GUI endpoint keeps working). Read the raw
    // settings.viewer document via the old /controllers/setting endpoint and assert the stored
    // extent/center are projected meters, not the 4326 degrees sent to the API.
    public function shouldStoreIn3857(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->cookie);
        $I->sendGET('/controllers/setting');
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);

        $storedExtent = $data['data']['extents'][$this->schemaName];
        $storedCenter = $data['data']['center'][$this->schemaName];
        // 3857 coordinates for Denmark are hundreds of thousands / millions of meters — far outside
        // the [-180, 180] / [-90, 90] range of the 4326 values the API accepted.
        foreach (array_merge($storedExtent, $storedCenter) as $coord) {
            $I->assertGreaterThan(180, abs($coord), 'stored coordinate must be projected meters (3857), not degrees');
        }
    }

    // PATCH only zoom — center and extent are left untouched.
    public function shouldPartiallyUpdate(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['zoom' => 7]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $map = $this->getMap($I);
        $I->assertEquals(7, $map['zoom']);
        $I->assertEqualsWithDelta($this->center, $map['center'], 1e-4);
        $I->assertEqualsWithDelta($this->extent, $map['extent'], 1e-4);
    }

    // null clears a single value without disturbing the others.
    public function shouldClearWithNull(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/map/schema/' . $this->schemaName, json_encode(['center' => null]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $map = $this->getMap($I);
        $I->assertNull($map['center']);
        $I->assertEquals(7, $map['zoom']);
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
