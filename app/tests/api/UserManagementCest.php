<?php 

class UserManagementCest
{
    private $date;

    private $userId;
    private $userAuthCookie;
    private $userName;
    private $userEmail;

    private $secondUserId;
    private $secondUserAuthCookie;
    private $secondUserName;
    private $secondUserEmail;

    private $subUserId;
    private $subUserAuthCookie;
    private $subUserName;
    private $subUserEmail;

    public function __construct()
    {
        $this->date = new DateTime();
        
        $this->userName = 'Test super user name ' . $this->date->getTimestamp();
        $this->userEmail = 'supertest' . $this->date->getTimestamp() . '@example.com';

        $this->secondUserName = 'Test super user 2 name ' . $this->date->getTimestamp();
        $this->secondUserEmail = 'supertest2' . $this->date->getTimestamp() . '@example.com';

        $this->subUserName = 'Test sub user name ' . $this->date->getTimestamp();
        $this->subUserEmail = 'subtest' . $this->date->getTimestamp() . '@example.com';
    }

    public function shouldCreateSuperUserWitnNonASCIICharactersInNameAndAllowAuthorizeWithName(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->userName . ' symbols ø § are non ascii',
            'email' => 'symbols_' . $this->userEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was created'
        ]);

        $response = json_decode($I->grabResponse(), true);
        $I->assertContains('_symbols_are_non_ascii', $response['data']['screenname']);

        $I->sendPOST('session/start', json_encode([
            'user' => $this->userName . ' symbols ø § are non ascii',
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started',
            'data' => [
                'subuser' => false,
                'passwordExpired' => false
            ]
        ]);
    }

    public function shouldCreateSuperUser(\ApiTester $I)
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
            'data' => [
                'subuser' => false,
                'passwordExpired' => false
            ]
        ]);
    }

    public function shouldCreateSubUser(\ApiTester $I)
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

    public function shouldLetSubUserAuthorize(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => $this->subUserId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie = $sessionCookie;

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started',
            'data' => [
                'subuser' => true,
                'passwordExpired' => false
            ]
        ]);
    }

    public function shouldCreateSubuserForDifferentSuperuserWithExistingName(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->secondUserName,
            'email' => $this->secondUserEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->secondUserId = $response->data->screenname;

        $I->sendPOST('session/start', json_encode([
            'user' => $this->secondUserId,
            'password' => 'A1abcabcabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->secondUserAuthCookie = $sessionCookie;

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->secondUserAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('user', json_encode([
            'name' => $this->subUserName,
            'email' => 'another_' . $this->subUserEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was created',
            'data' => [
                'parentdb' => $this->secondUserId
            ]
        ]);
    }

    public function shouldNotAllowSubUserWithConflictingNameAuthorizeWithNameOnly(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => $this->subUserName,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
            'message' => 'Session not started',
        ]);
    }

    public function shouldAllowSubUserWithConflictingNameAuthorizeWithNameAndEmail(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => 'another_' . $this->subUserEmail,
            'password' => 'A1abcabcabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started',
            'data' => [
                'parentdb' => $this->secondUserId,
                'subuser' => true
            ]
        ]);
    }

    public function shouldListSubUsersOfSuperUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('user/' . $this->userId . '/subusers');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                [
                    'screenName' => $this->subUserId,
                    'email' => $this->subUserEmail,
                    'zone' => null,
                    'parentdb' => $this->userId
                ]
            ]
        ]);
    }

    public function shouldNotListSubUsersOfSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('user/' . $this->subUserId . '/subusers');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
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

    public function superUserShouldGetInformationAboutHimself(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendGET('user/' . $this->userId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                'email' => $this->userEmail,
                'parentdb' => null
            ]
        ]);
    }

    public function subUserShouldGetInformationAboutHimself(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->sendGET('user/' . $this->subUserId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                'email' => $this->subUserEmail,
                'parentdb' => $this->userId
            ]
        ]);
    }

    public function superUserShouldGetInformationAboutHisSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendGET('user/' . $this->subUserId);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                'email' => $this->subUserEmail,
                'parentdb' => $this->userId
            ]
        ]);
    }

    public function userShouldUpdateHimself(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPUT('user/' . $this->userId, json_encode([
            'currentPassword' => 'A1abcabcabc',
            'password' => 'AB123oooooabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was updated'
        ]);
    }

    public function shouldLetUserAuthorizeAfterPasswordChange(\ApiTester $I)
    {
        $I->sendPOST('session/start', json_encode([
            'user' => $this->userId,
            'password' => 'AB123oooooabc',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Session started',
            'data' => [
                'subuser' => false,
                'passwordExpired' => false
            ]
        ]);
    }

    public function userShouldUpdateHisPasswordOnlyIfCurrentOneIsProvided(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPUT('user/' . $this->userId, json_encode([
            'password' => 'AB123oooooabc',
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
            'errorCode' => 'EMPTY_CURRENT_PASSWORD'
        ]);
    }

    public function userShouldUpdateHisSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPUT('user/' . $this->subUserId, json_encode([
            'password' => 'AB123oooooabc',
            'email' => 'newsubuseremail' . $this->date->getTimestamp() . '@test.com'
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was updated'
        ]);
    }

    public function superUserShouldDeleteHisSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendDELETE('user/' . $this->subUserId);

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was deleted'
        ]);
    }

    public function shouldReturn404OnDeletedSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendGET('user/' . $this->subUserId);

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false
        ]);
    }

    public function superUserShouldDeleteHimself(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendDELETE('user/' . $this->userId);

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'User was deleted'
        ]);
    }
}
