<?php 

class UserManagementCest
{
    private $userName;
    private $userEmail;
    private $userId;

    public function __construct()
    {
        $date = new DateTime();
        $this->userName = 'Test user name ' . $date->getTimestamp();
        $this->userEmail = 'test' . $date->getTimestamp() . '@example.com';
    }

    public function createUser(\ApiTester $I)
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

    public function shouldLoginAfterwards(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => $this->userId,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started'
        ]);
    }
}
