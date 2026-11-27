<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * `qml` and `use_qml` on the v4 layer resource (app/api/v4/controllers/Layer.php,
 * app/models/LayerQml.php): storing a QML, switching the layer between QGIS Server (QML) and
 * MapServer (classes) without losing either, and what the map and the legend show in each state.
 * The layer's class is green and its QML is red, so the drawn colour tells who drew it.
 */
class LayerQmlV4ApiCest
{
    private const QML = '<qgis styleCategories="Symbology"><renderer-v2 type="singleSymbol" symbollevels="0" enableorderby="0" forceraster="0">'
    . '<symbols><symbol type="fill" name="0" alpha="1" clip_to_extent="1" force_rhr="0">'
    . '<layer class="SimpleFill" enabled="1" locked="0" pass="0">'
    . '<Option type="Map"><Option name="color" type="QString" value="255,0,0,255"/><Option name="style" type="QString" value="solid"/>'
    . '<Option name="outline_style" type="QString" value="no"/></Option>'
    . '<prop k="color" v="255,0,0,255"/><prop k="style" v="solid"/><prop k="outline_style" v="no"/>'
    . '</layer></symbol></symbols></renderer-v2></qgis>';

    private $ts;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;
    private $subToken;

    public function __construct()
    {
        $this->ts = time();
        $this->schemaName = 'qml_test_' . $this->ts;
    }

    private function key(): string
    {
        return $this->schemaName . '.areas.the_geom';
    }

    private function rel(): string
    {
        return $this->schemaName . '.areas';
    }

    private function bearer(ApiTester $I, ?string $token = null): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . ($token ?? $this->token));
    }

    private function patch(ApiTester $I, array $body): void
    {
        $this->bearer($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/layers/' . $this->key(), json_encode($body));
    }

    private function layer(ApiTester $I): array
    {
        $this->bearer($I);
        $I->sendGET('/api/v4/layers/' . $this->key());
        $I->seeResponseCodeIs(HttpCode::OK);
        return json_decode($I->grabResponse(), true);
    }

    /** RGB of the centre pixel of the layer's map. */
    private function mapColour(ApiTester $I): array
    {
        $this->bearer($I);
        $I->haveHttpHeader('Accept', '*/*');
        $I->sendGET('/api/v4/ows/schema/' . $this->schemaName . '/database/' . $this->userId
            . '?SERVICE=WMS&REQUEST=GetMap&VERSION=1.1.1&LAYERS=' . $this->rel()
            . '&SRS=EPSG:4326&BBOX=-1,-1,1,1&WIDTH=64&HEIGHT=64&FORMAT=image/png&STYLES=');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertSame("\x89PNG", substr($body, 0, 4), 'the map is a PNG, got: ' . substr($body, 0, 300));
        $im = imagecreatefromstring($body);
        $c = imagecolorsforindex($im, imagecolorat($im, 32, 32));
        return [$c['red'], $c['green'], $c['blue']];
    }

    private function legendClassNames(ApiTester $I): array
    {
        $this->bearer($I);
        $I->sendGET('/api/v4/legends/database/' . $this->userId . '/' . $this->rel());
        $I->seeResponseCodeIs(HttpCode::OK);
        return array_column(json_decode($I->grabResponse(), true)[0]['classes'], 'name');
    }

    private function assertGreen(ApiTester $I, array $rgb, string $why): void
    {
        [$r, $g, $b] = $rgb;
        $I->assertTrue($g > 200 && $r < 60 && $b < 60, "$why: expected green, got $r,$g,$b");
    }

    private function assertRed(ApiTester $I, array $rgb, string $why): void
    {
        [$r, $g, $b] = $rgb;
        $I->assertTrue($r > 200 && $g < 60 && $b < 60, "$why: expected red, got $r,$g,$b");
    }

    public function shouldPrepareUserAndLayer(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "qml super $this->ts", 'email' => "qmlsuper$this->ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->bearer($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'areas',
            // MapServer needs a unique column to draw from; "gid" is what GC2 assumes
            'columns' => [['name' => 'gid', 'type' => 'int4'], ['name' => 'the_geom', 'type' => 'geometry(Polygon,4326)']],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => "INSERT INTO $this->schemaName.areas (gid, the_geom) VALUES (1, ST_GeomFromText('POLYGON((-2 -2,2 -2,2 2,-2 2,-2 -2))', 4326))",
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendPOST('/api/v4/layers', json_encode([
            'name' => $this->key(),
            'classes' => [['name' => 'Green', 'sortid' => 10, 'styles' => [['color' => '#00FF00']]]],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // A sub-user, to check that the QML is the owner's to set
        $I->deleteHeader('Authorization');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "qml sub $this->ts", 'email' => "qmlsub$this->ts@example.com",
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
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    public function aNewLayerHasNoQmlAndIsDrawnByMapServer(ApiTester $I)
    {
        $layer = $this->layer($I);
        $I->assertArrayHasKey('qml', $layer);
        $I->assertNull($layer['qml']);
        $I->assertFalse($layer['use_qml']);
        $this->assertGreen($I, $this->mapColour($I), 'classes');
    }

    public function shouldRefuseToUseAQmlThatIsNotThere(ApiTester $I)
    {
        $this->patch($I, ['use_qml' => true]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'QML_MISSING']);
    }

    public function shouldRejectAQmlThatIsNotXml(ApiTester $I)
    {
        $this->patch($I, ['qml' => '<qgis><renderer-v2></qgis>']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_QML']);
        $this->patch($I, ['use_qml' => 'yes']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertNull($this->layer($I)['qml']);
    }

    public function shouldNotLetASubUserSetTheQml(ApiTester $I)
    {
        $this->bearer($I, $this->subToken);
        $I->sendPATCH('/api/v4/layers/' . $this->key(), json_encode(['qml' => self::QML]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->assertNull($this->layer($I)['qml']);
    }

    public function storingAQmlDoesNotChangeTheDrawing(ApiTester $I)
    {
        $this->patch($I, ['qml' => self::QML]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertSame(self::QML, $layer['qml']);
        $I->assertFalse($layer['use_qml']);
        $this->assertGreen($I, $this->mapColour($I), 'QML stored but not used');
        $I->assertSame(['Green'], $this->legendClassNames($I));
    }

    public function switchingOnDrawsWithTheQml(ApiTester $I)
    {
        $this->patch($I, ['use_qml' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertTrue($layer['use_qml']);
        $I->assertSame(self::QML, $layer['qml']);
        $I->assertSame('Green', $layer['classes'][0]['name'], 'the classes are kept');
        $this->assertRed($I, $this->mapColour($I), 'QML in use');
        $I->assertSame([null], $this->legendClassNames($I), 'the legend is one picture from QGIS');
    }

    // The case that failed: a filter on the request (here from a LIMIT rule) for a QML layer
    public function aQmlLayerIsDrawnWhenARuleFiltersIt(ApiTester $I)
    {
        $this->bearer($I);
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => '*', 'service' => '*', 'request' => '*', 'access' => 'limit', 'filter' => '1=1',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        $this->assertRed($I, $this->mapColour($I), 'QML in use, with a rule filter');

        $this->bearer($I);
        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();
    }

    public function switchingOffDrawsWithTheClassesAndKeepsTheQml(ApiTester $I)
    {
        $this->patch($I, ['use_qml' => false]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertFalse($layer['use_qml']);
        $I->assertSame(self::QML, $layer['qml'], 'the QML is kept');
        $this->assertGreen($I, $this->mapColour($I), 'switched back to MapServer');
        $I->assertSame(['Green'], $this->legendClassNames($I));
    }

    public function qmlAndSwitchCanBeSentTogetherWithProperties(ApiTester $I)
    {
        $this->patch($I, ['qml' => self::QML, 'use_qml' => true, 'properties' => ['opacity' => '100']]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertTrue($layer['use_qml']);
        $I->assertSame('100', $layer['properties']['opacity']);
        $this->assertRed($I, $this->mapColour($I), 'switched on again');
    }

    public function removingTheQmlSwitchesItOff(ApiTester $I)
    {
        $this->patch($I, ['qml' => null]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertNull($layer['qml']);
        $I->assertFalse($layer['use_qml']);
        $this->assertGreen($I, $this->mapColour($I), 'QML removed');
    }

    public function aPatchWithoutQmlFieldsLeavesTheQmlAlone(ApiTester $I)
    {
        $this->patch($I, ['qml' => self::QML, 'use_qml' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $this->patch($I, ['properties' => ['opacity' => '90']]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $layer = $this->layer($I);
        $I->assertTrue($layer['use_qml']);
        $I->assertSame(self::QML, $layer['qml']);
    }
}
