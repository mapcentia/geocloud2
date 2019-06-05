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

    private $userName1;
    private $userEmail1;
    private $userAuthCookie1;
    private $subUserAuthCookie1;
    private $subUserName1;
    private $subUserEmail1;
    private $userId1;
    private $subUserId1;

    public function __construct()
    {
        $this->date = new DateTime();

        $this->userName = 'Database test super user name ' . $this->date->getTimestamp();
        $this->userEmail = 'databasetest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName = 'Database test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'databasesubtest' . $this->date->getTimestamp() . '@example.com';

        $this->userName1 = 'Another database test super user name ' . $this->date->getTimestamp();
        $this->userEmail1 = 'anotherdatabasetest' . $this->date->getTimestamp() . '@example.com';
        $this->subUserName1 = 'Database test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail1 = 'anotherdatabasesubtest' . $this->date->getTimestamp() . '@example.com';
    }

    public function shouldPrepareForTestFirstUser(\ApiTester $I) {
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


    public function shouldPrepareForTestSecondUser(\ApiTester $I) {
        // Create another super and subuser
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->userName1,
            'email' => $this->userEmail1,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId1 = $response->data->screenname;

        $I->sendPOST('session/start', json_encode([
            'user' => $this->userId1,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie1 = $sessionCookie;

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie1);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->subUserName1,
            'email' => $this->subUserEmail1,
            'password' => 'A1abcabcabc',
            'subuser' => true,
            'createschema' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->subUserId1 = $response->data->screenname;

        $I->sendPOST('session/start', json_encode([
            'user' => $this->subUserId1,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie1 = $sessionCookie;
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

    public function shouldListDatabasesForSubUserUsingNameOnly(\ApiTester $I) {
        //$I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('database/search?userName=' . urlencode($this->subUserName));

        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->assertEquals(2, sizeof($response->databases));
    }
}
