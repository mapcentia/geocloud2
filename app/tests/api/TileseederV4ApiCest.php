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
    /**
     * The grid name the fixture config's tileset declares. "g20" is what GC2's
     * own config generator writes for every tileset (app/models/Mapcachefile.php),
     * and it is deliberately not a file in app/conf/grids: validating `grid`
     * against app/conf/grids — what the code did before this round — refused this
     * exact value, so there was no grid a client could send that worked at all.
     */
    private $grid = 'g20';
    /** A grid the fixture config defines but its tileset does not declare. */
    private $foreignGrid = 'seedtest_notdeclared';
    /** How many <resolutions> the fixture grid has, i.e. its zoom levels. */
    private $levels = 13;
    private $subUserId;

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
        $I->assertArrayNotHasKey($this->grid, \app\controllers\Mapcache::getGrids(),
            'this install has a grid file called ' . $this->grid . ' in app/conf/grids, which makes the '
            . 'queue-a-job test pass for the wrong reason — rename the fixture grid in this cest');
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
        // A second relation, used by the extent_layer cases below: it is a layer
        // of this database the caller may or may not be allowed to read.
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'boundary', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiPolygon,25832)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // The tileset must exist in the database's mapcache config. The test writes
        // the config itself, exactly as MapcacheDeleteApiCest does: generating it
        // from the layer is a different feature's job, and this suite runs inside
        // the container, so it can write the file.
        //
        // It is written the way GC2 writes a real one: grids are defined at the
        // top level and each <tileset> declares, by name, the ones it can be
        // seeded with. `grid` is validated against the tileset's declarations, so
        // a fixture without them would make every POST a 400 UNKNOWN_GRID.
        $resolutions = implode(' ', array_slice([156543.033928041, 78271.5169640205, 39135.7584820102,
            19567.8792410051, 9783.93962050256, 4891.96981025128, 2445.98490512564, 1222.99245256282,
            611.496226281410, 305.748113140705, 152.874056570352, 76.4370282851763, 38.2185141425881,
            19.1092570712941], 0, $this->levels));
        file_put_contents($this->configPath(), <<<XML
            <mapcache>
              <grid name="{$this->grid}">
                <extent>-20037508.3427892 -20037508.3427892 20037508.3427892 20037508.3427892</extent>
                <srs>EPSG:3857</srs>
                <resolutions>$resolutions</resolutions>
              </grid>
              <grid name="{$this->foreignGrid}">
                <resolutions>1638.4 819.2</resolutions>
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
        $this->subUserId = $sub;
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
     * The other half of the grid fix. `grid` is only accepted when the tileset
     * itself declares it: $foreignGrid is defined in the very same config, so a
     * check that merely asked "is this grid configured anywhere" would accept it —
     * and mapcache_seed would then refuse it hours later with "grid not configured
     * for tileset", exit 1. The message has to name the grids that do work,
     * because that is the only way an operator finds out what to send.
     */
    public function shouldRefuseAGridTheTilesetDoesNotDeclare(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSuper($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'foreign grid', 'tileset' => $this->schema . '.roads', 'grid' => $this->foreignGrid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'UNKNOWN_GRID']);
        $I->assertStringContainsString($this->grid, json_decode($I->grabResponse(), true)['message'],
            'the message names the grid the tileset does declare');

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    /**
     * Spec §7: both zooms within the grid's levels. The fixture grid has $levels
     * resolutions, so its deepest zoom is $levels - 1; without the check,
     * zoom_end: 40 was queued and handed to the binary. The accepted case keeps
     * the bound from being tightened by one.
     */
    public function shouldRefuseZoomBeyondTheGridsLevels(ApiTester $I)
    {
        $this->asSuper($I);
        $deepest = $this->levels - 1;
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'deepest legal zoom', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => $deepest, 'zoom_end' => $deepest, 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $this->cancel($I, json_decode($I->grabResponse(), true)['uuid']);

        foreach ([$this->levels, 40] as $tooDeep) {
            $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
                'name' => 'too deep', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
                'zoom_start' => 0, 'zoom_end' => $tooDeep, 'threads' => 1,
            ]));
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
            $I->assertStringContainsString('zoom_end', json_decode($I->grabResponse(), true)['message']);
        }
    }

    /**
     * settings.seed_jobs stores name, tileset, grid and extent_layer as
     * varchar(255). Without a Length assert a 300-character name reached Postgres
     * and came back as a 500 with SQLSTATE[22001] and the INSERT echoed to the
     * client; this is a 400 that names the field, and nothing in the answer leaks
     * SQL.
     */
    public function shouldRefuseAnOverlongName(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSuper($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => str_repeat('a', 300), 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INPUT_VALIDATION_ERROR']);
        $body = json_decode($I->grabResponse(), true);
        $I->assertStringContainsString('name', $body['message']);
        $I->assertStringNotContainsStringIgnoringCase('sqlstate', json_encode($body));

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    /**
     * ?status[]=pending handed Input::get() an array, which reached
     * SeedJob::list(?string …) as a TypeError — a 500 for input that is plainly a
     * bad request. Both filters are checked, and the answer must not be a 500.
     */
    public function shouldRefuseArrayValuedFilters(ApiTester $I)
    {
        $this->asSuper($I);
        foreach (['status[]=pending', 'tileset[]=' . $this->schema . '.roads'] as $query) {
            $I->sendGET('/api/v4/tileseeder/jobs?' . $query);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
        }
        // The scalar form still works, so the guard refuses the array and not the
        // filter itself.
        $I->sendGET('/api/v4/tileseeder/jobs?status=pending');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertIsArray(json_decode($I->grabResponse(), true));
    }

    /**
     * A uuid in the path addresses a job, and POST creates one: queueing a new job
     * and ignoring the segment the client addressed is what postWithResource()
     * exists to refuse, in this controller as in the ten others that call it.
     */
    public function shouldRefusePostAddressedToAJobUuid(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSuper($I);
        $I->sendPOST('/api/v4/tileseeder/jobs/' . $this->uuid, json_encode([
            'name' => 'post with resource', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'POST_WITH_RESOURCE_IDENTIFIER']);

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    /**
     * Spec §7 asks for extent_layer to fail as a 400/404 rather than "minutes
     * later in a log": an unknown one is refused before the row is written. The
     * accepted case in the next test is what proves this is a real lookup and not
     * a blanket refusal.
     */
    public function shouldRefuseAnUnknownExtentLayer(ApiTester $I)
    {
        $before = $this->pendingCount($I);

        $this->asSuper($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'bad extent', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => $this->schema . '.nosuchrelation', 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'EXTENT_LAYER_NOT_FOUND']);

        $I->assertSame($before, $this->pendingCount($I), 'nothing was queued');
    }

    public function shouldQueueAJobWithAnExtentLayerTheCallerMayRead(ApiTester $I)
    {
        $this->asSuper($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'with extent', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => $this->schema . '.boundary', 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $uuid = json_decode($I->grabResponse(), true)['uuid'];

        $I->sendGET('/api/v4/tileseeder/jobs/' . $uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->schema . '.boundary', json_decode($I->grabResponse(), true)['extent_layer']);
        $this->cancel($I, $uuid);
    }

    /**
     * Spec §7 wants extent_layer to be "a relation the caller may read", and only
     * the tileset was ever authorized. seed_run.php opens the OGR datasource as
     * the configured Postgres superuser, so a sub-user with read/write on one
     * tileset could name any relation in the database as its extent source and
     * read that relation's bbox back out of the tiles it got.
     *
     * All three cases are asserted, because any one alone can pass for the wrong
     * reason: the sub-user's own tileset POST must succeed (so the 403 below is
     * about the extent layer, not about the tileset), the extent layer it cannot
     * read must be 403, and the same request must succeed once it is granted read
     * on that layer (so the check is a privilege lookup and not a blanket "no").
     */
    public function shouldRefuseAnExtentLayerTheSubUserCannotRead(ApiTester $I)
    {
        $this->grant($I, $this->schema . '.roads.the_geom', 'read/write');

        // Seeding the tileset itself: allowed, with no extent layer involved.
        $this->asSub($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'sub seeds its own tileset', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $ownJob = json_decode($I->grabResponse(), true)['uuid'];

        // The same request, with an extent layer it has no privilege on.
        $this->asSub($I);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'sub borrows an extent', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => $this->schema . '.boundary', 'threads' => 1,
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INSUFFICIENT_PRIVILEGES']);
        $I->assertStringContainsString('extent', json_decode($I->grabResponse(), true)['message'],
            'the refusal must point at the extent layer, not at the tileset');

        // Granted read on the extent layer, the same request goes through: read is
        // enough, the privilege vocabulary being none / read / read/write.
        $this->grant($I, $this->schema . '.boundary.the_geom', 'read');
        $this->asSub($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'sub with a readable extent', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => $this->schema . '.boundary', 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $grantedJob = json_decode($I->grabResponse(), true)['uuid'];

        $this->asSub($I);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $ownJob . ',' . $grantedJob);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    /**
     * Spec §4.2: the 202 for a running job "links to itself for polling". A
     * running row cannot be produced from the API (a worker claims it), so it is
     * flipped through the model, exactly as shouldShowLegacyRowsWithoutInventingA-
     * Status writes its legacy row, and finished here so no real worker can ever
     * pick it up.
     */
    public function shouldLinkToItselfWhenCancellingARunningJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'running cancel', 'tileset' => $this->schema . '.roads', 'grid' => $this->grid,
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $uuid = json_decode($I->grabResponse(), true)['uuid'];

        $model = $this->model();
        $res = $model->prepare("UPDATE settings.seed_jobs
                                   SET status = 'running', host = 'test-host', pid = 4242,
                                       started = now(), heartbeat = now()
                                 WHERE uuid = :uuid");
        $model->execute($res, ['uuid' => $uuid]);

        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $uuid);
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $body = json_decode($I->grabResponse(), true);
        $I->assertTrue($body['success']);
        $I->assertSame('/api/v4/tileseeder/jobs/' . $uuid, $body['_links']['self'],
            'the 202 links to the job, in the same shape a GET answers with');
        $I->assertContains($uuid, $body['uuid']);

        $I->sendGET('/api/v4/tileseeder/jobs/' . $uuid);
        $I->assertNotNull(json_decode($I->grabResponse(), true)['cancel_requested']);

        // Nothing is left running in this database for a real tick to find.
        $res = $model->prepare("UPDATE settings.seed_jobs SET status = 'cancelled', finished = now() WHERE uuid = :uuid");
        $model->execute($res, ['uuid' => $uuid]);
    }

    /** A Model addressing the test user's own database, for the few row states
     *  the API itself cannot produce (running, legacy/no-status). */
    private function model(): \app\inc\Model
    {
        return new \app\inc\Model(connection: new \app\inc\Connection(database: $this->userId));
    }

    /**
     * Grants the sub-user a privilege on one layer. Through the legacy layer
     * controller, because that is the path that stores the "read/write"
     * vocabulary the privilege check matches (the v4 privileges API's Choice
     * list is none/read/write) — the same recipe MapcacheWipeApiCest uses.
     */
    private function grant(ApiTester $I, string $key, string $privilege): void
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId,
            'password' => $this->password, 'schema' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPUT('/controllers/layer/privileges', json_encode([
            'data' => ['subuser' => $this->subUserId, 'privileges' => $privilege, '_key_' => $key],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    /** Cancels a job queued by a test, so the queue is left as it was found. */
    private function cancel(ApiTester $I, string $uuid): void
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $uuid);
        $I->seeResponseCodeIsSuccessful();
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
