<?php
use Codeception\Util\HttpCode;

/**
 * The v3 tileseeder keeps its shapes but stops shelling out: it queues into the
 * same table the v4 resource reads, so a job started here can be watched and
 * cancelled from any node.
 */
class TileseederV3ShimApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schema = 'seedv3';
    private $uuid;
    /**
     * The grid the fixture config's tileset declares — "g20", which is what GC2's
     * config generator writes for every real tileset. v3 used to pass `grid`
     * straight to mapcache_seed, so a v3 client sending g20 worked; validating it
     * against app/conf/grids (which has no g20) broke exactly that client, and
     * this value is what keeps it working.
     */
    private $grid = 'g20';

    public function shouldPrepare(ApiTester $I)
    {
        $I->assertArrayNotHasKey($this->grid, \app\controllers\Mapcache::getGrids(),
            'this install has a grid file called ' . $this->grid . ' in app/conf/grids, which makes this '
            . 'cest pass for the wrong reason — rename the fixture grid');
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seedv3 $ts", 'email' => "seedv3$ts@example.com", 'password' => $this->password]));
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $this->token = json_decode($I->grabResponse())->access_token;
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'roads', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiLineString,25832)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // The tileset must exist in the database's mapcache config, exactly as
        // TileseederV4ApiCest sets it up: generating it from the layer is a
        // different feature's job. Written the way GC2 writes a real one, with
        // the grid defined at the top level and declared by the tileset — that
        // declaration is what `grid` is validated against.
        file_put_contents($this->configPath(), <<<XML
            <mapcache>
              <grid name="{$this->grid}">
                <resolutions>1638.4 819.2 409.6 204.8 102.4 51.2 25.6 12.8</resolutions>
              </grid>
              <tileset name="{$this->schema}.roads">
                <grid>{$this->grid}</grid>
              </tileset>
            </mapcache>
            XML);
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->userId . '.xml';
    }

    /** A Model/Connection pair addressing the test user's own database, the
     *  same way the controller under test does -- used here only to reach
     *  into settings.seed_jobs for states v3 itself cannot produce (running,
     *  legacy/no-status), exactly as TileseederV4ApiCest's
     *  shouldShowLegacyRowsWithoutInventingAStatus does. */
    private function model(): \app\inc\Model
    {
        return new \app\inc\Model(connection: new \app\inc\Connection(database: $this->userId));
    }

    private function flipToRunning(string $uuid, int $pid, string $log): void
    {
        $model = $this->model();
        $res = $model->prepare("UPDATE settings.seed_jobs
                                    SET status = 'running', pid = :pid, host = 'test-host',
                                        started = now(), heartbeat = now(), log = :log
                                  WHERE uuid = :uuid");
        $model->execute($res, ['pid' => $pid, 'log' => $log, 'uuid' => $uuid]);
    }

    private function pinStatus(string $uuid, string $status): void
    {
        $model = $this->model();
        $res = $model->prepare("UPDATE settings.seed_jobs SET status = :status WHERE uuid = :uuid");
        $model->execute($res, ['status' => $status, 'uuid' => $uuid]);
    }

    /** @return string the uuid of the inserted row */
    private function insertLegacyRow(string $name, int $pid): string
    {
        $model = $this->model();
        $res = $model->prepare("INSERT INTO settings.seed_jobs (name, pid, host)
                                 VALUES (:name, :pid, 'old-node') RETURNING uuid");
        $model->execute($res, ['name' => $name, 'pid' => $pid]);
        return $model->fetchRow($res)['uuid'];
    }

    private function statusOf(string $uuid): ?string
    {
        $model = $this->model();
        $res = $model->prepare("SELECT status FROM settings.seed_jobs WHERE uuid = :uuid");
        $model->execute($res, ['uuid' => $uuid]);
        return $model->fetchRow($res)['status'] ?? null;
    }

    public function shouldKeepTheV3Shapes(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        // v3 field names, mapped on the way in.
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'v3 seed', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 2, 'extent' => null, 'threads' => 1,
        ]));
        $I->seeResponseCodeIsSuccessful();
        $body = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('uuid', $body);
        $I->assertArrayHasKey('pid', $body);
        $I->assertNull($body['pid'], 'a queued job has no process yet');
        $I->assertArrayNotHasKey('cmd', $body, 'cmd leaked the database password');
        $this->uuid = $body['uuid'];

        // The same job is visible in v4.
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->schema . '.roads', json_decode($I->grabResponse(), true)['tileset']);

        // Pin the status explicitly, so this test names which case it is
        // exercising (a still-pending job, cancelled outright) instead of
        // depending on no cron'd seed_worker tick having raced it to 'running'
        // between the POST above and the DELETE below. Once the image is
        // rebuilt with seed_worker in the crontab, that race would otherwise
        // make this assertion flaky -- and the running case is covered on its
        // own terms in shouldReportRunningJobsLogsAndWildcardCancel() below.
        $this->pinStatus($this->uuid, 'pending');

        $I->sendDELETE('/api/v3/tileseeder/' . $this->uuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertTrue(json_decode($I->grabResponse(), true)['success']);
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->assertSame('cancelled', json_decode($I->grabResponse(), true)['status']);
    }

    /**
     * A segment that is not a real uuid must never reach SQL (AGENTS.md §3) and
     * must never 500: both used to answer 200 with the not-found shape (the old
     * routing made Route::getParam("uuid") null for every singleton delete, so
     * neither case ever reached a row). Before the regex guard, the no-segment
     * case threw a PHP TypeError out of SeedJob::get(null) and the garbage-uuid
     * case echoed Postgres's SQLSTATE 22P02 back to the client -- so this test
     * would fail loudly (500, with the segment's validation removed) rather
     * than passing by accident.
     */
    public function shouldRejectMalformedUuidSegmentsWithoutTouchingSql(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);

        $I->sendDELETE('/api/v3/tileseeder');
        $I->seeResponseCodeIs(HttpCode::OK);
        $noSegment = json_decode($I->grabResponse(), true);
        $I->assertFalse($noSegment['success']);
        $I->assertStringNotContainsStringIgnoringCase('sqlstate', $noSegment['message'] ?? '');

        $I->sendDELETE('/api/v3/tileseeder/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::OK);
        $garbage = json_decode($I->grabResponse(), true);
        $I->assertFalse($garbage['success']);
        $I->assertStringContainsString('not-a-uuid', $garbage['message']);
        $I->assertStringNotContainsStringIgnoringCase('sqlstate', $garbage['message']);
    }

    /**
     * extent does not get a (string) cast on the way in to SeedCommand::validate()
     * (null is a legitimate value); a non-scalar used to reach its ?string
     * parameter directly and crash with a TypeError (500) instead of a 400.
     */
    public function shouldRejectNonScalarExtent(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'bad extent', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 1, 'extent' => ['a'], 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
    }

    /**
     * layer and grid get a (string) cast; extent must behave the same way for
     * any scalar, not just get rejected outright -- a numeric extent used to
     * coerce to "123" before an earlier, over-eager !is_string() guard turned it
     * into a 400 too. It still coerces; what stops it now is the extent layer
     * existence check spec §7 asks for, and the two are told apart by their error
     * codes: a non-scalar never becomes a string (INVALID_REQUEST, above) while
     * "123" does and is then looked up as a relation (EXTENT_LAYER_NOT_FOUND).
     */
    public function shouldCoerceNumericExtentAndThenLookItUpAsARelation(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'numeric extent', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 1, 'extent' => 123, 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'EXTENT_LAYER_NOT_FOUND']);
    }

    /**
     * The v3 shim goes through the same validator as v4, so an extent that is a
     * real relation of the database is queued and lands in the row v4 reads --
     * and an extent that is not is a 404 now, instead of an exit-1 seed run
     * minutes later in a log nobody reads (spec §7). The accepted case is what
     * keeps the refusal from passing for the wrong reason.
     */
    public function shouldQueueWithARealExtentAndRefuseAnUnknownOne(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'real extent', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 1, 'extent' => $this->schema . '.roads', 'threads' => 1,
        ]));
        $I->seeResponseCodeIsSuccessful();
        $queued = json_decode($I->grabResponse(), true)['uuid'];
        $I->sendGET('/api/v4/tileseeder/jobs/' . $queued);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->schema . '.roads', json_decode($I->grabResponse(), true)['extent_layer']);
        $I->sendDELETE('/api/v3/tileseeder/' . $queued);
        $I->seeResponseCodeIsSuccessful();

        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'unknown extent', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 1, 'extent' => $this->schema . '.nosuchrelation', 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'EXTENT_LAYER_NOT_FOUND']);
    }

    /**
     * v3's own compatibility case for the grid fix: the grid this tileset
     * declares is accepted and queued. Validated against app/conf/grids -- what
     * the code did before this round -- the same request was a 400, which broke
     * every v3 client that had been passing g20 straight through to the binary.
     */
    public function shouldAcceptTheGridTheTilesetDeclares(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'declared grid', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 2, 'threads' => 1,
        ]));
        $I->seeResponseCodeIsSuccessful();
        $uuid = json_decode($I->grabResponse(), true)['uuid'];
        $I->sendGET('/api/v4/tileseeder/jobs/' . $uuid);
        $I->assertSame($this->grid, json_decode($I->grabResponse(), true)['grid']);
        $I->sendDELETE('/api/v3/tileseeder/' . $uuid);
        $I->seeResponseCodeIsSuccessful();
    }

    /**
     * Everything shouldKeepTheV3Shapes() cannot exercise because the job it
     * queues stays 'pending' for its whole test: the status='running' filter
     * and the uuid/pid/name triple in GET's list, the preg_split()/end() last
     * line extraction in the log endpoint, and the * wildcard's
     * {success, pids:[...]} shape (the one shape §9 explicitly calls out). v3
     * has no way to make a worker claim a job inside a test, so the job is
     * flipped to 'running' directly through the model -- the same technique
     * TileseederV4ApiCest uses for its legacy-row case.
     */
    public function shouldReportRunningJobsLogsAndWildcardCancel(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);

        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'v3 running seed', 'layer' => $this->schema . '.roads', 'grid' => $this->grid,
            'start' => 0, 'end' => 2, 'extent' => null, 'threads' => 1,
        ]));
        $I->seeResponseCodeIsSuccessful();
        $runningUuid = json_decode($I->grabResponse(), true)['uuid'];
        // A tail containing both \r (mapcache_seed's own progress separator)
        // and \n (a real line break), so the "last line" the log endpoint
        // extracts is unambiguous and would be wrong if \r alone, or \n alone,
        // were used to split it.
        $this->flipToRunning($runningUuid, 4242, "Seeding 1/10\rSeeding 5/10\rSeeding 10/10\nDone\n");

        // GET (list): only the running job appears, with the real triple -- a
        // stub that always answers {success:true, pids:[]} cannot pass this.
        $I->sendGET('/api/v3/tileseeder');
        $I->seeResponseCodeIsSuccessful();
        $list = json_decode($I->grabResponse(), true);
        $I->assertTrue($list['success']);
        $I->assertCount(1, $list['pids'], 'exactly the one running job is listed');
        $I->assertSame($runningUuid, $list['pids'][0]['uuid']);
        $I->assertSame(4242, $list['pids'][0]['pid']);
        $I->assertSame('v3 running seed', $list['pids'][0]['name']);

        // GET log: the last line of the tail, not null and not the whole blob.
        $I->sendGET('/api/v3/tileseeder/log/' . $runningUuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertSame('Done', json_decode($I->grabResponse(), true)['data']);

        // Uppercase uuids must keep working: pre-v4 Util::guid() produced them,
        // and real legacy clients still hold those uppercase uuids today.
        $I->sendGET('/api/v3/tileseeder/log/' . strtoupper($runningUuid));
        $I->seeResponseCodeIsSuccessful();
        $I->assertSame('Done', json_decode($I->grabResponse(), true)['data']);

        // A segment that is not a real uuid must never reach SeedJob::get()
        // (AGENTS.md §3): the same bug round 1 closed in delete_index() but
        // left open here. Before the guard this answered 500 with Postgres's
        // SQLSTATE 22P02 echoed back; the endpoint's own not-found shape is
        // {"data":null}, same as a missing segment or an unknown-but-valid uuid.
        $I->sendGET('/api/v3/tileseeder/log/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::OK);
        $garbageLog = json_decode($I->grabResponse(), true);
        $I->assertNull($garbageLog['data']);
        $I->assertStringNotContainsStringIgnoringCase('sqlstate', json_encode($garbageLog));

        $unknownForLog = '00000000-0000-4000-8000-000000000001';
        $I->sendGET('/api/v3/tileseeder/log/' . $unknownForLog);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNull(json_decode($I->grabResponse(), true)['data']);

        $I->sendGET('/api/v3/tileseeder/log');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNull(json_decode($I->grabResponse(), true)['data']);

        // DELETE an unknown (but well-formed) uuid: the not-found shape.
        $unknown = '00000000-0000-4000-8000-000000000000';
        $I->sendDELETE('/api/v3/tileseeder/' . $unknown);
        $I->seeResponseCodeIsSuccessful();
        $unknownBody = json_decode($I->grabResponse(), true);
        $I->assertFalse($unknownBody['success']);
        $I->assertStringContainsString($unknown, $unknownBody['message']);

        // DELETE *: cancels only the caller's running jobs and answers the
        // {success, pids:[{uuid,pid,name}]} shape §9 calls out.
        $I->sendDELETE('/api/v3/tileseeder/*');
        $I->seeResponseCodeIsSuccessful();
        $wildcard = json_decode($I->grabResponse(), true);
        $I->assertTrue($wildcard['success']);
        $I->assertCount(1, $wildcard['pids']);
        $I->assertSame($runningUuid, $wildcard['pids'][0]['uuid']);
        $I->assertSame(4242, $wildcard['pids'][0]['pid']);
        $I->assertSame('v3 running seed', $wildcard['pids'][0]['name']);

        // A running row is asked to stop, not declared cancelled outright --
        // its worker still has to act (requestCancel() -> 'cancelling').
        $I->sendGET('/api/v4/tileseeder/jobs/' . $runningUuid);
        $afterWildcard = json_decode($I->grabResponse(), true);
        $I->assertSame('running', $afterWildcard['status']);
        $I->assertNotNull($afterWildcard['cancel_requested']);

        // Finish the row ourselves: nothing is left 'running' for a real
        // seed_worker to ever pick up in this database.
        $this->pinStatus($runningUuid, 'cancelled');
    }

    /**
     * A pre-v4 row (status null) cannot actually be cancelled -- no v4 code
     * claims it, and requestCancel() returns 'noop' for it. DELETE must say so
     * instead of claiming success, and must leave the row untouched.
     */
    public function shouldNotClaimToCancelALegacyRow(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);

        $legacyUuid = $this->insertLegacyRow('delete legacy', 9999);

        $I->sendDELETE('/api/v3/tileseeder/' . $legacyUuid);
        $I->seeResponseCodeIsSuccessful();
        $body = json_decode($I->grabResponse(), true);
        $I->assertFalse($body['success'], 'nothing can be cancelled on a legacy row');
        $I->assertSame('No running job with uuid: ' . $legacyUuid, $body['message']);
        $I->assertNull($this->statusOf($legacyUuid), 'the row was not touched');
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
        @unlink($this->configPath());
    }
}
