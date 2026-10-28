<?php

use Codeception\Util\HttpCode;

/**
 * WFS-T against a layer whose "Editable" switch (settings.geometry_columns_join.editable)
 * is off must fail with an exception report, not report a successful transaction that
 * saved nothing (issue #171). A request for a layer that does not exist must say so,
 * not "Layer is not enabled".
 */
class WfsTransactionEditableApiCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $token;
    private $schemaName;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Wfs editable test user ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'wfseditabletest' . $this->date->getTimestamp() . '@example.com';
        $this->schemaName = 'wfs_editable_test_' . $this->date->getTimestamp();
    }

    private function endpoint(): string
    {
        return '/api/v4/wfs/schema/' . $this->schemaName . '/database/' . $this->userId . '/srs/4326';
    }

    private function ns(): string
    {
        return 'http://localhost/' . $this->userId . '/' . $this->schemaName;
    }

    private function insertXml(string $layer, string $name): string
    {
        $ns = $this->ns();
        return '<Transaction xmlns="http://www.opengis.net/wfs" service="WFS" version="1.1.0"
             xmlns:gml="http://www.opengis.net/gml">
                <Insert xmlns="http://www.opengis.net/wfs">
                    <' . $layer . ' xmlns="' . $ns . '">
                        <name xmlns="' . $ns . '">' . $name . '</name>
                        <the_geom xmlns="' . $ns . '">
                            <gml:Point srsName="urn:ogc:def:crs:EPSG::4326">
                                <gml:pos>55.7 9.5</gml:pos>
                            </gml:Point>
                        </the_geom>
                    </' . $layer . '>
                </Insert>
            </Transaction>';
    }

    private function updateXml(string $name): string
    {
        return '<Transaction xmlns="http://www.opengis.net/wfs" service="WFS" version="1.1.0"
             xmlns:s="' . $this->ns() . '">
                <Update typeName="s:poi">
                    <Property><Name>s:name</Name><Value>' . $name . '</Value></Property>
                    <Filter xmlns="http://www.opengis.net/ogc"><FeatureId fid="poi.1"/></Filter>
                </Update>
            </Transaction>';
    }

    private function deleteXml(): string
    {
        return '<Transaction xmlns="http://www.opengis.net/wfs" service="WFS" version="1.1.0"
             xmlns:s="' . $this->ns() . '">
                <Delete typeName="s:poi">
                    <Filter xmlns="http://www.opengis.net/ogc"><FeatureId fid="poi.1"/></Filter>
                </Delete>
            </Transaction>';
    }

    /** Names of the rows in poi, read straight from the table. */
    private function names(ApiTester $I): array
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => 'SELECT name FROM ' . $this->schemaName . '.poi ORDER BY gid',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        return array_column(json_decode($I->grabResponse(), true)['data'], 'name');
    }

    private function setEditable(ApiTester $I, bool $editable): void
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.poi.the_geom', json_encode([
            'data' => ['editable' => $editable, '_key_' => $this->schemaName . '.poi.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    private function transact(ApiTester $I, string $xml): string
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'text/xml');
        $I->sendPOST($this->endpoint(), $xml);
        $I->seeResponseCodeIs(HttpCode::OK);
        return $I->grabResponse();
    }

    public function shouldPrepareUserSchemaAndLayer(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName, 'email' => $this->userEmail, 'password' => $this->password,
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
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function shouldInsertWhileEditable(ApiTester $I)
    {
        $body = $this->transact($I, $this->insertXml('poi', 'first'));
        $I->assertStringContainsString('<wfs:totalInserted>1</wfs:totalInserted>', $body);
        $I->assertSame(['first'], $this->names($I));
    }

    public function shouldTurnEditableOff(ApiTester $I)
    {
        $this->setEditable($I, false);
    }

    public function shouldRefuseInsertWhenNotEditable(ApiTester $I)
    {
        $body = $this->transact($I, $this->insertXml('poi', 'second'));
        $I->assertStringContainsString('ExceptionReport', $body);
        $I->assertStringContainsString('Layer is not editable', $body);
        $I->assertStringNotContainsString('TransactionResponse', $body);
        $I->assertSame(['first'], $this->names($I));
    }

    public function shouldRefuseUpdateWhenNotEditable(ApiTester $I)
    {
        $body = $this->transact($I, $this->updateXml('changed'));
        $I->assertStringContainsString('Layer is not editable', $body);
        $I->assertStringNotContainsString('TransactionResponse', $body);
        $I->assertSame(['first'], $this->names($I));
    }

    public function shouldRefuseDeleteWhenNotEditable(ApiTester $I)
    {
        $body = $this->transact($I, $this->deleteXml());
        $I->assertStringContainsString('Layer is not editable', $body);
        $I->assertStringNotContainsString('TransactionResponse', $body);
        $I->assertSame(['first'], $this->names($I));
    }

    // The v4 feature API drives the same WFS-T handler; the refusal must surface
    // as an error, not as a 201 that saved nothing.
    public function shouldRefuseFeatureApiInsertWhenNotEditable(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/features', json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['name' => 'via_feature_api'],
                'geometry' => ['type' => 'Point', 'coordinates' => [9.5, 55.7]],
            ]],
        ]));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'LAYER_NOT_EDITABLE']);
        $I->assertSame(['first'], $this->names($I));
    }

    // The v2 feature API, which Vidi's editor uses, posts WFS-T to the legacy /wfs
    // route. It used to answer success: true with totalInserted 0 (issue #171).
    public function shouldRefuseV2FeatureApiInsertWhenNotEditable(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        // v2 feature authorizes with the API key, not the session
        $I->haveHttpHeader('GC2-API-KEY', json_decode($I->grabResponse())->data->api_key);
        $I->sendPOST('/api/v2/feature/' . $this->userId . '/' . $this->schemaName . '.poi.the_geom/4326', json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['name' => 'via_v2'],
                'geometry' => ['type' => 'Point', 'coordinates' => [9.5, 55.7]],
            ]],
        ]));
        $I->deleteHeader('GC2-API-KEY');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['success' => false, 'code' => 403]);
        $I->assertStringContainsString('Layer is not editable', $I->grabResponse());
        $I->assertSame(['first'], $this->names($I));
    }

    public function shouldEditAgainWhenEditableIsTurnedBackOn(ApiTester $I)
    {
        $this->setEditable($I, true);
        $body = $this->transact($I, $this->updateXml('changed'));
        $I->assertStringContainsString('<wfs:totalUpdated>1</wfs:totalUpdated>', $body);
        $I->assertSame(['changed'], $this->names($I));
    }

    public function shouldSayLayerDoesNotExistOnTransaction(ApiTester $I)
    {
        $body = $this->transact($I, $this->insertXml('no_such_layer', 'x'));
        $I->assertStringContainsString('Layer does not exist', $body);
        $I->assertStringNotContainsString('Layer is not enabled', $body);
    }

    public function shouldSayLayerDoesNotExistOnGetFeature(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=no_such_layer');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertStringContainsString('Layer does not exist', $body);
        $I->assertStringNotContainsString('Layer is not enabled', $body);
    }
}
