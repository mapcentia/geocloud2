<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\handlers;

use app\exceptions\OwsException;
use app\models\Setting;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;
use PDOException;

final class GetCapabilities implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    /**
     * @throws OwsException
     */
    public function handle(Request $req, GmlWriter $writer): void
    {
        $thePath        = $this->ctx->thePath;
        $gmlNameSpace   = $writer->gmlNameSpace;
        $gmlNameSpaceUri = $writer->gmlNameSpaceUri;
        $postgisschema  = $this->ctx->schema;
        $version        = $req->version;
        // Use path-based SRS (from URL route parameter) as the override, mirroring legacy
        // $srs = Input::getPath()->part(4). Fall back to request-level srs if not set.
        $srs            = $this->ctx->srs ?? $req->srs;
        $postgisObject  = $this->ctx->model();

        $writer->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");

        if ($version == "1.1.0") {
            $writer->write("<wfs:WFS_Capabilities version=\"1.1.0\"
                    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                    xmlns=\"http://www.opengis.net/wfs\"
                    xmlns:wfs=\"http://www.opengis.net/wfs\"
                    xmlns:ows=\"http://www.opengis.net/ows\"
                    xmlns:gml=\"http://www.opengis.net/gml\"
                    xmlns:ogc=\"http://www.opengis.net/ogc\"
                    xmlns:xlink=\"http://www.w3.org/1999/xlink\"
                    xmlns:{$gmlNameSpace}=\"{$gmlNameSpaceUri}\"
                    xsi:schemaLocation=\"http://www.opengis.net/wfs http://127.0.0.1:8081/geoserver/schemas/wfs/1.1.0/wfs.xsd http://inspire.ec.europa.eu/schemas/inspire_dls/1.0 http://inspire.ec.europa.eu/schemas/inspire_dls/1.0/inspire_dls.xsd\"
                    xmlns:inspire_dls=\"http://inspire.ec.europa.eu/schemas/inspire_dls/1.0\"
                    xmlns:inspire_common=\"http://inspire.ec.europa.eu/schemas/common/1.0\"
                    xmlns:martin=\"mapcentia.com\" updateSequence=\"11\">
<ows:ServiceIdentification>
    <ows:Title/>
    <ows:Abstract/>
    <ows:ServiceType>WFS</ows:ServiceType>
    <ows:ServiceTypeVersion>1.1.0</ows:ServiceTypeVersion>
    <ows:Fees/>
    <ows:AccessConstraints/>
</ows:ServiceIdentification>
<ows:ServiceProvider>
    <ows:ProviderName/>
    <ows:ServiceContact>
        <ows:IndividualName/>
        <ows:PositionName/>
        <ows:ContactInfo>
            <ows:Phone>
                <ows:Voice/>
                <ows:Facsimile/>
            </ows:Phone>
            <ows:Address>
                <ows:DeliveryPoint/>
                <ows:City/>
                <ows:AdministrativeArea/>
                <ows:PostalCode/>
                <ows:Country/>
                <ows:ElectronicMailAddress/>
            </ows:Address>
        </ows:ContactInfo>
    </ows:ServiceContact>
</ows:ServiceProvider>
<ows:OperationsMetadata>
    <ows:Operation name=\"GetCapabilities\">
        <ows:DCP>
            <ows:HTTP>
                <ows:Get xlink:href=\"{$thePath}?\"/>
                <ows:Post xlink:href=\"{$thePath}?\"/>
            </ows:HTTP>
        </ows:DCP>
        <ows:Parameter name=\"AcceptVersions\">
            <ows:Value>1.0.0</ows:Value>
            <ows:Value>1.1.0</ows:Value>
        </ows:Parameter>
        <ows:Parameter name=\"AcceptFormats\">
            <ows:Value>text/xml</ows:Value>
        </ows:Parameter>
        <ows:Parameter name=\"Sections\">
            <ows:Value>ServiceIdentification</ows:Value>
            <ows:Value>ServiceProvider</ows:Value>
            <ows:Value>OperationsMetadata</ows:Value>
            <ows:Value>FeatureTypeList</ows:Value>
            <ows:Value>Filter_Capabilities</ows:Value>
        </ows:Parameter>
    </ows:Operation>
    <ows:Operation name=\"DescribeFeatureType\">
        <ows:DCP>
            <ows:HTTP>
                <ows:Get xlink:href=\"{$thePath}?\"/>
                <ows:Post xlink:href=\"{$thePath}?\"/>
            </ows:HTTP>
        </ows:DCP>
        <ows:Parameter name=\"outputFormat\">
            <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
        </ows:Parameter>
    </ows:Operation>
    <ows:Operation name=\"GetFeature\">
        <ows:DCP>
            <ows:HTTP>
                <ows:Get xlink:href=\"{$thePath}?\"/>
                <ows:Post xlink:href=\"{$thePath}?\"/>
            </ows:HTTP>
        </ows:DCP>
        <ows:Parameter name=\"resultType\">
            <ows:Value>results</ows:Value>
            <ows:Value>hits</ows:Value>
        </ows:Parameter>
        <ows:Parameter name=\"outputFormat\">
            <ows:Value>GML2</ows:Value>
            <ows:Value>gml3</ows:Value>
        </ows:Parameter>
        <ows:Constraint name=\"LocalTraverseXLinkScope\">
            <ows:Value>2</ows:Value>
        </ows:Constraint>
    </ows:Operation>
    <ows:Operation name=\"Transaction\">
        <ows:DCP>
            <ows:HTTP>
                <ows:Get xlink:href=\"{$thePath}?\"/>
                <ows:Post xlink:href=\"{$thePath}?\"/>
            </ows:HTTP>
        </ows:DCP>
        <ows:Parameter name=\"inputFormat\">
            <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
        </ows:Parameter>
        <ows:Parameter name=\"idgen\">
            <ows:Value>GenerateNew</ows:Value>
            <ows:Value>UseExisting</ows:Value>
            <!--<ows:Value>ReplaceDuplicate</ows:Value>-->
        </ows:Parameter>
        <ows:Parameter name=\"releaseAction\">
            <ows:Value>ALL</ows:Value>
            <ows:Value>SOME</ows:Value>
        </ows:Parameter>
    </ows:Operation>
</ows:OperationsMetadata>
        ");
            // Legacy writeTag ignores the $n (newline) param, so no newlines between tags here.
            $writer->writeTag("open", null, "FeatureTypeList", null, false);
            $writer->writeTag("open", null, "Operations", null, false);
            $writer->writeTag("open", null, "Operation", null, false);
            $writer->write("Query");
            $writer->writeTag("close", null, "Operation", null, false);
            $writer->writeTag("open", null, "Operation", null, false);
            $writer->write("Insert");
            $writer->writeTag("close", null, "Operation", null, false);
            $writer->writeTag("open", null, "Operation", null, false);
            $writer->write("Update");
            $writer->writeTag("close", null, "Operation", null, false);
            $writer->writeTag("open", null, "Operation", null, false);
            $writer->write("Delete");
            $writer->writeTag("close", null, "Operation", null, false);
            $writer->writeTag("close", null, "Operations", null, false);
        } else {
            $writer->write("<WFS_Capabilities version=\"1.0.0\"
                  xmlns=\"http://www.opengis.net/wfs\"
                  xmlns:{$gmlNameSpace}=\"{$gmlNameSpaceUri}\"
                  xmlns:ogc=\"http://www.opengis.net/ogc\"
                  xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                  xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd\">
    <Service>
        <Name>MaplinkWebFeatureServer</Name>
        <Title>{$gmlNameSpace}s awesome WFS</Title>
        <Abstract>Mygeocloud.com</Abstract>
        <Keywords>WFS</Keywords>
        <OnlineResource>{$thePath}</OnlineResource>
        <Fees>NONE</Fees>
        <AccessConstraints>NONE</AccessConstraints>
    </Service>
    <Capability>
        <Request>
            <GetCapabilities>
                <DCPType>
                    <HTTP>
                        <Get onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
                <DCPType>
                    <HTTP>
                        <Post onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
            </GetCapabilities>
            <DescribeFeatureType>
                <SchemaDescriptionLanguage>
                    <XMLSCHEMA/>
                </SchemaDescriptionLanguage>
                <DCPType>
                    <HTTP>
                        <Get onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
                <DCPType>
                    <HTTP>
                        <Post onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
            </DescribeFeatureType>
            <GetFeature>
                <ResultFormat>
                    <GML2/>
                </ResultFormat>
                <DCPType>
                    <HTTP>
                        <Get onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
                <DCPType>
                    <HTTP>
                        <Post onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
            </GetFeature>
            <Transaction>
                <DCPType>
                    <HTTP>
                        <Get onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
                <DCPType>
                    <HTTP>
                        <Post onlineResource=\"{$thePath}?\"/>
                    </HTTP>
                </DCPType>
            </Transaction>
        </Request>
        <VendorSpecificCapabilities>
        </VendorSpecificCapabilities>
    </Capability>\n");
            $writer->writeTag("open", null, "FeatureTypeList", null, false);
            $writer->writeTag("open", null, "Operations", null, false);
            $writer->writeTag("selfclose", null, "Query", null, false);
            $writer->writeTag("selfclose", null, "Insert", null, false);
            $writer->writeTag("selfclose", null, "Update", null, false);
            $writer->writeTag("selfclose", null, "Delete", null, false);
            $writer->writeTag("close", null, "Operations", null, false);
        }

        $sql = "SELECT * from settings.getColumns('f_table_schema=''{$postgisschema}'' AND enableows=true','raster_columns.r_table_schema=''{$postgisschema}'' AND enableows=true') order by sort_id";

        try {
            $result = $postgisObject->execQuery($sql);
        } catch (PDOException $e) {
            throw new OwsException($e->getMessage(), 'NoApplicableCode');
        }

        $settings = new Setting();
        $extents = $settings->get()["data"]->extents;
        $bbox = is_object($extents) && property_exists($extents, $postgisschema)
            ? $extents->$postgisschema
            : [-20037508.34, -20037508.34, 20037508.34, 20037508.34]; // Is in EPSG:3857
        $cache = [];
        while ($row = $postgisObject->fetchRow($result)) {
            if ($row['type'] != "RASTER" && $row['type'] != null) {
                if (!$srs) {
                    $srsTmp = $row['srid'];
                } else {
                    $srsTmp = $srs;
                }
                $latLongBoundingBoxSrs = "4326";
                $TableName = $row["f_table_name"];
                if (in_array($TableName, $cache)) {
                    continue;
                }
                $cache[] = $TableName;
                $writer->writeTag("open", null, "FeatureType", null, false);
                $writer->writeTag("open", null, "Name", null, false);
                if ($gmlNameSpace) $writer->write($gmlNameSpace . ":");
                $writer->write($TableName);
                $writer->writeTag("close", null, "Name", null, false);
                $writer->writeTag("open", null, "Title", null, false);
                $writer->write($row["f_table_title"] ? "<![CDATA[" . $row["f_table_title"] . "]]>" : "");
                $writer->writeTag("close", null, "Title", null, false);
                $writer->writeTag("open", null, "Abstract", null, false);
                $writer->write($row["f_table_abstract"] ? "<![CDATA[" . $row["f_table_abstract"] . "]]>" : "");
                $writer->writeTag("close", null, "Abstract", null, false);
                if ($version == "1.1.0") {
                    $writer->writeTag("open", "ows", "Keywords", null, false);
                    $writer->writeTag("open", "ows", "Keyword", null, false);
                    $writer->writeTag("close", "ows", "Keyword", null, false);
                    $writer->writeTag("close", "ows", "Keywords", null, false);
                    $writer->writeTag("open", null, "DefaultSRS", null, false);
                    $writer->write("urn:x-ogc:def:crs:EPSG:" . $srsTmp);
                    $writer->writeTag("close", null, "DefaultSRS", null, false);
                } else {
                    $writer->writeTag("open", null, "Keywords", null, false);
                    $writer->writeTag("close", null, "Keywords", null, false);
                    $writer->writeTag("open", null, "SRS", null, false);
                    $writer->write("EPSG:" . $srsTmp);
                    $writer->writeTag("close", null, "SRS", null, false);
                }

                if ($row['f_geometry_column']) {
                    // Estimated extent
                    $sql2 = "WITH bb AS (SELECT ST_astext(ST_Transform(ST_setsrid(ST_EstimatedExtent('" . $postgisschema . "', '" . $TableName . "', '" . $row['f_geometry_column'] . "')," . $row['srid'] . ")," . $latLongBoundingBoxSrs . ")) as geom)
                            SELECT ST_Xmin(ST_Extent(geom)) AS TXMin,ST_Xmax(ST_Extent(geom)) AS TXMax, ST_Ymin(ST_Extent(geom)) AS TYMin,ST_Ymax(ST_Extent(geom)) AS TYMax  FROM bb";

                    $result2 = $postgisObject->prepare($sql2);
                    try {
                        $result2->execute();
                        $row2 = $postgisObject->fetchRow($result2);
                        list($x1, $x2, $y1, $y2) = [$row2['txmin'], $row2['tymin'], $row2['txmax'], $row2['tymax']];

                        if (empty($row2['txmin'])) {
                            throw new PDOException('No estimated extent');
                        }
                    } catch (PDOException $e) {
                        $sql3 = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$bbox[0]},{$bbox[1]},{$bbox[2]},{$bbox[3]},3857),4326)) AS a) select ST_xmin(a) as txmin,ST_ymin(a) as tymin,ST_xmax(a) as txmax,ST_ymax(a) as tymax  from box";
                        $resultExtent = $postgisObject->execQuery($sql3);
                        $rowExtent = $postgisObject->fetchRow($resultExtent);
                        list($x1, $x2, $y1, $y2) = [$rowExtent['txmin'], $rowExtent['tymin'], $rowExtent['txmax'], $rowExtent['tymax']];
                    }
                    if ($version == "1.1.0") {
                        $writer->writeTag("open", "ows", "WGS84BoundingBox", null, false);
                        $writer->writeTag("open", "ows", "LowerCorner", null, false);
                        $writer->write("{$x1} {$x2}");
                        $writer->writeTag("close", "ows", "LowerCorner", null, false);
                        $writer->writeTag("open", "ows", "UpperCorner", null, false);
                        $writer->write("{$y1} {$y2}");
                        $writer->writeTag("close", "ows", "UpperCorner", null, false);
                        $writer->writeTag("close", "ows", "WGS84BoundingBox", null, false);
                    } else {
                        $writer->writeTag("open", null, "LatLongBoundingBox", ["minx" => $x1, "miny" => $x2, "maxx" => $y1, "maxy" => $y2], false);
                        $writer->writeTag("close", null, "LatLongBoundingBox", null, false);
                    }
                }
                $writer->writeTag("close", null, "FeatureType", null, false);
            }
        }
        $writer->writeTag("close", null, "FeatureTypeList", null, false);

        $writer->writeTag("open", "ogc", "Filter_Capabilities", null, false);

        // Spatial capabilities
        $writer->writeTag("open", "ogc", "Spatial_Capabilities", null, false);
        if ($version == "1.1.0") {
            $writer->writeTag("open", "ogc", "GeometryOperands", null, false);
            $writer->writeTag("open", "ogc", "GeometryOperand", null, false);
            $writer->write("gml:Envelope");
            $writer->writeTag("close", "ogc", "GeometryOperand", null, false);
            $writer->writeTag("close", "ogc", "GeometryOperands", null, false);
        }
        $writer->writeTag("open", "ogc", $version == "1.1.0" ? "SpatialOperators" : "Spatial_Operators", null, false);
        if ($version == "1.1.0") {
            $writer->writeTag("selfclose", "ogc", "SpatialOperator", ["name" => "Intersects"], false);
            $writer->writeTag("selfclose", "ogc", "SpatialOperator", ["name" => "BBOX"], false);
        } else {
            $writer->writeTag("selfclose", "ogc", "Intersect", null, false);
            $writer->writeTag("selfclose", "ogc", "BBOX", null, false);
        }
        $writer->writeTag("close", "ogc", $version == "1.1.0" ? "SpatialOperators" : "Spatial_Operators", null, false);
        $writer->writeTag("close", "ogc", "Spatial_Capabilities", null, false);

        // Scalar capabilities
        $writer->writeTag("open", "ogc", "Scalar_Capabilities", null, false);
        $writer->writeTag("selfclose", "ogc", $version == "1.1.0" ? "LogicalOperators" : "Logical_Operators", null, false);
        $writer->writeTag("open", "ogc", $version == "1.1.0" ? "ComparisonOperators" : "Comparison_Operators", null, false);
        if ($version == "1.1.0") {
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("LessThan");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("GreaterThan");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("LessThanEqualTo");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("GreaterThanEqualTo");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("EqualTo");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("NotEqualTo");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("Like");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
            $writer->writeTag("open", "ogc", "ComparisonOperator", null, false);
            $writer->write("Between");
            $writer->writeTag("close", "ogc", "ComparisonOperator", null, false);
        } else {
            $writer->writeTag("selfclose", "ogc", "Simple_Comparisons", null, false);
            $writer->writeTag("selfclose", "ogc", "Between", null, false);
            $writer->writeTag("selfclose", "ogc", "Like", null, false);
        }
        $writer->writeTag("close", "ogc", $version == "1.1.0" ? "ComparisonOperators" : "Comparison_Operators", null, false);
        $writer->writeTag("close", "ogc", "Scalar_Capabilities", null, false);

        // Id capabilities
        if ($version == "1.1.0") {
            $writer->writeTag("open", "ogc", "Id_Capabilities", null, false);
            $writer->writeTag("selfclose", "ogc", "FID", null, false);
            $writer->writeTag("selfclose", "ogc", "EID", null, false);
            $writer->writeTag("close", "ogc", "Id_Capabilities", null, false);
        }

        $writer->writeTag("close", "ogc", "Filter_Capabilities", null, false);
        $writer->writeTag("close", $version == "1.1.0" ? "wfs" : null, "WFS_Capabilities", null, false);
    }
}
