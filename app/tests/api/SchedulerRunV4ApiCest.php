<?php

use Codeception\Util\HttpCode;

/**
 * v4 scheduler runs (app/api/v4/controllers/SchedulerRun.php): start a job,
 * list/inspect runs, stop a run. Uses a tiny GeoJSON served by the container
 * as the job source. Ordered/stateful.
 */
class SchedulerRunV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $jobId;
    private $runUuid;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUserSourceAndJob(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "schedrun $ts", 'email' => "schedrun$ts@example.com", 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;
        file_put_contents('/var/www/geocloud2/public/schedrun.geojson', '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"id":1},"geometry":{"type":"Point","coordinates":[10,56]}}]}');

        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'run test', 'schema' => 'public', 'url' => 'http://localhost/schedrun.geojson', 'schedule' => '0 0 1 1 *', 'active' => false]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->jobId = (int)basename($I->grabHttpHeader('Location'));
    }

    public function shouldStartAJobAndListItsRun(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/runs', json_encode(['job' => $this->jobId]));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseContainsJson(['job' => $this->jobId, 'status' => 'starting']);
        sleep(15);

        $I->sendGET('/api/v4/scheduler/runs?job=' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $runs = json_decode($I->grabResponse());
        $I->assertGreaterThanOrEqual(1, count($runs));
        $run = $runs[0];
        $I->assertEquals($this->jobId, $run->job);
        $I->assertContains($run->status, ['succeeded', 'failed', 'running'], 'run status: ' . json_encode($run));
        $I->assertTrue(property_exists($run, 'host') && property_exists($run, 'stale') && property_exists($run, 'exit_reason'));
        $this->runUuid = $run->uuid;

        $I->assertFalse(property_exists($run, 'log'), 'the listing never carries the log');

        // The overwrite import must leave a GIST index on the_geom (get.php once
        // decided this from the final table before it existed and never indexed).
        if ($run->status === 'succeeded') {
            $I->sendPOST('/api/v4/sql', json_encode(['q' => "SELECT indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'run_test' AND indexdef ILIKE '%USING gist (the_geom)%'"]));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->assertStringContainsString('USING gist (the_geom)', $I->grabResponse(), 'imported table has a GIST index');
        }

        $I->sendGET('/api/v4/scheduler/runs/' . $this->runUuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['uuid' => $this->runUuid, 'job' => $this->jobId]);
        $single = json_decode($I->grabResponse());
        $I->assertTrue(property_exists($single, 'log'), 'the single run carries the log');
        $I->assertIsString($single->log, 'log of run: ' . json_encode($single));
        $I->assertStringContainsString('Info: Run ' . $this->runUuid . ' registered', $single->log);
    }

    public function shouldReject404AndUnknownJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/scheduler/runs/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET('/api/v4/scheduler/runs/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'RUN_NOT_FOUND']);
        $I->seeResponseContainsJson(['errorCode' => 'RUN_NOT_FOUND']);
        $I->sendPOST('/api/v4/scheduler/runs', json_encode(['job' => 987654321]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'JOB_NOT_FOUND']);
        $I->sendDELETE('/api/v4/scheduler/runs/' . $this->runUuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND); // finished runs cannot be stopped
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIsSuccessful();
        @unlink('/var/www/geocloud2/public/schedrun.geojson');
    }
}
