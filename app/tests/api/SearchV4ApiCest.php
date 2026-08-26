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
}
