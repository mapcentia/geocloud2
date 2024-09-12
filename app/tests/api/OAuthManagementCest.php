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
    private $tableName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'OAuth test super user name ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'databasetest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName = 'Database test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'databasesubtest' . $this->date->getTimestamp() . '@example.com';

        $this->schemaName = 'Test schema ' . $this->date->getTimestamp();
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

    public function shouldGetAccessTokenFromPasswordFlow(ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $this->userId,
            'password' => $this->password,
            'database' => $this->userId,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userAccessToken = $response->access_token;
    }

    public function shouldCreateSchema(ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendPOST('/api/v4/schemas', json_encode([
            'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        codecept_debug($location);
        $this->schemaUri = $location;
    }
}