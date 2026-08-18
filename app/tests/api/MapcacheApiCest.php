<?php

use Codeception\Util\HttpCode;

/**
 * The v4 MapCache proxy (api/v4/mapcache/database/{database}/...) authorizes every tile request
 * against the requested tileset's layer before forwarding to the MapCache backend, mirroring the
 * OWS proxy. This suite proves the auth decision for anonymous, HTTP Basic and Bearer identities
 * across a public and a protected (Read/write) layer, over both WMS-KVP and WMTS-RESTful URLs, and
 * that a tile request whose tileset cannot be resolved fails closed (403).
 *
 * "Past auth" is asserted as "not 401 and not 403": the freshly created database has no generated
 * MapCache config, so an authorized request forwards to the backend and comes back 200/404 — the
 * point is only that it was not rejected by the auth layer.
 */
class MapcacheApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;

    public function __construct()
    {
        $this->date = new DateTime();
    }

    private function wms(string $layer): string
    {
        return '/api/v4/mapcache/database/' . $this->userId . '/wms?'
            . 'SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $layer
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=32&HEIGHT=32&FORMAT=image/png&STYLES=';
    }

    private function wmtsRestful(string $tileset): string
    {
        return '/api/v4/mapcache/database/' . $this->userId . '/wmts/1.0.0/' . $tileset . '/default/g20/8/136/78.png';
    }

    private function anonymous(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Cookie');
    }

    private function basic(ApiTester $I, string $user, string $pw): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pw));
    }

    private function bearer(ApiTester $I): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function seePastAuth(ApiTester $I): void
    {
        $I->dontSeeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->dontSeeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'mc_owner_' . $ts, 'email' => 'mcowner' . $ts . '@example.com', 'password' => $this->password,
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
        foreach (['pubroads', 'protroads'] as $t) {
            $I->sendPOST('/api/v4/schemas/s1/tables', json_encode([
                'name' => $t, 'columns' => [['name' => 'the_geom', 'type' => 'geometry(LineString,4326)']],
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
            $I->sendPOST('/api/v4/layers', json_encode([
                'name' => 's1.' . $t . '.the_geom',
                'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
        }
        $I->deleteHeader('Authorization');

        // Protect s1.protroads (Read/write) up front, so the anonymous deny cases are never served
        // (and therefore never cached as an allow).
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 's1',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPUT('/controllers/layer/records/s1.protroads.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => 's1.protroads.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
        $I->sendGET('/api/v2/session/stop');
    }

    // Public (None) layer is readable anonymously — the request passes auth and is forwarded.
    public function publicLayerAnonymousPassesAuth(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wms('s1.pubroads'));
        $this->seePastAuth($I);
    }

    // Protected (Read/write) layer, anonymous → HTTP Basic challenge.
    public function protectedLayerAnonymousIsChallenged(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wms('s1.protroads'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function protectedLayerWrongBasicIsRejected(ApiTester $I)
    {
        $this->basic($I, $this->userId, 'definitely-wrong');
        $I->sendGET($this->wms('s1.protroads'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function protectedLayerCorrectBasicPassesAuth(ApiTester $I)
    {
        $this->basic($I, $this->userId, $this->password);
        $I->sendGET($this->wms('s1.protroads'));
        $this->seePastAuth($I);
    }

    public function protectedLayerBearerPassesAuth(ApiTester $I)
    {
        $this->bearer($I);
        $I->sendGET($this->wms('s1.protroads'));
        $this->seePastAuth($I);
    }

    // Same authorization applies to WMTS RESTful tile URLs (tileset in the path).
    public function wmtsRestfulProtectedAnonymousIsChallenged(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('s1.protroads'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    // A tile fetch whose tileset can't be resolved to a layer fails closed.
    public function unresolvableTilesetFailsClosed(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('notqualified'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
