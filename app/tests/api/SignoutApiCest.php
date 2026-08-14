<?php

use Codeception\Util\HttpCode;

/**
 * Guards the sign-out flow: GET /signout (and /api/v2/session/stop) must actually
 * destroy the server session. Regression guard — the /signout route dispatch does
 * not start a session itself, so a bare session_unset() left the session alive, and
 * a missing redirect_uri crashed redirectResponse(null) with a 500.
 */
class SignoutApiCest
{
    private $date;
    private $password;
    private $userId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->password = 'A1abcabcabc';
    }

    public function shouldPrepareUser(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'signout_' . $ts, 'email' => 'signout' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
    }

    private function startSession(ApiTester $I): string
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($cookie));
        return $cookie;
    }

    private function isSessionActive(ApiTester $I, string $cookie): bool
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendGET('/api/v2/session');
        $I->seeResponseCodeIs(HttpCode::OK);
        $active = json_decode($I->grabResponse(), true)['data']['session'] ?? null;
        $I->deleteHeader('Cookie');
        return $active === true;
    }

    // GET /signout destroys the session (and does not 500 without a redirect_uri).
    public function shouldDestroySessionOnSignout(ApiTester $I)
    {
        $cookie = $this->startSession($I);
        $I->assertTrue($this->isSessionActive($I, $cookie), 'session should be active after start');

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->stopFollowingRedirects();
        $I->sendGET('/signout');
        $I->seeResponseCodeIs(HttpCode::FOUND); // 302, not 500
        $I->startFollowingRedirects();
        $I->deleteHeader('Cookie');

        $I->assertFalse($this->isSessionActive($I, $cookie), 'session must be destroyed after /signout');
    }

    // The legacy /api/v2/session/stop path destroys the session too.
    public function shouldDestroySessionOnSessionStop(ApiTester $I)
    {
        $cookie = $this->startSession($I);
        $I->assertTrue($this->isSessionActive($I, $cookie));

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendGET('/api/v2/session/stop');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');

        $I->assertFalse($this->isSessionActive($I, $cookie));
    }
}
