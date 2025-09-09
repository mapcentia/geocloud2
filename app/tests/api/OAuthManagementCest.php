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

    public function shouldManageSchema(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
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

        $I->sendGET($this->schemaUri);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContains($this->schemaName);
        $I->seeResponseContains($this->tableName1);
        $I->seeResponseContains($this->tableName2);

        $newName = 'new_schema_name';
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

    public function shouldRunSql(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'q' => 'SELECT 1 as n'
        ]);
        $I->sendPOST('/api/v4/sql', $payload);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function shouldGetStats(ApiTester $I)
    {
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendGET('/api/v4/stats');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    // Tests for app/api/v4/controllers/Call.php
    public function shouldReturnInvalidRequestOnMissingMethodInRpc(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            // 'method' omitted on purpose
        ]);
        $I->sendPOST('/api/v4/call', $payload);
        // JSON-RPC errors are returned with 200 in this app
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
            'id' => '1',
        ]);
    }

    public function shouldReturnMethodNotFoundForUnknownMethod(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'nonExistingPreparedStatementMethodName',
            'params' => ['foo' => 'bar'],
            'id' => '99',
        ]);
        $I->sendPOST('/api/v4/call', $payload);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ],
            'id' => '99',
        ]);
    }

    public function shouldReturnNoContentForEmptyBatch(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([]); // Valid empty batch per our controller logic
        $I->sendPOST('/api/v4/call', $payload);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        // 204 should yield no body and no content-type
    }

    public function shouldManageOAuthClients(ApiTester $I)
    {
        // Create client
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $clientId = 'cli-' . $this->date->getTimestamp();
        $payload = json_encode([
            'id' => $clientId,
            'name' => 'Test Client',
            'homepage' => 'https://example.com',
            'description' => 'A test client',
            'redirect_uri' => ['https://example.com/callback'],
            'public' => true,
            'confirm' => true,
        ]);
        $I->sendPOST('/api/v4/clients', $payload);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->seeResponseIsJson();
        $location = $I->grabHttpHeader('Location');
        // Response can be object or wrapped; we rely on Location for id(s)
        $I->assertStringContainsString('/api/v4/clients/', $location);

        // Get client
        $I->sendGET('/api/v4/clients/' . $clientId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContains($clientId);
        $I->seeResponseContains('Test Client');

        // Update client name
        $updatePayload = json_encode([
            'name' => 'Updated Test Client'
        ]);
        $I->sendPatch('/api/v4/clients/' . $clientId, $updatePayload);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify update
        $I->sendGET('/api/v4/clients/' . $clientId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('Updated Test Client');

        // Delete client
        $I->sendDELETE('/api/v4/clients/' . $clientId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // Verify deletion (expect 404 or empty)
        $I->sendGET('/api/v4/clients/' . $clientId);
        // We don't know exact behavior; assert not 200
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

    }

    // Tests for app/api/v4/controllers/Table.php
    public function shouldCreateTable(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'name' => $this->tableName3,
        ]);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', $payload);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $I->assertStringContainsString('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName3, $location);
    }

    public function shouldGetTable(ApiTester $I)
    {
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName3);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'name' => $this->tableName3,
        ]);
    }

    public function shouldListTablesNamesOnly(ApiTester $I)
    {
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables?namesOnly=1');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'name' => $this->schemaName . '.' . $this->tableName3,
        ]);
    }

    public function shouldPatchRenameTableAndSetComment(ApiTester $I)
    {
        $I->stopFollowingRedirects();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $payload = json_encode([
            'name' => $this->tableName4,
            'comment' => 'Renamed by test',
        ]);
        $I->sendPatch('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName3, $payload);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $location = $I->grabHttpHeader('Location');
        $I->assertStringContainsString('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName4, $location);

        // Verify GET on new name
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName4);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'name' => $this->tableName4,
        ]);
    }

    public function shouldDeleteTable(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
        $I->sendDELETE('/api/v4/schemas/' . $this->schemaName . '/tables/' . $this->tableName4);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }
}
