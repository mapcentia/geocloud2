<?php

use Codeception\Util\HttpCode;

/**
 * The v4 MapCache tileset-delete endpoint (DELETE api/v4/mapcache/database/{database}/tileset/{tileset})
 * authorizes the caller for write on the layer and then launches `mapcache_seed -m delete` as a
 * detached background job, returning 202 with a job uuid. This suite proves the authorization and
 * validation gates and the 202 job-start contract.
 *
 * The happy path writes a stub MapCache config file so the endpoint's existence checks pass; the
 * background mapcache_seed process is irrelevant to the 202 (its pid is captured regardless) and is
 * cleaned up afterwards.
 */
class MapcacheDeleteApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;

    public function __construct()
    {
        $this->date = new DateTime();
    }

    private function configPath(): string
    {
        return '/var/www/geocloud2/app/wms/mapcache/' . $this->userId . '.xml';
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'mcd_owner_' . $ts, 'email' => 'mcdowner' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Make sure no stale config from a previous run exists (db name is timestamped, so this is
        // only belt-and-braces).
        @unlink($this->configPath());
    }

    private function bearer(ApiTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    // A qualified tileset ("schema.table") is required.
    public function deleteRejectsUnqualifiedTileset(ApiTester $I)
    {
        $this->bearer($I);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/nodot');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    // Deletion is a write operation — anonymous is challenged.
    public function deleteRequiresAuth(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/s1.roads');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    // A SCOPED delete needs the MapCache config (mapcache_seed reads it); missing config → 404.
    public function scopedDeleteReturns404WhenNoConfig(ApiTester $I)
    {
        @unlink($this->configPath());
        $this->bearer($I);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/s1.roads?zoom=0,0');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    // With a config present, a scoped delete for an unknown tileset is 404.
    public function scopedDeleteReturns404ForUnknownTileset(ApiTester $I)
    {
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"s1.roads\"/>\n</mapcache>\n");
        $this->bearer($I);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/s1.other?zoom=0,0');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    // A SCOPED delete (bbox/zoom) runs mapcache_seed as a background job → 202 with uuid/pid/scope.
    public function scopedDeleteStartsSeedJob(ApiTester $I)
    {
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"s1.roads\"/>\n</mapcache>\n");
        $this->bearer($I);
        // Scope tightly so the launched mapcache_seed has nothing to do and exits immediately.
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/s1.roads?zoom=0,0&bbox=0,0,1,1');
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseIsJson();
        $data = json_decode($I->grabResponse(), true);
        $I->assertTrue($data['success']);
        $I->assertSame('seed', $data['mode']);
        $I->assertNotEmpty($data['uuid']);
        $I->assertGreaterThan(0, $data['pid']);
        $I->assertSame('s1.roads', $data['tileset']);
        $I->assertSame('0,0,1,1', $data['scope']['bbox']);
        $I->assertSame('0,0', $data['scope']['zoom']);
    }

    // Rejects malformed scope (a dropped scope would become a full-tileset delete).
    public function deleteRejectsBadBbox(ApiTester $I)
    {
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"s1.roads\"/>\n</mapcache>\n");
        $this->bearer($I);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/s1.roads?bbox=1,2,3');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldCleanup(ApiTester $I)
    {
        @unlink($this->configPath());
    }
}
