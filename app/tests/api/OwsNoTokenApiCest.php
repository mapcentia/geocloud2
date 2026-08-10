<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v4/controllers/OwsNoToken.php — anonymous and HTTP Basic
 * access to the v4 OWS endpoint (WMS/WFS/UTFGRID) for clients like QGIS that
 * send no bearer token. Layers default to authentication level 'Write':
 * reads are anonymous; 'Read/write' layers require Basic auth for reads.
 */
class OwsNoTokenApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;
    private $subUserId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Ows no token test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'owsnotokentest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'ows_no_token_test_' . $this->date->getTimestamp();
    }

    private function endpoint(): string
    {
        return '/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId;
    }

    private function getMapQs(?string $layers = null): string
    {
        $layers = $layers ?? $this->schemaName . '.roads';
        return 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $layers
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=64&HEIGHT=64&FORMAT=image/png&STYLES=';
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

        // Schema + table with a geometry column, then configure the layer
        // (assigns classes + regenerates mapfiles).
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
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
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->schemaName . '.roads.the_geom',
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->deleteHeader('Authorization');

        // The HTTP Basic auth password for OWS/WFS is the separate "viewer"
        // password stored in settings.viewer — set it via the legacy
        // session-authenticated endpoint (same as DatabaseManagementCest).
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldServeGetCapabilitiesAnonymously(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('WMS_Capabilities', $body);
        $I->assertStringContainsString('roads', $body);
    }

    public function shouldServeGetMapAnonymously(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::OK);
        $ct = strtolower($I->grabHttpHeader('Content-Type'));
        // Empty table still produces a valid PNG (MapServer draws a blank image)
        $I->assertStringContainsString('image/png', $ct);
    }

    // GetCapabilities carries no layer, so per-layer auth never fires. A
    // fabricated Authorization header must not be trusted as identity:
    // present-but-wrong credentials are rejected with a 401 challenge.
    public function shouldRejectGetCapabilitiesWithInvalidCredentials(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, 'WrongPassword1');
        $I->sendGET($this->endpoint() . '?SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
        $I->deleteHeader('Authorization'); // don't leak creds into later anonymous tests
    }

    public function shouldServeGetCapabilitiesWithValidCredentials(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->sendGET($this->endpoint() . '?SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsString('WMS_Capabilities', $I->grabResponse());
        $I->deleteHeader('Authorization'); // don't leak creds into later anonymous tests
    }

    public function shouldReturnServiceExceptionForUnknownLayerAnonymously(ApiTester $I)
    {
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.nope'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=8&HEIGHT=8&FORMAT=image/png&STYLES=';
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsStringIgnoringCase('ServiceException', $I->grabResponse());
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

    // A geofence deny rule keyed to the parent-user (database) name must NOT
    // match an anonymous request, whose geofence identity is "*". Regression
    // guard for the identity fix in applyRules(): before, the anonymous user
    // was the database name and matched parent-user rules.
    public function shouldNotApplyParentUserGeofenceRuleToAnonymousRequest(ApiTester $I)
    {
        $id = $this->createRule($I, [
            'username' => $this->userId, 'service' => 'ows', 'request' => 'select', 'access' => 'deny',
        ]);
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::OK);
        $ct = strtolower($I->grabHttpHeader('Content-Type'));
        $body = $I->grabResponse();
        $I->assertStringContainsString('image/png', $ct);
        $I->assertStringNotContainsStringIgnoringCase('ServiceException', $body);
        $this->deleteRule($I, $id);
    }

    // A deny rule keyed to "*" DOES match anonymous requests — proves the
    // geofence is still consulted for the anonymous identity. The deny throws
    // inside the stream callback and renders as an OGC ServiceException body
    // (HTTP 200, since headers are already streamed).
    public function shouldApplyWildcardGeofenceRuleToAnonymousRequest(ApiTester $I)
    {
        $id = $this->createRule($I, [
            'username' => '*', 'service' => 'ows', 'request' => 'select', 'access' => 'deny',
        ]);
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsStringIgnoringCase('ServiceException', $body);
        $I->assertStringContainsString('DENY', $body);
        $this->deleteRule($I, $id);
    }

    public function shouldChangeLayerToReadWrite(ApiTester $I)
    {
        // The SQL API denies writes to system relations (settings.*), so use the
        // legacy session-authenticated layer-records endpoint, same as OwsApiCest.
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.roads.the_geom', json_encode([
            'data' => [
                'authentication' => 'Read/write',
                '_key_' => $this->schemaName . '.roads.the_geom',
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldRejectAnonymousGetMapOnReadWriteLayer(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
    }

    public function shouldServeGetMapOnReadWriteLayerWithBasicAuth(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::OK);
        $ct = strtolower($I->grabHttpHeader('Content-Type'));
        $I->assertStringContainsString('image/png', $ct);
    }

    public function shouldRejectWrongBasicCredentials(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, 'WrongPassword1');
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs());
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    // Create a subuser under the owner and give it a Basic-auth (viewer)
    // password. The layer is now Read/write (shouldChangeLayerToReadWrite ran
    // earlier), so per-layer Basic auth fires for reads.
    public function shouldPrepareSubUser(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $ownerCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($ownerCookie));

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $ownerCookie);
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'Ows no token subuser ' . $this->date->getTimestamp(),
            'email' => 'owsnotokensub' . $this->date->getTimestamp() . '@example.com',
            'password' => $this->password,
            'subuser' => true,
            'createschema' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        // Set the subuser's viewer password via its own session.
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->subUserId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subCookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $subCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    // Regression guard for the layer-name normalization in basicAuthPerLayer.
    // A subuser with no privilege on the Read/write layer requests it via an
    // UNQUALIFIED layer name ("roads" instead of "schema.roads"). Before the
    // fix the raw name reached BasicAuth, whose split on '.' produced an empty
    // table name, silently skipping the privilege check and granting access.
    // Now the name is qualified to "schema.roads" and the check denies it.
    public function shouldDenySubUserWithoutPrivilegeViaUnqualifiedLayer(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->subUserId, $this->password);
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs('roads'));
        $I->seeResponseCodeIs(HttpCode::OK); // streamed; denial renders as an XML error body
        $body = $I->grabResponse();
        $I->assertStringContainsStringIgnoringCase('privileges', $body);
        $I->assertStringNotContainsString('image/png', strtolower($I->grabHttpHeader('Content-Type')));
    }

    public function shouldGrantReadPrivilegeToSubUser(ApiTester $I)
    {
        // Clear the persistent Basic-auth header left by the previous test, then
        // start a fresh owner session so the browser cookie jar holds the owner
        // (not the subuser) session before the privilege grant.
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPUT('/controllers/layer/privileges', json_encode([
            'data' => [
                'subuser' => $this->subUserId,
                'privileges' => 'read',
                '_key_' => $this->schemaName . '.roads.the_geom',
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    // With 'read' privilege the same unqualified request passes the per-layer
    // privilege check — the "no privileges" denial is gone. (MapServer itself
    // may still reject the unqualified layer name, but that happens downstream
    // of auth; the point here is that the privilege gate no longer blocks it.)
    public function shouldAllowSubUserWithReadPrivilegeViaUnqualifiedLayer(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->subUserId, $this->password);
        $I->sendGET($this->endpoint() . '?' . $this->getMapQs('roads'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $I->grabResponse());
    }
}
