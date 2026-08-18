<?php

use Codeception\Util\HttpCode;

/**
 * Full (unscoped) MapCache tileset delete wipes the backend store directly instead of running
 * mapcache_seed (which iterates the whole grid coordinate space and never terminates on a deep
 * grid). This suite proves the per-backend dispatch: sqlite is unlinked synchronously (200), disk
 * is removed in the background (202), and s3/memcache — which can't be wiped cheaply here — are
 * rejected with 400 (steering to a scoped delete or TTL/lifecycle).
 *
 * Cache files are created as fixtures and chowned to www-data so the FPM worker (www-data) can
 * remove them, mirroring production where MapCache writes the tiles as www-data.
 */
class MapcacheWipeApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $layer = 's1.roads.the_geom';
    private $tileset = 's1.roads';

    public function __construct()
    {
        $this->date = new DateTime();
    }

    private function mapcacheDir(): string
    {
        return '/var/www/geocloud2/app/wms/mapcache/';
    }

    private function setCache(ApiTester $I, string $type): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH('/api/v4/layers/' . $this->layer, json_encode(['properties' => ['cache' => $type]]));
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    private function del(ApiTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/' . $this->tileset);
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'mcw_owner_' . $ts, 'email' => 'mcwowner' . $ts . '@example.com', 'password' => $this->password,
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
        $I->sendPOST('/api/v4/layers', json_encode(['name' => $this->layer, 'classes' => []]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    // sqlite: the tileset's .sqlite3 file is unlinked synchronously.
    public function fullSqliteWipeIsSynchronous(ApiTester $I)
    {
        $this->setCache($I, 'sqlite');
        $dir = $this->mapcacheDir() . 'sqlite/' . $this->userId;
        @mkdir($dir, 0777, true);
        $file = $dir . '/' . $this->tileset . '.sqlite3';
        file_put_contents($file, 'x');
        exec('chown -R www-data:www-data ' . escapeshellarg($dir));

        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('sqlite', $data['backend']);
        $I->assertSame('wipe', $data['mode']);
        $I->assertFileDoesNotExist($file);
    }

    // disk: the tileset directory is removed (renamed instantly, deleted in the background) → 202.
    public function fullDiskWipeIsBackground(ApiTester $I)
    {
        $this->setCache($I, 'disk');
        $dir = $this->mapcacheDir() . 'disk/' . $this->userId . '/' . $this->tileset;
        @mkdir($dir . '/g20/0/0', 0777, true);
        file_put_contents($dir . '/g20/0/0/0.png', 'x');
        exec('chown -R www-data:www-data ' . escapeshellarg($this->mapcacheDir() . 'disk/' . $this->userId));

        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('disk', $data['backend']);
        $I->assertSame('wipe', $data['mode']);
        $I->assertDirectoryDoesNotExist($dir);
    }

    // s3 can't be wiped cheaply here → 400 (use a scoped delete or a lifecycle rule).
    public function fullS3IsUnsupported(ApiTester $I)
    {
        $this->setCache($I, 's3');
        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    // memcache has no per-tileset delete → 400 (entries expire via TTL).
    public function fullMemcacheIsUnsupported(ApiTester $I)
    {
        $this->setCache($I, 'memcache');
        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldCleanup(ApiTester $I)
    {
        @unlink($this->mapcacheDir() . $this->userId . '.xml');
    }
}
