<?php

use Codeception\Util\HttpCode;

/**
 * Guards the OAuth device flow: POST /api/v4/oauth/device, the user approving
 * the code on /device while signed in, and the device polling /api/v4/oauth.
 *
 * Regression guard — the /device route dispatch does not start a session
 * itself. Without one the page never saw the user as signed in (so it sent
 * them back to sign-in for ever), and approving a code stored an empty session
 * against it, which used the code up and left the device polling "No device
 * matches the code".
 */
class DeviceFlowApiCest
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
            'name' => 'device_' . $ts, 'email' => 'device' . $ts . '@example.com', 'password' => $this->password,
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

    private function startDeviceFlow(ApiTester $I): array
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth/device', json_encode(['client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $codes = json_decode($I->grabResponse(), true);
        $I->assertNotEmpty($codes['device_code']);
        $I->assertNotEmpty($codes['user_code']);
        return $codes;
    }

    private function poll(ApiTester $I, string $deviceCode): array
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'device_code', 'device_code' => $deviceCode, 'client_id' => 'gc2-cli',
        ]));
        return json_decode($I->grabResponse(), true);
    }

    private function approve(ApiTester $I, string $userCode, ?string $cookie): void
    {
        if ($cookie) {
            $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        }
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/device', ['user-code' => $userCode]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    // A visitor who is not signed in is sent to the sign-in form.
    public function shouldShowSigninWhenNotSignedIn(ApiTester $I)
    {
        $I->sendGET('/device');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains("hx-post='/signin'");
    }

    // A signed-in user gets the code form instead of the sign-in form.
    public function shouldShowCodeFormWhenSignedIn(ApiTester $I)
    {
        $cookie = $this->startSession($I);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendGET('/device');
        $I->deleteHeader('Cookie');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains("hx-post='/device'");
    }

    // The device gets a token for the user who approved the code.
    public function shouldIssueTokenAfterApproval(ApiTester $I)
    {
        $codes = $this->startDeviceFlow($I);
        $I->assertSame('authorization_pending', $this->poll($I, $codes['device_code'])['error'] ?? null);

        $this->approve($I, $codes['user_code'], $this->startSession($I));

        $token = $this->poll($I, $codes['device_code']);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->assertNotEmpty($token['access_token'] ?? null, 'the device should get an access token');

        $I->haveHttpHeader('Authorization', 'Bearer ' . $token['access_token']);
        $I->sendGET('/api/v4/users/' . $this->userId);
        $I->deleteHeader('Authorization');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    // The device code works once.
    public function shouldNotIssueTokenTwice(ApiTester $I)
    {
        $codes = $this->startDeviceFlow($I);
        $this->approve($I, $codes['user_code'], $this->startSession($I));
        $I->assertNotEmpty($this->poll($I, $codes['device_code'])['access_token'] ?? null);
        $I->assertSame('invalid_request', $this->poll($I, $codes['device_code'])['error'] ?? null);
    }

    // Submitting a code without being signed in must not use the code up.
    public function shouldNotApproveWhenNotSignedIn(ApiTester $I)
    {
        $codes = $this->startDeviceFlow($I);
        $this->approve($I, $codes['user_code'], null);
        $I->assertSame('authorization_pending', $this->poll($I, $codes['device_code'])['error'] ?? null);

        // The code is still good for a user who is signed in.
        $this->approve($I, $codes['user_code'], $this->startSession($I));
        $I->assertNotEmpty($this->poll($I, $codes['device_code'])['access_token'] ?? null);
    }

    // The device code is the secret the device polls with, so it must not be guessable.
    public function shouldIssueUnpredictableDeviceCodes(ApiTester $I)
    {
        $a = $this->startDeviceFlow($I)['device_code'];
        $b = $this->startDeviceFlow($I)['device_code'];
        $I->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a, '256 bits of randomness, hex encoded');
        $I->assertNotSame($a, $b);
        // Two codes made back to back must not share a prefix the way time-based ids do.
        $I->assertNotSame(substr($a, 0, 8), substr($b, 0, 8));
    }

    // Once the token is issued nothing about the user is left behind under the user code.
    public function shouldForgetUserCodeAfterRedemption(ApiTester $I)
    {
        $codes = $this->startDeviceFlow($I);
        $cookie = $this->startSession($I);
        $this->approve($I, $codes['user_code'], $cookie);
        $I->assertNotEmpty($this->poll($I, $codes['device_code'])['access_token'] ?? null);

        $this->approve($I, $codes['user_code'], $cookie);
        $I->seeResponseContains('Code doesn');
        $I->dontSeeResponseContains('already used');
    }

    // The sign-in form on /device is told it serves the device flow, so the
    // social login buttons can be shown and can lead back to /device.
    public function shouldTellSigninItIsTheDeviceFlow(ApiTester $I)
    {
        $I->sendGET('/device');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('&quot;action&quot;:&quot;device&quot;');
        $I->seeResponseContains('&quot;social_signup&quot;:');
    }

    // The social login links on the sign-in form carry the device action.
    public function shouldRenderSocialLinksForDevice(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/signin', ['action' => 'device', 'social_signup' => 'true']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('/github/start?');
        $I->seeResponseContains('action=device');
    }
}
