<?php

use Codeception\Util\HttpCode;

/**
 * v4 scheduler job CRUD (app/api/v4/controllers/SchedulerJob.php). Super-user
 * only; jobs are scoped to the JWT's database. Ordered/stateful.
 */
class SchedulerJobV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $otherToken;
    private $jobId;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUsers(ApiTester $I)
    {
        $ts = time();
        foreach (['a', 'b'] as $k) {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPOST('/api/v2/user', json_encode(['name' => "schedjob $k $ts", 'email' => "schedjob$k$ts@example.com", 'password' => $this->password]));
            $I->seeResponseCodeIs(HttpCode::OK);
            $uid = json_decode($I->grabResponse())->data->screenname;
            $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $uid, 'password' => $this->password, 'database' => $uid, 'client_id' => 'gc2-cli']));
            $I->seeResponseCodeIs(HttpCode::CREATED);
            $tok = json_decode($I->grabResponse())->access_token;
            if ($k === 'a') { $this->userId = $uid; $this->token = $tok; } else { $this->otherToken = $tok; }
        }
    }

    public function shouldCreateJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            'name' => 'My Job', 'schema' => 'public', 'url' => 'https://example.com/data.zip', 'schedule' => '15 3 * * 1-5',
            'epsg' => 25832, 'snapshot' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $loc = $I->grabHttpHeader('Location');
        $I->assertMatchesRegularExpression('#/api/v4/scheduler/jobs/\d+$#', $loc);
        $this->jobId = (int)basename($loc);

        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $this->jobId, 'name' => 'my_job', 'schema' => 'public', 'schedule' => '15 3 * * 1-5',
            'epsg' => 25832, 'type' => 'AUTO', 'encoding' => 'UTF8', 'delete_append' => false, 'download_schema' => true,
            'active' => true, 'snapshot' => true, 'snapshot_formats' => null, 'use_sortby' => true,
        ]);
    }

    /**
     * use_sortby: the opt-out for WFS 2.0.0 servers that reject sortBy. Unlike
     * the other flags it defaults to true, so a POST that says nothing about it
     * keeps the scheduler's automatic sorting.
     */
    public function shouldTakeUseSortBy(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            'name' => 'No sort job', 'schema' => 'public', 'schedule' => '0 2 * * *',
            'url' => 'https://example.com/wfs?service=WFS&version=2.0.0&request=GetFeature&typeNames=a:b',
            'use_sortby' => false,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $id = (int)basename($I->grabHttpHeader('Location'));

        $I->sendGET('/api/v4/scheduler/jobs/' . $id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertFalse(json_decode($I->grabResponse())->use_sortby);

        // PATCH turns it back on, and a PATCH about something else leaves it alone.
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $id, json_encode(['use_sortby' => true]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $id, json_encode(['presql' => 'SELECT 1']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET('/api/v4/scheduler/jobs/' . $id);
        $I->assertTrue(json_decode($I->grabResponse())->use_sortby);

        // A non-boolean is refused by the assert, not coerced.
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $id, json_encode(['use_sortby' => 'nope']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendDELETE('/api/v4/scheduler/jobs/' . $id);
        $I->seeResponseCodeIsSuccessful();
    }

    /**
     * Per-job snapshot formats: a list is stored and read back, an unknown id
     * is refused, and PATCH null resets the job to the server default.
     */
    public function shouldTakeSnapshotFormats(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            'name' => 'fmt job', 'schema' => 'public', 'url' => 'https://example.com/data.zip', 'schedule' => '0 2 * * *',
            'active' => false, 'snapshot' => true, 'snapshot_formats' => ['parquet', 'flatgeobuf'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $id = (int)basename($I->grabHttpHeader('Location'));

        $I->sendGET('/api/v4/scheduler/jobs/' . $id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['parquet', 'flatgeobuf'], json_decode($I->grabResponse())->snapshot_formats);

        // Unknown, duplicate, empty and non-array values are all 400.
        foreach ([['geojson'], ['parquet', 'parquet'], [], 'parquet'] as $bad) {
            $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
                'name' => 'fmt bad', 'schema' => 'public', 'url' => 'https://example.com/data.zip',
                'schedule' => '0 2 * * *', 'snapshot' => true, 'snapshot_formats' => $bad,
            ]));
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        }

        // null resets the job to the server default.
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $id, json_encode(['snapshot_formats' => null]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET('/api/v4/scheduler/jobs/' . $id);
        $I->assertNull(json_decode($I->grabResponse())->snapshot_formats);

        $I->sendPATCH('/api/v4/scheduler/jobs/' . $id, json_encode(['snapshot_formats' => ['nope']]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendDELETE('/api/v4/scheduler/jobs/' . $id);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function shouldValidateOnCreate(ApiTester $I)
    {
        $this->asSuper($I);
        // a bad cron field in the second element creates nothing
        $I->sendGET('/api/v4/scheduler/jobs');
        $before = count(json_decode($I->grabResponse()));
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            ['name' => 'ok', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => '0 1 * * *'],
            ['name' => 'bad', 'schema' => 'public', 'url' => 'https://e.com/b', 'schedule' => 'nonsense fields here now'],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendGET('/api/v4/scheduler/jobs');
        $I->assertCount($before, json_decode($I->grabResponse()), 'all-or-nothing');
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => 'every day']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'schedule' => '* * * * *']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => '* * * * *', 'epsg' => 'abc']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldListAndPatch(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/scheduler/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->jobId]]);

        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $this->jobId, json_encode(['active' => false, 'schedule' => '0 4 * * *', 'presql' => 'SELECT 1']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseContainsJson(['active' => false, 'schedule' => '0 4 * * *', 'presql' => 'SELECT 1', 'snapshot' => true]);

        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/scheduler/jobs/' . $this->jobId, json_encode(['schedule' => 'nope']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->startFollowingRedirects();
    }

    public function shouldScopeJobsToTheCallersDatabase(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->otherToken);
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'JOB_NOT_FOUND']);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET('/api/v4/scheduler/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()));
    }

    public function shouldDeleteJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
    public function shouldCreateGetAndDeleteMultipleJobs(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            ['name' => 'multi a', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => '0 1 * * *', 'active' => false],
            ['name' => 'multi b', 'schema' => 'public', 'url' => 'https://e.com/b', 'schedule' => '0 2 * * *', 'active' => false],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $ids = explode(',', basename($location));
        $I->assertCount(2, $ids, "Location: $location");

        $I->sendGET('/api/v4/scheduler/jobs/' . implode(',', $ids));
        $I->seeResponseCodeIs(HttpCode::OK);
        $jobs = json_decode($I->grabResponse());
        $I->assertIsArray($jobs);
        $I->assertEquals(['multi_a', 'multi_b'], array_map(fn($j) => $j->name, $jobs));

        $I->sendGET('/api/v4/scheduler/jobs/' . $ids[0]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertIsObject(json_decode($I->grabResponse()), 'a single id returns an object');

        $I->sendGET('/api/v4/scheduler/jobs/' . $ids[0] . ',abc');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $ids[0] . ',987654321');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET('/api/v4/scheduler/jobs/' . $ids[0]);
        $I->seeResponseCodeIs(HttpCode::OK); // nothing was deleted by the failed list

        $I->sendDELETE('/api/v4/scheduler/jobs/' . implode(',', $ids));
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/scheduler/jobs/' . implode(',', $ids));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

}
