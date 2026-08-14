<?php

use Codeception\Util\HttpCode;

/**
 * HTTP Basic auth for WFS/OWS now checks the primary auth system (the user's login
 * password in the users table) first, then falls back to the legacy per-database
 * "viewer" password in settings.viewer. This suite proves the primary password
 * works with no viewer password set, that a viewer password still works as a
 * fallback, and that a wrong password is rejected.
 */
class BasicAuthPrimaryApiCest
{
    private $date;
    private $login = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->schemaName = 'ba_primary_' . $this->date->getTimestamp();
    }

    private function getMap(ApiTester $I, string $user, string $password): string
    {
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=32&HEIGHT=32&FORMAT=image/png&STYLES=';
        $I->amHttpAuthenticated($user, $password);
        $I->sendGET('/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::OK);
        return strtolower($I->grabHttpHeader('Content-Type'));
    }

    // Owner + Read/write layer, but NO viewer password is ever set.
    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'ba_owner_' . $ts, 'email' => 'baowner' . $ts . '@example.com', 'password' => $this->login,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->login,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'roads',
            'columns' => [['name' => 'the_geom', 'type' => 'geometry(LineString,4326)']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->schemaName . '.roads.the_geom',
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->deleteHeader('Authorization');

        // Protect the layer (Read/write) so reads require Basic auth.
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->login, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.roads.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->schemaName . '.roads.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    // The primary login password authenticates even though no viewer password exists.
    public function shouldAuthenticateWithPrimaryLoginPassword(ApiTester $I)
    {
        $I->assertStringContainsString('image/png', $this->getMap($I, $this->userId, $this->login));
    }

    public function shouldRejectWrongPassword(ApiTester $I)
    {
        $I->amHttpAuthenticated($this->userId, 'definitely-wrong');
        $qs = 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->schemaName . '.roads'
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=32&HEIGHT=32&FORMAT=image/png&STYLES=';
        $I->sendGET('/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId . '?' . $qs);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    // After a viewer password is set it still works as a fallback, and the primary
    // login password keeps working alongside it.
    public function shouldFallBackToViewerPassword(ApiTester $I)
    {
        $viewer = 'ViewerPw123';
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->login, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $viewer);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');

        $I->assertStringContainsString('image/png', $this->getMap($I, $this->userId, $viewer));  // viewer fallback
        $I->assertStringContainsString('image/png', $this->getMap($I, $this->userId, $this->login)); // primary still works
    }
}
