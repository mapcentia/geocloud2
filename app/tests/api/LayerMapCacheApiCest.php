<?php

use Codeception\Util\HttpCode;

/**
 * Changing a layer through the Layer API rewrites the WMS/WFS mapfiles; it must ALSO rewrite the
 * per-database MapCache config, but only when the change touches a caching-relevant property — the
 * MapCache config covers every layer in the database and is expensive to generate. This suite uses
 * the config file itself as a sentinel: it deletes the file, makes a change, and checks whether the
 * change recreated it. Mapcachefile::write() creates the file on any regeneration, so absence proves
 * the regeneration was skipped.
 */
class LayerMapCacheApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $layer = 's1.roads.the_geom';

    public function __construct()
    {
        $this->date = new DateTime();
    }

    private function configPath(): string
    {
        return '/var/www/geocloud2/app/wms/mapcache/' . $this->userId . '.xml';
    }

    private function patchProps(ApiTester $I, array $properties): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH('/api/v4/layers/' . $this->layer, json_encode(['properties' => $properties]));
        $I->seeResponseCodeIs(HttpCode::OK); // 303 followed
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'lmc_owner_' . $ts, 'email' => 'lmcowner' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => 's1']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/s1/tables', json_encode([
            'name' => 'roads', 'columns' => [['name' => 'the_geom', 'type' => 'geometry(LineString,4326)']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->layer,
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // A styling-only (non-caching) property must NOT rewrite the MapCache config.
    public function nonRelevantPropertyDoesNotRegenerate(ApiTester $I)
    {
        @unlink($this->configPath());
        $this->patchProps($I, ['opacity' => 50]);
        $I->assertFileDoesNotExist($this->configPath(), 'opacity is not a MapCache-relevant key');
    }

    // A caching-relevant property (ttl) MUST rewrite the MapCache config.
    public function relevantPropertyRegenerates(ApiTester $I)
    {
        @unlink($this->configPath());
        $this->patchProps($I, ['ttl' => 3600]);
        $I->assertFileExists($this->configPath(), 'ttl is a MapCache-relevant key');
    }

    // Changing styling (adding a style to a class) must NOT rewrite the MapCache config.
    public function styleChangeDoesNotRegenerate(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendGET('/api/v4/layers/' . $this->layer);
        $I->seeResponseCodeIs(HttpCode::OK);
        $classId = json_decode($I->grabResponse(), true)['classes'][0]['id'];

        @unlink($this->configPath());
        $I->sendPOST('/api/v4/layers/' . $this->layer . '/classes/' . $classId . '/styles',
            json_encode(['color' => '#ff0000', 'width' => '2']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->assertFileDoesNotExist($this->configPath(), 'a style change must not rewrite the MapCache config');
    }

    public function shouldCleanup(ApiTester $I)
    {
        @unlink($this->configPath());
    }
}
