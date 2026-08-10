<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v4/controllers/WfsNoToken.php — anonymous and HTTP Basic
 * access to the v4 WFS endpoint (for clients like QGIS that send no bearer
 * token). Layers default to authentication level 'Write': reads are anonymous,
 * transactions require Basic auth. 'Read/write' layers require Basic auth for
 * reads as well.
 */
class WfsNoTokenApiCest
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
        $this->userName = 'Wfs no token test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'wfsnotokentest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'wfs_no_token_test_' . $this->date->getTimestamp();
    }

    private function endpoint(): string
    {
        return '/api/v4/wfs/schema/' . $this->schemaName . '/database/' . $this->userId . '/srs/4326';
    }

    private function transactionXml(string $name): string
    {
        $ns = 'http://localhost/' . $this->userId . '/' . $this->schemaName;
        return '<Transaction xmlns="http://www.opengis.net/wfs" service="WFS" version="1.1.0"
             xmlns:gml="http://www.opengis.net/gml">
                <Insert xmlns="http://www.opengis.net/wfs">
                    <poi xmlns="' . $ns . '">
                        <name xmlns="' . $ns . '">' . $name . '</name>
                        <the_geom xmlns="' . $ns . '">
                            <gml:Point srsName="urn:ogc:def:crs:EPSG::4326">
                                <gml:pos>55.7 9.5</gml:pos>
                            </gml:Point>
                        </the_geom>
                    </poi>
                </Insert>
            </Transaction>';
    }

    public function shouldPrepareUserSchemaLayerAndData(ApiTester $I)
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
        // The WFS server needs a primary key to expose the layer
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => "INSERT INTO " . $this->schemaName . ".poi (name, the_geom) "
                . "VALUES ('alpha', ST_SetSRID(ST_MakePoint(9.5, 55.7), 4326))",
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
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
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetCapabilities&VERSION=1.1.0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('WFS_Capabilities', $body);
        $I->assertStringContainsString('poi', $body);
    }

    // GetCapabilities carries no typeName, so per-layer auth never fires. A
    // fabricated Authorization header must therefore not be trusted as identity:
    // present-but-wrong credentials are rejected with a 401 challenge.
    public function shouldRejectGetCapabilitiesWithInvalidCredentials(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, 'WrongPassword1');
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetCapabilities&VERSION=1.1.0');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
        $I->deleteHeader('Authorization'); // don't leak creds into later anonymous tests
    }

    public function shouldServeGetCapabilitiesWithValidCredentials(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetCapabilities&VERSION=1.1.0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsString('WFS_Capabilities', $I->grabResponse());
        $I->deleteHeader('Authorization'); // don't leak creds into later anonymous tests
    }

    public function shouldServeDescribeFeatureTypeAnonymously(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=DescribeFeatureType&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('poi', $body);
        $I->assertStringContainsString('the_geom', $body);
    }

    public function shouldServeGetFeatureAnonymously(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('FeatureCollection', $body);
        $I->assertStringContainsString('alpha', $body);
    }

    public function shouldServeGetFeatureAnonymouslyUsingPost(ApiTester $I)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<wfs:GetFeature service="WFS" version="1.1.0" xmlns:wfs="http://www.opengis.net/wfs" xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml">
    <wfs:Query typeName="poi"/>
</wfs:GetFeature>';
        $I->haveHttpHeader('Content-Type', 'application/xml');
        $I->sendPOST($this->endpoint(), $xml);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('FeatureCollection', $body);
        $I->assertStringContainsString('alpha', $body);
    }

    // Layers default to authentication 'Write': anonymous reads are fine, but a
    // WFS-T Transaction must trigger a Basic auth challenge.
    public function shouldRejectAnonymousTransactionOnWriteLayer(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/xml');
        $I->sendPOST($this->endpoint(), $this->transactionXml('anon_insert'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
    }

    public function shouldAcceptTransactionWithBasicAuth(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->haveHttpHeader('Content-Type', 'application/xml');
        $I->sendPOST($this->endpoint(), $this->transactionXml('basic_insert'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('TransactionResponse', $body);
        $I->assertStringNotContainsStringIgnoringCase('ExceptionReport', $body);
    }

    public function shouldSeeInsertedFeatureAnonymously(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringContainsString('basic_insert', $I->grabResponse());
    }

    public function shouldRejectWrongBasicCredentialsForTransaction(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, 'WrongPassword1');
        $I->haveHttpHeader('Content-Type', 'application/xml');
        $I->sendPOST($this->endpoint(), $this->transactionXml('bad_insert'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function shouldChangeLayerToReadWrite(ApiTester $I)
    {
        // The SQL API denies writes to system relations (settings.*), so use the
        // legacy session-authenticated layer-records endpoint, same as
        // OwsApiCest/DatabaseManagementCest.
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.poi.the_geom', json_encode([
            'data' => [
                'authentication' => 'Read/write',
                '_key_' => $this->schemaName . '.poi.the_geom',
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldRejectAnonymousGetFeatureOnReadWriteLayer(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeHttpHeader('WWW-Authenticate');
    }

    public function shouldServeGetFeatureOnReadWriteLayerWithBasicAuth(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('FeatureCollection', $body);
        $I->assertStringContainsString('alpha', $body);
        $I->deleteHeader('Authorization');
    }

    // WFS uses GET/POST only. The #[AcceptableMethods] attribute makes Route2
    // reject other verbs with 406 before dispatching to the stub handlers.
    public function shouldRejectDeleteMethod(ApiTester $I)
    {
        $I->sendDELETE($this->endpoint());
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }

    public function shouldRejectPutMethod(ApiTester $I)
    {
        $I->sendPUT($this->endpoint(), '');
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }

    // A browser CORS preflight (OPTIONS + Access-Control-Request-Method) must be
    // answered with 204 and an Access-Control-Allow-Methods header so cross-origin
    // POST WFS requests are permitted.
    public function shouldAnswerCorsPreflight(ApiTester $I)
    {
        $I->haveHttpHeader('Access-Control-Request-Method', 'POST');
        $I->sendOPTIONS($this->endpoint());
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->seeHttpHeader('Access-Control-Allow-Methods');
        $I->deleteHeader('Access-Control-Request-Method');
    }
}
