<?php 

class DatabaseManagementCest
{
    private $date;
    private $userName;
    private $userEmail;
    private $userAuthCookie;
    private $subUserAuthCookie;
    private $subUserName;
    private $subUserEmail;
    private $userId;
    private $subUserId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Database test super user name ' . $this->date->getTimestamp();
        $this->userEmail = 'databasetest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName = 'Database test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'databasesubtest' . $this->date->getTimestamp() . '@example.com';
    }

    public function shouldPrepareForTest(\ApiTester $I) {
        // Create a super and subuser
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;

        $I->sendPOST('session/start', json_encode([
            'user' => $this->userId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie = $sessionCookie;

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->subUserName,
            'email' => $this->subUserEmail,
            'password' => 'A1abcabcabc',
            'subuser' => true,
            'createschema' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->subUserId = $response->data->screenname;

        $I->sendPOST('session/start', json_encode([
            'user' => $this->subUserId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie = $sessionCookie;
    }

    public function shouldListSchemasForSuperUser(\ApiTester $I) {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('database/schemas');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                ['schema' => $this->subUserId],
                ['schema' => 'public']
            ]
        ]);
    }
}
