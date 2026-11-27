<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * GET /api/v4/legends/database/{database}/{layers} (app/api/v4/controllers/Legend.php).
 *
 * Checks the shape, that each class gets the icon of its own MapServer class (by colour), and
 * the access model: a legend follows read access to its layer, a layer without access is left
 * out instead of failing the request, and geofence deny rules do not hide a legend.
 */
class LegendV4ApiCest
{
    private $ts;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;
    private $subUserId;
    private $subToken;

    public function __construct()
    {
        $this->ts = time();
        $this->schemaName = 'legend_test_' . $this->ts;
    }

    private function uri(string $layers): string
    {
        return '/api/v4/legends/database/' . $this->userId . '/' . $layers;
    }

    private function areas(): string
    {
        return $this->schemaName . '.areas';
    }

    private function lakes(): string
    {
        return $this->schemaName . '.lakes';
    }

    private function anonymous(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    private function bearer(ApiTester $I, string $token): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $token);
    }

    private function ownerSession(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
    }

    /** @return list<string> the layer ids of the answer, which must be a bare array */
    private function ids(ApiTester $I, string $layers): array
    {
        $I->sendGET($this->uri($layers));
        $I->seeResponseCodeIs(HttpCode::OK);
        $legends = json_decode($I->grabResponse(), true);
        $I->assertIsArray($legends);
        $I->assertTrue(array_is_list($legends), 'the response is a bare JSON array');
        return array_column($legends, 'id');
    }

    /** RGB of the centre pixel of a base64 PNG. */
    private function centre(ApiTester $I, ?string $img): array
    {
        $I->assertNotEmpty($img, 'the class has an icon');
        $bytes = base64_decode($img, true);
        $I->assertSame("\x89PNG", substr($bytes, 0, 4), 'img is a base64 PNG');
        $im = imagecreatefromstring($bytes);
        $c = imagecolorsforindex($im, imagecolorat($im, intdiv(imagesx($im), 2), intdiv(imagesy($im), 2)));
        return [$c['red'], $c['green'], $c['blue']];
    }

    public function shouldPrepareUserSchemaAndLayers(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "legend super $this->ts", 'email' => "legendsuper$this->ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->bearer($I, $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        foreach (['areas', 'lakes'] as $table) {
            $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
                'name' => $table,
                'columns' => [
                    ['name' => 'kind', 'type' => 'varchar'],
                    ['name' => 'the_geom', 'type' => 'geometry(Polygon,4326)'],
                ],
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
        }
        // Stored out of order: the mapfile has them by sortid (Green, Blue, Red), so "Red" is
        // MapServer's class 2 although it is stored first.
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->areas() . '.the_geom',
            'classes' => [
                ['name' => 'Red', 'sortid' => 30, 'expression' => "'[kind]'='r'", 'styles' => [['color' => '#FF0000']]],
                ['name' => 'Green', 'sortid' => 10, 'expression' => "'[kind]'='g'", 'styles' => [['color' => '#00FF00']]],
                ['name' => 'Blue', 'sortid' => 20, 'styles' => [['color' => '#0000FF']]],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->lakes() . '.the_geom',
            'classes' => [['name' => 'Water', 'sortid' => 10, 'styles' => [['color' => '#0000FF']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // Viewer password, for HTTP Basic
        $this->ownerSession($I);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldReturnLegendsAnonymouslyForOpenLayers(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->uri($this->areas() . ',' . $this->lakes()));
        $I->seeResponseCodeIs(HttpCode::OK);
        $legends = json_decode($I->grabResponse(), true);
        $I->assertTrue(array_is_list($legends), 'the response is a bare JSON array');
        $I->assertSame([$this->areas(), $this->lakes()], array_column($legends, 'id'), 'in the order asked for');

        $areas = $legends[0];
        $I->assertSame(['id', 'title', 'classes'], array_keys($areas));
        $I->assertSame('areas', $areas['title'], 'the table name stands in for a missing title');
        $I->assertSame(['Green', 'Blue', 'Red'], array_column($areas['classes'], 'name'), 'by sortid');
        $I->assertSame(["'[kind]'='g'", null, "'[kind]'='r'"], array_column($areas['classes'], 'expression'),
            'no expression is null, not an empty string');
        $I->assertSame(['name', 'expression', 'img'], array_keys($areas['classes'][0]));

        // Each class carries the icon of its own MapServer class
        [$r, $g, $b] = $this->centre($I, $areas['classes'][0]['img']);
        $I->assertTrue($g > 200 && $r < 60 && $b < 60, "Green's icon is green, got $r,$g,$b");
        [$r, $g, $b] = $this->centre($I, $areas['classes'][1]['img']);
        $I->assertTrue($b > 200 && $r < 60 && $g < 60, "Blue's icon is blue, got $r,$g,$b");
        [$r, $g, $b] = $this->centre($I, $areas['classes'][2]['img']);
        $I->assertTrue($r > 200 && $g < 60 && $b < 60, "Red's icon is red, got $r,$g,$b");

        $lakes = $legends[1];
        $I->assertSame(['Water'], array_column($lakes['classes'], 'name'));
        [$r, $g, $b] = $this->centre($I, $lakes['classes'][0]['img']);
        $I->assertTrue($b > 200 && $r < 60 && $g < 60, "Water's icon is blue, got $r,$g,$b");
    }

    public function shouldReturnASingleLayerAsAnArrayToo(ApiTester $I)
    {
        $this->anonymous($I);
        $I->assertSame([$this->lakes()], $this->ids($I, $this->lakes()));
    }

    public function shouldExpandASchemaToItsLayers(ApiTester $I)
    {
        $this->anonymous($I);
        $I->assertSame([$this->areas(), $this->lakes()], $this->ids($I, $this->schemaName));
        // A layer named twice, directly and through its schema, is answered once
        $I->assertSame([$this->lakes(), $this->areas()], $this->ids($I, $this->lakes() . ',' . $this->schemaName));
    }

    public function shouldLeaveOutUnknownLayers(ApiTester $I)
    {
        $this->anonymous($I);
        $I->assertSame([$this->lakes()], $this->ids($I, $this->schemaName . '.nope,' . $this->lakes() . ',nope_schema'));
        $I->assertSame([], $this->ids($I, 'nope_schema.nope'));
    }

    public function shouldReturn400ForAMalformedLayerList(ApiTester $I)
    {
        $this->anonymous($I);
        foreach ([$this->lakes() . ',', $this->lakes() . '.the_geom', 'a%20b.c', "a.b'c"] as $layers) {
            $I->sendGET($this->uri($layers));
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
        }
    }

    public function shouldReturn404ForAnUnknownDatabase(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET('/api/v4/legends/database/no_such_database_' . $this->ts . '/public.x');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'DATABASE_NOT_FOUND', 'message' => 'Database not found']);
        // Not the driver's message, which names the database server
        $I->assertStringNotContainsString('SQLSTATE', $I->grabResponse());
    }

    public function shouldPickUpAChangedClass(ApiTester $I)
    {
        // The legend is cached per layer; a class change must not leave the old one standing
        $this->bearer($I, $this->token);
        $I->sendGET('/api/v4/layers/' . $this->lakes() . '.the_geom/classes');
        $I->seeResponseCodeIs(HttpCode::OK);
        $classes = json_decode($I->grabResponse(), true);
        $classId = (array_is_list($classes) ? $classes[0] : $classes)['id'];
        $I->sendPATCH('/api/v4/layers/' . $this->lakes() . '.the_geom/classes/' . $classId, json_encode(['name' => 'Lake']));
        $I->seeResponseCodeIsSuccessful();

        $this->anonymous($I);
        $I->stopFollowingRedirects();
        $I->sendGET($this->uri($this->lakes()));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['Lake'], array_column(json_decode($I->grabResponse(), true)[0]['classes'], 'name'));
    }

    public function shouldNotLetAGeofenceDenyRuleHideALegend(ApiTester $I)
    {
        $this->bearer($I, $this->token);
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => '*', 'service' => '*', 'request' => '*', 'access' => 'deny',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        // The rule does bite where rules apply: the map of the same layer is denied
        $this->anonymous($I);
        $I->sendGET('/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId
            . '?SERVICE=WMS&REQUEST=GetMap&VERSION=1.3.0&LAYERS=' . $this->lakes()
            . '&CRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=8&HEIGHT=8&FORMAT=image/png&STYLES=');
        $I->assertStringContainsString('DENY', $I->grabResponse());

        // ... but not the legend
        $this->anonymous($I);
        $I->assertSame([$this->areas(), $this->lakes()], $this->ids($I, $this->areas() . ',' . $this->lakes()));

        $this->bearer($I, $this->token);
        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();
    }

    public function shouldProtectAreas(ApiTester $I)
    {
        $this->ownerSession($I);
        $I->sendPUT('/controllers/layer/records/' . $this->areas() . '.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->areas() . '.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldLeaveOutAProtectedLayerForAnonymous(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET($this->uri($this->areas() . ',' . $this->lakes()));
        // Skipped, not refused: no 401/403 and no Basic challenge
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeHttpHeader('WWW-Authenticate');
        $I->assertSame([$this->lakes()], array_column(json_decode($I->grabResponse(), true), 'id'));

        $I->assertSame([], $this->ids($I, $this->areas()), 'only a protected layer asked for: an empty array');
        $I->assertSame([$this->lakes()], $this->ids($I, $this->schemaName));
    }

    public function shouldReturnAProtectedLayerToTheOwner(ApiTester $I)
    {
        $both = [$this->areas(), $this->lakes()];
        $this->bearer($I, $this->token);
        $I->assertSame($both, $this->ids($I, $this->areas() . ',' . $this->lakes()));

        $this->anonymous($I);
        $I->amHttpAuthenticated($this->userId, $this->password);
        $I->assertSame($both, $this->ids($I, $this->areas() . ',' . $this->lakes()));
        $I->deleteHeader('Authorization');
    }

    public function shouldRejectWrongCredentials(ApiTester $I)
    {
        // Wrong credentials are not the same as none: they are refused, as on the OWS endpoint
        $this->anonymous($I);
        $I->amHttpAuthenticated($this->userId, 'WrongPassword1');
        $I->sendGET($this->uri($this->lakes()));
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->deleteHeader('Authorization');
    }

    public function shouldRejectAnInvalidToken(ApiTester $I)
    {
        $this->bearer($I, 'not.a.token');
        $I->sendGET($this->uri($this->lakes()));
        $I->seeResponseCodeIsClientError();
        $I->seeResponseContainsJson(['success' => false]);
        $I->deleteHeader('Authorization');
    }

    public function shouldPrepareSubUser(ApiTester $I)
    {
        $this->ownerSession($I);
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "legend sub $this->ts", 'email' => "legendsub$this->ts@example.com",
            'password' => $this->password, 'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;

        // The sub-user's viewer password, for HTTP Basic
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->subUserId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldLeaveOutAProtectedLayerForASubUserWithoutPrivilege(ApiTester $I)
    {
        $this->bearer($I, $this->subToken);
        $I->assertSame([$this->lakes()], $this->ids($I, $this->areas() . ',' . $this->lakes()));

        $this->anonymous($I);
        $I->amHttpAuthenticated($this->subUserId, $this->password);
        $I->sendGET($this->uri($this->areas() . ',' . $this->lakes()));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeHttpHeader('WWW-Authenticate');
        $I->assertSame([$this->lakes()], array_column(json_decode($I->grabResponse(), true), 'id'));
        $I->deleteHeader('Authorization');
    }

    public function shouldGrantReadToTheSubUser(ApiTester $I)
    {
        $this->ownerSession($I);
        $I->sendPUT('/controllers/layer/privileges', json_encode([
            'data' => ['subuser' => $this->subUserId, 'privileges' => 'read', '_key_' => $this->areas() . '.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldReturnAProtectedLayerToASubUserWithRead(ApiTester $I)
    {
        $both = [$this->areas(), $this->lakes()];
        // Still left out for someone without credentials
        $this->anonymous($I);
        $I->assertSame([$this->lakes()], $this->ids($I, $this->areas() . ',' . $this->lakes()));

        $this->bearer($I, $this->subToken);
        $I->assertSame($both, $this->ids($I, $this->areas() . ',' . $this->lakes()));

        // Last: Basic credentials stay on the test client for the rest of a test
        $this->anonymous($I);
        $I->amHttpAuthenticated($this->subUserId, $this->password);
        $I->assertSame($both, $this->ids($I, $this->areas() . ',' . $this->lakes()));
    }

    public function shouldNotLetADenyRuleForTheSubUserHideALegend(ApiTester $I)
    {
        $this->bearer($I, $this->token);
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => $this->subUserId, 'service' => '*', 'request' => '*', 'access' => 'deny',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        $this->bearer($I, $this->subToken);
        $I->assertSame([$this->areas(), $this->lakes()], $this->ids($I, $this->areas() . ',' . $this->lakes()));

        $this->bearer($I, $this->token);
        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();
    }

    public function shouldAnswerPreflight(ApiTester $I)
    {
        $this->anonymous($I);
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->sendOPTIONS($this->uri($this->lakes()));
        $I->seeResponseCodeIsSuccessful();
        $I->deleteHeader('Access-Control-Request-Method');
    }

    public function shouldRejectWrite(ApiTester $I)
    {
        $this->bearer($I, $this->token);
        $I->sendPOST($this->uri($this->lakes()), json_encode([]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }
}
