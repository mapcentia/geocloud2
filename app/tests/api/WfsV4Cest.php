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
     * Verifies the StreamedResponse → Route2 → Wfs controller → Server → handler
     * chain works end-to-end. At this point handlers are stubs returning a
     * comment that identifies which one ran.
     */
    public function getCapabilitiesReachesStubHandler(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetCapabilities");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'text/xml; charset=UTF-8');
        $I->seeResponseContains('GetCapabilities not yet implemented');
    }

    public function getFeatureReachesStubHandler(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=GetFeature&typeName={$this->table}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('GetFeature not yet implemented');
    }

    public function describeFeatureTypeReachesStubHandler(\ApiTester $I): void
    {
        $I->sendGet("/api/v4/wfs/{$this->db}/{$this->schema}/4326?service=WFS&version=1.1.0&request=DescribeFeatureType&typeName={$this->table}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('DescribeFeatureType not yet implemented');
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
