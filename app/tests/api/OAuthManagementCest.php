<?php

use Codeception\Util\HttpCode;

class OAuthManagementCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $subUserName;
    private $subUserEmail;
    private $userId;
    private $subUserId;
    private $userAccessToken;
    private $subUserAccessToken;

    private $schemaName;
    private $schemaUri;
    private $tableName1;
    private $tableName2;
    private $tableName3;
    private $tableName4;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'OAuth test super user name ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'oauthtest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName = 'Oauth database test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'Oauth databasesubtest' . $this->date->getTimestamp() . '@example.com';

        $this->schemaName = 'test_schema_' . $this->date->getTimestamp();
        $this->tableName1 = 'test_table_1';
        $this->tableName2 = 'test_table_2';
        $this->tableName3 = 'test_table_3';
        $this->tableName4 = 'test_table_4';
    }

    public function shouldPrepareForTestFirstUser(ApiTester $I)
    {
        // Create a super and subuser
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => $this->password,
            'schema' => 'public',
        ]));
    }

    public function shouldGetAccessTokenFromPasswordFlow(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $this->userId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $response = json_decode($I->grabResponse());
        $this->userAccessToken = $response->access_token;
    }

    public function shouldCreateSchema(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'name' => $this->schemaName,
            'tables' => [
                ['name' => $this->tableName1],
                ['name' => $this->tableName2],
            ]
        ]);
        $I->sendPOST('/api/v4/schemas', $payload);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $this->schemaUri = $location;
    }

    public function shouldGetSchema(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendGET($this->schemaUri);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContains($this->schemaName);
        $I->seeResponseContains($this->tableName1);
        $I->seeResponseContains($this->tableName2);
    }
    public function shouldRenameSchema(ApiTester $I) {
        $newName = 'new_schema_name';
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'name' => 'new_schema_name',
        ]);
        $I->sendPatch($this->schemaUri, $payload);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeResponseContains('new_schema_name');
        $I->seeResponseContains($this->tableName1);
        $I->seeResponseContains($this->tableName2);

        $I->stopFollowingRedirects();
        $payload = json_encode([
            'name' => $this->schemaName,
        ]);
        $I->sendPatch('/api/v4/schemas/' . $newName, $payload);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $location = $I->grabHttpHeader('Location');
        $this->schemaUri = $location;
    }
}