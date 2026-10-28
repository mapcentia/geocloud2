<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v2/Feature.php — the v2 GeoJSON feature API Vidi's editor uses.
 * It drives the in-process WFS engine with the identity its former internal
 * request to /wfs had: the session for transactions, none for reads, and never
 * the caller's Basic credentials.
 */
class FeatureV2ApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $apiKey;
    private $sessionCookie;
    private $schemaName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Feature v2 test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'featurev2test' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'feature_v2_test_' . $this->date->getTimestamp();
    }

    private function endpoint(): string
    {
        return '/api/v2/feature/' . $this->userId . '/' . $this->schemaName . '.poi.the_geom/4326';
    }

    private function featureCollection(array $properties, ?array $coordinates = [9.5, 55.7]): string
    {
        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => $properties,
                'geometry' => $coordinates === null ? null : ['type' => 'Point', 'coordinates' => $coordinates],
            ]],
        ]);
    }

    /** Rows of poi, read straight from the table. */
    private function rows(ApiTester $I): array
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => 'SELECT gid, name, ST_AsText(the_geom) AS g FROM ' . $this->schemaName . '.poi ORDER BY gid',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Authorization');
        return json_decode($I->grabResponse(), true)['data'];
    }

    private function asApiKey(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('GC2-API-KEY', $this->apiKey);
    }

    private function clearHeaders(ApiTester $I): void
    {
        $I->deleteHeader('GC2-API-KEY');
        $I->deleteHeader('Cookie');
        $I->deleteHeader('Authorization');
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
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->deleteHeader('Authorization');

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->sessionCookie = $I->capturePHPSESSID();
        $this->apiKey = json_decode($I->grabResponse())->data->api_key;
    }

    public function shouldInsertWithApiKey(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->sendPOST($this->endpoint(), $this->featureCollection(['name' => 'alpha']));
        $this->clearHeaders($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertStringContainsString('"wfs:totalInserted":"1"', $I->grabResponse());
        $I->assertSame([['gid' => 1, 'name' => 'alpha', 'g' => 'POINT(9.5 55.7)']], $this->rows($I));
    }

    public function shouldGetFeatureAsGeoJson(ApiTester $I)
    {
        $I->sendGET($this->endpoint() . '/1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame('FeatureCollection', $body['type']);
        $I->assertSame(['gid' => '1', 'name' => 'alpha'], $body['features'][0]['properties']);
        $I->assertSame('Point', $body['features'][0]['geometry']['type']);
    }

    public function shouldUpdateWithApiKey(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->sendPUT($this->endpoint(), $this->featureCollection(['gid' => 1, 'name' => 'alpha2'], [9.6, 55.8]));
        $this->clearHeaders($I);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertStringContainsString('"wfs:totalUpdated":"1"', $I->grabResponse());
        $I->assertSame([['gid' => 1, 'name' => 'alpha2', 'g' => 'POINT(9.6 55.8)']], $this->rows($I));
    }

    // A transaction runs in the caller's own session when it sends one
    public function shouldInsertWithApiKeyAndSessionCookie(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->sessionCookie);
        $I->sendPOST($this->endpoint(), $this->featureCollection(['name' => 'beta'], null));
        $this->clearHeaders($I);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertCount(2, $this->rows($I));
    }

    // The engine never saw the caller's Basic credentials, so wrong ones change nothing
    public function shouldIgnoreBasicCredentials(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->haveHttpHeader('Authorization', 'Basic ' . base64_encode($this->userId . ':WrongPassword1'));
        $I->sendPOST($this->endpoint(), $this->featureCollection(['name' => 'gamma'], null));
        $this->clearHeaders($I);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertCount(3, $this->rows($I));
    }

    public function shouldReportWfsExceptionAsFailure(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->sendPOST($this->endpoint(), $this->featureCollection(['nope' => 'x'], null));
        $this->clearHeaders($I);
        $I->seeResponseCodeIs(HttpCode::INTERNAL_SERVER_ERROR);
        $I->seeResponseContainsJson(['success' => false, 'code' => '500']);
        $I->assertStringContainsString('ows:Exception', $I->grabResponse());
        $I->assertCount(3, $this->rows($I));
    }

    public function shouldAnswer404ForUnknownLayer(ApiTester $I)
    {
        $I->sendGET('/api/v2/feature/' . $this->userId . '/' . $this->schemaName . '.nolayer.the_geom/4326/1');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'code' => 404]);
        $I->assertStringContainsString('Layer does not exist', $I->grabResponse());
    }

    public function shouldRefuseWithoutApiKey(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($this->endpoint(), $this->featureCollection(['name' => 'nokey'], null));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['success' => false]);
    }

    public function shouldDeleteWithApiKey(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->sendDELETE($this->endpoint() . '/2');
        $this->clearHeaders($I);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertStringContainsString('"wfs:totalDeleted":"1"', $I->grabResponse());
        $I->assertSame([1, 3], array_column($this->rows($I), 'gid'));
    }

    // Reads carry no session: a Read/write layer answers them with a 401,
    // without ending the request on a Basic auth challenge
    public function shouldRefuseReadOnReadWriteLayer(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->sessionCookie);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.poi.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->schemaName . '.poi.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET($this->endpoint() . '/1');
        $this->clearHeaders($I);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeResponseContainsJson([
            'success' => false, 'message' => 'Attempt to login using Basic Auth was cancelled', 'code' => 401,
        ]);
    }

    public function shouldStillWriteOnReadWriteLayerWithApiKey(ApiTester $I)
    {
        $this->asApiKey($I);
        $I->sendPOST($this->endpoint(), $this->featureCollection(['name' => 'delta'], null));
        $this->clearHeaders($I);
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertCount(3, $this->rows($I));
    }
}
