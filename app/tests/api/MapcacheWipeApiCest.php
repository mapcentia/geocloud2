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

    // sqlite: the tileset's tiles are emptied in place (DELETE FROM tiles), NOT unlinked, so a
    // MapCache instance that this process can't reload keeps the same file and re-seeds on demand.
    public function fullSqliteWipeEmptiesInPlace(ApiTester $I)
    {
        $this->setCache($I, 'sqlite');
        $dir = $this->mapcacheDir() . 'sqlite/' . $this->userId;
        @mkdir($dir, 0777, true);
        $file = $dir . '/' . $this->tileset . '.sqlite3';
        // A minimal MapCache sqlite cache: a "tiles" table with one row.
        $pdo = new PDO('sqlite:' . $file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tiles(tileset text, grid text, x integer, y integer, z integer, data blob, dim text, ctime datetime, primary key(tileset,grid,x,y,z,dim))');
        $pdo->exec("INSERT INTO tiles(tileset,grid,x,y,z,dim) VALUES('t','g',0,0,0,'')");
        $pdo = null;
        exec('chown -R www-data:www-data ' . escapeshellarg($dir));

        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('sqlite', $data['backend']);
        $I->assertSame('wipe', $data['mode']);
        // The file survives (so MapCache's open handle stays valid) and the tiles are gone.
        $I->assertFileExists($file);
        $check = new PDO('sqlite:' . $file);
        $I->assertSame(0, (int)$check->query('SELECT count(*) FROM tiles')->fetchColumn());
    }

    // disk: the tileset directory is removed (renamed instantly, deleted in the background) → 202.
    // mbtiles end to end: MapCache accepts the generated config, renders a tile and stores it in
    // the tileset's own file for the grid.
    public function mbtilesCacheStoresTheTilesMapCacheServes(ApiTester $I)
    {
        $this->setCache($I, 'mbtiles');
        // What the cron job does every minute: publish the changed config to Apache.
        exec('php -f /var/www/geocloud2/app/scripts/mapcache_conf.php 2>&1', $out);
        $file = $this->mapcacheDir() . 'mbtiles/' . $this->userId . '/' . $this->tileset . '/g20.mbtiles';

        // Apache reloads gracefully, so the first requests may still see the old config.
        $code = null;
        for ($i = 0; $i < 20 && !is_file($file); $i++) {
            $ch = curl_init('http://localhost/api/v4/mapcache/database/' . $this->userId . '/wmts/1.0.0/' . $this->tileset . '/default/g20/8/136/78.png');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token]]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!is_file($file)) {
                usleep(500000);
            }
        }
        $I->assertFileExists($file, "no MBTiles file after a tile request (last status $code); mapcache_conf said: " . implode(' ', $out));
        $I->assertSame(200, $code);
        $pdo = new PDO('sqlite:' . $file);
        $I->assertGreaterThan(0, (int)$pdo->query('SELECT count(*) FROM tiles')->fetchColumn());
        $pdo = null;

        // A vector tile of the same layer is served too. MapCache's mbtiles cache cannot hold one
        // ("unrecognized image format"), so the .mvt tileset is kept in sqlite.
        $ch = curl_init('http://localhost/api/v4/mapcache/database/' . $this->userId . '/gmaps/' . $this->tileset . '.mvt@g20/8/136/78.mvt');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token]]);
        $body = curl_exec($ch);
        $I->assertSame(200, curl_getinfo($ch, CURLINFO_HTTP_CODE), 'vector tile: ' . substr((string)$body, 0, 300));
        $I->assertFileExists($this->mapcacheDir() . 'sqlite/' . $this->userId . '/' . $this->tileset . '.mvt.sqlite3');
        $I->assertDirectoryDoesNotExist($this->mapcacheDir() . 'mbtiles/' . $this->userId . '/' . $this->tileset . '.mvt');

        // And the wipe empties what MapCache wrote
        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame('mbtiles', json_decode($I->grabResponse(), true)['backend']);
        $I->assertSame(0, (int)(new PDO('sqlite:' . $file))->query('SELECT count(*) FROM tiles')->fetchColumn());
    }

    // mbtiles: one file per grid in the tileset's directory; every one is emptied in place.
    public function fullMbtilesWipeEmptiesEveryGridInPlace(ApiTester $I)
    {
        $this->setCache($I, 'mbtiles');
        $dir = $this->mapcacheDir() . 'mbtiles/' . $this->userId . '/' . $this->tileset;
        @mkdir($dir, 0777, true);
        $files = [$dir . '/g20.mbtiles', $dir . '/25832.mbtiles'];
        foreach ($files as $file) {
            @unlink($file);
            // The schema MapCache creates for an mbtiles cache
            $pdo = new PDO('sqlite:' . $file);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE images(tile_id text, tile_data blob, primary key(tile_id))');
            $pdo->exec('CREATE TABLE map(zoom_level integer, tile_column integer, tile_row integer, tile_id text, primary key(tile_row,tile_column,zoom_level))');
            $pdo->exec('CREATE VIEW tiles AS SELECT map.zoom_level, map.tile_column, map.tile_row, images.tile_data FROM map JOIN images ON images.tile_id = map.tile_id');
            $pdo->exec("INSERT INTO images VALUES('a', x'00')");
            $pdo->exec("INSERT INTO map VALUES(0, 0, 0, 'a')");
            $pdo = null;
        }
        // mkdir may just have created mbtiles/ itself, as root; MapCache must be able to add
        // the directories of other databases to it.
        exec('chown www-data:www-data ' . escapeshellarg($this->mapcacheDir() . 'mbtiles'));
        exec('chown -R www-data:www-data ' . escapeshellarg($this->mapcacheDir() . 'mbtiles/' . $this->userId));

        $this->del($I);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('mbtiles', $data['backend']);
        $I->assertSame('wipe', $data['mode']);
        $I->assertSame(2, $data['removed']);
        foreach ($files as $file) {
            $I->assertFileExists($file);
            $check = new PDO('sqlite:' . $file);
            $I->assertSame(0, (int)$check->query('SELECT count(*) FROM tiles')->fetchColumn());
            $I->assertSame(0, (int)$check->query('SELECT count(*) FROM images')->fetchColumn());
        }
    }

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

    /**
     * requireWrite() grants the tile-cache delete to a sub-user whose layer
     * privilege is `write`. That branch was unreachable until
     * Model::getGeometryColumns($layer, 'privileges') started returning the
     * privileges JSON (added for the snapshot read API): it returned null for
     * every field it did not know, so the privileges array was always empty
     * and every sub-user got 403 regardless of their grant. Pin both sides of
     * the now-live branch.
     */
    public function subUserNeedsWritePrivilegeToWipeTileset(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->deleteHeader('Authorization');
        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'mcw_sub_' . $ts, 'email' => 'mcwsub' . $ts . '@example.com',
            'password' => $this->password, 'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $subToken = json_decode($I->grabResponse())->access_token;

        // sqlite with no cache file yet: the wipe is a no-op 200, so the only
        // thing under test is the authorization gate.
        $this->setCache($I, 'sqlite');
        @unlink($this->mapcacheDir() . 'sqlite/' . $this->userId . '/' . $this->tileset . '.sqlite3');

        // Granted through the legacy layer controller, which is the path that
        // stores the "write" vocabulary requireWrite() matches (the v4
        // privileges API's Choice list is none/read/write).
        $grant = function (string $privilege) use ($I, $subUserId) {
            $I->deleteHeader('Authorization');
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 's1']));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->sendPUT('/controllers/layer/privileges', json_encode([
                'data' => ['subuser' => $subUserId, 'privileges' => $privilege, '_key_' => $this->layer],
            ]));
            $I->seeResponseCodeIs(HttpCode::OK);
        };

        $grant('read');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $subToken);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/' . $this->tileset);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'INSUFFICIENT_PRIVILEGES']);

        $grant('write');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $subToken);
        $I->sendDELETE('/api/v4/mapcache/database/' . $this->userId . '/tileset/' . $this->tileset);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('sqlite', $data['backend']);
        $I->assertSame('wipe', $data['mode']);
    }

    public function shouldCleanup(ApiTester $I)
    {
        @unlink($this->mapcacheDir() . $this->userId . '.xml');
    }
}
