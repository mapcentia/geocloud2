<?php

use Codeception\Util\HttpCode;

/**
 * Tests app/api/v4/controllers/Meta.php — specifically that the per-column
 * Elasticsearch/OpenSearch mapping config (the `elasticsearch` column of
 * settings.geometry_columns_join) is exposed and editable through the v4
 * Meta API, so it can be configured there and consumed by the Search API's
 * index rebuild.
 */
class MetaV4ApiCest
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
        $ts = $this->date->getTimestamp();
        $this->userName = 'Meta v4 test user ' . $ts;
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'metav4test' . $ts . '@example.com';
        $this->schemaName = 'meta_v4_test_' . $ts;
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
    }

    /**
     * PATCH the per-column elasticsearch mapping via Meta, then GET it back.
     */
    public function shouldRoundTripElasticsearchMapping(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $rel = $this->schemaName . '.poi';

        $es = [
            'name' => [
                'column' => 'name',
                'id' => 'name',
                'elasticsearchtype' => 'text',
                'analyzer' => 'auto_complete_analyzer',
                'search_analyzer' => 'auto_complete_search_analyzer',
            ],
            'gid' => [
                'column' => 'gid',
                'id' => 'gid',
                'elasticsearchtype' => 'integer',
            ],
        ];

        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/meta', json_encode(['relations' => [$rel => ['elasticsearch' => $es]]]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/meta/' . $rel);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['relations' => [$rel => ['elasticsearch' => [
            'name' => ['analyzer' => 'auto_complete_analyzer', 'elasticsearchtype' => 'text'],
            'gid' => ['elasticsearchtype' => 'integer'],
        ]]]]);
    }
}
