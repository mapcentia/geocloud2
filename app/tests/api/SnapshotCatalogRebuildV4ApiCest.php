<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the v4 snapshot catalog rebuild API
 * (app/api/v4/controllers/SnapshotCatalogRebuild.php). Queues a rebuild and
 * reads its status; never waits for the worker. Ordered/stateful: the prepare
 * test provisions the users.
 */
class SnapshotCatalogRebuildV4ApiCest
{
    private const string BASE = '/api/v4/snapshots/catalog/rebuilds';

    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $rebuildId;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUsers(ApiTester $I)
    {
        $ts = (new DateTime())->getTimestamp();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'catalog rebuild super ' . $ts,
            'email' => 'catrebuildsuper' . $ts . '@example.com',
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

        // Sub user via the super user's session
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'catalog rebuild sub ' . $ts,
            'email' => 'catrebuildsub' . $ts . '@example.com',
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldListNoRebuildsAtFirst(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()));
    }

    public function shouldQueueRebuildAndReturn202(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST(self::BASE);
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'pending']);
        $body = json_decode($I->grabResponse());
        $this->rebuildId = $body->id;
        $I->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->rebuildId);
        $I->assertEquals(self::BASE . '/' . $this->rebuildId, $body->_links->self);
    }

    public function shouldRefuseSecondRebuildWhileOneIsActive(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST(self::BASE);
        // The cron worker may have finished the first one in between; then a
        // new one is rightly accepted.
        $body = json_decode($I->grabResponse());
        if (($body->errorCode ?? null) === 'CATALOG_REBUILD_IN_PROGRESS') {
            $I->seeResponseCodeIs(HttpCode::CONFLICT);
        } else {
            $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        }
    }

    public function shouldReadRebuildById(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET(self::BASE . '/' . $this->rebuildId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['id' => $this->rebuildId, 'username' => $this->userId]);
        $body = json_decode($I->grabResponse());
        $I->assertIsObject($body, 'a single id returns one object');
        $I->assertContains($body->status, ['pending', 'running', 'succeeded', 'failed']);
        foreach (['documents', 'failed_documents', 'error', 'created', 'started', 'finished'] as $field) {
            $I->assertTrue(property_exists($body, $field), "has $field");
        }
    }

    public function shouldReadSeveralByCommaSeparatedIdsAndList(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET(self::BASE . '/' . $this->rebuildId . ',' . $this->rebuildId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $both = json_decode($I->grabResponse());
        $I->assertIsArray($both);
        $I->assertCount(2, $both);

        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->rebuildId]]);
    }

    public function shouldReturn404ForUnknownId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET(self::BASE . '/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'CATALOG_REBUILD_NOT_FOUND']);

        $I->sendGET(self::BASE . '/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldNotShadowTheSnapshotJobRoute(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()), 'the job list is still the job list');
    }

    public function shouldRejectPostWithResourceId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST(self::BASE . '/' . $this->rebuildId);
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }

    public function shouldRejectUnsupportedMethods(ApiTester $I)
    {
        $this->asSuper($I);
        // Refused by #[AcceptableMethods] before the controller is reached.
        $I->sendDELETE(self::BASE . '/' . $this->rebuildId);
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->seeResponseContainsJson(['errorCode' => 'NOT_ACCEPTABLE']);
    }

    public function shouldRejectSubUser(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendPOST(self::BASE);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
    }
}
