<?php

use Codeception\Util\HttpCode;

/**
 * Exercises the v4 Keyvalue API (app/api/v4/controllers/Keyvalue.php) and its
 * owner/public access model on settings.key_value:
 *   - a sub-user may READ its own keys and any public key, and MODIFY only its own,
 *   - a super-user has full CRUD over every key,
 *   - a legacy key created without owner/public (e.g. via the v2 API) is treated as
 *     owned by the super-user and public (readable by everyone, writable only by super).
 *
 * The owner stored on write is always taken from the JWT (never trusted from the body).
 */
class KeyvalueV4ApiCest
{
    private $date;
    private $password;
    private $userId;        // super-user screen name (== database)
    private $token;         // super-user access token
    private $subUserId;     // sub-user screen name
    private $subToken;      // sub-user access token

    private $superPrivate;
    private $superPublic;
    private $subPrivate;
    private $subPublic;
    private $legacyKey;
    private $projKey;

    public function __construct()
    {
        $this->date = new DateTime();
        $ts = $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->superPrivate = 'super_private_' . $ts;
        $this->superPublic = 'super_public_' . $ts;
        $this->subPrivate = 'sub_private_' . $ts;
        $this->subPublic = 'sub_public_' . $ts;
        $this->legacyKey = 'legacy_' . $ts;
        $this->projKey = 'proj_' . $ts;
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function asSub(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
    }

    public function shouldPrepareUsersAndTokens(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();

        // Super user
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'kv super ' . $ts,
            'email' => 'kvsuper' . $ts . '@example.com',
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Sub user (created through the super user's session)
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'kv sub ' . $ts,
            'email' => 'kvsub' . $ts . '@example.com',
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    // ---- create ----------------------------------------------------------

    public function superCanCreatePrivateAndPublicKeys(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/keyvalue/' . $this->superPrivate, json_encode(['value' => ['a' => 1], 'public' => false]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->assertStringContainsString('/api/v4/keyvalue/' . $this->superPrivate, $I->grabHttpHeader('Location'));

        $I->sendPOST('/api/v4/keyvalue/' . $this->superPublic, json_encode(['value' => ['a' => 2], 'public' => true]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function subCanCreateOwnPrivateAndPublicKeys(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendPOST('/api/v4/keyvalue/' . $this->subPrivate, json_encode(['value' => ['b' => 1], 'public' => false]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendPOST('/api/v4/keyvalue/' . $this->subPublic, json_encode(['value' => ['b' => 2], 'public' => true]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // ---- read ------------------------------------------------------------

    public function subCanReadOwnKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->subPrivate);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['key' => $this->subPrivate, 'value' => ['b' => 1], 'public' => false]);
    }

    public function subCanReadSuperPublicKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->superPublic);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['key' => $this->superPublic, 'public' => true]);
    }

    public function subCannotReadSuperPrivateKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->superPrivate);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function subListSeesOwnAndPublicButNotOthersPrivate(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains($this->subPrivate);
        $I->seeResponseContains($this->subPublic);
        $I->seeResponseContains($this->superPublic);
        $I->dontSeeResponseContains($this->superPrivate);
    }

    public function superListSeesEverything(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains($this->subPrivate);
        $I->seeResponseContains($this->subPublic);
        $I->seeResponseContains($this->superPrivate);
        $I->seeResponseContains($this->superPublic);
    }

    // ---- update ----------------------------------------------------------

    public function subCanUpdateOwnKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/keyvalue/' . $this->subPrivate, json_encode(['value' => ['b' => 99]]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/keyvalue/' . $this->subPrivate);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['value' => ['b' => 99]]);
    }

    public function subCanPublishOwnKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/keyvalue/' . $this->subPrivate, json_encode(['public' => true]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/keyvalue/' . $this->subPrivate);
        $I->seeResponseContainsJson(['public' => true]);
    }

    public function subCannotUpdateSuperKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendPATCH('/api/v4/keyvalue/' . $this->superPublic, json_encode(['value' => ['hacked' => true]]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function subCannotDeleteSuperKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendDELETE('/api/v4/keyvalue/' . $this->superPrivate);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function superCanUpdateSubKey(ApiTester $I)
    {
        $this->asSuper($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/keyvalue/' . $this->subPublic, json_encode(['value' => ['owned_by_super' => true]]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/keyvalue/' . $this->subPublic);
        $I->seeResponseContainsJson(['value' => ['owned_by_super' => true]]);
    }

    public function superCanDeleteAnyKey(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/keyvalue/' . $this->subPublic);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->subPublic);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    // ---- legacy rows (no owner/public) -----------------------------------

    public function legacyKeyIsReadableByEveryoneButOwnedBySuper(ApiTester $I)
    {
        // Create a legacy row through the v2 API: it stores key+value only, so
        // owner ends up NULL — which the model must treat as super-owned & public.
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/keyvalue/' . $this->userId . '/' . $this->legacyKey, json_encode(['legacy' => 1]));
        $I->seeResponseCodeIs(HttpCode::OK);

        // Sub-user can READ it (treated as public)...
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->legacyKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains($this->legacyKey);

        // ...but cannot MODIFY it (treated as super-owned).
        $I->sendPATCH('/api/v4/keyvalue/' . $this->legacyKey, json_encode(['value' => ['legacy' => 2]]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Super-user has full control over the legacy row.
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->legacyKey);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendDELETE('/api/v4/keyvalue/' . $this->legacyKey);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    // ---- paths (JSON projection) -----------------------------------------

    public function shouldCreateProjectionKey(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/keyvalue/' . $this->projKey, json_encode([
            'value' => ['user' => ['name' => 'Alice', 'age' => 30], 'active' => true],
            'public' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // A single ?paths projects one JSON sub-tree, keyed by the path string.
    public function shouldProjectSinglePath(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=user.name');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['key' => $this->projKey, 'value' => ['user.name' => 'Alice']]);
    }

    // Multiple paths (semicolon-separated) each project their own sub-tree.
    public function shouldProjectMultiplePaths(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=user.name,active');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['value' => ['user.name' => 'Alice', 'active' => true]]);
    }

    // A path may point at a whole object, not just a leaf.
    public function shouldProjectWholeObjectPath(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=user');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['value' => ['user' => ['name' => 'Alice', 'age' => 30]]]);
    }

    // A public projected key is reachable by a sub-user too.
    public function shouldProjectPathForSubUserOnPublicKey(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=active');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['value' => ['active' => true]]);
    }

    // Injection safety: a quote in a path segment must be bound, not interpolated.
    // It simply matches no JSON key (null) instead of producing a SQL error.
    public function shouldHandleQuoteInPathSafely(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=na%27me');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    // An empty path or empty segment is rejected rather than silently ignored.
    public function shouldRejectEmptyPathSegment(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/keyvalue/' . $this->projKey . '?paths=user..name');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_PATHS']);
    }

    // ---- validation ------------------------------------------------------

    public function shouldRejectPostWithoutValue(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/keyvalue/no_value_key_' . $this->date->getTimestamp(), json_encode(['public' => true]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
    }
}
