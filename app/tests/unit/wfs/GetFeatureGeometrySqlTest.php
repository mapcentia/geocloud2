<?php
namespace app\tests\unit\wfs;

use app\wfs\handlers\GetFeature;
use app\wfs\Request;
use Codeception\Test\Unit;

class GetFeatureGeometrySqlTest extends Unit
{
    private function req(string $outputFormat, string $version = '1.1.0', ?string $srsName = null): Request
    {
        return new Request(
            operation: 'GETFEATURE', version: $version, service: 'WFS', outputFormat: $outputFormat,
            typeNames: ['t'], properties: null, featureIds: null, bbox: null, resultType: null,
            srsName: $srsName, srs: 4326, maxFeatures: null, timeSlice: null,
            filter: null, transactionBody: null, rawPostBody: null,
        );
    }

    public function testGml3PointKeepsLegacyExpression(): void
    {
        $sql = GetFeature::geometrySql('the_geom', 'POINT', 4326, $this->req('GML3'));
        // longCrs(1) + flipAxis(16) + 4 = 21 for 1.1.0 + EPSG:4326
        $this->assertSame('ST_AsGml(3,ST_Transform(ST_CollectionExtract("the_geom",1),4326),7,21) as "the_geom"', $sql);
    }

    public function testGml2UnknownTypeKeepsLegacyExpression(): void
    {
        $sql = GetFeature::geometrySql('geom', 'GEOMETRY', 25832, $this->req('GML2', '1.0.0'));
        $this->assertSame('ST_AsGml(2,ST_Transform("geom",25832),5,4) as "geom"', $sql);
    }

    public function testGeoJsonUsesStAsGeoJson(): void
    {
        $sql = GetFeature::geometrySql('the_geom', 'POINT', 25832, $this->req('GEOJSON'));
        $this->assertSame('ST_AsGeoJSON(ST_Transform("the_geom",25832),9) as "the_geom"', $sql);
    }

    public function testGeoJsonFlipsAxesForEpsg4326Uri(): void
    {
        $sql = GetFeature::geometrySql('the_geom', 'POINT', 4326, $this->req('GEOJSON', srsName: Request::LATLON_4326_URI));
        $this->assertSame('ST_AsGeoJSON(ST_FlipCoordinates(ST_Transform("the_geom",4326)),9) as "the_geom"', $sql);
    }

    public function testGeoJsonDoesNotFlipForCrs84(): void
    {
        $sql = GetFeature::geometrySql('the_geom', 'POINT', 4326, $this->req('GEOJSON', srsName: 'http://www.opengis.net/def/crs/OGC/1.3/CRS84'));
        $this->assertSame('ST_AsGeoJSON(ST_Transform("the_geom",4326),9) as "the_geom"', $sql);
    }
}
