<?php
use Codeception\Util\HttpCode;

/**
 * GET/POST/DELETE /api/v4/tileseeder/jobs. The seed itself is run by a worker, so
 * these tests only exercise the queue: what the API writes, shows and cancels.
 */
class TileseederV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $schema = 'seedtest';
    private $uuid;
    private $grid;

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

    /** Count of pending jobs, read as super-user, for before/after "nothing queued" proofs. */
    private function pendingCount(ApiTester $I): int
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/tileseeder/jobs?status=pending');
        $I->seeResponseCodeIs(HttpCode::OK);
        return count(json_decode($I->grabResponse(), true));
    }

    public function shouldPrepare(ApiTester $I)
    {
        // array_key_first() on a numeric-looking grid name ("25832") returns an
        // int, since PHP casts such array keys; cast back to string, or a POST
        // sends {"grid": 25832} and fails the SeedJob string-type assert.
        $this->grid = (string)array_key_first(\app\controllers\Mapcache::getGrids());
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seed $ts", 'email' => "seed$ts@example.com", 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'roads', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiLineString,25832)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // The tileset must exist in the database's mapcache config. The test writes
        // the config itself, exactly as MapcacheDeleteApiCest does: generating it
        // from the layer is a different feature's job, and this suite runs inside
        // the container, so it can write the file.
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"" . $this->schema . ".roads\"/>\n</mapcache>\n");
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->userId . '.xml';
    }

    public function shouldQueueAJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'Seed roads', 'tileset' => $this->schema . '.roads',
            'grid' => $this->grid, 'zoom_start' => 0, 'zoom_end' => 3, 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $body = json_decode($I->grabResponse(), true);
        $this->uuid = $body[0]['uuid'] ?? $body['uuid'];
        $I->assertStringContainsString('/api/v4/tileseeder/jobs/' . $this->uuid, $I->grabHttpHeader('Location'));

        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $job = json_decode($I->grabResponse(), true);
        $I->assertSame('pending', $job['status']);
        $I->assertSame($this->schema . '.roads', $job['tileset']);
        $I->assertSame(0, $job['zoom_start']);
        $I->assertSame($this->userId, $job['username']);
        $I->assertNull($job['pid']);
        $I->assertArrayHasKey('log', $job, 'the single read carries the log');

        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $list = json_decode($I->grabResponse(), true);
        $I->assertIsArray($list, 'a bare JSON array');
        $I->assertArrayNotHasKey('log', $list[0], 'the list leaves the log out');
    }

    /**
     * Five distinct failures, each asserted by its own errorCode: a wrong
     * errorCode (e.g. the injection case answering 404 instead of failing
     * validation, or the thread cap answering the grid error) must fail this
     * test, not just "some 4xx".
     */
    public function shouldRefuseWhatCannotBeSeeded(ApiTester $I)
    {
        $this->asSuper($I);
        foreach ([
            ['tileset' => 'no.such_tileset', 'grid' => $this->grid, 'zoom_start' => 0, 'zoom_end' => 1, 'errorCode' => 'TILESET_NOT_FOUND'],
            ['tileset' => $this->schema . '.roads', 'grid' => 'NoSuchGrid', 'zoom_start' => 0, 'zoom_end' => 1, 'errorCode' => 'UNKNOWN_GRID'],
            ['tileset' => $this->schema . '.roads', 'grid' => $this->grid, 'zoom_start' => 5, 'zoom_end' => 1, 'errorCode' => 'INVALID_REQUEST'],
            // Rejected by getAssert()'s Regex on `tileset` before it can reach
            // requireWrite()/SeedCommand::validate() at all.
            ['tileset' => $this->schema . '.roads; whoami', 'grid' => $this->grid, 'zoom_start' => 0, 'zoom_end' => 1, 'errorCode' => 'INPUT_VALIDATION_ERROR'],
            ['tileset' => $this->schema . '.roads', 'grid' => $this->grid, 'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 99, 'errorCode' => 'INVALID_REQUEST'],
        ] as $case) {
            $errorCode = $case['errorCode'];
            unset($case['errorCode']);
            $I->sendPOST('/api/v4/tileseeder/jobs', json_encode($case + ['name' => 'bad']));
            $I->seeResponseCodeIsClientError();
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => $errorCode]);
        }
    }

    /**
     * A malformed body must be refused before anything is queued. An empty
     * list and an empty object (json_decode(..., true) makes them the same
     * PHP array) mirror Snapshot::validate()'s "empty list" rule; a bare
     * JSON scalar or null must not reach array_is_list() in post_index(),
     * which is a TypeError (and was a 500) for a non-array argument.
     */
    public function shouldRefuseMalformedBodies(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        foreach (['[]', '{}', 'null', '"seed roads"', '42'] as $rawBody) {
            $I->sendPOST('/api/v4/tileseeder/jobs', $rawBody);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
        }

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    /**
     * allowMissingFields must stay off: a POST missing zoom_start is a 400
     * naming that field, not (int) null silently becoming a zoom-0 job.
     */
    public function shouldRefuseIncompletePayload(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSuper($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'tileset' => $this->schema . '.roads', 'grid' => $this->grid, 'zoom_end' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INPUT_VALIDATION_ERROR']);
        $body = json_decode($I->grabResponse(), true);
        $I->assertStringContainsString('zoom_start', $body['message'], 'names the missing field');

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    /**
     * tileseeder.maxPending caps the queue per database, so one token cannot fill
     * it. An array POST is checked as a whole: 21 jobs in one request is refused
     * and the pending count is unchanged (not just under the cap — a batch that
     * partially queued would also satisfy "under the cap").
     */
    public function shouldRefuseMoreThanMaxPending(ApiTester $I)
    {
        $before = $this->pendingCount($I);
        $max = 20;   // App::$param['tileseeder']['maxPending'] default

        $this->asSuper($I);
        $batch = array_fill(0, $max + 1, [
            'name' => 'flood', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode($batch));
        $I->seeResponseCodeIs(HttpCode::TOO_MANY_REQUESTS);
        $I->seeResponseContainsJson(['errorCode' => 'TOO_MANY_PENDING']);

        $I->assertSame($before, $this->pendingCount($I), 'a refused batch queues nothing');
    }

    public function shouldCancelAPendingJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->assertSame('cancelled', json_decode($I->grabResponse(), true)['status']);
        // Idempotent: cancelling a finished job changes nothing and still succeeds.
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function shouldNotLeakJobsAcrossUsers(ApiTester $I)
    {
        // A sub-user of the same database sees only its own jobs.
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seedsub $ts", 'email' => "seedsub$ts@example.com",
            'password' => $this->password, 'subuser' => true]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sub = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $sub,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $this->subToken = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true), 'the sub-user has queued nothing');
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * requireWrite()'s refusal branch: this suite's sub-user has no privilege
     * on seedtest.roads, so a POST for it is 403, and nothing is queued. Uses
     * the real, valid tileset name — after the Critical fix's reordering,
     * SeedCommand::validate() has already passed by the time requireWrite()
     * runs, so this is what actually exercises the privilege check, not the
     * tileset lookup.
     */
    public function shouldRefuseSubUserWithoutPrivilege(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSub($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'sub attempt', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INSUFFICIENT_PRIVILEGES']);

        $I->assertSame($before, $this->pendingCount($I), 'the refused job was not queued');
    }

    /**
     * The Critical fix, from the seat that actually reaches the vulnerable
     * code path: a super-user returns early from requireWrite(), so only a
     * sub-user's request ever gets far enough for tileset to reach
     * Model::getGeometryColumns() -> getColumns(), which interpolates it into
     * settings.getColumns()'s literal-quoted SQL. With SeedCommand::validate()
     * (and getAssert()'s Regex, one layer earlier) run first, the
     * injection-shaped tileset never gets that far.
     */
    public function shouldRefuseSubUserInjectionAttempt(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSub($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'sub injection', 'tileset' => $this->schema . ".roads'' OR 1=1 --", 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->seeResponseCodeIsClientError();
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INPUT_VALIDATION_ERROR']);

        $I->assertSame($before, $this->pendingCount($I), 'the injection attempt queued nothing');
    }

    /**
     * Rows written by the pre-v4 code and by MapcacheTileset have no status. They
     * must be listed as they are, never presented as running, and never cancelled.
     */
    public function shouldShowLegacyRowsWithoutInventingAStatus(ApiTester $I)
    {
        $this->asSuper($I);
        // MapcacheTileset writes exactly this shape for its background delete jobs.
        // settings.seed_jobs is not a registered layer, so /api/v4/sql refuses to
        // write to it (same rule that protects every other unregistered system
        // relation); insert directly through the app's own Model/Connection, the
        // way the worker itself will.
        $model = new \app\inc\Model(connection: new \app\inc\Connection(database: $this->userId));
        $model->execute($model->prepare(
            "INSERT INTO settings.seed_jobs (name, pid, host) VALUES ('delete legacy', 4242, 'old-node')"
        ));

        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $legacy = array_values(array_filter(json_decode($I->grabResponse(), true), fn($j) => $j['name'] === 'delete legacy'));
        $I->assertCount(1, $legacy);
        $I->assertNull($legacy[0]['status'], 'no status is invented');
        $I->assertFalse($legacy[0]['stale'], 'and a row with no status is not a stuck run');
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
        @unlink($this->configPath());
    }
}
