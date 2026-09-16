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
            'active' => true, 'snapshot' => true,
        ]);
    }

    public function shouldValidateOnCreate(ApiTester $I)
    {
        $this->asSuper($I);
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
}
