<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Jwt;
use app\inc\Route;
use app\models\Layer;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Xmlworkspace
 * @package app\api\v3
 */
class Xmlworkspace extends Controller
{
    /**
     * @var Layer
     */
    private Layer $layers;

    /**
     *
     */
    function __construct()
    {
        parent::__construct();
        $this->layers = new Layer();
    }

    /**
     * @return never
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Get(
     *   path="/api/v3/xmlworkspace/{layer}",
     *   tags={"ESRI"},
     *   summary="Get XML Workspace document for ESRI software. Only schema - no data.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="layer",
     *     in="path",
     *     required=true,
     *     description="Layer identifier - schema qualified relation name",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="XML Workspace document",
     *     @OA\MediaType(
     *       mediaType="application/xml",
     *     )
     *   )
     * )
     */
    public function get_index(): never
    {
        // Get the URI params from request½
        $jwt = Jwt::validate()["data"];
        $db = $jwt["database"];
        $search = Route::getParam("layer");
        header('Content-type: application/xml; charset=utf-8');
        echo $this->create($search, $db);
        exit();
    }

    /**
     * @param string $query
     * @param string $db
     * @param array|null $include
     * @return string
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function create(string $query, string $db, array $include = null): string
    {
        $intTypes = ["integer"];
        $textTypes = ["text", "character varying"];
        $dateTypes = ["timestamp with time zone", "timestamp without time zone", "date"];
        $datasetName = explode(".", $query)[1];
        $arr = $this->layers->getAll($query, false, false, false, false, $db);
        $fields = $arr["data"][0]["fields"];
        $alreadyPassedDomains = [];

        ob_start();
        echo "<esri:Workspace xmlns:esri='http://www.esri.com/schemas/ArcGIS/10.8'
                xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xmlns:xs='http://www.w3.org/2001/XMLSchema'>";
        echo "<WorkspaceDefinition xsi:type='esri:WorkspaceDefinition'>";
        echo "    <WorkspaceType>esriLocalDatabaseWorkspace</WorkspaceType>";
        echo "    <Version></Version>";
        echo "    <Domains xsi:type='esri:ArrayOfDomain'>";
        foreach ($fields as $key => $field) {
            if ($include && !in_array($key, $include)) {
                continue;
            }
            if ($field["reference"] && !in_array($field["reference"], $alreadyPassedDomains)) {
                $referenceName = explode(".", $field["reference"])[1];
                echo "        <Domain xsi:type='esri:CodedValueDomain'>";
                echo "            <DomainName>$referenceName</DomainName>";
                echo "            <FieldType>esriFieldTypeInteger</FieldType>";
                echo "            <MergePolicy>esriMPTDefaultValue</MergePolicy>";
                echo "            <SplitPolicy>esriSPTDefaultValue</SplitPolicy>";
                echo "            <Description></Description>";
                echo "            <Owner></Owner>";
                echo "            <CodedValues xsi:type='esri:ArrayOfCodedValue'>";
                foreach ($field["restriction"] as $r) {
                    echo "                <CodedValue xsi:type='esri:CodedValue'>";
                    echo "                    <Name>{$r["alias"]}</Name>";
                    echo "                    <Code xsi:type='xs:int'>{$r["value"]}</Code>";
                    echo "                </CodedValue>";
                }
                echo "            </CodedValues>";
                echo "        </Domain>";
                $alreadyPassedDomains[] = $field["reference"];
            }
        }
        echo "    </Domains>";
        echo "    <DatasetDefinitions xsi:type='esri:ArrayOfDataElement'>";
        echo "        <DataElement xsi:type='esri:DEFeatureClass'>";
        echo "            <CatalogPath>/FC=$datasetName</CatalogPath>";
        echo "            <Name>$datasetName</Name>";
        echo "            <MetadataRetrieved>false</MetadataRetrieved>";
        echo "            <DatasetType>esriDTFeatureClass</DatasetType>";
        echo "            <DSID>3</DSID>";
        echo "            <Versioned>false</Versioned>";
        echo "            <CanVersion>false</CanVersion>";
        echo "            <ConfigurationKeyword></ConfigurationKeyword>";
        echo "            <HasOID>false</HasOID>";
//        echo "            <OIDFieldName>OBJECTID</OIDFieldName>";
        echo "            <Fields xsi:type='esri:Fields'>";
        echo "                <FieldArray xsi:type='esri:ArrayOfField'>";
        foreach ($fields as $key => $field) {
            if ($include && !in_array($key, $include)) {
                continue;
            }
            if (in_array($field["type"], $intTypes)) {
                $esriType = "esriFieldTypeInteger";
            } elseif (in_array($field["type"], $textTypes)) {
                $esriType = "esriFieldTypeString";
            } elseif (in_array($field["type"], $dateTypes)) {
                $esriType = "esriFieldTypeDate";
            } elseif ($field["type"] == "geometry") {
                $esriType = "esriFieldTypeGeometry";
                $key = "Shape";
            } else {
                $esriType = "esriFieldTypeString";
            }
            $length = $field["max_bytes"] ?? 0;
            $precision = $field["numeric_precision"] ?? 0;
            $scale = $field["numeric_scale"] ?? 0;

            echo "                    <Field xsi:type='esri:Field'>";
            echo "                      <Name>$key</Name>";
            echo "                      <Type>$esriType</Type>";
            echo "                      <IsNullable>true</IsNullable>";
            echo "                      <Length>$length</Length>";
            echo "                      <Precision>$precision</Precision>";
            echo "                      <Scale>$scale</Scale>";
            echo "                      <AliasName></AliasName>";
//            echo "                      <Required>true</Required>";
//            echo "                      <DefaultValue xsi:type='xs:string'></DefaultValue>";
            if ($esriType == "esriFieldTypeGeometry") {
                $geomType = "";
                switch ($field["geom_type"]) {
                    case "Point":
                    case "MultiPoint":
                        $geomType = "esriGeometryPoint";
                        break;
                    case "LineString":
                    case "MultiLineString":
                        $geomType = "esriGeometryPolyline";
                        break;
                    case "Polygon":
                    case "MultiPolygon":
                        $geomType = "esriGeometryPolygon";
                        break;

                }
                echo "                      <Required>true</Required>";
                echo "  
                            <GeometryDef xsi:type='esri:GeometryDef'>
                                <AvgNumPoints>0</AvgNumPoints>
                                <GeometryType>$geomType</GeometryType>
                                <HasM>false</HasM>
                                <HasZ>false</HasZ>
                                <SpatialReference xsi:type='esri:ProjectedCoordinateSystem'>
                                    <WKT>PROJCS[&quot;ETRS_1989_UTM_Zone_32N&quot;,GEOGCS[&quot;GCS_ETRS_1989&quot;,DATUM[&quot;D_ETRS_1989&quot;,SPHEROID[&quot;GRS_1980&quot;,6378137.0,298.257222101]],PRIMEM[&quot;Greenwich&quot;,0.0],UNIT[&quot;Degree&quot;,0.0174532925199433]],PROJECTION[&quot;Transverse_Mercator&quot;],PARAMETER[&quot;False_Easting&quot;,500000.0],PARAMETER[&quot;False_Northing&quot;,0.0],PARAMETER[&quot;Central_Meridian&quot;,9.0],PARAMETER[&quot;Scale_Factor&quot;,0.9996],PARAMETER[&quot;Latitude_Of_Origin&quot;,0.0],UNIT[&quot;Meter&quot;,1.0],AUTHORITY[&quot;EPSG&quot;,25832]]</WKT>
                                    <XOrigin>-5120900</XOrigin>
                                    <YOrigin>-9998100</YOrigin>
                                    <XYScale>10000</XYScale>
                                    <ZOrigin>-100000</ZOrigin>
                                    <ZScale>10000</ZScale>
                                    <MOrigin>-100000</MOrigin>
                                    <MScale>10000</MScale>
                                    <XYTolerance>0.001</XYTolerance>
                                    <ZTolerance>0.001</ZTolerance>
                                    <MTolerance>0.001</MTolerance>
                                    <HighPrecision>true</HighPrecision>
                                    <WKID>25832</WKID>
                                    <LatestWKID>25832</LatestWKID>
                                </SpatialReference>
                                <GridSize0>0</GridSize0>
                            </GeometryDef>
                ";
            }
            if ($field["reference"]) {
                $referenceName = explode(".", $field["reference"])[1];
                echo "        <Domain xsi:type='esri:CodedValueDomain'>";
                echo "            <DomainName>$referenceName</DomainName>";
                echo "            <FieldType>esriFieldTypeInteger</FieldType>";
                echo "            <MergePolicy>esriMPTDefaultValue</MergePolicy>";
                echo "            <SplitPolicy>esriSPTDefaultValue</SplitPolicy>";
                echo "            <Description></Description>";
                echo "            <Owner></Owner>";
                echo "            <CodedValues xsi:type='esri:ArrayOfCodedValue'>";
                foreach ($field["restriction"] as $r) {
                    echo "                <CodedValue xsi:type='esri:CodedValue'>";
                    echo "                    <Name>{$r["alias"]}</Name>";
                    echo "                    <Code xsi:type='xs:int'>{$r["value"]}</Code>";
                    echo "                </CodedValue>";
                }
                echo "            </CodedValues>";
                echo "        </Domain>";
                $alreadyPassedDomains[] = $field["reference"];
            }
            echo "                    </Field>";
        }
        echo "                </FieldArray>";
        echo "            </Fields>";
        echo "            <CLSID></CLSID>";
        echo "            <EXTCLSID></EXTCLSID>";
        echo "            <RelationshipClassNames xsi:type='esri:Names'></RelationshipClassNames>";
        echo "            <AliasName></AliasName>";
        echo "            <ModelName></ModelName>";
        echo "            <HasGlobalID>false</HasGlobalID>";
//        echo "            <GlobalIDFieldName>GlobalID</GlobalIDFieldName>";
        echo "            <RasterFieldName></RasterFieldName>";
        echo "            <ExtensionProperties xsi:type='esri:PropertySet'>";
        echo "                <PropertyArray xsi:type='esri:ArrayOfPropertySetProperty'></PropertyArray>";
        echo "            </ExtensionProperties>";
        echo "            <ControllerMemberships xsi:type='esri:ArrayOfControllerMembership'></ControllerMemberships>";
        echo "            <EditorTrackingEnabled>false</EditorTrackingEnabled>";
        echo "            <CreatorFieldName></CreatorFieldName>";
        echo "            <EditedAtFieldName></EditedAtFieldName>";
        echo "            <IsTimeInUTC>true</IsTimeInUTC>";
        echo "            <FeatureType>esriFTSimple</FeatureType>";
        echo "            <ShapeType>esriGeometryPoint</ShapeType>";
        echo "            <ShapeFieldName>Shape</ShapeFieldName>";
        echo "            <HasM>false</HasM>";
        echo "            <HasZ>false</HasZ>";
        echo "            <HasSpatialIndex>false</HasSpatialIndex>";
        echo "            <AreaFieldName></AreaFieldName>";
        echo "            <LengthFieldName></LengthFieldName>";
        echo "<Extent xsi:type='esri:EnvelopeN'>
                    <XMin>NaN</XMin>
                    <YMin>NaN</YMin>
                    <XMax>NaN</XMax>
                    <YMax>NaN</YMax>
                    <SpatialReference xsi:type='esri:ProjectedCoordinateSystem'>
                        <WKT>PROJCS[&quot;ETRS_1989_UTM_Zone_32N&quot;,GEOGCS[&quot;GCS_ETRS_1989&quot;,DATUM[&quot;D_ETRS_1989&quot;,SPHEROID[&quot;GRS_1980&quot;,6378137.0,298.257222101]],PRIMEM[&quot;Greenwich&quot;,0.0],UNIT[&quot;Degree&quot;,0.0174532925199433]],PROJECTION[&quot;Transverse_Mercator&quot;],PARAMETER[&quot;False_Easting&quot;,500000.0],PARAMETER[&quot;False_Northing&quot;,0.0],PARAMETER[&quot;Central_Meridian&quot;,9.0],PARAMETER[&quot;Scale_Factor&quot;,0.9996],PARAMETER[&quot;Latitude_Of_Origin&quot;,0.0],UNIT[&quot;Meter&quot;,1.0],AUTHORITY[&quot;EPSG&quot;,25832]]</WKT>
                        <XOrigin>-5120900</XOrigin>
                        <YOrigin>-9998100</YOrigin>
                        <XYScale>10000</XYScale>
                        <ZOrigin>-100000</ZOrigin>
                        <ZScale>10000</ZScale>
                        <MOrigin>-100000</MOrigin>
                        <MScale>10000</MScale>
                        <XYTolerance>0.001</XYTolerance>
                        <ZTolerance>0.001</ZTolerance>
                        <MTolerance>0.001</MTolerance>
                        <HighPrecision>true</HighPrecision>
                        <WKID>25832</WKID>
                        <LatestWKID>25832</LatestWKID>
                    </SpatialReference>
                </Extent>";
        echo "
            <SpatialReference xsi:type='esri:ProjectedCoordinateSystem'>
                    <WKT>PROJCS[&quot;ETRS_1989_UTM_Zone_32N&quot;,GEOGCS[&quot;GCS_ETRS_1989&quot;,DATUM[&quot;D_ETRS_1989&quot;,SPHEROID[&quot;GRS_1980&quot;,6378137.0,298.257222101]],PRIMEM[&quot;Greenwich&quot;,0.0],UNIT[&quot;Degree&quot;,0.0174532925199433]],PROJECTION[&quot;Transverse_Mercator&quot;],PARAMETER[&quot;False_Easting&quot;,500000.0],PARAMETER[&quot;False_Northing&quot;,0.0],PARAMETER[&quot;Central_Meridian&quot;,9.0],PARAMETER[&quot;Scale_Factor&quot;,0.9996],PARAMETER[&quot;Latitude_Of_Origin&quot;,0.0],UNIT[&quot;Meter&quot;,1.0],AUTHORITY[&quot;EPSG&quot;,25832]]</WKT>
                    <XOrigin>-5120900</XOrigin>
                    <YOrigin>-9998100</YOrigin>
                    <XYScale>10000</XYScale>
                    <ZOrigin>-100000</ZOrigin>
                    <ZScale>10000</ZScale>
                    <MOrigin>-100000</MOrigin>
                    <MScale>10000</MScale>
                    <XYTolerance>0.001</XYTolerance>
                    <ZTolerance>0.001</ZTolerance>
                    <MTolerance>0.001</MTolerance>
                    <HighPrecision>true</HighPrecision>
                    <WKID>25832</WKID>
                    <LatestWKID>25832</LatestWKID>
                </SpatialReference>";
        echo "            <ChangeTracked>false</ChangeTracked>";
        echo "            <ReplicaTracked>false</ReplicaTracked>";
        echo "            <FieldFilteringEnabled>false</FieldFilteringEnabled>";
        echo "            <FilteredFieldNames xsi:type='esri:Names'></FilteredFieldNames>";
        echo "        </DataElement>";
        echo "    </DatasetDefinitions>";
        echo "</WorkspaceDefinition>";
        echo "<WorkspaceData xsi:type='esri:WorkspaceData'></WorkspaceData>";
        echo "<WorkspaceData xsi:type='esri:WorkspaceData'></WorkspaceData>";
        echo "</esri:Workspace>";
        return ob_get_clean();
    }
}

