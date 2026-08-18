<?php

use Codeception\Util\HttpCode;

class OwsApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Ows api test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'owsapitest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'ows_api_test_' . $this->date->getTimestamp();
    }

    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function endpoint(): string
    {
        return '/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId;
    }

    public function shouldPrepareUserTokenSchemaAndLayer(ApiTester $I)
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

        // Schema + table with a geometry column, then write mapfiles
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
        // Configure the layer (assigns classes + regenerates mapfiles)
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->schemaName . '.roads.the_geom',
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // A presented Bearer token must be valid — a garbage token is not silently
    // downgraded to anonymous access. Jwt::validate sends a WWW-Authenticate
    // Bearer challenge (401) and the body is an OGC ServiceException report.
    public function shouldRejectInvalidToken(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer not-a-jwt');
        $I->sendGET($this->endpoint() . '?SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
        $I->assertStringContainsStringIgnoringCase('ServiceException', $I->grabResponse());
        $I->deleteHeader('Authorization');
    }

    // A valid token for ANOTHER database must not authorize requests against
    // this database (mirrors the MapCache proxy's database check).
    public function shouldRejectTokenForWrongDatabase(ApiTester $I)
    {
        $otherName = 'Ows api other user ' . $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $otherName, 'email' => 'owsapiother' . $this->date->getTimestamp() . '@example.com',
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $otherId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $otherId, 'password' => $this->password,
            'database' => $otherId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $otherToken = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $otherToken);
        $I->sendGET($this->endpoint() . '?SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsStringIgnoringCase('ServiceException', $body);
        $I->assertStringContainsString('Token is not valid for this database', $body);
        $I->deleteHeader('Authorization');
    }

    public function shouldServeGetCapabilitiesMatchingLegacy(ApiTester $I)
    {
        $qs = 'SERVICE=WMS&REQUEST=GetCapabilities&VERSION=1.3.0';

        // v4 (token)
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $v4 = $I->grabResponse();
        $I->deleteHeader('Authorization');

        // legacy (basic auth)
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->sendGET('/ows/' . $this->userId . '/' . $this->schemaName . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $legacy = $I->grabResponse();

        // Both are WMS capabilities XML mentioning the layer
        $I->assertStringContainsString('roads', $v4);
        $I->assertStringContainsString('WMS_Capabilities', $v4);
        // Structural parity: same root element as legacy
        $I->assertEquals(
            substr($legacy, strpos($legacy, '<'), 40),
            substr($v4, strpos($v4, '<'), 40)
        );
    }

    public function shouldServeGetMap(ApiTester $I)
    {
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=64&HEIGHT=64&FORMAT=image/png&STYLES=';
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $ct = strtolower($I->grabHttpHeader('Content-Type'));
        // Empty table still produces a valid PNG (MapServer draws a blank image)
        $I->assertStringContainsString('image/png', $ct);
    }

    // Regression (CRITICAL 1): authorizeLayers() used to always pass the JWT
    // user as subUser, forcing the sub-user privilege branch in Authorization::check
    // even for the database OWNER, so any Read/write layer 403'd the owner's own
    // GetMap requests. The owner token from shouldPrepareUserTokenSchemaAndLayer
    // IS the database (parentUser=true), so this must succeed once the layer is
    // Read/write protected.
    public function shouldAllowOwnerToGetMapOnReadWriteProtectedLayer(ApiTester $I)
    {
        // Set the layer's authentication level to Read/write. The SQL API denies writes
        // to unregistered/system relations (settings.*), so use the legacy session-authenticated
        // layer-records endpoint, same as DatabaseManagementCest::shouldChangeTheAuthenticationLevelFromWriteToReadwrite.
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

        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=64&HEIGHT=64&FORMAT=image/png&STYLES=';
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $ct = strtolower($I->grabHttpHeader('Content-Type'));
        $body = $I->grabResponse();
        $I->assertStringContainsString('image/png', $ct);
        $I->assertStringNotContainsStringIgnoringCase('Insufficient privileges', $body);
        $I->assertStringNotContainsStringIgnoringCase('ServiceExceptionReport', $body);
    }

    // The per-layer allow decision is cached for 60s. The previous test already served (and cached)
    // this token on the Read/write layer; a second identical request must be served from the cached
    // allow — same result, exercising the cache-hit path.
    public function shouldServeGetMapFromCachedAuthDecision(ApiTester $I)
    {
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=64&HEIGHT=64&FORMAT=image/png&STYLES=';
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsString('image/png', strtolower($I->grabHttpHeader('Content-Type')));
        $I->assertStringNotContainsStringIgnoringCase('ServiceExceptionReport', $I->grabResponse());
    }

    public function shouldReturnServiceExceptionForUnknownLayer(ApiTester $I)
    {
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.nope'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=8&HEIGHT=8&FORMAT=image/png&STYLES=';
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        // MapServer returns an XML ServiceException for an unknown layer
        $I->assertStringContainsStringIgnoringCase('ServiceException', $I->grabResponse());
    }

    public function shouldNotLeakTmpFilesForFilteredRequest(ApiTester $I)
    {
        $filters = rtrim(strtr(base64_encode(json_encode([
            $this->schemaName . '.roads' => ["name = 'x'"],
        ])), '+/', '-_'), '=');
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=8&HEIGHT=8&FORMAT=image/png&STYLES=&filters=' . $filters;
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET($this->endpoint() . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        // Tmp files are cleaned up in the controller's finally block.
        $leftovers = glob('/var/www/geocloud2/app/tmp/*.map');
        // There may be unrelated tmp files from other tests; assert none reference our schema.
        $mine = array_filter($leftovers ?: [], fn($f) => str_contains(@file_get_contents($f) ?: '', $this->schemaName));
        $I->assertEmpty($mine, 'v4 OWS tmp mapfiles should be cleaned up');
    }
}
