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
        // A second schema whose only layer is public, so a merged schema tileset can
        // be shown to pass authorization and not merely to be refused.
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => 's2']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/s2/tables', json_encode([
            'name' => 'openroads', 'columns' => [['name' => 'the_geom', 'type' => 'geometry(LineString,4326)']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => 's2.openroads.the_geom',
            'classes' => [['name' => 'All', 'sortid' => 10, 'styles' => [['color' => '#008000', 'width' => '1']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
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

    /**
     * A merged per-schema tileset is drawn from every layer in the schema, so it
     * inherits the strictest one's requirement: s1 contains the Read/write
     * s1.protroads, so the bare "s1" tileset must be challenged too.
     *
     * Without the expansion this answered 200: the bare name authorizes nothing,
     * because getGeometryColumns() finds no layer called "s1" and the anonymous
     * branch then treats it as readable. That made the merged tileset a way to
     * read a protected layer's pixels without credentials.
     */
    public function schemaTilesetAnonymousIsChallengedWhenItHoldsAProtectedLayer(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('s1'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        // And over TMS, where the grid is glued to the name with "@".
        $I->sendGET($this->tms('s1'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function schemaTilesetWithCorrectBasicPassesAuth(ApiTester $I)
    {
        $this->basic($I, $this->userId, $this->password);
        $I->sendGET($this->wmtsRestful('s1'));
        $this->seePastAuth($I);
    }

    /**
     * The case that was reported broken: a schema whose layers are all readable
     * must serve its merged tileset. It used to answer 403 "Could not resolve
     * tileset for authorization" through every service, because a name without a
     * dot was discarded before authorization could happen.
     */
    public function schemaTilesetOfAPublicSchemaPassesAuth(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('s2'));
        $this->seePastAuth($I);
        $I->sendGET($this->tms('s2'));
        $this->seePastAuth($I);
        // The vector variant resolves to the same schema.
        $I->sendGET($this->tms('s2.mvt'));
        $this->seePastAuth($I);
    }

    /**
     * A tile fetch whose tileset resolves to nothing fails closed. A name without a
     * dot is now read as a schema, so this is unresolvable because no such schema
     * has layers — not because it lacks a dot.
     */
    public function unresolvableTilesetFailsClosed(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('notqualified'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    /**
     * SECURITY regression. MapCache's gmaps URL is gmaps/{tileset}@{grid}/…, so the
     * proxy used to read the layer name as "s1.protroads@g20", match no layer, and
     * fall through to "readable anonymously" — serving a Read/write layer's tiles
     * to an unauthenticated caller (measured: 200 image/png, 7954 bytes, on a real
     * protected layer). Present since the proxy was introduced in 731bf91a.
     */
    public function gmapsProtectedLayerAnonymousIsChallenged(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->gmaps('s1.protroads'));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function gmapsProtectedLayerCorrectBasicPassesAuth(ApiTester $I)
    {
        $this->basic($I, $this->userId, $this->password);
        $I->sendGET($this->gmaps('s1.protroads'));
        $this->seePastAuth($I);
    }

    /**
     * A name that looks like a layer but is none must fail closed, not fall through
     * to "readable anonymously". That fall-through is what made the gmaps hole
     * exploitable, and it would reopen it the moment any parser gap appears.
     */
    public function aDottedNameThatIsNoLayerFailsClosed(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->wmtsRestful('s1.no_such_table'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendGET($this->wmtsRestful('nosuchschema.nosuchtable'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        // And with a valid token: unresolvable is unresolvable, not a privilege question.
        $this->bearer($I);
        $I->sendGET($this->wmtsRestful('s1.no_such_table'));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    private function gmaps(string $tileset): string
    {
        return '/api/v4/mapcache/database/' . $this->userId . '/gmaps/' . $tileset . '@g20/8/136/78.png';
    }

    private function tms(string $tileset): string
    {
        return '/api/v4/mapcache/database/' . $this->userId . '/tms/1.0.0/' . $tileset . '@g20/8/136/78.png';
    }
}
