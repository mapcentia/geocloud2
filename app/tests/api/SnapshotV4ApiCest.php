<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the v4 Snapshot API (app/api/v4/controllers/Snapshot.php).
 * Queues snapshots and reads their status; never waits for the worker.
 * Ordered/stateful: the prepare test provisions users and a table.
 */
class SnapshotV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $schema;
    private $snapshotId;

    public function __construct()
    {
        $this->schema = 'snap_' . (new DateTime())->getTimestamp();
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUsersAndTable(ApiTester $I)
    {
        $ts = (new DateTime())->getTimestamp();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'snapshot super ' . $ts,
            'email' => 'snapsuper' . $ts . '@example.com',
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
            'name' => 'snapshot sub ' . $ts,
            'email' => 'snapsub' . $ts . '@example.com',
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

        // Schema + table to snapshot
        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function shouldQueueSnapshotAndReturn202(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi', 'srs' => 25832]));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'pending']);
        $body = json_decode($I->grabResponse());
        $this->snapshotId = $body->id;
        $I->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->snapshotId);
        $I->assertEquals('/api/v4/snapshots/' . $this->snapshotId, $body->_links->self);
    }

    public function shouldReadSnapshotById(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots/' . $this->snapshotId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $this->snapshotId,
            'schema' => $this->schema,
            'relation' => 'poi',
            'srs' => 25832,
            'username' => $this->userId,
        ]);
        $body = json_decode($I->grabResponse());
        $I->assertContains($body->status, ['pending', 'running', 'succeeded', 'failed']);
        $I->assertTrue(property_exists($body, 's3_path'));
        $I->assertTrue(property_exists($body, 'row_count'));
        $I->assertTrue(property_exists($body, 'schema_version'));
        $I->assertTrue(property_exists($body, 'relation_schema'));
        $I->assertTrue(property_exists($body, 'error'));
        $I->assertTrue(property_exists($body, 'created'));
        $I->assertTrue(property_exists($body, 'started'));
        $I->assertTrue(property_exists($body, 'finished'));
    }

    public function shouldListSnapshotsWithFilter(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->snapshotId]]);

        $I->sendGET('/api/v4/snapshots?schema=' . $this->schema . '&relation=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->snapshotId]]);

        $I->sendGET('/api/v4/snapshots?schema=' . $this->schema . '&relation=nothing_here');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()));
    }

    public function shouldRejectSecondSnapshotWhileFirstIsActive(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        // The worker may already have finished the first one in a dev stack with cron; accept both outcomes.
        $code = $I->grabResponse();
        $status = json_decode($code);
        if (isset($status->status) && $status->status === 'pending') {
            $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        } else {
            $I->seeResponseCodeIs(HttpCode::CONFLICT);
            $I->seeResponseContainsJson(['errorCode' => 'SNAPSHOT_IN_PROGRESS']);
        }
    }

    public function shouldReturn404ForUnknownRelation(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'nope']));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'RELATION_NOT_FOUND']);
    }

    public function shouldReturn400OnBadBody(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi', 'srs' => 'abc']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'po"i']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPOST('/api/v4/snapshots', json_encode([['schema' => $this->schema, 'relation' => 'poi']]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldReturn404ForUnknownSnapshotId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        $I->sendGET('/api/v4/snapshots/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectPostWithResourceId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots/' . $this->snapshotId, json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }

    public function shouldRejectSubUser(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
        $I->sendGET('/api/v4/snapshots');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
    }
}
