<?php 

class ConfigurationManagementCest
{
    private $date;
    private $userName;
    private $userEmail;
    private $userAuthCookie;
    private $userId;

    private $publishedConfigurationId;
    private $nonPublishedConfigurationId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Configuration test super user name ' . $this->date->getTimestamp();
        $this->userEmail = 'configurationtest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName = 'Configuration test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'configurationsubtest' . $this->date->getTimestamp() . '@example.com';
    }

    public function shouldPrepareForTest(\ApiTester $I) {
        // Create a super and sub user
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie = $sessionCookie;
    }

    public function shouldNotCreateConfigurationForGuest(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/api/v2/configuration/' . $this->userId, json_encode([
            "name" => 'Test configuration',
            "published" => true,
            "description" => 'Configuration description',
            "body" => []
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false
        ]);
    }

    public function shouldCreateConfigurationForUser(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/api/v2/configuration/' . $this->userId, json_encode([
            "name" => 'Test configuration 1',
            "published" => true,
            "description" => 'Configuration description',
            "body" => '{"a": 1, "b": 2}'
        ]));

        $response = json_decode($I->grabResponse(), true);
        $this->publishedConfigurationId = $response['data']['key'];

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true
        ]);

        $I->sendPOST('/api/v2/configuration/' . $this->userId, json_encode([
            "name" => 'Test configuration 2',
            "published" => false,
            "description" => 'Configuration description',
            "body" => '{"a": 1, "b": 2}'
        ]));

        $response = json_decode($I->grabResponse(), true);
        $this->nonPublishedConfigurationId = $response['data']['key'];
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
    }

    public function shouldUpdateConfigurationForUser(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId, json_encode([
            "name" => "Test configuration 1",
            "published" => true,
            "description" => 'Configuration description changed',
            "body" => '{"a": 1, "b": 2}'
        ]));

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals($this->publishedConfigurationId, $response['data']['key']);
    }

    public function shouldGetNoKeyValueItemsExceptConfigurations(\ApiTester $I) {
        // Create non-configuration key-value item
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/api/v2/keyvalue/' . $this->userId . '/somekey', json_encode([
            "abc" => 123
        ]));

        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId);

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals(1, sizeof($response['data']));
    }


    public function shouldGetPublishedConfigurationsForGuest(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId);

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals(1, sizeof($response['data']));
    }

    public function shouldGetAllConfigurationsForUser(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId);

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals(2, sizeof($response['data']));
    }

    public function shouldGetSpecificPublishedConfigurationForGuest(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId);

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals(1, $response['data']['id']);
    }

    public function shouldNotGetSpecificPublishedConfigurationForGuest(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->nonPublishedConfigurationId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => false]);
    }

    public function shouldGetSpecificNonPublishedConfigurationForUser(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->nonPublishedConfigurationId);

        $response = json_decode($I->grabResponse(), true);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
        $I->assertEquals(3, sizeof($response['data']));
    }

    public function shouldGetSpecificConfigurationAsJSONFile(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId . '.json');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['a' => 1, 'b' => 2]);
    }

    public function shouldNotGetNotPublishedConfigurationOnlyForGuest(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->nonPublishedConfigurationId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => false]);
    }

    public function shouldGetNotPublishedConfigurationOnlyForOwner(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->nonPublishedConfigurationId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
    }

    public function shouldDeleteSpecificConfiguration(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendDELETE('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => true]);
    }

    public function shouldNotGetNonExistingConfiguration(\ApiTester $I) {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId . 'abc123');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => false]);

        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->publishedConfigurationId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success' => false]);
    }
}
