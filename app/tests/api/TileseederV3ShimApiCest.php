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
    private $grid;

    public function shouldPrepare(ApiTester $I)
    {
        // Use whatever grid this environment's mapcache is actually configured
        // with, the same way TileseederV4ApiCest does, rather than a hardcoded
        // name that may not exist here.
        $this->grid = (string)array_key_first(\app\controllers\Mapcache::getGrids());
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
        // different feature's job.
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"" . $this->schema . ".roads\"/>\n</mapcache>\n");
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->userId . '.xml';
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

        $I->sendGET('/api/v3/tileseeder');
        $I->seeResponseCodeIsSuccessful();
        $list = json_decode($I->grabResponse(), true);
        $I->assertTrue($list['success']);
        $I->assertIsArray($list['pids'], 'v3 keeps its {success, pids} shape');

        $I->sendGET('/api/v3/tileseeder/log/' . $this->uuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertArrayHasKey('data', json_decode($I->grabResponse(), true));

        $I->sendDELETE('/api/v3/tileseeder/' . $this->uuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertTrue(json_decode($I->grabResponse(), true)['success']);
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->assertSame('cancelled', json_decode($I->grabResponse(), true)['status']);
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
        @unlink($this->configPath());
    }
}
