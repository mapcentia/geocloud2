<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

class WfsV4Cest
{
    private string $db;
    private string $schema;
    private string $table;

    public function __construct()
    {
        $this->db     = getenv('GC2_TEST_DB')     ?: 'mydb';
        $this->schema = getenv('GC2_TEST_SCHEMA') ?: 'public';
        $this->table  = getenv('GC2_TEST_TABLE')  ?: 'polygon';
    }

    /**
     * Verifies the GetCapabilities handler returns real WFS capabilities XML
     * and that key structural markers are present.
     */
    public function getCapabilitiesReturnsRealCapabilities(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'text/xml; charset=UTF-8');
        $I->seeResponseContains('<wfs:WFS_Capabilities');
        $I->seeResponseContains('<FeatureTypeList>');
        $I->seeResponseContains('<ows:Operation name="GetFeature">');
    }

    public function getCapabilitiesMatchesGoldenFile(\ApiTester $I): void
    {
        $golden = file_get_contents(codecept_data_dir('wfs/golden/getcapabilities-1_1_0.xml'));
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $body = preg_replace('/timeStamp="[^"]*"/', 'timeStamp="REDACTED"', $body);
        $body = preg_replace('/Memory used: \d+ KB/', 'Memory used: REDACTED', $body);
        $I->assertSame($golden, $body);
    }

    public function getFeatureReturnsFeatureCollection(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$this->table}&maxFeatures=2");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('<wfs:FeatureCollection');
        $I->seeResponseContains('numberOfFeatures="2"');
        $I->seeResponseContains('<gml:featureMembers>');
        $I->seeResponseContains('<' . $this->schema . ':' . $this->table . ' gml:id="' . $this->table . '.0">');
    }

    public function getFeatureMatchesGoldenFile(\ApiTester $I): void
    {
        $golden = file_get_contents(codecept_data_dir('wfs/golden/getfeature-polygon-1_1_0-v4.xml'));
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$this->table}&maxFeatures=2");
        $body = preg_replace('/timeStamp="[^"]*"/', 'timeStamp="REDACTED"', $I->grabResponse());
        $I->assertSame($golden, $body);
    }

    public function describeFeatureTypeReturnsXsd(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=DescribeFeatureType&typeName={$this->table}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('<xsd:schema');
        $I->seeResponseContains('targetNamespace="http://localhost/' . $this->db . '/' . $this->schema . '"');
        $I->seeResponseContains('<xsd:complexType name="' . $this->table . 'Type">');
        $I->seeResponseContains('substitutionGroup="gml:_Feature"');
    }

    public function describeFeatureTypeMatchesGoldenFile(\ApiTester $I): void
    {
        $golden = file_get_contents(codecept_data_dir('wfs/golden/describefeaturetype-polygon-1_1_0-v4.xml'));
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=DescribeFeatureType&typeName={$this->table}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($golden, $I->grabResponse());
    }

    /**
     * An invalid REQUEST value should produce an OWS exception report,
     * not a stub. This proves the Server-level error handling is wired up.
     */
    public function unknownOperationReturnsExceptionReport(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=NonsenseOperation");
        $I->seeResponseCodeIs(HttpCode::OK);   // WFS uses 200 for exception reports
        $I->seeResponseContains('<ows:ExceptionReport');
        $I->seeResponseContains('OperationNotSupported');
    }

    /**
     * Missing service parameter — protocol validation.
     */
    public function missingServiceReturnsExceptionReport(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?version=1.1.0&request=GetCapabilities");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('<ows:ExceptionReport');
    }
}
