<?php

use Codeception\Util\HttpCode;

/**
 * Scheduler run registry through the v3 API. Runs get.php twice for the same
 * fake job id inside the container and expects one real run and one skipped
 * run to be listed. Ordered.
 */
class SchedulerApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $jobId;

    public function __construct()
    {
        $this->jobId = 900000 + (int)substr((string)time(), -5);
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUserAndSource(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'sched super ' . $ts, 'email' => 'schedsuper' . $ts . '@example.com', 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;
        file_put_contents('/var/www/geocloud2/public/schedtest.geojson', '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"id":1},"geometry":{"type":"Point","coordinates":[10,56]}}]}');
    }

    public function shouldListOneRunAndOneSkippedAfterADoubleStart(ApiTester $I)
    {
        $args = '--db ' . escapeshellarg($this->userId) . ' --schema public --safeName schedtest --url "http://localhost/schedtest.geojson" --srid 4326 --type AUTO --encoding UTF8 --jobId ' . $this->jobId
            . ' --deleteAppend 0 --extra null --preSql null --postSql null --downloadSchema 0 --snapshot 0';
        $cmd = 'php -f /var/www/geocloud2/app/scripts/get.php -- ' . $args . ' > /dev/null 2>&1 &';
        shell_exec($cmd);
        shell_exec($cmd);
        sleep(15);

        $this->asSuper($I);
        $I->sendGET('/api/v3/scheduler');
        $I->seeResponseCodeIs(HttpCode::OK);
        $jobs = array_values(array_filter(json_decode($I->grabResponse())->jobs, fn($j) => (int)$j->id === $this->jobId));
        $I->assertCount(2, $jobs, 'one real run and one skipped run');
        $statuses = array_map(fn($j) => $j->status, $jobs);
        sort($statuses);
        $I->assertContains('skipped', $statuses);
        $I->assertTrue(in_array('succeeded', $statuses, true) || in_array('running', $statuses, true) || in_array('failed', $statuses, true), 'the other run is real: ' . json_encode($statuses));
        foreach ($jobs as $j) {
            $I->assertTrue(property_exists($j, 'host'));
            $I->assertTrue(property_exists($j, 'started_at'));
            $I->assertTrue(property_exists($j, 'exit_reason'));
            $I->assertTrue(property_exists($j, 'stale'));
        }
    }

    public function shouldAnswer404ForUnknownRunOnDelete(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v3/scheduler/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        @unlink('/var/www/geocloud2/public/schedtest.geojson');
    }
}
