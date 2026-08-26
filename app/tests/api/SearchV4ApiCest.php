<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v4/controllers/SearchSettings.php — the v4 Search Settings API
 * (per-database OpenSearch `analysis` block, owner-only), and later (Tasks 5–6)
 * the rest of the v4 Search API.
 */
class SearchV4ApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;
    private $subUserId;
    private $subUserToken;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Search v4 test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'searchv4test' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'search_v4_test_' . $this->date->getTimestamp();
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

        // A table in the 'public' schema (always readable/enterable per AbstractApi::initiate()'s
        // schema gate, unlike the custom schema above, which only the owner may address) — used
        // solely to get a sub-user request past that gate and into Search::assertOwner().
        $I->sendPOST('/api/v4/schemas/public/tables', json_encode([
            'name' => 'search_v4_sub_test',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // Sub user (created through the owner's session), used to assert 403 NOT_OWNER on build.
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'search v4 sub ' . $ts,
            'email' => 'searchv4sub' . $ts . '@example.com',
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subUserToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldSetAndGetPerDbAnalysis(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $analysis = [
            'analyzer' => ['t' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase']]],
            'filter' => new stdClass(),
        ];
        $I->sendPUT('/api/v4/search/settings', json_encode(['analysis' => $analysis]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('/api/v4/search/settings');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['analysis' => ['analyzer' => ['t' => ['tokenizer' => 'standard']]]]);
    }

    public function shouldRoundTripAnalysisContainingSingleQuote(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $analysis = [
            'analyzer' => ['q' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['my_syn']]],
            'filter' => ['my_syn' => ['type' => 'synonym', 'synonyms' => ["o'clock, oclock"]]],
        ];
        $I->sendPUT('/api/v4/search/settings', json_encode(['analysis' => $analysis]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('/api/v4/search/settings');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['analysis' => ['filter' => ['my_syn' => ['synonyms' => ["o'clock, oclock"]]]]]);
    }

    public function shouldReturn404WhenNoIndex(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search?q=*');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'INDEX_NOT_FOUND']);
    }

    public function shouldBuildSearchAndDropIndex(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');

        // Seed one row directly via SQL (not through the WFS-backed Feature API).
        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => 'INSERT INTO "' . $this->schemaName . '"."poi" (name, the_geom) VALUES (\'Findme\', ST_SetSRID(ST_MakePoint(9.5, 55.7), 4326))',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);

        // Build
        $I->sendPUT('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['index' => $this->userId . '_' . $this->schemaName . '_poi']);

        // OpenSearch is near-real-time; the build refreshes the index before returning, so this should be visible immediately.
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search',
            json_encode(['query' => ['match' => ['properties.name' => 'Findme']]]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        $I->assertGreaterThanOrEqual(1, $data['hits']['total']['value'] ?? 0);

        // Drop
        $I->sendDELETE('/api/v4/schemas/' . $this->schemaName . '/tables/poi/search');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function shouldForbidSubUserBuild(ApiTester $I)
    {
        // Uses the 'public' schema (not $this->schemaName): a sub user is rejected before
        // reaching Search::assertOwner() on a schema it doesn't own (AbstractApi::initiate()'s
        // schema gate — 403 UNAUTHORIZED — see task-6-report.md for the full explanation).
        // 'public' passes that gate for any authenticated user, so this exercises the
        // owner-only check inside the controller itself.
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subUserToken);
        $I->sendPUT('/api/v4/schemas/public/tables/search_v4_sub_test/search');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'NOT_OWNER']);
    }
}
