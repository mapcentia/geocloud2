<?php

use Codeception\Util\HttpCode;

/**
 * Tests the v4 Users API `user_group` contract: POST/PATCH accept a JSON array of group
 * names (with back-compat for a JSON-array string and null-to-clear), and GET returns the
 * membership as a decoded array (or null). Regression guard for the old double-encoded
 * behaviour where a real JSON array produced a 500 (invalid JSON for the JSONB column).
 */
class UserGroupApiCest
{
    private $date;
    private $password;
    private $userId;
    private $token;
    private $sub; // sub-user screen name

    public function __construct()
    {
        $this->date = new DateTime();
        $this->password = 'A1abcabcabc';
    }

    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function getUserGroup(ApiTester $I): mixed
    {
        $this->auth($I);
        $I->sendGET('/api/v4/users/' . $this->sub);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        return array_key_exists('user_group', $data) ? $data['user_group'] : '__missing__';
    }

    public function shouldPrepareOwnerAndToken(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'ug_owner_' . $ts, 'email' => 'ugowner' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;
        $this->sub = 'ug_sub_' . $ts;
    }

    // POST create with a real JSON array (previously a 500) and GET returns the array.
    public function shouldCreateSubUserWithArrayGroups(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/users', json_encode([
            'name' => $this->sub, 'email' => $this->sub . '@example.com', 'password' => $this->password,
            'user_group' => ['g1', 'g2'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->assertSame(['g1', 'g2'], $this->getUserGroup($I));
    }

    public function shouldPatchWithArrayGroups(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/users/' . $this->sub, json_encode(['user_group' => ['a', 'b']]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['a', 'b'], $this->getUserGroup($I));
    }

    // Back-compat: a JSON-array string is still accepted and stored as an array.
    public function shouldPatchWithJsonStringBackCompat(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/users/' . $this->sub, json_encode(['user_group' => '["c"]']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['c'], $this->getUserGroup($I));
    }

    public function shouldClearGroupsWithNull(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/users/' . $this->sub, json_encode(['user_group' => null]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNull($this->getUserGroup($I));
    }

    // Validation: user_group must be a list of strings; a non-string element is rejected.
    public function shouldRejectNonStringGroupElements(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/users/' . $this->sub, json_encode(['user_group' => ['ok', 123]]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
}
