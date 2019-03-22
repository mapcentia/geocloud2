<?php 

class UserManagementCest
{
    private $userName;
    private $userEmail;
    private $userAuthCookie;
    private $subUserName;
    private $subUserEmail;
    private $userId;
    private $subUserId;

    public function __construct()
    {
        $date = new DateTime();
        $this->userName = 'Test user name ' . $date->getTimestamp();
        $this->userEmail = 'test' . $date->getTimestamp() . '@example.com';
        $this->subUserName = 'Test sub user name ' . $date->getTimestamp();
        $this->subUserEmail = 'subtest' . $date->getTimestamp() . '@example.com';
    }

    public function createSuperUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was created'
        ]);

        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;
    }

    public function shouldLetSuperUserAuthorize(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => $this->userId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie = $sessionCookie;

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started',
            'subuser' => false,
            'passwordExpired' => false
        ]);
    }

    public function createSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->subUserName,
            'email' => $this->subUserEmail,
            'password' => 'A1abcabcabc',
            'subuser' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was created'
        ]);

        $response = json_decode($I->grabResponse());
        $this->subUserId = $response->data->screenname;

        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was created',
            'data' => [
                'parentdb' => $this->userId
            ]
        ]);
    }

    public function shouldNotCreateUserWithSameName(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));
       
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);

        $response = $I->grabResponse();
        $I->assertContains('User identifier', $response);
        $I->assertContains('already exists', $response);
    }

    public function shouldNotCreateUserWithSameEmail(\ApiTester $I)
    {
        $I->sendPOST('user', json_encode([
            'name' => $this->userName . ' random name',
            'email' => $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);

        $response = $I->grabResponse();
        $I->assertContains('Email', $response);
        $I->assertContains('already exists', $response);
    }

    public function shouldNotCreateUserWithWeakPassword(\ApiTester $I)
    {
        $I->sendPOST('user', json_encode([
            'name' => $this->userName . ' another random name',
            'email' => 'random' . $this->userEmail,
            'password' => 'abc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);

        $response = $I->grabResponse();
        $I->assertContains('Password does not meet following requirements', $response);
    }


}
