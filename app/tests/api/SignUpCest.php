<?php 

class SignUpCest
{
    private $userName;

    public function _before(ApiTester $I)
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
        $this->userId = $response->userId;
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



