<?php 

class SignUpCest
{
    public function _before(ApiTester $I)
    {
    }

    // tests
    public function createUserViaAPI(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', ['name' => 'test user name', 'email' => 'test@example.com']);


        $response = $I->grabResponse();
        var_dump($response);

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);

        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'user' => [
                'name' => 'test_user_name',
                'status' => 'inactive'
            ]
        ]);
    }
}
