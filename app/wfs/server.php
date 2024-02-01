<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\conf\App;
use app\controllers\Tilecache;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Log;
use app\inc\PgHStore;
use app\inc\Util;
use app\models\Setting;
use app\models\Table;
use app\models\Layer;
use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use app\inc\UserFilter;
use app\models\Geofence;
use app\models\Rule;
use app\conf\Connection;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use sad_spirit\pg_builder\StatementFactory;


ini_set("max_execution_time", "0");

header('Content-Type:text/xml; charset=UTF-8', TRUE);
header('Connection:close', TRUE);

//include "/usr/share/php/XML/Unserializer.php";
//include "/usr/share/php/XML/Serializer.php";

include __DIR__ . "/../libs/PEAR/XML/Unserializer.php";
include __DIR__ . "/../libs/PEAR/XML/Serializer.php";

Util::disableOb();
const FEATURE_LIMIT = 1000000;

$host = Util::protocol() . "://" . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] != "80" && $_SERVER['SERVER_PORT'] != "443" ? ":" . $_SERVER["SERVER_PORT"] : "");

$gmlNameSpace = Connection::$param["postgisschema"];
$gmlNameSpaceUri = $host . "/" . Connection::$param["postgisdb"] . "/" . Connection::$param["postgisschema"];
// Always set ns uri to http://
$gmlNameSpaceUri = str_replace("https://", "http://", $gmlNameSpaceUri);

$postgisschema = Connection::$param["postgisschema"];
$layerObj = new Layer();

$srs = Input::getPath()->part(4);

$timeSlice = Input::getPath()->part(5);
if (!empty($timeSlice) && $timeSlice != "all") {
    $unixTime = strtotime(urldecode($timeSlice));
    if ($unixTime) {
        $timeSlice = date("Y-m-d G:i:s.u", $unixTime);
    } else {
        $timeSlice = false;
    }
}

$postgisObject = new \app\inc\Model();
$geometryColumnsObj = new \app\controllers\Layer();

$trusted = false;
foreach (App::$param["trustedAddresses"] as $address) {
    if (Util::ipInRange(Util::clientIp(), $address)) {
        if (getenv("MODE_ENV") != "test") {
            $trusted = true;
        }
        break;
    }
}

/**
 * @return float
 */
function microtime_float(): float
{
    list($utime, $time) = explode(" ", microtime());
    return ((float)$utime + (float)$time);
}

$startTime = microtime_float();

$uri = str_replace("index.php", "", $_SERVER['REDIRECT_URL']);
$uri = str_replace("//", "/", $uri);

$thePath = $host . $uri;
$server = $host;
$BBox = null;
$fullSql = "";
$arr = [];

$currentTable = null;
$currentTag = null;
$gen = array();
$gen[0] = "";
$level = 0;
$depth = 0;
$tables = array();
$fields = array();
$wheres = array();
$limits = array();

$unserializer_options = array(
    "parseAttributes" => true,
    "contentName" => "_content",
//    "guessTypes" => true,
);
$unserializer = new XML_Unserializer($unserializer_options);

$sessionComment = "";

$specialChars = "/['^£$%&*()}{@#~?><>,|=+¬]/";

$logPath = "/var/www/geocloud2/public/logs/wfs_transactions.log";

// Post method is used
// ===================

$HTTP_RAW_POST_DATA = file_get_contents("php://input");

if ($HTTP_RAW_POST_DATA) {
    $HTTP_RAW_POST_DATA = dropNameSpace($HTTP_RAW_POST_DATA);

    // HACK. MapInfo 15 sends invalid XML with newline \n and double xmlns:wfs namespace. So we strip those
    $HTTP_RAW_POST_DATA = str_replace("\\n", " ", $HTTP_RAW_POST_DATA);
    $HTTP_RAW_POST_DATA = str_replace("xmlns:wfs=\"http://www.opengis.net/wfs\"", " ", $HTTP_RAW_POST_DATA);

    $unserializer->unserialize($HTTP_RAW_POST_DATA);
    $arr = $unserializer->getUnserializedData();
    $HTTP_FORM_VARS["VERSION"] = $arr["version"] ?? null;
    $HTTP_FORM_VARS["SERVICE"] = $arr["service"] ?? null;
    $HTTP_FORM_VARS["MAXFEATURES"] = $arr["maxFeatures"] ?? null;
    $HTTP_FORM_VARS["RESULTTYPE"] = $arr["resultType"] ?? null;
    $HTTP_FORM_VARS["OUTPUTFORMAT"] = $arr["outputFormat"] ?? null;
    switch ($unserializer->getRootName()) {
        case "GetFeature":
            if (!isset($arr['Query'][0])) {
                $arr['Query'] = array(0 => $arr['Query']);
            }
            for ($i = 0; $i < sizeof($arr['Query']); $i++) {
                if (isset($arr['Query'][$i]['PropertyName']) && !is_array($arr['Query'][$i]['PropertyName'])) {
                    $arr['Query'][$i]['PropertyName'] = array(0 => $arr['Query'][$i]['PropertyName']);
                }
            }
            $HTTP_FORM_VARS["REQUEST"] = "GetFeature";
            foreach ($arr['Query'] as $queries) {
                $HTTP_FORM_VARS["srsName"] = $queries['srsName'] ?? null;
                $queries['typeName'] = dropAllNameSpaces($queries['typeName']);
                if (!isset($HTTP_FORM_VARS["TYPENAME"])) {
                    $HTTP_FORM_VARS["TYPENAME"] = "";
                }
                $HTTP_FORM_VARS["TYPENAME"] .= $queries['typeName'] . ",";
                if (!empty($queries['PropertyName'][0])) {
                    foreach ($queries['PropertyName'] as $PropertyNames) {
                        // We check if typeName is prefix and add it if its not
                        if (strpos($PropertyNames, ".")) {
                            $HTTP_FORM_VARS["PROPERTYNAME"] .= $PropertyNames . ",";
                        } else {
                            $HTTP_FORM_VARS["PROPERTYNAME"] .= $queries['typeName'] . "." . $PropertyNames . ",";
                        }
                    }
                }
                if (is_array($queries['Filter'])) {
                    $HTTP_FORM_VARS["FILTER"] = $queries["Filter"];
                }
            }
            $HTTP_FORM_VARS["TYPENAME"] = dropLastChrs($HTTP_FORM_VARS["TYPENAME"], 1);
            $HTTP_FORM_VARS["PROPERTYNAME"] = !empty($HTTP_FORM_VARS["PROPERTYNAME"]) ? dropLastChrs($HTTP_FORM_VARS["PROPERTYNAME"], 1) : null;
            break;
        case "DescribeFeatureType":
            $HTTP_FORM_VARS["REQUEST"] = "DescribeFeatureType";
            $HTTP_FORM_VARS["TYPENAME"] = $arr['TypeName'];
            break;
        case "GetCapabilities":
            $HTTP_FORM_VARS["REQUEST"] = "GetCapabilities";
            break;
        case "Transaction":
            $HTTP_FORM_VARS["REQUEST"] = "Transaction";
            break;
    }

// Get method is used
// ==================

} else {
    if (sizeof($_GET) > 0) {
        $HTTP_FORM_VARS = $_GET;
        $HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS, CASE_UPPER); // Make keys case insensative
        $HTTP_FORM_VARS["TYPENAME"] = dropAllNameSpaces($HTTP_FORM_VARS["TYPENAME"]); // We remove name space, so $where will get key without it.

        if (!empty($HTTP_FORM_VARS['FILTER'])) {
            $filter = dropNameSpace($HTTP_FORM_VARS['FILTER']);
            @$checkXml = simplexml_load_string($filter);
            if ($checkXml === FALSE) {
                makeExceptionReport("Filter is not valid");
            }
            $unserializer->unserialize($filter);
            $HTTP_FORM_VARS['FILTER'] = $unserializer->getUnserializedData();
        }
    } else {
        $HTTP_FORM_VARS = array("");
    }

}
// Log the request
Log::write($logPath, $HTTP_RAW_POST_DATA);

//HTTP_FORM_VARS is set in script if POST is used
$HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS, CASE_UPPER); // Make keys case
$HTTP_FORM_VARS["TYPENAME"] = !empty($HTTP_FORM_VARS["TYPENAME"]) ? dropAllNameSpaces($HTTP_FORM_VARS["TYPENAME"]) : null;
$tables = !empty($HTTP_FORM_VARS["TYPENAME"]) ? explode(",", $HTTP_FORM_VARS["TYPENAME"]) : null;
$properties = !empty($HTTP_FORM_VARS["PROPERTYNAME"]) ? explode(",", dropAllNameSpaces($HTTP_FORM_VARS["PROPERTYNAME"])) : null;
$featureids = !empty($HTTP_FORM_VARS["FEATUREID"]) ? explode(",", $HTTP_FORM_VARS["FEATUREID"]) : null;
$bbox = !empty($HTTP_FORM_VARS["BBOX"]) ? explode(",", $HTTP_FORM_VARS["BBOX"]) : null;
$resultType = !empty($HTTP_FORM_VARS["RESULTTYPE"]) ? $HTTP_FORM_VARS["RESULTTYPE"] : null;
$srsName = !empty($HTTP_FORM_VARS["SRSNAME"]) ? $HTTP_FORM_VARS["SRSNAME"] : null;
$version = !empty($HTTP_FORM_VARS["VERSION"]) ? $HTTP_FORM_VARS["VERSION"] : "1.1.0";
$service = !empty($HTTP_FORM_VARS["SERVICE"]) ? $HTTP_FORM_VARS["SERVICE"] : ($HTTP_FORM_VARS["REQUEST"] == "GetFeature" ? "WFS" : null);
$maxFeatures = !empty($HTTP_FORM_VARS["MAXFEATURES"]) ? $HTTP_FORM_VARS["MAXFEATURES"] : null;
$outputFormat = !empty($HTTP_FORM_VARS["OUTPUTFORMAT"]) ? $HTTP_FORM_VARS["OUTPUTFORMAT"] : ($version == "1.1.0" ? "GML3" : "GML2");
$srs = $srsName ? parseEpsgCode($srsName) : ($srs ?: App::$param["epsg"] ?: null);

if (!empty($HTTP_FORM_VARS["FILTER"])) {
    $wheres[$HTTP_FORM_VARS["TYPENAME"]] = parseFilter($HTTP_FORM_VARS["FILTER"], $HTTP_FORM_VARS["TYPENAME"]);
}

if ($version != "1.0.0" && $version != "1.1.0") {
    makeExceptionReport("Version $version is not supported");
}
if (!$service || strcasecmp($service, "wfs") != 0) {
    makeExceptionReport("No service", ["exceptionCode" => "MissingParameterValue", "locator" => "service"]);
}
if (strpos($outputFormat, "gml/3") != false) {
    $outputFormat = "GML3";
}
if (strcasecmp($outputFormat, "XMLSCHEMA") != 0 && strcasecmp($outputFormat, "GML2") != 0 && strcasecmp($outputFormat, "GML3") != 0) {
    $outputFormat = "GML2";
}

// Start HTTP basic authentication
if (!$trusted) {
    $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $HTTP_FORM_VARS["TYPENAME"], "authentication");
    if ($auth == "Read/write" || !empty(Input::getAuthUser())) {
        include(__DIR__ . "/../inc/http_basic_authen.php");
    }
}
// End HTTP basic authentication


if (!(empty($properties[0]))) {
    foreach ($properties as $property) {
        $__u = explode(".", $property); // Is it "/" for get method?
        // We first check if typeName is namespace
        if ($__u[1]) {
            foreach ($tables as $table) {
                if ($table == $__u[0]) {
                    $fields[$table] .= $__u[1] . ",";
                }
            }
        } // No, typeName is not a part of value
        else {
            foreach ($tables as $table) {
                $fields[$table] .= $property . ",";
            }
        }
    }
}
if (!(empty($featureids[0]))) {
    $tables = [];
    foreach ($featureids as $featureid) {
        $u = explode(".", $featureid, 2);
        $table = $u[0];
        $HTTP_FORM_VARS["TYPENAME"] = $table;
        if (!in_array($table, $tables)) $tables[] = $table;
        $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $table);

        $wheresArr[$table][] = "{$primeryKey['attname']}='{$u[1]}'";
        $wheres[$table] = implode(" OR ", $wheresArr[$table]);
    }
}
if (!(empty($bbox[0]))) {
    if (!(empty($featureids[0]))) {
        $wheres[$table] .= " AND ";
    }
    foreach ($tables as $table) {
        $bbox[4] = $bbox[4] ?? $srsName ?? $srs;
        $axisOrder = getAxisOrder($bbox[4]);
        if ($axisOrder == "longitude") {
            $wheres[$table] .= "ST_intersects"
                . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $bbox[0] . " " . $bbox[1] . "," . $bbox[0] . " " . $bbox[3] . "," . $bbox[2] . " " . $bbox[3] . "," . $bbox[2] . " " . $bbox[1] . "," . $bbox[0] . " " . $bbox[1] . "))',"
                . parseEpsgCode($bbox[4])
                . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "srid") . "),"
                . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column") . ")";
        } else {
            $wheres[$table] .= "ST_intersects"
                . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $bbox[1] . " " . $bbox[0] . "," . $bbox[3] . " " . $bbox[0] . "," . $bbox[3] . " " . $bbox[2] . "," . $bbox[1] . " " . $bbox[2] . "," . $bbox[1] . " " . $bbox[0] . "))',"
                . parseEpsgCode($bbox[4])
                . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "srid") . "),"
                . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column") . ")";
        }
    }
}
//get the request'

if (!isset($HTTP_FORM_VARS["REQUEST"])) {
    makeExceptionReport("No request", ["exceptionCode" => "MissingParameterValue", "locator" => "request"]);
}

// Check if layer is enabled
$isEnabled = false;
if ($postgisObject->getGeometryColumns($postgisschema . "." . $HTTP_FORM_VARS["TYPENAME"], "*")["enableows"]) {
    $isEnabled = true;
}

try {
    switch (strtoupper($HTTP_FORM_VARS["REQUEST"])) {
        case "GETCAPABILITIES":
            getCapabilities($postgisObject);
            break;
        case "GETFEATURE":
            if (!$isEnabled) {
                makeExceptionReport("Layer is not enabled", ["exceptionCode" => "InvalidParameterValue", "locator" => "typename"]);
            }
            print ("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            doQuery("Select");
            print "</wfs:FeatureCollection>";
            print "\n<!--\n";
            print_r($fullSql);
            print "\n-->";
            break;
        case "DESCRIBEFEATURETYPE":
            if (!$isEnabled) {
                makeExceptionReport("Layer is not enabled", ["exceptionCode" => "InvalidParameterValue", "locator" => "typename"]);
            }
            getXSD($postgisObject);
            break;
        case "TRANSACTION":
            doParse($arr);
            break;
        default:
            makeExceptionReport("No such operation WFS {$HTTP_FORM_VARS["REQUEST"]}", ["exceptionCode" => "OperationNotSupported", "locator" => $HTTP_FORM_VARS["REQUEST"]]);
            break;
    }
} catch (Exception $e) {
    makeExceptionReport($e->getMessage());
}

/**
 * @param \app\inc\Model $postgisObject
 * @throws PhpfastcacheInvalidArgumentException
 */
function getCapabilities(\app\inc\Model $postgisObject)
{
    global $srs;
    global $thePath;
    global $gmlNameSpace;
    global $gmlNameSpaceUri;
    global $postgisschema;
    global $depth;
    global $version;

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

    if ($version == "1.1.0") {
        echo "<wfs:WFS_Capabilities version=\"1.1.0\"
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
        ";
        $depth = 3;
        writeTag("open", null, "FeatureTypeList", null, true, true);
        writeTag("open", null, "Operations", null, true, true);
        $depth++;
        writeTag("open", null, "Operation", null, true, false);
        echo "Query";
        writeTag("close", null, "Operation", null, false, true);
        writeTag("open", null, "Operation", null, true, false);
        echo "Insert";
        writeTag("close", null, "Operation", null, false, true);
        writeTag("open", null, "Operation", null, true, false);
        echo "Update";
        writeTag("close", null, "Operation", null, false, true);
        writeTag("open", null, "Operation", null, true, false);
        echo "Delete";
        writeTag("close", null, "Operation", null, false, true);
        writeTag("close", null, "Operations", null, true, true);
    } else {
        echo "<WFS_Capabilities version=\"1.0.0\"
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
    </Capability>\n";
        $depth = 3;
        writeTag("open", null, "FeatureTypeList", null, true, true);
        writeTag("open", null, "Operations", null, true, true);
        $depth++;
        writeTag("selfclose", null, "Query", null, true, true);
        writeTag("selfclose", null, "Insert", null, false, True);
        writeTag("selfclose", null, "Update", null, false, True);
        writeTag("selfclose", null, "Delete", null, false, True);
        writeTag("close", null, "Operations", null, False, True);
    }

    $sql = "SELECT * from settings.getColumns('f_table_schema=''{$postgisschema}'' AND enableows=true','raster_columns.r_table_schema=''{$postgisschema}'' AND enableows=true') order by sort_id";

    try {
        $result = $postgisObject->execQuery($sql);
    } catch (PDOException $e) {
        makeExceptionReport($e->getMessage());
    }

    $settings = new Setting();
    $extents = $settings->get()["data"]->extents;
    $bbox = is_object($extents) && property_exists($extents, $postgisschema) ? $extents->$postgisschema : [-20037508.34, -20037508.34, 20037508.34, 20037508.34]; // Is in EPSG:3857
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
            writeTag("open", null, "FeatureType", null, True, True);
            $depth++;
            writeTag("open", null, "Name", null, True, False);
            if ($gmlNameSpace) echo $gmlNameSpace . ":";
            echo $TableName;
            writeTag("close", null, "Name", null, False, True);
            writeTag("open", null, "Title", null, True, False);
            echo $row["f_table_title"] ? "<![CDATA[" . $row["f_table_title"] . "]]>" : "";
            writeTag("close", null, "Title", null, False, True);
            writeTag("open", null, "Abstract", null, True, False);
            echo $row["f_table_abstract"] ? "<![CDATA[" . $row["f_table_abstract"] . "]]>" : "";
            writeTag("close", null, "Abstract", null, False, True);
            if ($version == "1.1.0") {
                writeTag("open", "ows", "Keywords", null, True, False);
                writeTag("open", "ows", "Keyword", null, True, False);
                writeTag("close", "ows", "Keyword", null, False, True);
                writeTag("close", "ows", "Keywords", null, False, True);
                writeTag("open", null, "DefaultSRS", null, True, False);
                echo "urn:x-ogc:def:crs:EPSG:" . $srsTmp;
                writeTag("close", null, "DefaultSRS", null, False, True);

            } else {
                writeTag("open", null, "Keywords", null, True, False);
                writeTag("close", null, "Keywords", null, False, True);
                writeTag("open", null, "SRS", null, True, False);
                echo "EPSG:" . $srsTmp;
                writeTag("close", null, "SRS", null, False, True);
            }

            if ($row['f_geometry_column']) {
                // Precis extent
                //$sql2 = "WITH bb AS (SELECT ST_astext(ST_Transform(ST_setsrid(ST_Extent(" . $row['f_geometry_column'] . ")," . $row['srid'] . ")," . $latLongBoundingBoxSrs . ")) as geom FROM " . $postgisObject->doubleQuoteQualifiedName($postgisschema . "." . $TableName) . ") ";
                //$sql2.= "SELECT ST_Xmin(ST_Extent(geom)) AS TXMin,ST_Xmax(ST_Extent(geom)) AS TXMax, ST_Ymin(ST_Extent(geom)) AS TYMin,ST_Ymax(ST_Extent(geom)) AS TYMax  FROM bb";

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

                    $sql = "with box as (select ST_extent(st_transform(ST_MakeEnvelope({$bbox[0]},{$bbox[1]},{$bbox[2]},{$bbox[3]},3857),4326)) AS a) select ST_xmin(a) as txmin,ST_ymin(a) as tymin,ST_xmax(a) as txmax,ST_ymax(a) as tymax  from box";
                    $resultExtent = $postgisObject->execQuery($sql);
                    $rowExtent = $postgisObject->fetchRow($resultExtent);
                    list($x1, $x2, $y1, $y2) = [$rowExtent['txmin'], $rowExtent['tymin'], $rowExtent['txmax'], $rowExtent['tymax']];

//                    echo "<!--";
//                    echo "WARNING: Optional LatLongBoundingBox could not be established for this layer - using extent set for schema";
//                    echo "-->";
                }
                if ($version == "1.1.0") {
                    writeTag("open", "ows", "WGS84BoundingBox", null, true, true);
                    writeTag("open", "ows", "LowerCorner", null, true, true);
                    echo "{$x1} {$x2}";
                    writeTag("close", "ows", "LowerCorner", null, false, true);
                    writeTag("open", "ows", "UpperCorner", null, true, true);
                    echo "{$y1} {$y2}";
                    writeTag("close", "ows", "UpperCorner", null, false, true);
                    writeTag("close", "ows", "WGS84BoundingBox", null, false, true);
                } else {
                    writeTag("open", null, "LatLongBoundingBox", array("minx" => $x1, "miny" => $x2, "maxx" => $y1, "maxy" => $y2), true, false);
                    writeTag("close", null, "LatLongBoundingBox", null, false, true);
                }
            }
            $depth--;
            writeTag("close", null, "FeatureType", null, True, True);
        }
    }
    $depth--;
    writeTag("close", null, "FeatureTypeList", null, True, True);


    writeTag("open", "ogc", "Filter_Capabilities", null, true, true);

    // Spatial capabilities
    writeTag("open", "ogc", "Spatial_Capabilities", null, true, true);
    if ($version == "1.1.0") {
        writeTag("open", "ogc", "GeometryOperands", null, true, true);
        writeTag("open", "ogc", "GeometryOperand", null, true, true);
        echo "gml:Envelope";
        writeTag("close", "ogc", "GeometryOperand", null, false, true);
        writeTag("close", "ogc", "GeometryOperands", null, true, true);
    }
    writeTag("open", "ogc", $version == "1.1.0" ? "SpatialOperators" : "Spatial_Operators", null, true, true);
    if ($version == "1.1.0") {
        writeTag("selfclose", "ogc", "SpatialOperator", array("name" => "Intersects"), true, true);
        writeTag("selfclose", "ogc", "SpatialOperator", array("name" => "BBOX"), true, true);

    } else {
        writeTag("selfclose", "ogc", "Intersect", null, true, true);
        writeTag("selfclose", "ogc", "BBOX", null, true, true);
    }
    writeTag("close", "ogc", $version == "1.1.0" ? "SpatialOperators" : "Spatial_Operators", null, true, true);
    writeTag("close", "ogc", "Spatial_Capabilities", null, false, true);

    // Scalar capabilities
    writeTag("open", "ogc", "Scalar_Capabilities", null, true, true);
    writeTag("selfclose", "ogc", $version == "1.1.0" ? "LogicalOperators" : "Logical_Operators", null, true, true);
    writeTag("open", "ogc", $version == "1.1.0" ? "ComparisonOperators" : "Comparison_Operators", null, true, true);
    if ($version == "1.1.0") {
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "LessThan";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "GreaterThan";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "LessThanEqualTo";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "GreaterThanEqualTo";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "EqualTo";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "NotEqualTo";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "Like";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
        writeTag("open", "ogc", "ComparisonOperator", null, true, false);
        echo "Between";
        writeTag("close", "ogc", "ComparisonOperator", null, false, true);
    } else {
        writeTag("selfclose", "ogc", "Simple_Comparisons", null, true, true);
        writeTag("selfclose", "ogc", "Between", null, true, true);
        writeTag("selfclose", "ogc", "Like", null, true, true);
    }
    writeTag("close", "ogc", $version == "1.1.0" ? "ComparisonOperators" : "Comparison_Operators", null, true, true);
    writeTag("close", "ogc", "Scalar_Capabilities", null, true, true);

    // Id capabilities
    if ($version == "1.1.0") {
        writeTag("open", "ogc", "Id_Capabilities", null, false, true);
        writeTag("selfclose", "ogc", "FID", null, true, true);
        writeTag("selfclose", "ogc", "EID", null, true, true);
        writeTag("close", "ogc", "Id_Capabilities", null, false, true);
    }

    writeTag("close", "ogc", "Filter_Capabilities", null, true, true);
    writeTag("close", $version == "1.1.0" ? "wfs" : null, "WFS_Capabilities", null, true, true);
}

/**
 * @param \app\inc\Model $postgisObject
 * @throws PhpfastcacheInvalidArgumentException
 */
function getXSD(\app\inc\Model $postgisObject)
{
    ob_start();
    print ("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    global $server;
    global $depth;
    global $tables;
    global $gmlUseAltFunctions;
    global $gmlNameSpace;
    global $gmlNameSpaceUri;
    global $postgisschema;
    global $version;

    $atts["xmlns:xsd"] = "http://www.w3.org/2001/XMLSchema";
    $atts["xmlns:gml"] = "http://www.opengis.net/gml";
    $atts["xmlns:gc2"] = "http://www.mapcentia.com/gc2";
    $atts["xmlns:{$gmlNameSpace}"] = $gmlNameSpaceUri;
    $atts["elementFormDefault"] = "qualified";
    $atts["targetNamespace"] = $gmlNameSpaceUri;
    $atts["version"] = $version;
    writeTag("open", "xsd", "schema", $atts, True, True);
    $atts = null;
    $depth++;
    $atts["namespace"] = "http://www.opengis.net/gml";
    $atts["schemaLocation"] = "http://schemas.opengis.net/gml/" . ($version == "1.1.0" ? "3.1.1/base" : "2.1.2") . "/feature.xsd";
    writeTag("selfclose", "xsd", "import", $atts, True, True);
    $atts["namespace"] = "http://www.mapcentia.com/gc2";
    $atts["schemaLocation"] = $server . "/xmlschemas/gc2.xsd";
    writeTag("selfclose", "xsd", "import", $atts, True, True);
    $atts = null;

    if (!$tables[0]) {
        $tables = array();
        $sql = "SELECT f_table_name,f_geometry_column,srid FROM public.geometry_columns WHERE f_table_schema='{$postgisschema}'";
        try {
            $result = $postgisObject->execQuery($sql);
        } catch (PDOException) {
            makeExceptionReport("Relation doesn't exist", ["exceptionCode" => "InvalidParameterValue"]);
        }
        while ($row = $postgisObject->fetchRow($result)) {
            $tables[] = $row['f_table_name'];
        }
    }
    $cache = [];
    foreach ($tables as $table) {
        if (in_array($table, $cache)) {
            continue;
        }
        $cache[] = $table;
        $tableObj = new \app\models\table($postgisschema . "." . $table);
        $primeryKey = $tableObj->primaryKey;

        $simpleType = false;

        foreach ($tableObj->metaData as $key => $value) {
            $fieldsArr[$table][] = $key;
        }
        $fields = implode(",", $fieldsArr[$table]);
        $sql = "SELECT '{$fields}' FROM \"" . $postgisschema . "\".\"" . $table . "\" LIMIT 1";
        try {
            $postgisObject->execQuery($sql);
        } catch (PDOException) {
            makeExceptionReport("Relation doesn't exist", ["exceptionCode" => "InvalidParameterValue"]);
        }
        $atts["name"] = $table . "Type";
        writeTag("open", "xsd", "complexType", $atts, True, True);
        $atts = null;
        $depth++;
        writeTag("open", "xsd", "complexContent", Null, True, True);
        $depth++;
        $atts["base"] = "gml:AbstractFeatureType";

        writeTag("open", "xsd", "extension", $atts, True, True);
        $depth++;
        writeTag("open", "xsd", "sequence", NULL, True, True);

        $atts = null;
        $depth++;

        $sql = "SELECT * FROM settings.getColumns('f_table_name=''{$table}'' AND f_table_schema=''{$postgisschema}''',
                    'raster_columns.r_table_name=''{$table}'' AND raster_columns.r_table_schema=''{$postgisschema}''')";
        $fieldConfRow = $postgisObject->fetchRow($postgisObject->execQuery($sql));
        $fieldConf = json_decode($fieldConfRow['fieldconf']);
        $fieldConfArr = json_decode($fieldConfRow['fieldconf'], true);

        // Start sorting the fields by sort_id
        $arr = array();
        foreach ($fieldsArr[$table] as $value) {
            if (!empty($fieldConfArr[$value]["sort_id"])) {
                $arr[] = array($fieldConfArr[$value]["sort_id"], $value);
            } else {
                $arr[] = array(0, $value);
            }
        }
        usort($arr, function ($a, $b) {
            return $a[0] - $b[0];
        });
        // Filter out ignored fields
        $arr = array_filter($arr, function ($item, $key) use (&$fieldConfArr) {
            if (empty($fieldConfArr[$item[1]]['ignore'])) {
                return $item;
            }
        }, ARRAY_FILTER_USE_BOTH);
        $fieldsArr[$table] = array();
        foreach ($arr as $value) {
            $fieldsArr[$table][] = $value[1];
        }
        foreach ($fieldsArr[$table] as $hello) {
            $atts["nillable"] = $tableObj->metaData[$hello]["is_nullable"] ? "true" : "false";
            $atts["name"] = $hello;
            $properties = !empty($fieldConf->{$atts["name"]}) ? $fieldConf->{$atts["name"]} : null;
            //$atts["label"] = !empty($properties->alias) ? $properties->alias : $atts["name"];
            if ($gmlUseAltFunctions[$table]['changeFieldName']) {
                $atts["name"] = changeFieldName($atts["name"]);
            }
            $atts["maxOccurs"] = "1";
            if ($tableObj->metaData[$atts["name"]]['type'] == "geometry") {
                $sql = "SELECT * FROM settings.getColumns('f_table_name=''{$table}'' AND f_table_schema=''{$postgisschema}'' AND f_geometry_column=''{$atts["name"]}''',
                    'raster_columns.r_table_name=''{$table}'' AND raster_columns.r_table_schema=''{$postgisschema}''')";
                $typeRow = $postgisObject->fetchRow($postgisObject->execQuery($sql));
                $def = json_decode($typeRow['def']);
                if ($def->geotype && $def->geotype !== "Default") {
                    if ($def->geotype == "LINE") {
                        $def->geotype = "LINESTRING";
                    }
                    $typeRow['type'] = "MULTI" . $def->geotype;
                }
                switch ($typeRow['type']) {
                    case "POINT":
                        $atts["type"] = "gml:PointPropertyType";
                        break;
                    case "LINESTRING":
                        $atts["type"] = "gml:LineStringPropertyType";
                        break;
                    case "POLYGON":
                        $atts["type"] = "gml:PolygonPropertyType";
                        break;
                    case "MULTIPOINT":
                        $atts["type"] = "gml:MultiPointPropertyType";
                        break;
                    case "MULTILINESTRING":
                        $atts["type"] = "gml:MultiLineStringPropertyType";
                        break;
                    case "MULTIPOLYGON":
                        $atts["type"] = "gml:MultiPolygonPropertyType";
                        break;
                }
            } elseif ($tableObj->metaData[$atts["name"]]['type'] == "bytea") {
                if (isset($properties->image) && $properties->image == true) {
                    $atts["type"] = "gc2:imageType";
                    if (isset($fieldConf->$atts["name"]->properties)) {
                        $pJson = json_decode($fieldConf->$atts["name"]->properties, true);
                        if ($pJson["width"]) {
                            $atts["width"] = $pJson["width"];
                        }
                        if ($pJson["quality"]) {
                            $atts["quality"] = $pJson["quality"];
                        }
                    }
                }
            } else {
                if ($tableObj->metaData[$atts["name"]]['type'] == "decimal") {
                    $atts["type"] = "xsd:decimal";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "double") {
                    $atts["type"] = "xsd:double";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "text") {
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "timestamp") {
                    //$atts["type"] = "xsd:dateTime";
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "timestamptz") {
                    //$atts["type"] = "xsd:dateTime";
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "date") {
                    //$atts["type"] = "xsd:date";
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "time") {
                    //$atts["type"] = "xsd:time";
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "timetz") {
                    //$atts["type"] = "xsd:time";
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "bytea") {
                    $atts["type"] = "xsd:base64Binary";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "json") {
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "uuid") {
                    $atts["type"] = "xsd:string";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "int") {
                    $atts["type"] = "xsd:int";
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "string") {
                    unset($atts["type"]);
                } else {
                    if ($tableObj->metaData[$atts["name"]]['is_array']) {
                        $atts["type"] = "xsd:string";
                    } else {
                        $atts["type"] = "xsd:" . $tableObj->metaData[$atts["name"]]['type'];
                    }
                }
                $simpleType = true;
            }
            $atts["minOccurs"] = "0";
            if (!empty($fieldConf->{$atts["name"]}->properties)) {
                unset($atts["type"]);
            }
            writeTag("open", "xsd", "element", $atts, True, True);
            if ($simpleType) {
                $minLength = "0";
                $maxLength = "256";
                if ($tableObj->metaData[$atts["name"]]['type'] == "string") {
                    $maxLength = filter_var($tableObj->metaData[$atts["name"]]['full_type'], FILTER_SANITIZE_NUMBER_INT);
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "decimal") {
                    $tableObj->metaData[$atts["name"]]['type'] = "decimal";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "double") {
                    $tableObj->metaData[$atts["name"]]['type'] = "double";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "text") {
                    $tableObj->metaData[$atts["name"]]['type'] = "string";
                    $maxLength = null;
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "uuid") {
                    $tableObj->metaData[$atts["name"]]['type'] = "string";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "timestamp") {
                    $tableObj->metaData[$atts["name"]]['type'] = "datetime";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "timestamptz") {
                    $tableObj->metaData[$atts["name"]]['type'] = "datetime";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "date") {
                    $tableObj->metaData[$atts["name"]]['type'] = "date";
                    $maxLength = "256";
                }
                if ($tableObj->metaData[$atts["name"]]['type'] == "bytea") {
                    $tableObj->metaData[$atts["name"]]['type'] = "base64Binary";
                }
                if ($atts["name"] == $primeryKey['attname']) {
                    $tableObj->metaData[$atts["name"]]['type'] = "string";
                }
                if (!empty($fieldConf->{$atts["name"]}->properties)) {
                    unset($atts["type"]);
                    echo '<xsd:simpleType><xsd:restriction base="xsd:' . $tableObj->metaData[$atts["name"]]['type'] . '">';

                    if ($fieldConf->{$atts["name"]}->properties == "*") {
                        $distinctValues = $tableObj->getGroupByAsArray($atts["name"]);
                        foreach ($distinctValues["data"] as $prop) {
                            echo "<xsd:enumeration value=\"{$prop}\"/>";
                        }
                    } else {

                        foreach (json_decode($properties->properties) as $prop) {
                            echo "<xsd:enumeration value=\"{$prop}\"/>";
                        }
                    }
                    echo '</xsd:restriction></xsd:simpleType>';

                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "string") {
                    echo '<xsd:simpleType><xsd:restriction base="xsd:' . $tableObj->metaData[$atts["name"]]['type'] . '">';
                    echo "<xsd:minLength value=\"{$minLength}\"/>";
                    if ($maxLength) echo "<xsd:maxLength value=\"{$maxLength}\"/>";
                    echo '</xsd:restriction></xsd:simpleType>';
                }
            }
            writeTag("close", "xsd", "element", NULL, False, True);
            $atts = Null;
        }
        $depth--;
        writeTag("close", "xsd", "sequence", Null, True, True);
        $depth--;
        writeTag("close", "xsd", "extension", Null, True, True);
        $depth--;
        writeTag("close", "xsd", "complexContent", Null, True, True);
        $depth--;
        writeTag("close", "xsd", "complexType", Null, True, True);
        $atts["name"] = $table;
        $atts["type"] = $table . "Type";
        if ($gmlNameSpace) $atts["type"] = $gmlNameSpace . ":" . $atts["type"];

        $atts["substitutionGroup"] = "gml:_Feature";
        writeTag("selfclose", "xsd", "element", $atts, True, True);
        $atts = null;
    }
    $postgisObject->close();
    $depth--;
    writeTag("close", "xsd", "schema", Null, True, True);
}


/**
 * @param string $queryType
 * @throws PhpfastcacheInvalidArgumentException
 */
function doQuery(string $queryType)
{
    global $BBox;
    global $tables;
    global $fields;
    global $wheres;
    global $filters;
    global $postgisObject;
    global $srs;
    global $postgisschema;
    global $tableObj;
    global $timeSlice;
    global $user;
    global $parentUser;
    global $layerObj;
    global $fieldConfArr;
    global $geometryColumnsObj;
    global $specialChars;
    global $version;
    global $outputFormat;
    global $from;

    if (!$srs) {
        makeExceptionReport("You need to specify a srid in the URL.");
    }

    switch ($queryType) {
        case "Select":
            foreach ($tables as $table) {
                $HTTP_FORM_VARS["TYPENAME"] = $table;
                $tableObj = new table($postgisschema . "." . $table);
                if (!$tableObj->exists) {
                    makeExceptionReport("Relation doesn't exist", ["exceptionCode" => "InvalidParameterValue", "locator" => "typeName"]);
                }
                $primeryKey = $tableObj->getPrimeryKey($postgisschema . "." . $table);
                $geomField = $tableObj->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column");
                $fieldConfArr = json_decode($geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$geomField}", "fieldconf"), true);
                $sql = "SELECT ";
                $fieldsArr = [];
                $wheresFlag = false;
                $sql2 = null;
                if (!(empty($fields[$table]))) {
                    $fields[$table] = substr($fields[$table], 0, strlen($fields[$table]) - 1);
                    $fieldsArr[$table] = explode(",", $fields[$table]);
                } else {
                    foreach ($postgisObject->getMetaData($table) as $key => $value) {
                        if (!preg_match($specialChars, $key)) {
                            $fieldsArr[$table][] = $key;
                        }
                    }
                }

                // Start sorting the fields by sort_id
                $arr = array();
                foreach ($fieldsArr[$table] as $value) {
                    if (!empty($fieldConfArr[$value]["sort_id"])) {
                        $arr[] = array($fieldConfArr[$value]["sort_id"], $value);
                    } else {
                        $arr[] = array(0, $value);
                    }
                }
                usort($arr, function ($a, $b) {
                    return $a[0] - $b[0];
                });
                // Filter out ignored fields
                $arr = array_filter($arr, function ($item, $key) use (&$fieldConfArr) {
                    if (empty($fieldConfArr[$item[1]]['ignore'])) {
                        return $item;
                    }
                }, ARRAY_FILTER_USE_BOTH);
                $fieldsArr[$table] = array();
                foreach ($arr as $value) {
                    $fieldsArr[$table][] = $value[1];
                }

                // We add "" around field names in sql, so sql keywords don't mess things up
                foreach ($fieldsArr[$table] as $key => $value) {
                    $fieldsArr[$table][$key] = "\"{$value}\"";
                }
                $sql = $sql . implode(",", $fieldsArr[$table]) . ",\"{$primeryKey['attname']}\" as fid";

                foreach ($tableObj->metaData as $key => $arr) {
                    if ($arr['type'] == "geometry") {
                        $gmlVersion = $outputFormat == "GML3" ? "3" : "2";
                        $longCrs = $version == "1.1.0" ? 1 : 0;
                        $flipAxis = $version == "1.1.0" && $srs == "4326" ? 16 : 0; // flip axis if lat/lon
                        $options = (string)($longCrs + $flipAxis + 4);
                        $sql = str_replace("\"{$key}\"", "ST_AsGml({$gmlVersion},ST_Transform(\"{$key}\",{$srs}),5,{$options}) as \"{$key}\"", $sql);
                        $sql2 = "SELECT ST_Xmin(ST_Extent(ST_Transform(\"" . $key . "\",{$srs}))) AS TXMin,ST_Xmax(ST_Extent(ST_Transform(\"" . $key . "\",{$srs}))) AS TXMax, ST_Ymin(ST_Extent(ST_Transform(\"" . $key . "\",{$srs}))) AS TYMin,ST_Ymax(ST_Extent(ST_Transform(\"" . $key . "\",{$srs}))) AS TYMax ";
                    }
                    if ($arr['type'] == "bytea") {
                        $sql = str_replace("\"{$key}\"", "encode(\"" . $key . "\",'escape') as " . $key, $sql);
                    }
                }
                $from = " FROM \"{$postgisschema}\".\"{$table}\"";
                if ($tableObj->versioning && $timeSlice != false && $timeSlice != "all") {
                    $from .= ",(SELECT gc2_version_gid as _gc2_version_gid,max(gc2_version_start_date) as max_gc2_version_start_date from \"{$postgisschema}\".\"{$table}\" where gc2_version_start_date <= '{$timeSlice}' AND (gc2_version_end_date > '{$timeSlice}' OR gc2_version_end_date is null) GROUP BY gc2_version_gid) as gc2_join";
                }
                if ((!(empty($BBox))) || (!(empty($wheres[$table]))) || (!(empty($filters[$table])))) {
                    $from .= " WHERE ";
                    $wheresFlag = true;
                }
                if (!(empty($wheres[$table]))) {
                    $from .= "(" . $wheres[$table] . ")";

                }
                if ($tableObj->versioning && $timeSlice != "all") {
                    if (!$wheresFlag) {
                        $from .= " WHERE ";
                    } else {
                        $from .= " AND ";
                    }
                    if (!$timeSlice) {
                        $from .= "gc2_version_end_date is null";
                    } else {
                        $from .= "gc2_join._gc2_version_gid = gc2_version_gid AND gc2_version_start_date = gc2_join.max_gc2_version_start_date";
                    }
                }
                if ($tableObj->workflow && $parentUser == false) {
                    $roleObj = $layerObj->getRole($postgisschema, $table, $user);
                    $role = $roleObj["data"][$user];
                    switch ($role) {
                        case "author":
                            $from .= " AND (gc2_status = 3 OR gc2_workflow @> 'author => {$user}')";
                            break;
                        case "publisher":
                        case "reviewer":
                            $from .= "";
                            break;
                        default:
                            $from .= " AND (gc2_status = 3)";
                            break;
                    }

                }
//                die($sql . $from);
                doSelect($table, $sql, $from, $sql2);
            }
            break;
        default:
            break;
    }
}


/**
 *
 *
 * @param $XMin
 * @param $YMin
 * @param $XMax
 * @param $YMax
 */
function genBBox($XMin, $YMin, $XMax, $YMax)
{
    global $depth;
    global $tables;
    global $db;
    global $srs;
    global $version;
    global $srsName;

    writeTag("open", "gml", "boundedBy", null, True, True);
    if ($version == "1.1.0") {

        writeTag("open", "gml", "Envelope", array("srsName" => "urn:ogc:def:crs:EPSG::" . $srs), true, true);
        writeTag("open", "gml", "lowerCorner", null, true, false);
        echo $srs == "4326" ? "{$YMin} {$XMin}" : "{$XMin} {$YMin}";
        writeTag("close", "gml", "lowerCorner", null, false, true);
        writeTag("open", "gml", "upperCorner", null, true, false);
        echo $srs == "4326" ? "{$YMax} {$XMax}" : "{$XMax} {$YMax}";
        writeTag("close", "gml", "upperCorner", null, false, true);
        writeTag("close", "gml", "Envelope", null, True, True);

    } else {
        writeTag("open", "gml", "Box", array("srsName" => "EPSG:" . $srs), True, True);
        writeTag("open", "gml", "coordinates", array("decimal" => ".", "cs" => ",", "ts" => " "), True, False);
        print $XMin . "," . $YMin . " " . $XMax . "," . $YMax;
        writeTag("close", "gml", "coordinates", null, False, True);
        writeTag("close", "gml", "Box", null, True, True);
    }
    writeTag("close", "gml", "boundedBy", null, True, True);
}


/**
 * @param string $table
 * @param string $sql
 * @param string $from
 * @param string|null $sql2
 */
function doSelect(string $table, string $sql, string $from, ?string $sql2): void
{
    global $db;
    global $depth;
    global $postgisObject;
    global $srs;
    global $gmlNameSpaceUri;
    global $lf;
    global $gmlNameSpace;
    global $gmlNameSpaceGeom;
    global $gmlFeature;
    global $gmlGeomFieldName;
    global $gmlUseAltFunctions;
    global $defaultBoundedBox;
    global $cacheDir;
    global $startTime;
    global $thePath;
    global $HTTP_FORM_VARS;
    global $tableObj;
    global $postgisschema;
    global $fieldConfArr;
    global $resultType;
    global $server;
    global $version;
    global $maxFeatures;
    global $fullSql;
    global $user;
    ob_start();

    if (!$postgisObject->db) {
        $postgisObject->connect();
    }
    // Start rules
    $rule = new Rule();
    $walkerRelation = new TableWalkerRelation();
    $walkerRule = new TableWalkerRule(isAuth() ? $user : "*", "wfst", 'select', '');
    $factory = new StatementFactory(PDOCompatible: true);
    // End rules

    $featureCount = "";
    if ($maxFeatures) {
        $featureCount = $maxFeatures;
    } else {
        $countSql = "SELECT COUNT(*) {$from} LIMIT " . FEATURE_LIMIT;
        // Rewrite according to rules
        $select = $factory->createFromString($countSql);
        $rules = $rule->get();
        $walkerRule->setRules($rules);
        // Try DENY
        try {
            $select->dispatch($walkerRule);
        } catch (Exception $e) {
            makeExceptionReport($e->getMessage());
        }
        $countSql = $factory->createFromAST($select, true)->getSql();
        // Rewrite done
        try {
            $res = $postgisObject->prepare($countSql);
            $res->execute();
            $featureCount = (string)$postgisObject->fetchRow($res)["count"];
        } catch (PDOException $e) {
            makeExceptionReport($e->getMessage());
        }
    }
    print "<wfs:FeatureCollection ";
    print "xmlns:xs=\"http://www.w3.org/2001/XMLSchema\" ";
    print "xmlns:wfs=\"http://www.opengis.net/wfs\" ";
    print "xmlns:{$gmlNameSpace}=\"{$gmlNameSpaceUri}\" ";
    print "xmlns:gml=\"http://www.opengis.net/gml\" ";
    print "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" ";
    if ($version == "1.1.0") print "numberOfFeatures=\"{$featureCount}\" timeStamp=\"" . date("Y-m-d\TH:i:s.v\Z") . "\" ";
    print "xsi:schemaLocation=\"{$gmlNameSpaceUri} {$thePath}?service=wfs&amp;version=1.1.0&amp;request=DescribeFeatureType&amp;typeName=" . $HTTP_FORM_VARS["TYPENAME"];
    print " http://www.opengis.net/wfs http://schemas.opengis.net/wfs/{$version}/" . ($version == "1.1.0" ? "wfs" : "WFS-basic") . ".xsd\"";
    print ">";
    if ($resultType == "hits") {
        return;
    }
    if (!$gmlFeature[$table]) {
        $gmlFeature[$table] = $table;
    }
    $postgisObject->begin();
    if ($sql2) {
        $result = $postgisObject->execQuery($sql2 . $from);
        if ($postgisObject->numRows($result) == 1) {
            while ($myrow = $postgisObject->fetchRow($result)) {
                if (!(empty($myrow["txmin"]))) {
                    //added NR
                    genBBox($myrow["txmin"], $myrow["tymin"], $myrow["txmax"], $myrow["tymax"]);
                }
            }
        } else
            print $defaultBoundedBox;
    } else {
        print $defaultBoundedBox;
    }

    $fullSql = $sql . $from . " LIMIT " . ($maxFeatures ?? FEATURE_LIMIT);
    // Rewrite according to rules
    $select = $factory->createFromString($fullSql);
    $rules = $rule->get();
    $walkerRule->setRules($rules);
    // Try DENY
    try {
        $select->dispatch($walkerRule);
    } catch (Exception $e) {
        makeExceptionReport($e->getMessage());
    }
    $fullSql = $factory->createFromAST($select, true)->getSql();
    // Rewrite done
    try {
        $postgisObject->prepare("DECLARE curs CURSOR FOR {$fullSql}")->execute();
        $innerStatement = $postgisObject->prepare("FETCH 1 FROM curs");
    } catch (PDOException $e) {
        makeExceptionReport($e->getMessage(), ["exceptionCode" => "InvalidParameterValue", "locator" => "typeName"]);
    }
    if ($version == "1.1.0") writeTag("open", "gml", "featureMembers", null, True, True);
    while ($innerStatement->execute() && $myrow = $postgisObject->fetchRow($innerStatement, "assoc")) {
        $str = "";
        if ($version != "1.1.0") $str .= writeTag("open", "gml", "featureMember", null, True, True, true);
        $str .= writeTag("open", $gmlNameSpace, $gmlFeature[$table], $version == "1.1.0" ? array("gml:id" => "{$table}.{$myrow["fid"]}") : array("fid" => "{$table}.{$myrow["fid"]}"), True, True, true);
        $numFields = sizeof($myrow);
        $keys = array_keys($myrow);
        for ($i = 0; $i < $numFields; $i++) {
            $fieldName = $keys[$i];
            $fieldValue = $myrow[$fieldName];
            if (
                !empty($fieldName) &&
                !empty($tableObj->metaData[$fieldName] && $tableObj->metaData[$fieldName]['type'] != "geometry") &&
                $fieldName != "txmin" && $fieldName != "tymin" &&
                $fieldName != "txmax" && $fieldName != "tymax" &&
                $fieldName != "tymax" && $fieldName != "oid"
            ) {
                if (!empty($gmlUseAltFunctions['altFieldValue'])) {
                    $fieldValue = altFieldValue($fieldName, $fieldValue);
                }
                if (!empty($gmlUseAltFunctions['altFieldNameToUpper'])) {
                    $fieldName = altFieldNameToUpper($fieldName);
                }
                if (!empty($gmlUseAltFunctions['changeFieldName'])) {
                    $fieldName = changeFieldName($fieldName);
                }
                $fieldProperties = !empty($fieldConfArr[$fieldName]->properties) ? json_decode($fieldConfArr[$fieldName]->properties, true) : null;

                // Important to use $FieldValue !== or else will int 0 evaluate to false
                if ($fieldValue !== false && ($fieldName != "fid" && $fieldName != "FID")) {
                    if (!isset($fieldProperties["type"]) || $fieldProperties["type"] != "image") {
                        $imageAttr = null;
                        if (!empty($fieldValue) && (in_array($tableObj->metaData[$fieldName]["type"], ["string", "text", "json", "jsonb"]))) {
                            $fieldValue = "<![CDATA[" . $fieldValue . "]]>";
                            $fieldValue = str_replace("&", "&#38;", $fieldValue);
                        }
                    }
                    $str .= writeTag("open", $gmlNameSpace, $fieldName, $imageAttr, True, False, true);
                    $str .= $fieldValue;
                    $str .= writeTag("close", $gmlNameSpace, $fieldName, null, False, True, true);
                }
            } elseif (!empty($tableObj->metaData[$fieldName]) && $tableObj->metaData[$fieldName]['type'] == "geometry") {
                // Check if the geometry field use another name space and element name
                if (empty($gmlGeomFieldName[$table])) {
                    $gmlGeomFieldName[$table] = $fieldName;
                }
                if ($gmlNameSpaceGeom) {
                    $tmpNameSpace = $gmlNameSpaceGeom;
                } else {
                    $tmpNameSpace = $gmlNameSpace;
                }
                if (!empty($myrow[$fieldName])) {
                    $str .= writeTag("open", $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True, true);
                    $str .= $myrow[$fieldName];
                    $str .= writeTag("close", $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True, true);
                }
                unset($gmlGeomFieldName[$table]);
            }
        }
        $str .= writeTag("close", $gmlNameSpace, $gmlFeature[$table], null, True, True, true);
        if ($version != "1.1.0") $str .= writeTag("close", "gml", "featureMember", null, True, True, true);
        echo $str;
        flush();
        ob_flush();
    }
    if ($version == "1.1.0") writeTag("close", "gml", "featureMembers", null, True, True);
    $postgisObject->execQuery("CLOSE curs");
    $postgisObject->commit();
    $totalTime = microtime_float() - $startTime;
}

/**
 * @param string $str
 * @param int $no
 * @return string
 */
function dropLastChrs(string $str, int $no): string
{
    $strLen = strlen($str);
    return substr($str, 0, ($strLen) - $no);
}

/**
 * @param string $str
 * @param int $no
 * @return string
 */
function dropFirstChrs(string $str, int $no): string
{
    $strLen = strlen($str);
    return substr($str, $no, $strLen);
}

/**
 * @param string $tag
 * @return string
 */
function dropNameSpace(string $tag): string
{
    $tag = preg_replace('/ \w*(?:\:\w*?)?(?<!gml)(?<!service)(?<!version)(?<!outputFormat)(?<!maxFeatures)(?<!resultType)(?<!typeName)(?<!srsName)(?<!fid)(?<!id)=(\".*?\"|\'.*?\')/s', "", $tag);
    $tag = preg_replace('/\<[a-z|0-9]*(?<!gml):(?:.*?)/', "<", $tag);
    $tag = preg_replace('/\<\/[a-z|0-9]*(?<!gml):(?:.*?)/', "</", $tag);
    return $tag;
}

function dropAllNameSpaces($tag)
{
    $tag = preg_replace("/[\w-]*:/", "", $tag); // remove any namespaces
    // Trim double qoutes. Openlayers adds them to ogc:PropertyName in WFS requets
    $tag = trim($tag, '"');
    return ($tag);
}

/**
 * @param string $field
 * @return string
 */
function altFieldNameToUpper(string $field): string
{
    return strtoupper($field);
}

/**
 * @param string $field
 * @return string
 */
function changeFieldName(string $field): string
{
    if ($field == "ref") {
        return "aendret_navn_paa_element";
    }
    if ($field == "skabt_af") {
        return "ref";
    } else {
        return $field;
    }
}

/**
 * @param string $field
 * @param string $value
 * @return string|bool
 */
function altFieldValue(string $field, string $value): string|bool
{
    global $ODEUMhostName;
    if ($value == -1 || $value == -3600) {
        $value = false;
    }
    if ($value) {
        if (substr($field, 0, 4) == "dato") {
            $value = date("Ymd", $value);
        }
        if ($field == "doklink") {
            if (substr($value, 0, 4) != "http") {
                $value = $ODEUMhostName . "/download" . $value;
            }
        }
        $result = $value;
    } else {
        $result = false;
    }
    return $result;
}

/**
 * @param $value
 * @param $name
 * @return array|float|int|string|string[]
 */
function altUseCdataOnStrings($value, $name)
{
    if (!empty($value) && !is_numeric($value)) {
        $value = "<![CDATA[" . $value . "]]>";
        $value = str_replace("&", "&#38;", $value);
        $result = $value;
    } else {
        $result = $value;
    }
    return $result;
}

/**
 * @param array<mixed> $arr
 * @throws PhpfastcacheInvalidArgumentException
 * @throws GC2Exception
 */
function doParse(array $arr)
{
    global $postgisObject;
    global $user;
    global $postgisschema;
    global $layerObj;
    global $parentUser;
    global $transaction;
    global $db;
    global $trusted;
    global $rowIdsChanged;
    global $logFile;
    global $version;

    ob_start();
    $HTTP_FORM_VARS["TRANSACTION"] = true;

    // We start sql BEGIN block
    $postgisObject->connect();
    $postgisObject->begin();

    $workflowData = [];
    $notEditable = [];

    // Start rules
    $rule = new Rule();
    $walkerRelation = new TableWalkerRelation();
    // End rules

    foreach ($arr as $key => $featureMember) {

        /**
         * INSERT
         */
        if ($key == "Insert") {
            $handles = [];
            if (!is_array($featureMember[0]) && isset($featureMember)) {
                $featureMember = array(0 => $featureMember);
            }
            $walkerRule = new TableWalkerRule(isAuth() ? $user : "*", "wfst", 'insert', '');
            $factory = new StatementFactory(PDOCompatible: true);
            foreach ($featureMember as $hey) {
                $primeryKey = null;
                $globalSrsName = $hey["srsName"] ?? null;
                foreach ($hey as $typeName => $feature) {
                    $gmlId = null;
                    $typeName = dropAllNameSpaces($typeName);
                    if (is_array($feature)) { // Skip handles
                        $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $typeName);
                        $gmlId = $feature["gml:id"] ?? null;
                        if (!$primeryKey) {
                            makeExceptionReport("UnknownFeature", ["exceptionCode" => "NoApplicableCode/"]);
                        }
                        // Filter out any gml ns elements at top level, which shall not be inserted in db
                        foreach ($feature as $field => $value) {
                            $split = explode(":", $field);
                            if (isset($split[1]) && $split[0] != "gml") {
                                $feature[dropAllNameSpaces($field)] = $value;
                                unset($feature[$field]);
                            } elseif (!isset($split[1])) {
                                $feature[$field] = $value;
                            } else {
                                unset($feature[$field]); // unsetting gml ns elementes
                            }
                        }

                        /**
                         * Load pre-processors
                         */
                        foreach (glob(dirname(__FILE__) . "/processors/*/classes/pre/*.php") as $filename) {
                            $class = "app\\wfs\\processors\\" . array_reverse(explode("/", $filename))[3] .
                                "\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
                            $preProcessor = new $class($postgisObject);
                            $preRes = $preProcessor->processInsert($feature, $typeName);
                            if (!$preRes["success"]) {
                                makeExceptionReport($preRes["message"]);
                            }
                            $feature = $preRes["arr"];
                        }

                        /**
                         * Check if table is versioned or has workflow. Add fields when clients doesn't send unaltered fields.
                         */
                        $tableObj = new Table($postgisschema . "." . $typeName);
                        if (!array_key_exists("gc2_version_user", $feature) && $tableObj->versioning) $feature["gc2_version_user"] = null;
                        if (!array_key_exists("gc2_status", $feature) && $tableObj->workflow) $feature["gc2_status"] = null;
                        if (!array_key_exists("gc2_workflow", $feature) && $tableObj->workflow) $feature["gc2_workflow"] = null;

                        $roleObj = $layerObj->getRole($postgisschema, $typeName);

                        $fields = array();
                        $values = array();

                        // In case of UseExisting key generation
                        // UseExisting is set implicit when gml:id attribut is provided in transaction
                        if (!empty($gmlId)) {
                            $fields[] = $primeryKey["attname"];
                            $values[] = $gmlId;
                        }
                        foreach ($feature as $field => $value) {
                            // If primary field is provided we skip it
                            // Or else we get a duplicate key error
                            // when using GenerateNew key generation.
                            // But not for 1.0.0 which doesn't support idgen
                            if ($field == $primeryKey["attname"] && $version != "1.0.0" && !empty($gmlId)) {
                                continue;
                            }
                            $fields[] = $field;
                            $role = $roleObj["data"][$user] ?? "none";
                            if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                                makeExceptionReport("You don't have a role in the workflow of '{$typeName}'");
                            }
                            if (is_array($value) && numberOfDimensions($value) > 1) { // Must be geom if array
                                $wktArr = toWkt($value, false, getAxisOrder($globalSrsName), parseEpsgCode($globalSrsName));
                                //makeExceptionReport(print_r($value, true));
                                $values[] = array("{$field}" => $wktArr[0], "srid" => $wktArr[1]);
                                if (!empty($wktArr[2])) {
                                    // If global gml:id is used and geometry has it owns
                                    // then use the latter
                                    if ($fields[0] = $primeryKey) {
                                        unset($fields[0]);
                                        unset($values[0]);
                                        $fields = array_values($fields);
                                        $values = array_values($values);
                                    }
                                    $fields[] = $primeryKey["attname"];
                                    $values[] = $wktArr[2];
                                }
                                unset($gmlCon);
                                unset($wktArr);
                            } elseif ($field == "gc2_version_user") {
                                $values[] = $user;
                            } elseif ($field == "gc2_status") {
                                switch ($role) {
                                    case "author":
                                        $values[] = 1;
                                        break;
                                    case "reviewer":
                                        $values[] = 2;
                                        break;
                                    default:
                                        $values[] = 3;
                                        break;
                                }
                            } elseif ($field == "gc2_workflow") {
                                switch ($role) {
                                    case "author":
                                        $values[] = "hstore('author', '{$user}')";
                                        break;
                                    case "reviewer":
                                        $values[] = "hstore('reviewer', '{$user}')";
                                        break;
                                    case "publisher":
                                        $values[] = "hstore('publisher', '{$user}')";
                                        break;
                                    default:
                                        $values[] = "''";
                                        break;
                                }
                            } else {
                                $values[] = pg_escape_string($value);
                            }

                        }

                        $forSql['tables'][] = $typeName;
                        $forSql['fields'][] = $fields;
                        $forSql['values'][] = $values;

                        $fields = array();
                        $values = array();

                        // Start HTTP basic authentication
                        if (!$trusted) {
                            $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $typeName, "authentication");
                            if ($auth == "Write" || $auth == "Read/write" || !empty(Input::getAuthUser())) {
                                $HTTP_FORM_VARS["TYPENAME"] = $typeName;
                                include(__DIR__ . "/../inc/http_basic_authen.php");
                            }
                        }
                        // End HTTP basic authentication
                    } else {
                        $handles[] = $feature;
                    }
                }
            }
        } /**
         * UPDATE
         */
        elseif ($key == "Update") {
            if (!isset($featureMember[0])) {
                $featureMember = array(0 => $featureMember);
            }
            $fid = 0;
            $walkerRule = new TableWalkerRule(isAuth() ? $user : "*", "wfst", 'update', '');
            $factory = new StatementFactory(PDOCompatible: true);
            foreach ($featureMember as $hey) {
                $globalSrsName = $hey["srsName"] ?? null;
                $hey["typeName"] = dropAllNameSpaces($hey["typeName"]);
                if (isset($hey['Property']) && !isset($hey['Property'][0])) {
                    $hey['Property'] = array(0 => $hey['Property']);
                }

                /**
                 * Load pre-processors
                 */
                foreach (glob(dirname(__FILE__) . "/processors/*/classes/pre/*.php") as $filename) {
                    $class = "app\\wfs\\processors\\" . array_reverse(explode("/", $filename))[3] .
                        "\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
                    $preProcessor = new $class($postgisObject);
                    $preRes = $preProcessor->processUpdate($hey, $hey["typeName"]);
                    if (!$preRes["success"]) {
                        makeExceptionReport($preRes["message"]);
                    }
                    $hey = $preRes["arr"];
                }

                // Check if table is versioned or has workflow. Add fields when clients doesn't send unaltered fields.
                $tableObj = new table($postgisschema . "." . $hey["typeName"]);
                $gc2_version_user_flag = false;
                $gc2_version_start_date_flag = false;
                $gc2_status_flag = false;
                $gc2_workflow_flag = false;
                foreach ($hey["Property"] as $v) {
                    if ($v["Name"] == "gc2_version_user") $gc2_version_user_flag = true;
                    if ($v["Name"] == "gc2_version_start_date") $gc2_version_start_date_flag = true;
                    if ($v["Name"] == "gc2_status") $gc2_status_flag = true;
                    if ($v["Name"] == "gc2_workflow") $gc2_workflow_flag = true;
                }
                if (!$gc2_version_user_flag && $tableObj->versioning) $hey["Property"][] = array("Name" => "gc2_version_user", "Value" => null);
                if (!$gc2_version_start_date_flag && $tableObj->versioning) $hey["Property"][] = array("Name" => "gc2_version_start_date", "Value" => null);
                if (!$gc2_status_flag && $tableObj->workflow) $hey["Property"][] = array("Name" => "gc2_status", "Value" => null);
                if (!$gc2_workflow_flag && $tableObj->workflow) $hey["Property"][] = array("Name" => "gc2_workflow", "Value" => null);

                $roleObj = $layerObj->getRole($postgisschema, $hey['typeName'], $user);

                foreach ($hey['Property'] as $pair) {
                    // Some clients use ns in the Name element, so it must be stripped
                    $split = explode(":", $pair['Name']);
                    if (isset($split[1])) {
                        $pair['Name'] = dropAllNameSpaces($pair['Name']);
                    }
                    //else {
                    //   continue;
                    // }
                    $fields[$fid][] = $pair['Name'];
                    $role = $roleObj["data"][$user] ?? "none";
                    if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                        makeExceptionReport("You don't have a role in the workflow of '{$hey['typeName']}'");
                    }
                    if (is_array($pair['Value'])) { // Must be geom if array
                        $wktArr = toWkt($pair["Value"], false, getAxisOrder($globalSrsName), parseEpsgCode($globalSrsName));
                        $values[$fid][] = (array("{$pair['Name']}" => $wktArr[0], "srid" => $wktArr[1]));
                        unset($gmlCon);
                        unset($wktArr);
                    } else {
                        $values[$fid][] = $pair['Value'];
                    }
                }
                $forSql2['tables'][$fid] = $hey['typeName'];
                $forSql2['fields'] = $fields;
                $forSql2['values'] = $values;
                $forSql2['wheres'][$fid] = parseFilter($hey['Filter'], $hey['typeName']);
                $fid++;

                // Start HTTP basic authentication
                if (!$trusted) {
                    $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $hey['typeName'], "authentication");
                    if ($auth == "Write" || $auth == "Read/write" || !empty(Input::getAuthUser())) {
                        $HTTP_FORM_VARS["TYPENAME"] = $hey['typeName'];
                        include(__DIR__ . "/../inc/http_basic_authen.php");
                    }
                }
                // End HTTP basic authentication
            }
            $pair = array();
            $values = array();
            $fields = array();
        } /**
         * DELETE
         */
        elseif ($key == "Delete") {
            if (!is_array($featureMember[0]) && isset($featureMember)) {
                $featureMember = array(0 => $featureMember);
            }
            $walkerRule = new TableWalkerRule(isAuth() ? $user : "*", "wfst", 'delete', '');
            $factory = new StatementFactory(PDOCompatible: true);
            foreach ($featureMember as $hey) {
                $hey['typeName'] = dropAllNameSpaces($hey['typeName']);
                if (!isset($hey['Filter'])) {
                    makeExceptionReport("Must specify filter for delete", ["exceptionCode" => "MissingParameterValue"]);
                }

                /**
                 * Load pre-processors
                 */
                foreach (glob(dirname(__FILE__) . "/processors/*/classes/pre/*.php") as $filename) {
                    $class = "app\\wfs\\processors\\" . array_reverse(explode("/", $filename))[3] .
                        "\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
                    $preProcessor = new $class($postgisObject);
                    $preRes = $preProcessor->processDelete($hey, $hey['typeName']);
                    if (!$preRes["success"]) {
                        makeExceptionReport($preRes["message"]);
                    }
                    $hey = $preRes["arr"];
                }

                $forSql3['tables'][] = $hey['typeName'];
                $forSql3['wheres'][] = parseFilter($hey['Filter'], $hey['typeName']);
                $roleObj = $layerObj->getRole($postgisschema, $hey['typeName'], $user);
                $role = $roleObj["data"][$user] ?? "none";
                $tableObj = new table($postgisschema . "." . $hey["typeName"]);
                if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                    makeExceptionReport("You don't have a role in the workflow of '{$hey['typeName']}'");
                }

                // Start HTTP basic authentication
                if (!$trusted) {
                    $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $hey['typeName'], "authentication");
                    if ($auth == "Write" || $auth == "Read/write" || !empty(Input::getAuthUser())) {
                        $HTTP_FORM_VARS["TYPENAME"] = $hey['typeName'];
                        include(__DIR__ . "./../inc/http_basic_authen.php");
                    }
                }
                // End HTTP basic authentication
            }
        } /**
         * NATIVE
         */
        elseif ($key == "Native") {
            makeExceptionReport("", ["exceptionCode" => "NoApplicableCode"]);
        }
    }

    print "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

    if ($version == "1.1.0") {
        echo '<wfs:TransactionResponse xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:wfs="http://www.opengis.net/wfs"
                         xmlns:gml="http://www.opengis.net/gml" xmlns:ogc="http://www.opengis.net/ogc"
                         xmlns:ows="http://www.opengis.net/ows" xmlns:xlink="http://www.w3.org/1999/xlink"
                         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.1.0"
                         xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">';
    } else {
        echo '<wfs:WFS_TransactionResponse version="1.0.0" xmlns:wfs="http://www.opengis.net/wfs"
               xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd">';
    }

    // First we loop through inserts
    if (isset($forSql) && sizeof($forSql['tables']) > 0) {
        $values = [];
        for ($i = 0; $i < sizeof($forSql['tables']); $i++) {
            if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql['tables'][$i], "editable")) {
                Tilecache::bust($postgisschema . "." . $forSql['tables'][$i]);
                $gc2_workflow_flag = false;
                $roleObj = $layerObj->getRole($postgisschema, $forSql['tables'][$i], $user);
                $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $forSql['tables'][$i]);
                $sql = "INSERT INTO \"{$postgisschema}\".\"{$forSql['tables'][$i]}\" (";
                foreach ($forSql['fields'][$i] as $key => $field) {
                    if ($field != "gc2_version_uuid" && $field != "gc2_version_start_date" && $field != "gc2_version_gid") {
                        $fields[] = "\"" . $field . "\"";
                    }
                }
                $sql .= implode(",", $fields);
                unset($fields);
                $sql .= ") VALUES(";
                foreach ($forSql['values'][$i] as $key => $value) {
                    if ($forSql['fields'][$i][$key] != "gc2_version_uuid" && $forSql['fields'][$i][$key] != "gc2_version_start_date" && $forSql['fields'][$i][$key] != "gc2_version_gid") {
                        if (is_array($value)) {
                            $values[] = "ST_Transform(ST_GeometryFromText('" . current($value) . "'," . next($value) . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $forSql['tables'][$i], "srid") . ")";
                        } elseif (empty($value) && !is_numeric($value)) {
                            $values[] = "NULL";
                        } elseif ($forSql['fields'][$i][$key] == "gc2_workflow") { // Don't quote a hstore
                            $values[] = $value;
                            $gc2_workflow_flag = true;
                        } else {
                            $values[] = $postgisObject->quote($value);
                        }
                    }
                }
                $sql .= implode(",", $values);
                $values = [];
                $sql .= ") RETURNING \"{$primeryKey['attname']}\" as gid"; // The query will return the new key
                if ($gc2_workflow_flag) {
                    $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . PgHStore::toPg($roleObj["data"]) . " as roles";
                }
                $sqls['insert'][] = $sql;
//                makeExceptionReport($sql);
            } else {
                $notEditable[$forSql['tables'][0]] = true;
            }
        }
    }

    // Second we loop through updates
    if (isset($forSql2['tables']) && (sizeof($forSql2['tables']) > 0)) {
        for ($i = 0; $i < sizeof($forSql2['tables']); $i++) {
            if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql2['tables'][$i], "editable")) {
                Tilecache::bust($postgisschema . "." . $forSql2['tables'][$i]);
                $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $forSql2['tables'][$i]);
                $tableObj = new table($postgisschema . "." . $forSql2['tables'][$i]);
                $originalFeature = null;
                if ($tableObj->versioning) {
                    // Get original feature
                    $query = "SELECT * FROM {$postgisschema}.{$forSql2['tables'][$i]} WHERE {$forSql2['wheres'][$i]}";
                    $res = $postgisObject->execQuery($query);
                    $originalFeature = $postgisObject->fetchRow($res);
                    // Check if feature is ended
                    if ($originalFeature["gc2_version_end_date"]) {
                        makeExceptionReport("You can't change the history!");
                    }
                    // Clone original feature for ended version
                    $intoArr = array();
                    $selectArr = array();
                    foreach ($originalFeature as $k => $v) {
                        if ($k != $primeryKey['attname']) {
                            if ($k == "gc2_version_end_date") {
                                $intoArr[] = "\"$k\"";
                                $selectArr[] = "now()";
                            } else {
                                $intoArr[] = $selectArr[] = "\"$k\"";
                            }
                        }
                    }
                    $sql = "INSERT INTO \"{$postgisschema}\".\"{$forSql2['tables'][$i]}\"(";
                    $sql .= implode(",", $intoArr);
                    $sql .= ")";
                    $sql .= " SELECT ";
                    $sql .= implode(",", $selectArr);
                    $sql .= " FROM {$postgisschema}.{$forSql2['tables'][$i]}";
                    $sql .= " WHERE {$forSql2['wheres'][$i]}";
                    //makeExceptionReport($sql);

                    $postgisObject->execQuery($sql);
                }
                $sql = "UPDATE \"{$postgisschema}\".\"{$forSql2['tables'][$i]}\" SET ";
                $roleObj = $layerObj->getRole($postgisschema, $forSql2['tables'][$i], $user);
                $role = $roleObj["data"][$user];

                foreach ($forSql2['fields'][$i] as $key => $field) {
                    if (is_array($forSql2['values'][$i][$key])) { // is geometry
                        $value = "ST_Transform(ST_GeometryFromText('" . current($forSql2['values'][$i][$key]) . "'," . next($forSql2['values'][$i][$key]) . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $forSql2['tables'][$i], "srid") . ")";
                    } elseif ($field == "gc2_version_user") {
                        $value = $postgisObject->quote($user);
                    } elseif ($field == "gc2_status") {
                        switch ($role) {
                            case "author":
                                if ($originalFeature[$field] > 1) {
                                    makeExceptionReport("This feature has been " . ($originalFeature[$field] == 2 ? "reviewed" : "published") . ", so an author can't edit it.");
                                }
                                $value = 1;
                                break;
                            case "reviewer":
                                if ($originalFeature[$field] > 2) {
                                    makeExceptionReport("This feature has been published, so a reviewer can't edit it.");
                                }
                                $value = 2;
                                break;
                            case "publisher":
                                $value = 3;
                                break;
                            default:
                                $value = $originalFeature[$field];
                                break;
                        }
                    } elseif ($field == "gc2_workflow") {
                        switch ($role) {
                            case "author":
                                $value = "'{$originalFeature[$field]}'::hstore || hstore('author', '{$user}')";
                                break;
                            case "reviewer":
                                $value = "'{$originalFeature[$field]}'::hstore || hstore('reviewer', '{$user}')";
                                break;
                            case "publisher":
                                $value = "'{$originalFeature[$field]}'::hstore || hstore('publisher', '{$user}')";
                                break;
                            default:
                                $value = "'{$originalFeature[$field]}'::hstore";
                                break;
                        }
                    } elseif ($field == "gc2_version_start_date") {
                        $value = "now()";
                    } elseif (empty($forSql2['values'][$i][$key]) && !is_numeric($forSql2['values'][$i][$key])) {
                        $value = "NULL";
                    } else {
                        $value = $postgisObject->quote($forSql2['values'][$i][$key]); // We need to escape the string
                    }
                    if ($field == $primeryKey['attname']) {
                        // We need the original feature so, we can se if the pri key is changed
                        $query = "SELECT * FROM {$postgisschema}.{$forSql2['tables'][$i]} WHERE {$forSql2['wheres'][$i]}";
                        $res = $postgisObject->execQuery($query);
                        $originalFeature = $postgisObject->fetchRow($res);
                        $newValue = (string)$forSql2['values'][$i][$key];
                        $oldValue = (string)$originalFeature[$primeryKey['attname']];
                        if ($oldValue != $newValue) {
                            makeExceptionReport("It's not possible to update the primary key ({$primeryKey['attname']}). The value of the key is {$oldValue} and new value is {$newValue}");
                        }
                    }
                    $pairs[] = "\"" . $field . "\" =" . $value;
                }
                $sql .= implode(",", $pairs);
                $sql .= " WHERE {$forSql2['wheres'][$i]} RETURNING \"{$primeryKey['attname']}\" as gid";
                if ($tableObj->workflow) {
                    $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . PgHStore::toPg($roleObj["data"]) . " as roles";
                }
                //makeExceptionReport($sql);
                unset($pairs);
                $sqls['update'][] = $sql;
            } else {
                $notEditable[$forSql2['tables'][0]] = true;
            }
        }
    }
    // Third we loop through deletes
    if (isset($forSql3['tables']) && sizeof($forSql3['tables']) > 0) {
        for ($i = 0; $i < sizeof($forSql3['tables']); $i++) {
            if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql3['tables'][$i], "editable")) {
                Tilecache::bust($postgisschema . "." . $forSql3['tables'][$i]);
                $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $forSql3['tables'][$i]);
                $tableObj = new table($postgisschema . "." . $forSql3['tables'][$i]);
                if ($tableObj->versioning) {
                    // Check if its history
                    $res = $postgisObject->execQuery("SELECT gc2_version_end_date FROM {$postgisschema}.{$forSql3['tables'][$i]} WHERE {$forSql3['wheres'][$i]}", "PDO", "select");
                    $checkRow = $postgisObject->fetchRow($res);
                    if ($checkRow["gc2_version_end_date"]) {
                        makeExceptionReport("You can't change the history!");
                    }
                    // Update old record start
                    $sql = "UPDATE \"{$postgisschema}\".\"{$forSql3['tables'][$i]}\" SET gc2_version_end_date = now(), gc2_version_user='{$user}'";
                    if ($tableObj->workflow) {
                        // get original feature from feature
                        $query = "SELECT * FROM {$postgisschema}.{$forSql3['tables'][$i]} WHERE {$forSql3['wheres'][$i]}";
                        $resStatus = $postgisObject->execQuery($query);
                        $originalFeature = $postgisObject->fetchRow($resStatus);
                        $status = $originalFeature["gc2_status"];
                        // Get role
                        $roleObj = $layerObj->getRole($postgisschema, $forSql3['tables'][$i], $user);
                        $role = $roleObj["data"][$user];
                        switch ($role) {
                            case "author":
                                if ($status > 1) {
                                    makeExceptionReport("This feature has been " . ($status == 2 ? "reviewed" : "published") . ", so an author can't delete it.");
                                }
                                $value = 1;
                                break;
                            case "reviewer":
                                if ($status > 2) {
                                    makeExceptionReport("This feature has been published so a reviewer can't delete it.");
                                }
                                $value = 2;
                                break;
                            case "publisher":
                                $value = 3;
                                break;
                            default:
                                $value = $status;
                                break;
                        }
                        $sql .= ", gc2_status = {$value}";
                    }

                    // Update workflow
                    if ($tableObj->workflow) {
                        $workflow = $originalFeature["gc2_workflow"];
                        switch ($role) {
                            case "author":
                                $value = "'{$workflow}'::hstore || hstore('author', '{$user}')";
                                break;
                            case "reviewer":
                                $value = "'{$workflow}'::hstore || hstore('reviewer', '{$user}')";
                                break;
                            case "publisher":
                                $value = "'{$workflow}'::hstore || hstore('publisher', '{$user}')";
                                break;
                            default:
                                $value = "'{$workflow}'::hstore";
                                break;
                        }
                        $sql .= ", gc2_workflow = {$value}";
                    }

                    $sql .= " WHERE {$forSql3['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid";
                    if ($tableObj->workflow) {
                        $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . PgHStore::toPg($roleObj["data"]) . " as roles";
                    }
                    $sqls['delete'][] = $sql;
                    // Update old record end
                } // delete start for not versioned
                else {
                    $sqls['delete'][] = "DELETE FROM \"{$postgisschema}\".\"{$forSql3['tables'][$i]}\" WHERE {$forSql3['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid";
                }
            } else {
                $notEditable[$forSql3['tables'][0]] = true;
            }
        }
    }
    // We fire the sqls
    //makeExceptionReport(print_r($sqls, true));
    if (isset($sqls)) {
        foreach ($sqls as $operation => $sql) {
            foreach ($sql as $singleSql) {
                // Rewrite according to rules
                $select = $factory->createFromString($singleSql);
                $rules = $rule->get();
                $walkerRule->setRules($rules);
                // Try DENY
                try {
                    $select->dispatch($walkerRule);
                } catch (Exception $e) {
                    makeExceptionReport($e->getMessage());
                }
                $statement = $factory->createFromAST($select, true);
                $singleSql = $statement->getSql();
                // Rewrite done
                echo "\n<!-- $singleSql -->";
                $select->dispatch($walkerRelation);
                $usedRelations = $walkerRelation->getRelations();
                if ($operation == "insert") {
                    $split = explode(".", $usedRelations["insert"][0]);
                } else {
                    $split = explode(".", $usedRelations["updateAndDelete"][0]);
                }
                $userFilter = new UserFilter($user, "wfs", $operation, "*", $split[0], $split[1]);
                $geofence = new Geofence($userFilter);
                // Try post proccesing
                try {
                    $geofence->postProcessQuery($select, $rules);
                } catch (Exception $e) {
                    makeExceptionReport($e->getMessage());
                }
                $results[$operation][] = $postgisObject->execQuery($singleSql); // Returning PDOStatement object
            }
        }
    }

    if (sizeof($notEditable) > 0) {
        makeExceptionReport("Layer not editable");
    }
    // TransactionSummary
    echo '<wfs:TransactionSummary>';
    if (isset($results)) {
        $i = 0;
        $u = 0;
        $d = 0;
        //makeExceptionReport(print_r($results, true));
        foreach ($results as $operation => $result) {
            foreach ($result as $tran) {
                $c = isset($tran) ? $tran->rowCount() : 0;
                if ($operation == "insert") {
                    $i += $c;
                }
                if ($operation == "update") {
                    $u += $c;
                }
                if ($operation == "delete") {
                    $d += $c;
                }
            }
        }
        echo "<wfs:totalInserted>" . $i . "</wfs:totalInserted>";
        echo "<wfs:totalUpdated>" . $u . "</wfs:totalUpdated>";
        echo "<wfs:totalDeleted>" . $d . "</wfs:totalDeleted>";
    }

    echo '</wfs:TransactionSummary>';

// TransactionResult
    $rowIdsChanged = []; // Global Array that holds ids from all affected rows. Can be used in post-processes
    echo $version == "1.1.0" ? '<wfs:TransactionResults/>' : '<wfs:TransactionResult handle="mygeocloud-WFS-default-handle"><wfs:Status><wfs:SUCCESS/></wfs:Status></wfs:TransactionResult>';

// InsertResult
    if (isset($results['insert'][0]) && $results['insert'][0]->rowCount() > 0) {
        if (isset($forSql['tables'])) reset($forSql['tables']);
        if (isset($handles)) reset($handles);
        echo $version == "1.1.0" ? '<wfs:InsertResults>' : '<wfs:InsertResult>';
        foreach ($results['insert'] as $res) {
            echo $version === "1.1.0" ? "<wfs:Feature handle=\"" . (isset($handles) ? current($handles) : "") . "\">" : "";
            echo '<ogc:FeatureId fid="';
            if (isset($forSql['tables'])) echo current($forSql['tables']) . ".";
            $row = $postgisObject->fetchRow($res);
            $rowIdsChanged[] = $row['gid'];
            if (isset($row['gid'])) {
                echo $row['gid'];
            } else {
                echo "nan";
            }

            echo '"/>';
            if (isset($row["gc2_workflow"])) {
                $workflowData[] = array(
                    "schema" => $postgisschema,
                    "table" => current($forSql['tables']),
                    "gid" => $row['gid'],
                    "user" => $user,
                    "status" => $row['gc2_status'],
                    "workflow" => $row['gc2_workflow'],
                    "roles" => $row['roles'],
                    "version_gid" => $row['gc2_version_gid'],
                    "operation" => "insert",
                );
            }
            if (isset($forSql['tables'])) next($forSql['tables']);
            if (isset($handles)) next($handles);
            echo $version === "1.1.0" ? '</wfs:Feature>' : '';
        }
        echo $version == "1.1.0" ? '</wfs:InsertResults>' : '</wfs:InsertResult>';
    }

// UpdateResult
    if (isset($results['update'][0]) && $results['update'][0]->rowCount() > 0) {
        if (isset($forSql2['tables'])) reset($forSql2['tables']);
        //echo $version == "1.1.0" ? '<wfs:UpdateResults>' : '<wfs:UpdateResult>';
        foreach ($results['update'] as $res) {
            //echo $version === "1.1.0" ? '<wfs:Feature>' : '';
            //echo '<ogc:FeatureId fid="';
            //if (isset($forSql2['tables'])) echo current($forSql2['tables']) . ".";
            $row = $postgisObject->fetchRow($res);
            $rowIdsChanged[] = $row['gid'];
            if (isset($row['gid'])) {
                //  echo $row['gid'];
            } else {
                //  echo "nan";
            }

            //echo '" />';
            if (isset($row["gc2_workflow"])) {
                $workflowData[] = array(
                    "schema" => $postgisschema,
                    "table" => current($forSql2['tables']),
                    "gid" => $row['gid'],
                    "user" => $user,
                    "status" => $row['gc2_status'],
                    "workflow" => $row['gc2_workflow'],
                    "roles" => $row['roles'],
                    "version_gid" => $row['gc2_version_gid'],
                    "operation" => "update",
                );
            }
            if (isset($forSql2['tables'])) next($forSql2['tables']);
            //echo $version === "1.1.0" ? '</wfs:Feature>' : '';
        }
        //echo $version == "1.1.0" ? '</wfs:UpdateResults>' : '</wfs:UpdateResult>';
    }

// deleteResult
    if (isset($results['delete'][0]) && $results['delete'][0]->rowCount() > 0) {
        if (isset($forSql3['tables'])) reset($forSql3['tables']);
        //echo $version == "1.1.0" ? '<wfs:DeleteResults>' : '<wfs:DeleteResult>';
        foreach ($results['delete'] as $res) {
            //echo $version === "1.1.0" ? '<wfs:Feature>' : '';
            //echo '<ogc:FeatureId fid="';
            //if (isset($forSql3['tables'])) echo current($forSql3['tables']) . ".";
            $row = $postgisObject->fetchRow($res);
            $rowIdsChanged[] = $row['gid'];
            if (isset($row['gid'])) {
                //    echo $row['gid'];
            } else {
                //    echo "nan";
            }
            //echo '" />';
            if (isset($row["gc2_workflow"])) {
                $workflowData[] = array(
                    "schema" => $postgisschema,
                    "table" => current($forSql3['tables']),
                    "gid" => $row['gid'],
                    "user" => $user,
                    "status" => $row['gc2_status'],
                    "workflow" => $row['gc2_workflow'],
                    "roles" => $row['roles'],
                    "version_gid" => $row['gc2_version_gid'],
                    "operation" => "delete",
                );
            }
            if (isset($forSql2['tables'])) next($forSql2['tables']);
            // echo $version === "1.1.0" ? '</wfs:Feature>' : '';
        }
        //echo $version == "1.1.0" ? '</wfs:DeleteResults>' : '</wfs:DeleteResult>';
    }


    if ($version == "1.1.0") {
        echo '</wfs:TransactionResponse>';
    } else {
        echo '</wfs:WFS_TransactionResponse>';
    }


    if (sizeof($workflowData) > 0) {
        $sqls = array();
        foreach ($workflowData as $w) {
            $sql = "INSERT INTO settings.workflow (f_schema_name,f_table_name,gid,status,gc2_user,roles,workflow,version_gid,operation)";
            $sql .= " VALUES('{$w["schema"]}','{$w["table"]}',{$w["gid"]},{$w["status"]},'{$w["user"]}','{$w["roles"]}'::hstore,'{$w["workflow"]}'::hstore,{$w["version_gid"]},'{$w["operation"]}')";
            $sqls[] = $sql;
        }
        // We fire the sqls
        try {
            foreach ($sqls as $sql) {
                $postgisObject->execQuery($sql, "PDO", "transaction");
            }
        } catch (PDOException $e) {
            makeExceptionReport($e->getMessage());
        }
    }

    /**
     * Load post-processors
     */
    foreach (glob(dirname(__FILE__) . "/processors/*/classes/post/*.php") as $filename) {
        $class = "app\\wfs\\processors\\" . array_reverse(explode("/", $filename))[3] .
            "\\classes\\post\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
        $postProcessor = new $class($postgisObject);
        $postRes = $postProcessor->process();
        if (!$postRes["success"]) {
            makeExceptionReport($postRes["message"]);
        }
    }
    $postgisObject->commit();

    $data = ob_get_clean();
    echo $data;
}

/**
 * @param string|array<string> $value
 * @param array<string> $attributes
 */
function makeExceptionReport($value, array $attributes = []): void
{
    global $version;

    ob_get_clean();
    ob_start();
    header("HTTP/1.0 200 " . Util::httpCodeText("200"));
    if ($version == "1.1.0") {
        echo '<ows:ExceptionReport
                xmlns:xs="http://www.w3.org/2001/XMLSchema" 
                xmlns:ows="http://www.opengis.net/ows" 
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                version="1.0.0" 
                xsi:schemaLocation="http://www.opengis.net/ows http://bp.schemas.opengis.net/06-080r2/ows/1.0.0/owsExceptionReport.xsd">';
        writeTag("open", "ows", "Exception", $attributes, true, true);
        writeTag("open", "ows", "ExceptionText", null, true, false);
    } else {
        echo '<ServiceExceptionReport
        	   version="1.2.0"
	           xmlns="http://www.opengis.net/ogc"
	           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	           xsi:schemaLocation="http://www.opengis.net/ogc http://schemas.opengis.net/wfs/1.0.0/OGC-exception.xsd">';
        writeTag("open", null, "ServiceException", null, true, true);
    }
    print "<![CDATA[";
    if (is_array($value)) {
        if (sizeof($value) == 1) {
            print $value[0];
        } else {
            print_r($value);
        }
    } else {
        print $value;
    }
    print "]]>";

    if ($version == "1.1.0") {
        writeTag("close", "ows", "ExceptionText", null, true, true);
        writeTag("close", "ows", "Exception", null, true, true);
        writeTag("close", "ows", "ExceptionReport", null, true, true);
    } else {
        writeTag("close", null, "ServiceException", null, true, true);
        writeTag("close", null, "ServiceExceptionReport", null, true, true);
    }

    $data = ob_get_clean();
    echo $data;
    die();
}

print("\n<!-- Memory used: " . round(memory_get_peak_usage() / 1024) . " KB -->\n");
//print($sessionComment);
// Make sure all is flushed
echo str_pad("", 4096);
flush();
ob_flush();

/**
 * @param array<mixed> $arr
 * @return array<mixed>
 */
function addNs(array $arr): array
{
    return array_combine(
        array_map(function ($k) {
            if ($k == "srsName" || $k == "_content") {
                return $k;
            } else {
                return 'gml:' . $k;
            }
        }, array_keys($arr)),
        array_map(function ($k) {
            if (is_array($k)) {
                return addNs($k);
            } else {
                return $k;
            }
        }, $arr)
    );
}

/**
 * @param array<mixed> $arr
 * @return array<mixed>
 */
function removeNs(array $arr): array
{
    return array_combine(
        array_map(function ($k) {
            return preg_replace("/[\w-]*:/", "", $k);
        }, array_keys($arr)),
        array_map(function ($k) {
            if (is_array($k)) {
                return removeNs($k);
            } else {
                return $k;
            }
        }, $arr)
    );
}

/**
 * @param array<array<mixed>> $arr
 * @param bool|null $coordsOnly
 * @param string|null $axisOrder
 * @param string|null $globalSrid
 * @return array<string|null>
 */
function toWkt(array $arr, ?bool $coordsOnly = false, ?string $axisOrder = null, ?string $globalSrid = null): array
{
    $arr = removeNs($arr);
    $arr = addNs($arr);
    $strEnd = "";
    $srid = null;
    $fid = null;
    foreach ($arr as $key => $value) {
        $str = "";
        $strEnd = ")";
        $srid = isset($value["srsName"]) ? parseEpsgCode($value["srsName"]) : $globalSrid;
        $fid = $value["gml:id"] ?? null;
        if (isset($value["srsName"])) {
            $axisOrder = getAxisOrder($value["srsName"]);
        }
        if (sizeof(explode(":", $key)) == 1) {
            $key = "gml:" . $key;
        }
        switch ($key) {
            case "gml:Point":
            case "gml:LineString":

                $str .= $coordsOnly ? "(" : ($key == "gml:Point" ? "POINT" : "LINESTRING") . "(";
                if (isset($value["gml:coordinates"]) && is_array($value["gml:coordinates"])) {
                    $str .= coordinatesToWKT($value["gml:coordinates"]["_content"], $axisOrder);
                } elseif (isset($value["gml:coordinates"])) {
                    $str .= coordinatesToWKT($value["gml:coordinates"], $axisOrder);
                } elseif (isset($value["gml:pos"]) && is_array($value["gml:pos"])) {
                    $str .= postListToWKT($value["gml:pos"]["_content"], $axisOrder);
                } elseif (isset($value["gml:pos"])) {
                    $str .= postListToWKT($value["gml:pos"], $axisOrder);
                } elseif (isset($value["gml:posList"]) && is_array($value["gml:posList"])) {
                    $str .= postListToWKT($value["gml:posList"]["_content"], $axisOrder);
                } elseif (isset($value["gml:posList"])) {
                    $str .= postListToWKT($value["gml:posList"], $axisOrder);
                }
                break;
            case "gml:Polygon":

                $str .= $coordsOnly ? "((" : "POLYGON((";
                $v = $value["gml:outerBoundaryIs"]["gml:LinearRing"] ?: $value["gml:exterior"]["gml:LinearRing"];
                if (isset($v["gml:coordinates"]) && is_array($v["gml:coordinates"])) {
                    $str .= coordinatesToWKT($v["gml:coordinates"]["_content"], $axisOrder);
                } elseif (isset($v["gml:coordinates"])) {
                    $str .= coordinatesToWKT($v["gml:coordinates"], $axisOrder);
                } elseif (isset($v["gml:posList"]) && is_array($v["gml:posList"])) {
                    $str .= postListToWKT($v["gml:posList"]["_content"], $axisOrder);
                } elseif (isset($v["gml:posList"])) {
                    $str .= postListToWKT($v["gml:posList"], $axisOrder);
                }
                $str .= ")";
                $inner = $value["gml:innerBoundaryIs"] ?: $value["gml:interior"] ?: null;
                if (isset($inner)) {
                    $inner = addDiminsionOnArray($inner);
                }
                if (isset($inner[0]["gml:LinearRing"])) {
                    foreach ($inner as $linearRing) {
                        $v = $linearRing["gml:LinearRing"];
                        if (isset($v["gml:coordinates"]) && is_array($v["gml:coordinates"])) {
                            $str .= ",(" . coordinatesToWKT($v["gml:coordinates"]["_content"], $axisOrder) . ")";
                        } elseif (isset($v["gml:coordinates"])) {
                            $str .= ",(" . coordinatesToWKT($v["gml:coordinates"], $axisOrder) . ")";
                        } elseif (isset($v["gml:posList"]) && is_array($v["gml:posList"])) {
                            $str .= ",(" . postListToWKT($v["gml:posList"]["_content"], $axisOrder) . ")";
                        } elseif (isset($v["gml:posList"])) {
                            $str .= ",(" . postListToWKT($v["gml:posList"], $axisOrder) . ")";
                        }
                    }
                }
                break;
            case "gml:MultiPoint":
                $str .= "MULTIPOINT(";
                $arr = [];
                if (isset(reset($value["gml:pointMember"])["gml:Point"])) {
                    foreach ($value["gml:pointMember"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } elseif (isset($value["gml:pointMember"])) {
                    $arr[] = toWkt($value["gml:pointMember"], true, $axisOrder)[0];
                }
                // MapInfo v15 uses pointMembers instead of pointMember
                if (isset($value["gml:pointMembers"]) && is_array($value["gml:pointMembers"]) && isset(reset($value["gml:pointMembers"])["gml:Point"])) {
                    foreach ($value["gml:pointMembers"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } elseif (isset($value["gml:pointMembers"])) {
                    $arr[] = toWkt($value["gml:pointMembers"], true, $axisOrder)[0];
                }

                $str .= implode(",", $arr);
                break;
            case "gml:MultiLineString":
                $str .= "MULTILINESTRING(";
                $arr = [];
                if (isset(reset($value["gml:lineStringMember"])["gml:LineString"])) {
                    foreach ($value["gml:lineStringMember"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } else {
                    $arr[] = toWkt($value["gml:lineStringMember"], true, $axisOrder)[0];
                }
                $str .= implode(",", $arr);
                break;
            case "gml:MultiCurve":
                $str .= "MULTILINESTRING(";
                $arr = [];
                if (isset(reset($value["gml:curveMember"])["gml:LineString"])) {
                    foreach ($value["gml:curveMember"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } else {
                    $arr[] = toWkt($value["gml:curveMember"], true, $axisOrder)[0];
                }
                $str .= implode(",", $arr);
                break;
            case "gml:MultiPolygon":
                $str .= "MULTIPOLYGON(";
                $arr = [];
                if (isset(reset($value["gml:polygonMember"])["gml:Polygon"])) {
                    foreach ($value["gml:polygonMember"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } else {
                    $arr[] = toWkt($value["gml:polygonMember"], true, $axisOrder)[0];
                }
                $str .= implode(",", $arr);
                break;
            case "gml:MultiSurface":
                $str .= "MULTIPOLYGON(";
                $arr = [];
                if (isset(reset($value["gml:surfaceMember"])["gml:Polygon"])) {
                    foreach ($value["gml:surfaceMember"] as $member) {
                        $arr[] = toWkt($member, true, $axisOrder)[0];
                    }
                } else {
                    $arr[] = toWkt($value["gml:surfaceMember"], true, $axisOrder)[0];
                }
                $str .= implode(",", $arr);
                break;
        }
    }
    return [$str . $strEnd, $srid, $fid];
}

/**
 * @param string $str
 * @param string $axisOrder
 * @return string
 */
function coordinatesToWKT(string $str, string $axisOrder): string
{
    $str = trim(preg_replace('/\s\s+/', ' ', $str));
    $str = str_replace(" ", "&", $str);
    $str = str_replace(",", " ", $str);
    $str = str_replace("&", ",", $str);
    // If urn EPSG reverse the axixOrder
    if ($axisOrder == "latitude") {
        $split = explode(",", $str);
        foreach ($split as $value) {
            $splitCoord = explode(" ", $value);
            $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
        }
        $str = implode(",", $reversedArr);
    }
    return $str;
}

/**
 * @param string $str
 * @param string $axisOrder
 * @return string
 */
function postListToWKT(string $str, string $axisOrder): string
{
    $str = trim(preg_replace('/\s\s+/', ' ', $str));
    $arr = explode(" ", trim($str));
    $i = 1;
    $newStr = "";
    foreach ($arr as $value) {
        $newStr .= $value;
        if (is_int($i / 2)) {
            $newStr .= ",";
        } else {
            $newStr .= " ";
        }
        $i++;
    }
    $newStr = substr($newStr, 0, strlen($newStr) - 1);
    // If urn EPSG reverse the axixOrder
    if ($axisOrder == "latitude") {
        $split = explode(",", $newStr);
        foreach ($split as $value) {
            $splitCoord = explode(" ", $value);
            $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
        }
        $newStr = implode(",", $reversedArr);

    }
    return $newStr;
}

/**
 * EPSG:4326 longitude/latitude assumption
 * http://www.opengis.net/gml/srs/epsg.xml#xxxx longitude/latitude strict
 * urn:x-ogc:def:crs:EPSG:xxxx latitude/longitude strict
 * urn:ogc:def:crs:EPSG::4326 latitude/longitude strict
 *
 * @param string|null $epsg
 * @return string|null
 */
function getAxisOrder(?string $epsg): ?string
{
    if (!$epsg) return null;
    if ($epsg == "urn:ogc:def:crs:EPSG::4326" || substr($epsg, 0, 23) === "urn:x-ogc:def:crs:EPSG:") {
        $first = "latitude";
    } else {
        $first = "longitude";
    }
    return $first;
}

/**
 * EPSG:4326 longitude/latitude assumption
 * http://www.opengis.net/gml/srs/epsg.xml#xxxx longitude/latitude strict
 * urn:x-ogc:def:crs:EPSG:xxxx latitude/longitude strict
 * urn:ogc:def:crs:EPSG::4326 latitude/longitude strict
 *
 * @param string|null $epsg
 * @return string|null
 */
function parseEpsgCode(?string $epsg): ?string
{
    if (!$epsg) {
        return null;
    }
    if (strpos($epsg, "#") !== false) {
        $separartor = "#";
    } else {
        $separartor = ":";
    }

    $split = explode($separartor, $epsg);
    $clean = end($split);
    return preg_replace("/[\w]\./", "", $clean);
}

/**
 * @param array|null $array $array
 * @return array|array[]|null
 */
function addDiminsionOnArray(?array $array): ?array
{
    if (!is_array($array[0]) && isset($array)) {
        $array = array(0 => $array);
    }
    return $array;
}

/**
 * @param string $type
 * @param string|null $ns
 * @param string|null $tag
 * @param array|null $atts
 * @param bool|null $ind
 * @param bool|null $n
 * @param bool $rtn
 * @return string|null
 */
function writeTag(string $type, ?string $ns, ?string $tag, ?array $atts, ?bool $ind, ?bool $n, $rtn = false): ?string
{
    $str = "";
    if ($ns != null) {
        $tag = $ns . ":" . $tag;
    }
    $str .= "<";
    if ($type == "close") {
        $str .= "/";
    }
    $str .= $tag;
    if (!empty($atts)) {
        foreach ($atts as $key => $value) {
            $str .= ' ' . $key . '="' . $value . '"';
        }
    }
    if ($type == "selfclose") {
        $str .= "/";
    }
    $str .= ">";
    if ($rtn) {
        return $str;
    }
    echo $str;
    return null;
}

function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
 * @param array|string $filter
 * @param string $table
 * @return string
 * @throws PhpfastcacheInvalidArgumentException
 */
function parseFilter($filter, string $table): string
{
    global $postgisObject;
    global $postgisschema;
    global $srsName;
    global $srs;

    $table = dropAllNameSpaces($table);
    $st = \app\inc\Model::explodeTableName($table);
    if (!$st['schema']) {
        $st['schema'] = $postgisschema;
    }
    $primeryKey = $postgisObject->getPrimeryKey($st['schema'] . "." . $st['table']);
    if (!isset($filter[0]) && !(isset($filter['And']) || isset($filter['Or']))) {
        $filter = array(0 => $filter);
    }
    $sridOfTable = $postgisObject->getGeometryColumns($table, "srid");
    $i = 0;
    $boolOperator = null;
    $where = [];
    foreach ($filter as $key => $arr) {
        // Skip xmlns:gml key
        if (!is_array($arr)) {
            continue;
        }
        if ($key == "And" || $key == "Or") {
            $boolOperator = $key;
        }
        $first = array_key_first($arr);
        if ($first !== "Not" && is_array($arr[$first]) && !isAssoc($arr[$first])) {
            foreach ($arr[$first] as $f) {
                $where[] = parseFilter([$first => $f], $table);
            }
        } elseif (isset($arr["And"]) || isset($arr["Or"]) && $key !== "Not") {
            if (isset($arr["And"]) && !isAssoc($arr["And"])) {
                foreach ($arr["And"] as $f) {
                    $where[] = parseFilter(["And" => $f], $table);
                }
            } elseif (isset($arr["Or"]) && !isAssoc($arr["Or"])) {
                foreach ($arr["Or"] as $f) {
                    $where[] = parseFilter(["Or" => $f], $table);
                }
            } else {
                $where[] = parseFilter($arr, $table);
            }
        }

        // TODO strip double qoutes from PropertyName - OpenLayers adds them!
        $prop = "Not";
        if (isset($arr[$prop])) {
            $value = $arr[$prop];
            if (is_array($value) && !isAssoc($value)) {
                foreach ($value as $f) {
                    $where[] = "NOT" . parseFilter($f, $table);
                }
            } else {
                $where[] = "NOT" . parseFilter($arr["Not"], $table);
            }
        }
        $prop = "PropertyIsEqualTo";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $matchCase = !(isset($value["matchCase"]) && $value["matchCase"] == "false");
            $value["PropertyName"] = $value["PropertyName"] == "gml:name" ? $primeryKey["attname"] : $value["PropertyName"];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . ($matchCase ? "\"=" : "\" ILIKE ") . $postgisObject->quote($value['Literal']);
        }

        $prop = "PropertyIsNotEqualTo";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\"<>'" . $value['Literal'] . "'";
        }

        $prop = "PropertyIsLessThan";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\"<'" . $value['Literal'] . "'";
        }

        $prop = "PropertyIsGreaterThan";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\">'" . $value['Literal'] . "'";
        }

        $prop = "PropertyIsLessThanOrEqualTo";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\"<='" . $value['Literal'] . "'";
        }

        $prop = "PropertyIsGreaterThanOrEqualTo";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "Not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\">='" . $value['Literal'] . "'";
        }

        $prop = "PropertyIsLike";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "not") {
            $value = $arr[$prop];
            $where[] = "\"" . dropAllNameSpaces($value['PropertyName']) . "\" LIKE '%" . $value['Literal'] . "%'";
        }

        $prop = "PropertyIsBetween";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "not") {
            $value = $arr[$prop];
            $w = [];
            $value['PropertyName'] = dropAllNameSpaces($value['PropertyName']);
            if ($value['LowerBoundary']) {
                $w[] = "\"" . $value['PropertyName'] . "\" > '" . $value['LowerBoundary']['Literal'] . "'";
            }
            if ($value['UpperBoundary']) {
                $w[] = "\"" . $value['PropertyName'] . "\" < '" . $value['UpperBoundary']['Literal'] . "'";
            }
            $where[] = "(" . implode(" AND ", $w) . ")";
        }

        // FeatureID
        if (isset($arr['FeatureId']) && !isset($arr['FeatureId'][0])) {
            $arr['FeatureId'] = array(0 => $arr['FeatureId']);
        }
        if (isset($arr['FeatureId'])) foreach ($arr['FeatureId'] as $value) {
            $value['fid'] = preg_replace("/{$table}\./", "", $value['fid']); // remove table name
            $where[] = "{$primeryKey['attname']}='" . $value['fid'] . "'";
        }
        // GmlObjectId
        if (isset($arr['GmlObjectId'])) {
            $arr['GmlObjectId'] = addDiminsionOnArray($arr['GmlObjectId']);
            if (is_array($arr['GmlObjectId'])) foreach ($arr['GmlObjectId'] as $value) {
                $value['gml:id'] = preg_replace("/{$table}\./", "", $value['gml:id']); // remove table name
                $where[] = "{$primeryKey['attname']}='" . $value['gml:id'] . "'";
            }
        }

        $prop = "Intersects";
        if (isset($arr[$prop]) && isAssoc($arr[$prop]) && $key !== "not") {
            $value = $arr[$prop];
            $value['PropertyName'] = dropAllNameSpaces($value['PropertyName']);
            $wktArr = toWKT($value, false, $srsName ? getAxisOrder($srsName) : "latitude");
            $sridOfFilter = $wktArr[1];
            if (empty($sridOfFilter)) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
            if (empty($sridOfFilter)) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
            $g = "ST_Transform(ST_GeometryFromText('" . $wktArr[0] . "'," . $sridOfFilter . "),$sridOfTable)";
            $where[] =
                "ST_Intersects"
                . "({$g},"
                . dropAllNameSpaces($value['PropertyName']) . ")";
            unset($wktArr);
        }
        //BBox
        if ($arr['BBOX']) {
            $axisOrder = null;
            $sridOfFilter = null;
            if (is_array($arr['BBOX']['gml:Box']['gml:coordinates'])) {
                $arr['BBOX']['gml:Box']['gml:coordinates']['_content'] = str_replace(" ", ",", $arr['BBOX']['gml:Box']['gml:coordinates']['_content']);
                $coordsArr = explode(",", $arr['BBOX']['gml:Box']['gml:coordinates']['_content']);
            } else {
                $arr['BBOX']['gml:Box']['gml:coordinates'] = str_replace(" ", ",", $arr['BBOX']['gml:Box']['gml:coordinates']);
                $coordsArr = explode(",", $arr['BBOX']['gml:Box']['gml:coordinates']);

            }
            if (is_array($arr['BBOX']['gml:Box'])) {
                $sridOfFilter = parseEpsgCode($arr['BBOX']['gml:Box']['srsName']);
                $axisOrder = getAxisOrder($arr['BBOX']['gml:Box']['srsName']);
                if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
                if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
            }
            if (isset($arr['BBOX']['gml:Envelope'])) {
                $coordsArr = array_merge(explode(" ", $arr['BBOX']['gml:Envelope']['gml:lowerCorner']), explode(" ", $arr['BBOX']['gml:Envelope']['gml:upperCorner']));
                $sridOfFilter = parseEpsgCode($arr['BBOX']['gml:Envelope']['srsName']);
                $axisOrder = getAxisOrder($arr['BBOX']['gml:Envelope']['srsName']);
                if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
                if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
            }
            if ($axisOrder == "longitude") {
                $where[] = "ST_Intersects"
                    . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $coordsArr[0] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[1] . "))',"
                    . $sridOfFilter
                    . "),$sridOfTable),"
                    . "\"" . (dropAllNameSpaces($arr['BBOX']['PropertyName']) ?: $postgisObject->getGeometryColumns($table, "f_geometry_column")) . "\")";
            } else {
                $where[] = "ST_Intersects"
                    . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $coordsArr[1] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[0] . "))',"
                    . $sridOfFilter
                    . "),$sridOfTable),"
                    . "\"" . (dropAllNameSpaces($arr['BBOX']['PropertyName']) ?: $postgisObject->getGeometryColumns($table, "f_geometry_column")) . "\")";
            }
        }
        // End of filter parsing
        $i++;
    }
    if (empty($boolOperator)) {
        $boolOperator = "OR";
    }
//    print_r($where);
//    die();
    return "(" . implode(" " . $boolOperator . " ", $where) . ")";
}

/**
 * @param array<mixed> $array
 * @return int
 *
 */
function numberOfDimensions(array $array): int
{
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
    $d = 0;
    foreach ($it as $v)
        $it->getDepth() >= $d and $d = $it->getDepth();
    return ++$d;
}

function getClassName(string $classname): string
{
    if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
    return $pos;
}

function isAuth(): bool
{
    global $user;
    $auth = false;
    $sess = $_SESSION;
    if (isset($_SESSION) && sizeof($_SESSION) > 0) {
        if ($user == $sess["screen_name"]) {
            $auth = true;
        } elseif (!empty($sess["http_auth"]) && ($user == $sess["http_auth"])) {
            $auth = true;
        }
    } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
        $user = explode("@", $_SERVER['PHP_AUTH_USER'])[0];
        $auth = true;
    }
    return $auth;
}
