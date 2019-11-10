<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

use \app\conf\App;
use \app\inc\Log;
use \app\inc\Util;
use \app\models\Table;
use \app\models\Layer;
use \app\conf\Connection;

header('Content-Type:text/xml; charset=UTF-8', TRUE);
header('Connection:close', TRUE);

include "libs/phpgeometry_class.php";
include "models/versions.php";
include "libs/PEAR/XML/Unserializer.php";
include "libs/PEAR/XML/Serializer.php";
include "libs/PEAR/Cache_Lite/Lite.php";
include 'convertgeom.php';
include 'explodefilter.php';

if (empty($gmlNameSpace)) {
    $gmlNameSpace = Connection::$param["postgisdb"];
}

if (empty($gmlNameSpaceUri)) {
    $gmlNameSpaceUri = "http://mapcentia.com/" . Connection::$param["postgisdb"];
}

$postgisdb = Connection::$param["postgisdb"];
$postgisschema = Connection::$param["postgisschema"];
$layerObj = new Layer();

$srs = \app\inc\Input::getPath()->part(4);

$timeSlice = \app\inc\Input::getPath()->part(5);
if ($timeSlice != "all") {
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
        $trusted = true;
        break;
    }
}

function microtime_float()
{
    list($utime, $time) = explode(" ", microtime());
    return ((float)$utime + (float)$time);
}

$startTime = microtime_float();

$uri = str_replace("index.php", "", $_SERVER['REDIRECT_URL']);
$uri = str_replace("//", "/", $uri);

$thePath = "http://" . $_SERVER['SERVER_NAME'] . $uri;
$server = "http://" . $_SERVER['SERVER_NAME'];
$BBox = null;

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
    'parseAttributes' => TRUE,
    'typeHints' => FALSE
);
$unserializer = new XML_Unserializer($unserializer_options);

$sessionComment = "";

$specialChars = "/['^£$%&*()}{@#~?><>,|=+¬]/";

// Post method is used
// ===================

$HTTP_RAW_POST_DATA = file_get_contents("php://input");

if ($HTTP_RAW_POST_DATA) {
    Log::write($HTTP_RAW_POST_DATA);
    $HTTP_RAW_POST_DATA = dropNameSpace($HTTP_RAW_POST_DATA);
    //makeExceptionReport($HTTP_RAW_POST_DATA);

    // HACK. MapInfo 15 sends invalid XML with newline \n and double xmlns:wfs namespace. So we strip those
    $HTTP_RAW_POST_DATA = str_replace("\\n", " ", $HTTP_RAW_POST_DATA);
    $HTTP_RAW_POST_DATA = str_replace("xmlns:wfs=\"http://www.opengis.net/wfs\"", " ", $HTTP_RAW_POST_DATA);

    $status = $unserializer->unserialize($HTTP_RAW_POST_DATA);
    $arr = $unserializer->getUnserializedData();

    $request = $unserializer->getRootName();
    switch ($request) {
        case "GetFeature":
            $transaction = false;
            if (!is_array($arr['Query'][0])) {
                $arr['Query'] = array(0 => $arr['Query']);
            }
            for ($i = 0; $i < sizeof($arr['Query']); $i++) {
                if (!is_array($arr['Query'][$i]['PropertyName'])) {
                    $arr['Query'][$i]['PropertyName'] = array(0 => $arr['Query'][$i]['PropertyName']);
                }
            }
            $HTTP_FORM_VARS["REQUEST"] = "GetFeature";
            foreach ($arr['Query'] as $queries) {
                $queries['typeName'] = dropAllNameSpaces($queries['typeName']);
                $HTTP_FORM_VARS["TYPENAME"] .= $queries['typeName'] . ",";
                if ($queries['PropertyName'][0]) {
                    foreach ($queries['PropertyName'] as $PropertyNames) {
                        // We check if typeName is prefix and add it if its not
                        if (strpos($PropertyNames, ".")) {
                            $HTTP_FORM_VARS["PROPERTYNAME"] .= $PropertyNames . ",";
                        } else {
                            $HTTP_FORM_VARS["PROPERTYNAME"] .= $queries['typeName'] . "." . $PropertyNames . ",";
                        }
                    }
                }
                if (is_array($queries['Filter']) /*&& $arr['version'] == "1.0.0"*/) {
                    @$checkXml = simplexml_load_string($queries['Filter']);
                    if ($checkXml === FALSE) {
                        makeExceptionReport("Filter is not valid");
                    }
                    $wheres[$queries['typeName']] = parseFilter($queries['Filter'], $queries['typeName']);
                }
            }
            $HTTP_FORM_VARS["TYPENAME"] = dropLastChrs($HTTP_FORM_VARS["TYPENAME"], 1);
            $HTTP_FORM_VARS["PROPERTYNAME"] = dropLastChrs($HTTP_FORM_VARS["PROPERTYNAME"], 1);
            break;
        case "DescribeFeatureType":
            $HTTP_FORM_VARS["REQUEST"] = "DescribeFeatureType";
            $HTTP_FORM_VARS["TYPENAME"] = $arr['TypeName'];
            break;
        case "GetCapabilities":
            $HTTP_FORM_VARS["REQUEST"] = "GetCapabilities";
            break;
        case "Transaction":
            $transaction = true;
            $HTTP_FORM_VARS["REQUEST"] = "Transaction";
            if (isset($arr["Insert"])) {
                $transactionType = "Insert";
            }
            if ($arr["Update"]) {
                $transactionType = "update";
            }
            if ($arr["Delete"]) $transactionType = "Delete";
            break;
    }

// Get method is used
// ==================

} else {
    if (sizeof($_GET) > 0) {
        Log::write($_SERVER['QUERY_STRING'] . "\n\n");
        $HTTP_FORM_VARS = $_GET;
        $HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS, CASE_UPPER); // Make keys case insensative
        $HTTP_FORM_VARS["TYPENAME"] = dropAllNameSpaces($HTTP_FORM_VARS["TYPENAME"]); // We remove name space, so $where will get key without it.

        if (!empty($HTTP_FORM_VARS['FILTER'])) {
            @$checkXml = simplexml_load_string($HTTP_FORM_VARS['FILTER']);
            if ($checkXml === FALSE) {
                makeExceptionReport("Filter is not valid");
            }
            //$forUseInSpatialFilter = $HTTP_FORM_VARS['FILTER'];
            $status = $unserializer->unserialize(dropNameSpace($HTTP_FORM_VARS['FILTER']));
            $arr = $unserializer->getUnserializedData();
            $wheres[$HTTP_FORM_VARS['TYPENAME']] = parseFilter($arr, $HTTP_FORM_VARS['TYPENAME']);
        }
    } else {
        $HTTP_FORM_VARS = array("");
    }
}

//HTTP_FORM_VARS is set in script if POST is used
$HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS, CASE_UPPER); // Make keys case
$HTTP_FORM_VARS["TYPENAME"] = dropAllNameSpaces($HTTP_FORM_VARS["TYPENAME"]);
$tables = explode(",", $HTTP_FORM_VARS["TYPENAME"]);
$properties = !empty($HTTP_FORM_VARS["PROPERTYNAME"]) ? explode(",", dropAllNameSpaces($HTTP_FORM_VARS["PROPERTYNAME"])) : null;
$featureids = !empty($HTTP_FORM_VARS["FEATUREID"]) ? explode(",", $HTTP_FORM_VARS["FEATUREID"]) : null;
$bbox = !empty($HTTP_FORM_VARS["BBOX"]) ? explode(",", $HTTP_FORM_VARS["BBOX"]) : null;
$resultType = !empty($HTTP_FORM_VARS["RESULTTYPE"]) ? $HTTP_FORM_VARS["RESULTTYPE"] : null;


// Start HTTP basic authentication
if (!$trusted) {
    $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $HTTP_FORM_VARS["TYPENAME"], "authentication");
    if ($auth == "Read/write") {
        include('inc/http_basic_authen.php');
    }
}
// End HTTP basic authentication

print ("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
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
    foreach ($featureids as $featureid) {
        $__u = explode(".", $featureid);
        foreach ($tables as $table) {
            $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $table);
            if ($table == $__u[0]) {
                $wheresArr[$table][] = "{$primeryKey['attname']}='{$__u[1]}'";
            }
            $wheres[$table] = implode(" OR ", $wheresArr[$table]);
        }
    }
}
if (!(empty($bbox[0]))) {
    if (!(empty($featureids[0]))) {
        $wheres[$table] .= " AND ";
    }

    foreach ($tables as $table) {
        if (!$bbox[4]) {
            //$bbox[4] = $postgisObject->getGeometryColumns($postgisschema . "." . $table, "srid");
            $bbox[4] = $srs;
        }
        $axisOrder = gmlConverter::getAxisOrderFromEpsg($bbox[4]);
        if ($axisOrder == "longitude") {
            $wheres[$table] .= "ST_intersects"
                . "(public.ST_Transform(public.ST_GeometryFromText('POLYGON((" . $bbox[0] . " " . $bbox[1] . "," . $bbox[0] . " " . $bbox[3] . "," . $bbox[2] . " " . $bbox[3] . "," . $bbox[2] . " " . $bbox[1] . "," . $bbox[0] . " " . $bbox[1] . "))',"
                . gmlConverter::parseEpsgCode($bbox[4])
                . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "srid") . "),"
                . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column") . ")";
        } else {
            $wheres[$table] .= "ST_intersects"
                . "(public.ST_Transform(public.ST_GeometryFromText('POLYGON((" . $bbox[1] . " " . $bbox[0] . "," . $bbox[3] . " " . $bbox[0] . "," . $bbox[3] . " " . $bbox[2] . "," . $bbox[1] . " " . $bbox[2] . "," . $bbox[1] . " " . $bbox[0] . "))',"
                . gmlConverter::parseEpsgCode($bbox[4])
                . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "srid") . "),"
                . $postgisObject->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column") . ")";
        }
    }
}
//get the request'


switch (strtoupper($HTTP_FORM_VARS["REQUEST"])) {
    case "GETCAPABILITIES":
        getCapabilities($postgisObject);
        break;
    case "GETFEATURE":
        if (empty($gmlFeatureCollection)) {
            $gmlFeatureCollection = "wfs:FeatureCollection";
        }
        print "<" . $gmlFeatureCollection . "\n";
        print "xmlns=\"http://www.opengis.net/wfs\"\n";
        print "xmlns:wfs=\"http://www.opengis.net/wfs\"\n";
        print "xmlns:gml=\"http://www.opengis.net/gml\"\n";
        print "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n";
        print "xmlns:{$gmlNameSpace}=\"{$gmlNameSpaceUri}\"\n";

        if (!empty($gmlSchemaLocation)) {
            print "xsi:schemaLocation=\"{$gmlSchemaLocation}\"";
        } else {
            print "xsi:schemaLocation=\"{$gmlNameSpaceUri} {$thePath}?REQUEST=DescribeFeatureType&amp;TYPENAME=" . $HTTP_FORM_VARS["TYPENAME"] .
                " http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-basic.xsd\"" .
                " ";
        }
        if ($resultType != "hits") print ">\n";
        doQuery("Select");
        print "</" . $gmlFeatureCollection . ">";

        break;
    case "DESCRIBEFEATURETYPE":
        getXSD($postgisObject);
        break;
    case "TRANSACTION":
        doParse($arr);
        break;
    default:
        makeExceptionReport("Don't know that request");
        break;
}

/**
 *
 *
 * @param unknown $postgisObject
 */
function getCapabilities($postgisObject)
{
    global $srs;
    global $thePath;
    global $db;
    global $gmlNameSpace;
    global $gmlNameSpaceUri;
    global $cacheDir;
    global $postgisschema;
    include 'capabilities.php';
}


/**
 *
 *
 * @param unknown $postgisObject
 */
function getXSD($postgisObject)
{
    global $server;
    global $depth;
    global $db;
    global $tables;
    global $gmlUseAltFunctions;
    global $gmlNameSpace;
    global $gmlNameSpaceUri;
    global $cacheDir;
    global $postgisschema;
    global $geometryColumnsObj;
    include 'XSD.php';
}


/**
 *
 *
 * @param unknown $queryType
 */
function doQuery($queryType)
{
    global $currentTag;
    global $BBox;
    global $tables;
    global $fields;
    global $values;
    global $wheres;
    global $filters;
    global $limits;
    global $disjoints;
    global $resultType;
    global $notDisjoints;
    global $disjointCoords;
    global $notDisjointCoords;
    global $WKTfilters;
    global $filterPropertyNames;
    global $postgisObject;
    global $srs;
    global $useWktToGmlInPHP;
    global $postgisschema;
    global $tableObj;
    global $timeSlice;
    global $user;
    global $parentUser;
    global $layerObj;
    global $dbSplit;
    global $fieldConfArr;
    global $geometryColumnsObj;
    global $specialChars;
    global $trusted;

    if (!$srs) {
        makeExceptionReport("You need to specify a srid in the URL.");
    }

    switch ($queryType) {
        case "Select":
            foreach ($tables as $table) {
                $HTTP_FORM_VARS["TYPENAME"] = $table;
                $tableObj = new table($postgisschema . "." . $table);
                $primeryKey = $tableObj->getPrimeryKey($postgisschema . "." . $table);
                $geomField = $tableObj->getGeometryColumns($postgisschema . "." . $table, "f_geometry_column");
                $fieldConfArr = (array)json_decode($geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$geomField}", "fieldconf"));
                $sql = "SELECT ";
                if ($resultType != "hits") {
                    if (!(empty($fields[$table]))) {

                        $fields[$table] = substr($fields[$table], 0, strlen($fields[$table]) - 1);
                        $fieldsArr[$table] = explode(",", $fields[$table]);
                    } else {
                        foreach ($postgisObject->getMetaData($table) as $key => $value) {
                            if ($key != $primeryKey['attname']) {
                                if (!preg_match($specialChars, $key)) {
                                    $fieldsArr[$table][] = $key;
                                }
                            }
                        }
                    }

                    // Start sorting the fields by sort_id
                    $arr = array();
                    foreach ($fieldsArr[$table] as $value) {
                        if (!empty($fieldConfArr[$value]->sort_id)) {
                            $arr[] = array($fieldConfArr[$value]->sort_id, $value);
                        } else {
                            $arr[] = array(0, $value);
                        }
                    }
                    usort($arr, function ($a, $b) {
                        return $a[0] - $b[0];
                    });
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
                            if ($useWktToGmlInPHP) {
                                $sql = str_replace("\"{$key}\"", "public.ST_AsText(public.ST_Transform(\"" . $key . "\"," . $srs . ")) as " . $key, $sql);
                            } else {
                                $sql = str_replace("\"{$key}\"", "ST_AsGml(public.ST_Transform(\"" . $key . "\"," . $srs . ")) as " . $key, $sql);
                            }
                            $sql2 = "SELECT public.ST_Xmin(public.ST_Extent(public.ST_Transform(\"" . $key . "\",{$srs}))) AS TXMin,public.ST_Xmax(public.ST_Extent(public.ST_Transform(\"" . $key . "\",{$srs}))) AS TXMax, public.ST_Ymin(public.ST_Extent(public.ST_Transform(\"" . $key . "\",{$srs}))) AS TYMin,public.ST_Ymax(public.ST_Extent(public.ST_Transform(\"" . $key . "\",{$srs}))) AS TYMax ";
                        }
                        if ($arr['type'] == "bytea") {
                            $sql = str_replace("\"{$key}\"", "encode(\"" . $key . "\",'escape') as " . $key, $sql);
                        }
                    }
                } else {
                    $sql .= "count(*) as count";
                }
                $from = " FROM {$postgisschema}.{$table}";
                if ($tableObj->versioning && $timeSlice != false && $timeSlice != "all") {
                    $from .= ",(SELECT gc2_version_gid as _gc2_version_gid,max(gc2_version_start_date) as max_gc2_version_start_date from {$postgisschema}.{$table} where gc2_version_start_date <= '{$timeSlice}' AND (gc2_version_end_date > '{$timeSlice}' OR gc2_version_end_date is null) GROUP BY gc2_version_gid) as gc2_join";
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
                        case "reviewer":
                            $from .= "";
                            break;
                        case "publisher":
                            $from .= "";
                            break;
                        default:
                            $from .= " AND (gc2_status = 3)";
                            break;
                    }

                }
                //die($from);
                if ((!(empty($BBox))) || (!(empty($wheres[$table])))) {
                    //$from =dropLastChrs($from, 5);
                    //$from.=")";
                }

                if (!(empty($limits[$table]))) {
                    //$from .= " LIMIT " . $limits[$table];
                }
                //die($sql.$from);
                doSelect($table, $sql, $sql2, $from);
            }
            break;
        default:
            break;
    }
}


/**
 *
 *
 * @param unknown $XMin
 * @param unknown $YMin
 * @param unknown $XMax
 * @param unknown $YMax
 */
function genBBox($XMin, $YMin, $XMax, $YMax)
{
    global $depth;
    global $tables;
    global $db;
    global $srs;

    writeTag("open", "gml", "boundedBy", null, True, True);
    $depth++;
    writeTag("open", "gml", "Box", array("srsName" => "EPSG:" . $srs), True, True);
    $depth++;
    writeTag("open", "gml", "coordinates", array("decimal" => ".", "cs" => ",", "ts" => " "), True, False);
    print $XMin . "," . $YMin . " " . $XMax . "," . $YMax;
    writeTag("close", "gml", "coordinates", null, False, True);
    $depth--;
    writeTag("close", "gml", "Box", null, True, True);
    $depth--;
    writeTag("close", "gml", "boundedBy", null, True, True);
}


/**
 *
 *
 * @param unknown $table
 * @param unknown $sql
 * @param unknown $sql2
 * @param unknown $from
 */
function doSelect($table, $sql, $sql2, $from)
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
    global $useWktToGmlInPHP;
    global $thePath;
    global $HTTP_FORM_VARS;
    global $tableObj;
    global $postgisschema;
    global $fieldConfArr;
    global $resultType;
    global $server;

    if (!$gmlFeature[$table]) {
        $gmlFeature[$table] = $table;
    }
    if ($sql2) {
        $postgisObject->execQuery("BEGIN");
        $result = $postgisObject->execQuery($sql2 . $from);
        //Log::write($sql2.$from."\n");
        if ($postgisObject->numRows($result) == 1) {

            while ($myrow = $postgisObject->fetchRow($result)) {
                if (!(empty($myrow["txmin"]))) {
                    //added NR
                    genBBox($myrow["txmin"], $myrow["tymin"], $myrow["txmax"], $myrow["tymax"]);
                } else {
                    //return;
                }
            }
        } else
            print $defaultBoundedBox;
    } else {
        print $defaultBoundedBox;
    }
    //die($sql . $from);
    $result = $postgisObject->execQuery($sql . $from . " LIMIT 50000");
    if ($postgisObject->numRows($result) < 1) {
        $sql = str_replace(",public.ST_AsText(public.ST_Transform(the_geom,25832)) as the_geom", "", $sql);
        $from = str_replace("view", "join", $from);
        $result = $postgisObject->execQuery($sql . $from);
    }
    Log::write("SQL fired\n\n");
    Log::write($sql . $from . "\n");
    $totalTime = microtime_float() - $startTime;
    Log::write("\nQuery time {$totalTime}\n");
    if ($postgisObject->PDOerror) {
        makeExceptionReport($postgisObject->PDOerror);
    }
    if ($resultType == "hits") {
        $myrow = $postgisObject->fetchRow($result);
        print "\nnumberOfFeatures=\"{$myrow['count']}\"\n";
        // Close the GeometryCollection tag
        print ">\n";
    } else {
        while ($myrow = $postgisObject->fetchRow($result)) {
            writeTag("open", "gml", "featureMember", null, True, True);
            $depth++;
            writeTag("open", $gmlNameSpace, $gmlFeature[$table], array("fid" => "{$table}.{$myrow["fid"]}"), True, True);
            $depth++;
            $checkIfGeomHasPassed = false; // Check that geom field is written out only once.
            $numFields = sizeof($myrow);
            $keys = array_keys($myrow);
            for ($i = 0; $i < $numFields; $i++) {
                $FieldName = $keys[$i];
                $FieldValue = $myrow[$FieldName];
                if ((!empty($tableObj->metaData[$FieldName]) && $tableObj->metaData[$FieldName]['type'] != "geometry") && ($FieldName != "txmin") && ($FieldName != "tymin") && ($FieldName != "txmax") && ($FieldName != "tymax") && ($FieldName != "tymax") && ($FieldName != "oid")) {
                    if (!empty($gmlUseAltFunctions['altFieldValue'])) {
                        $FieldValue = altFieldValue($FieldName, $FieldValue);
                    }
                    if (!empty($gmlUseAltFunctions['altFieldNameToUpper'])) {
                        $FieldName = altFieldNameToUpper($FieldName);
                    }
                    if (!empty($gmlUseAltFunctions['changeFieldName'])) {
                        $FieldName = changeFieldName($FieldName);
                    }
                    $fieldProperties = !empty($fieldConfArr[$FieldName]->properties) ? (array)json_decode($fieldConfArr[$FieldName]->properties) : null;

                    // Important to use $FieldValue !== or else will int 0 evaluate to false
                    if ($FieldValue !== false && ($FieldName != "fid" && $FieldName != "FID")) {
                        if (isset($fieldProperties["type"]) && $fieldProperties["type"] == "image") {
                            //$imageAttr = array("width" => $fieldProperties["width"], "height" => $fieldProperties["height"]);
                        } else {
                            $imageAttr = null;
                            $FieldValue = altUseCdataOnStrings($FieldValue, $FieldName);
                        }
                        writeTag("open", $gmlNameSpace, $FieldName, $imageAttr, True, False);
                        echo (string)$FieldValue;
                        writeTag("close", $gmlNameSpace, $FieldName, null, False, True);
                    }
                } elseif (!empty($tableObj->metaData[$FieldName]) && $tableObj->metaData[$FieldName]['type'] == "geometry") {
                    // Check if the geometry field use another name space and element name
                    if (empty($gmlGeomFieldName[$table])) {
                        $gmlGeomFieldName[$table] = $FieldName;
                    }
                    if ($gmlNameSpaceGeom) {
                        $tmpNameSpace = $gmlNameSpaceGeom;
                    } else {
                        $tmpNameSpace = $gmlNameSpace;
                    }
                    writeTag("open", $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True);
                    $depth++;
                    if ($useWktToGmlInPHP) {
                        $__geoObj = geometryfactory::createGeometry($myrow[$FieldName], "EPSG:" . $srs);
                        echo $__geoObj->getGML();
                        unset($__geoObj);
                    } else {
                        echo $myrow[$FieldName];
                    }
                    $depth--;
                    writeTag("close", $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True);
                    unset($gmlGeomFieldName[$table]);
                }
            }
            $depth--;
            writeTag("close", $gmlNameSpace, $gmlFeature[$table], null, True, True);
            $depth--;
            writeTag("close", "gml", "featureMember", null, True, True);
            $postgisObject->free($result);
            flush();
            ob_flush();
        }
    }
    $totalTime = microtime_float() - $startTime;
    $postgisObject->execQuery("ROLLBACK");
}

/**
 *
 *
 * @param unknown $str
 * @param unknown $no
 * @return unknown
 */
function dropLastChrs($str, $no)
{
    $strLen = strlen($str);
    return substr($str, 0, ($strLen) - $no);
}

/**
 *
 *
 * @param unknown $str
 * @param unknown $no
 * @return unknown
 */
function dropFirstChrs($str, $no)
{
    $strLen = strlen($str);
    return substr($str, $no, $strLen);
}


/**
 *
 *
 * @param unknown $tag
 * @return unknown
 */
function dropNameSpace($tag)
{

    //$tag = html_entity_decode($tag);
    //$tag = gmlConverter::oneLineXML($tag);
    $tag = preg_replace('/ xmlns(?:.*?)?=\".*?\"/', "", $tag); // Remove xmlns with "
    $tag = preg_replace('/ xmlns(?:.*?)?=\'.*?\'/', "", $tag); // Remove xmlns with '
    $tag = preg_replace('/ xsi(?:.*?)?=\".*?\"/', "", $tag); // remove xsi:schemaLocation with "
    $tag = preg_replace('/ xsi(?:.*?)?=\'.*?\'/', "", $tag); // remove xsi:schemaLocation with '
    $tag = preg_replace('/ cs(?:.*?)?=\".*?\"/', "", $tag); //
    $tag = preg_replace('/ cs(?:.*?)?=\'.*?\'/', "", $tag);
    $tag = preg_replace('/ ts(?:.*?)?=\".*?\"/', "", $tag);
    $tag = preg_replace('/ decimal(?:.*?)?=\".*?\"/', "", $tag);
    $tag = preg_replace('/\<wfs:(?:.*?)/', "<", $tag);
    $tag = preg_replace('/\<gml:(?:.*?)/', "<", $tag);
    $tag = preg_replace('/\<ogc:(?:.*?)/', "<", $tag);
    $tag = preg_replace('/\<ns:(?:.*?)/', "<", $tag);

    $tag = preg_replace('/\<\/wfs:(?:.*?)/', "</", $tag);
    $tag = preg_replace('/\<\/gml:(?:.*?)/', "</", $tag);
    $tag = preg_replace('/\<\/ogc:(?:.*?)/', "</", $tag);
    $tag = preg_replace('/\<\/ns:(?:.*?)/', "</", $tag);
    //$tag = preg_replace('/EPSG:(?:.*?)/', "", $tag);


    //$tag = preg_replace("/[\w-]*:(?![\w-]*:)/", "", $tag); // remove any namespaces
    return ($tag);
}

function dropAllNameSpaces($tag)
{

    $tag = preg_replace("/[\w-]*:/", "", $tag); // remove any namespaces
    return ($tag);
}


/**
 *
 *
 * @param unknown $field
 * @return unknown
 */
function altFieldNameToUpper($field)
{
    return strtoupper($field);
}

function changeFieldName($field)
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
 *
 *
 * @param unknown $field
 * @param unknown $value
 * @return unknown
 */
function altFieldValue($field, $value)
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
 *
 *
 * @param unknown $field
 * @param unknown $value
 * @return unknown
 */
function altUseCdataOnStrings($value, $name)
{
    if (!is_numeric($value) && ($value)) {
        $value = "<![CDATA[" . $value . "]]>";
        $value = str_replace("&", "&#38;", $value);
        $result = $value;
    } else {
        $result = $value;
    }
    return $result;
}

function getCartoMobilePictureUrl($table, $fieldName, $cartomobilePictureField, $fid)
{
    global $postgisdb;
    global $postgisschema;
    $str = "http://{$_SERVER['SERVER_NAME']}/apps/getimage/{$postgisdb}/{$postgisschema}/{$table}/{$cartomobilePictureField}/{$fid}";
    //$str  = "<![CDATA[".$str."]]>";
    return $str;
    //return "";
}

$totalTime = microtime_float() - $startTime;
Log::write("\nTotal time {$totalTime}\n");
Log::write("==================\n");
echo "\n<!-- Time: {$totalTime} -->\n";

function doParse($arr)
{
    global $postgisObject;
    global $user;
    global $postgisschema;
    global $layerObj;
    global $parentUser;
    global $transaction;
    global $db;
    global $trusted;

    $serializer_options = array(
        'indent' => '  ',
    );

    // We start sql BEGIN block
    $postgisObject->connect("PDO");
    $postgisObject->begin();

    $Serializer = new XML_Serializer($serializer_options);
    $workflowData = array();
    foreach ($arr as $key => $featureMember) {

        /**
         * INSERT
         */
        if ($key == "Insert") {
            if (!is_array($featureMember[0]) && isset($featureMember)) {
                $featureMember = array(0 => $featureMember);
            }
            foreach ($featureMember as $hey) {
                foreach ($hey as $typeName => $feature) {
                    $typeName = dropAllNameSpaces($typeName);
                    if (is_array($feature)) { // Skip handles
                        // Remove ns from properties
                        foreach ($feature as $field => $value) {
                            $split = explode(":", $field);
                            if ($split[1]) {
                                $feature[dropAllNameSpaces($field)] = $value;
                                unset($feature[$field]);
                            }
                        }

                        /**
                         * Load pre-processors
                         */
                        foreach (glob(dirname(__FILE__) . "/../conf/wfsprocessors/classes/pre/*.php") as $filename) {
                            $class = "app\\conf\\wfsprocessors\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
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
                        $tableObj = new table($postgisschema . "." . $typeName);
                        if (!array_key_exists("gc2_version_user", $feature) && $tableObj->versioning) $feature["gc2_version_user"] = null;
                        if (!array_key_exists("gc2_status", $feature) && $tableObj->workflow) $feature["gc2_status"] = null;
                        if (!array_key_exists("gc2_workflow", $feature) && $tableObj->workflow) $feature["gc2_workflow"] = null;

                        foreach ($feature as $field => $value) {
                            $fields[] = $field;
                            $roleObj = $layerObj->getRole($postgisschema, $typeName, $user);
                            $role = $roleObj["data"][$user];
                            if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                                makeExceptionReport("You don't have a role in the workflow of '{$typeName}'");
                            }
                            if (is_array($value)) { // Must be geom if array
                                // We serialize the geometry back to XML for parsing
                                $Serializer->serialize($value);
                                $gmlCon = new gmlConverter();
                                $wktArr = $gmlCon->gmlToWKT($Serializer->getSerializedData(), array());
                                $values[] = array("{$field}" => $wktArr[0][0], "srid" => $wktArr[1][0]);
                                unset($gmlCon);
                                unset($wktArr);
                                //Log::write($Serializer->getSerializedData()."\n\n");
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
                                    case "publisher":
                                        $values[] = 3;
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
                        //TODO check
                        //$field = "";
                        //$value = "";

                        // Start HTTP basic authentication
                        if (!$trusted) {
                            $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $typeName, "authentication");
                            if ($auth == "Write" OR $auth == "Read/write") {
                                $HTTP_FORM_VARS["TYPENAME"] = $typeName;
                                include('inc/http_basic_authen.php');
                            }
                        }
                        // End HTTP basic authentication

                    }
                }
            }
        }

        /**
         * UPDATE
         */
        if ($key == "Update") {
            if (!is_array($featureMember[0]) && isset($featureMember)) {
                $featureMember = array(0 => $featureMember);
            }
            $fid = 0;
            foreach ($featureMember as $hey) {


                $hey["typeName"] = dropAllNameSpaces($hey["typeName"]);
                if (!is_array($hey['Property'][0]) && isset($hey['Property'])) {
                    $hey['Property'] = array(0 => $hey['Property']);
                }

                /**
                 * Load pre-processors
                 */
                foreach (glob(dirname(__FILE__) . "/../conf/wfsprocessors/classes/pre/*.php") as $filename) {
                    $class = "app\\conf\\wfsprocessors\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
                    $preProcessor = new $class($postgisObject);
                    $preRes = $preProcessor->processUpdate($hey, $hey["typeName"]);
                    if (!$preRes["success"]) {
                        makeExceptionReport($preRes["message"]);
                    }
                    $hey = $preRes["arr"];
                }

                // Check if table is versioned or has workflow. Add fields when clients doesn't send unaltered fields.
                $tableObj = new table($postgisschema . "." . $hey["typeName"]);
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

                foreach ($hey['Property'] as $pair) {
                    // Some clients use ns in the Name element, so it must be stripped
                    $split = explode(":", $pair['Name']);
                    if ($split[1]) {
                        $pair['Name'] = dropAllNameSpaces($pair['Name']);
                    }
                    $fields[$fid][] = $pair['Name'];
                    $roleObj = $layerObj->getRole($postgisschema, $hey['typeName'], $user);
                    $role = $roleObj["data"][$user];
                    if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                        makeExceptionReport("You don't have a role in the workflow of '{$hey['typeName']}'");
                    }
                    if (is_array($pair['Value'])) { // Must be geom if array
                        // We serialize the geometry back to XML for parsing
                        $Serializer->serialize($pair['Value']);
                        Log::write($Serializer->getSerializedData() . "\n\n");
                        $gmlCon = new gmlConverter();
                        $wktArr = $gmlCon->gmlToWKT($Serializer->getSerializedData(), array());
                        $values[$fid][] = (array("{$pair['Name']}" => current($wktArr[0]), "srid" => current($wktArr[1])));
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
                    if ($auth == "Write" OR $auth == "Read/write") {
                        $HTTP_FORM_VARS["TYPENAME"] = $hey['typeName'];
                        include('inc/http_basic_authen.php');
                    }
                }
                // End HTTP basic authentication
            }
            $pair = array();
            $values = array();
            $fields = array();
        }

        /**
         * DELETE
         */
        if ($key == "Delete") {
            if (!is_array($featureMember[0]) && isset($featureMember)) {
                $featureMember = array(0 => $featureMember);
            }
            foreach ($featureMember as $hey) {
                $hey['typeName'] = dropAllNameSpaces($hey['typeName']);

                /**
                 * Load pre-processors
                 */
                foreach (glob(dirname(__FILE__) . "/../conf/wfsprocessors/classes/pre/*.php") as $filename) {
                    $class = "app\\conf\\wfsprocessors\\classes\\pre\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
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
                $role = $roleObj["data"][$user];
                $tableObj = new table($postgisschema . "." . $hey["typeName"]);
                if ($tableObj->workflow && ($role == "none" && $parentUser == false)) {
                    makeExceptionReport("You don't have a role in the workflow of '{$hey['typeName']}'");
                }

                // Start HTTP basic authentication
                if (!$trusted) {
                    $auth = $postgisObject->getGeometryColumns($postgisschema . "." . $hey['typeName'], "authentication");
                    if ($auth == "Write" OR $auth == "Read/write") {
                        $HTTP_FORM_VARS["TYPENAME"] = $hey['typeName'];
                        include('inc/http_basic_authen.php');
                    }
                }
                // End HTTP basic authentication
            }
        }
    }

    echo '<wfs:WFS_TransactionResponse version="1.0.0" xmlns:wfs="http://www.opengis.net/wfs"
  xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd">';

    // First we loop through inserts
    if (sizeof($forSql['tables']) > 0) for ($i = 0; $i < sizeof($forSql['tables']); $i++) {
        if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql['tables'][$i], "editable")) {
            \app\controllers\Tilecache::bust($postgisschema . "." . $forSql['tables'][$i]);
            $gc2_workflow_flag = false;
            $roleObj = $layerObj->getRole($postgisschema, $forSql['tables'][$i], $user);
            $primeryKey = $postgisObject->getPrimeryKey($postgisschema . "." . $forSql['tables'][$i]);
            $sql = "INSERT INTO {$postgisschema}.{$forSql['tables'][$i]} (";
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
                        $values[] = "public.ST_Transform(public.ST_GeometryFromText('" . current($value) . "'," . next($value) . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $forSql['tables'][$i], "srid") . ")";
                    } elseif (!$value) {
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
            unset($values);
            $sql .= ") RETURNING {$primeryKey['attname']} as gid"; // The query will return the new key
            if ($gc2_workflow_flag) {
                $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . \app\inc\PgHStore::toPg($roleObj["data"]) . " as roles";
                $gc2_workflow_flag = false;
            }
            $sqls['insert'][] = $sql;
        } else {
            $notEditable[$forSql['tables'][0]] = true;
        }
    }
    // Second we loop through updates
    if (sizeof($forSql2['tables']) > 0) for ($i = 0; $i < sizeof($forSql2['tables']); $i++) {
        if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql2['tables'][$i], "editable")) {
            \app\controllers\Tilecache::bust($postgisschema . "." . $forSql2['tables'][$i]);
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
                            $intoArr[] = $k;
                            $selectArr[] = "now()";
                        } else {
                            $intoArr[] = $selectArr[] = $k;
                        }
                    }
                }
                $sql = "INSERT INTO {$postgisschema}.{$forSql2['tables'][$i]}(";
                $sql .= implode(",", $intoArr);
                $sql .= ")";
                $sql .= " SELECT ";
                $sql .= implode(",", $selectArr);
                $sql .= " FROM {$postgisschema}.{$forSql2['tables'][$i]}";
                $sql .= " WHERE {$forSql2['wheres'][$i]}";
                //makeExceptionReport($sql);

                $postgisObject->execQuery($sql);
            }
            $sql = "UPDATE {$postgisschema}.{$forSql2['tables'][$i]} SET ";
            $roleObj = $layerObj->getRole($postgisschema, $forSql2['tables'][$i], $user);
            $role = $roleObj["data"][$user];

            foreach ($forSql2['fields'][$i] as $key => $field) {
                if (is_array($forSql2['values'][$i][$key])) { // is geometry
                    $value = "public.ST_Transform(public.ST_GeometryFromText('" . current($forSql2['values'][$i][$key]) . "'," . next($forSql2['values'][$i][$key]) . ")," . $postgisObject->getGeometryColumns($postgisschema . "." . $forSql2['tables'][$i], "srid") . ")";
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
                            $value = "'{$originalFeature[$field]}'::hstore || hstore('author', '{$user}')";;
                            break;
                        case "reviewer":
                            $value = "'{$originalFeature[$field]}'::hstore || hstore('reviewer', '{$user}')";;
                            break;
                        case "publisher":
                            $value = "'{$originalFeature[$field]}'::hstore || hstore('publisher', '{$user}')";;
                            break;
                        default:
                            $value = "'{$originalFeature[$field]}'::hstore";
                            break;
                    }
                } elseif ($field == "gc2_version_start_date") {
                    $value = "now()";
                } elseif (!$forSql2['values'][$i][$key]) {
                    $value = "NULL";
                } else {
                    $value = $postgisObject->quote($forSql2['values'][$i][$key]); // We need to escape the string
                }
                $pairs[] = "\"" . $field . "\" =" . $value;

            }
            $sql .= implode(",", $pairs);
            $sql .= " WHERE {$forSql2['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid";
            if ($tableObj->workflow) {
                $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . \app\inc\PgHStore::toPg($roleObj["data"]) . " as roles";
            }
            //makeExceptionReport($sql);
            unset($pairs);
            $sqls['update'][] = $sql;
        } else {
            $notEditable[$forSql2['tables'][0]] = true;
        }
    }
    // Third we loop through deletes
    if (sizeof($forSql3['tables']) > 0) for ($i = 0; $i < sizeof($forSql3['tables']); $i++) {
        if ($postgisObject->getGeometryColumns($postgisschema . "." . $forSql3['tables'][$i], "editable")) {
            \app\controllers\Tilecache::bust($postgisschema . "." . $forSql3['tables'][$i]);
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
                $sql = "UPDATE {$postgisschema}.{$forSql3['tables'][$i]} SET gc2_version_end_date = now(), gc2_version_user='{$user}'";
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
                            $value = "'{$workflow}'::hstore || hstore('author', '{$user}')";;
                            break;
                        case "reviewer":
                            $value = "'{$workflow}'::hstore || hstore('reviewer', '{$user}')";;
                            break;
                        case "publisher":
                            $value = "'{$workflow}'::hstore || hstore('publisher', '{$user}')";;
                            break;
                        default:
                            $value = "'{$workflow}'::hstore";
                            break;
                    }
                    $sql .= ", gc2_workflow = {$value}";
                }

                $sql .= " WHERE {$forSql3['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid";
                if ($tableObj->workflow) {
                    $sql .= ",gc2_version_gid,gc2_status,gc2_workflow," . \app\inc\PgHStore::toPg($roleObj["data"]) . " as roles";
                }
                $sqls['delete'][] = $sql;
                // Update old record end
            } // delete start for not versioned
            else {
                $sqls['delete'][] = "DELETE FROM {$postgisschema}.{$forSql3['tables'][$i]} WHERE {$forSql3['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid";
            }
        } else {
            $notEditable[$forSql3['tables'][0]] = true;
        }
    }
    // We fire the sqls
    if (isset($sqls)) foreach ($sqls as $operation => $sql) {
        foreach ($sql as $singleSql) {
            $results[$operation][] = $postgisObject->execQuery($singleSql, "PDO", "select"); // Returning PDOStatement object
            Log::write("Sqls fired\n");
            Log::write("{$singleSql}\n");
        }
    }
    // If a layer is not editable, PDOerror is set.
    if (sizeof($notEditable) > 0) {
        $postgisObject->PDOerror[0] = "Layer not editable";
    }

    // WFS message
    echo '<wfs:Message>';
    echo '</wfs:Message>';

    // TransactionResult
    if (sizeof($postgisObject->PDOerror) == 0) {
        echo '<wfs:TransactionResult handle="mygeocloud-WFS-default-handle"><wfs:Status><wfs:SUCCESS/></wfs:Status></wfs:TransactionResult>';
    } else {
        echo '<wfs:TransactionResult handle="mygeocloud-WFS-default-handle"><wfs:Status><wfs:FAILURE/></wfs:Status></wfs:TransactionResult>';
        Log::write("Error in\n");
        foreach ($postgisObject->PDOerror as $str) {
            Log::write("{$str}\n");
        }
        Log::write("ROLLBACK\n");
        $postgisObject->rollback();
        $results['insert'] = NULL; // Was object
        $results['update'] = NULL; // Was object
        $results['delete'] = 0;
        makeExceptionReport($postgisObject->PDOerror); // This output a exception and kills the script
    }

    // InsertResult
    $numOfInserts = 0;
    if (sizeof($results['insert']) > 0) {
        if (isset($forSql['tables'])) reset($forSql['tables']);
        echo '<wfs:InsertResult>';
        foreach ($results['insert'] as $res) {
            echo '<ogc:FeatureId fid="';
            if (isset($forSql['tables'])) echo current($forSql['tables']) . ".";
            $row = $postgisObject->fetchRow($res);

            if ($row['gid']) {
                echo $row['gid'];
                $numOfInserts++;
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
        }
        echo '</wfs:InsertResult>';
    }

    // UpdateResult
    $numOfUpdates = 0;
    if (sizeof($results['update']) > 0) {
        if (isset($forSql2['tables'])) reset($forSql2['tables']);
        echo '<wfs:UpdateResult>';
        foreach ($results['update'] as $res) {
            echo '<ogc:FeatureId fid="';
            if (isset($forSql2['tables'])) echo current($forSql2['tables']) . ".";
            $row = $postgisObject->fetchRow($res);

            if ($row['gid']) {
                echo $row['gid'];
                $numOfUpdates++;
            } else {
                echo "nan";
            }

            echo '" />';
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
        }
        echo '</wfs:UpdateResult>';
    }

    // deleteResult
    $numOfDeletes = 0;
    if (sizeof($results['delete']) > 0) {
        if (isset($forSql3['tables'])) reset($forSql3['tables']);
        foreach ($results['delete'] as $res) {
            $row = $postgisObject->fetchRow($res);

            if ($row['gid']) {
                echo $row['gid'];
                $numOfDeletes++;
            } else {
                echo "nan";
            }

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
        }
    }

    // TransactionSummary
    echo '<wfs:TransactionSummary>';
    if (isset($results)) foreach ($results as $operation => $result) {

        if ($operation == "insert") {
            echo "<wfs:totalInserted>" . $numOfInserts . "</wfs:totalInserted>";
        }
        if ($operation == "update") {
            echo "<wfs:totalUpdated>" . $numOfUpdates . "</wfs:totalUpdated>";
        }
        if ($operation == "delete") {
            echo "<wfs:totalDeleted>" . $numOfDeletes . "</wfs:totalDeleted>";
        }
    }
    echo '</wfs:TransactionSummary>';
    echo '</wfs:WFS_TransactionResponse>';

    if (sizeof($workflowData) > 0) {
        $sqls = array();
        foreach ($workflowData as $w) {
            $sql = "INSERT INTO settings.workflow (f_schema_name,f_table_name,gid,status,gc2_user,roles,workflow,version_gid,operation)";
            $sql .= " VALUES('{$w["schema"]}','{$w["table"]}',{$w["gid"]},{$w["status"]},'{$w["user"]}','{$w["roles"]}'::hstore,'{$w["workflow"]}'::hstore,{$w["version_gid"]},'{$w["operation"]}')";
            $sqls[] = $sql;
        }
        // We fire the sqls
        foreach ($sqls as $sql) {
            $postgisObject->execQuery($sql, "PDO", "transaction");
        }
        if (sizeof($postgisObject->PDOerror) > 0) {
            makeExceptionReport($postgisObject->PDOerror); // This output a exception and kills the script
        }
    }

    /**
     * Load post-processors
     */
    foreach (glob(dirname(__FILE__) . "/../conf/wfsprocessors/classes/post/*.php") as $filename) {
        $class = "app\\conf\\wfsprocessors\\classes\\post\\" . explode(".", array_reverse(explode("/", $filename))[0])[0];
        $postProcessor = new $class($postgisObject);
        $postRes = $postProcessor->process();
        if (!$postRes["success"]) {
            makeExceptionReport($postRes["message"]);
        }
    }
    $postgisObject->commit();
}

function makeExceptionReport($value)
{
    global $sessionComment;
    global $postgisObject;
    ob_get_clean();
    ob_start();
    //$postgisObject->rollback();
    echo '<ServiceExceptionReport
	   version="1.2.0"
	   xmlns="http://www.opengis.net/ogc"
	   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	   xsi:schemaLocation="http://www.opengis.net/ogc http://wfs.plansystem.dk:80/geoserver/schemas//wfs/1.0.0/OGC-exception.xsd">
	   <ServiceException>';
    if (is_array($value)) {
        if (sizeof($value) == 1) {
            print $value[0];
        } else {
            print_r($value);
        }
    } else {
        print $value;
    }
    echo '</ServiceException>
	</ServiceExceptionReport>';
    $data = ob_get_clean();
    header("HTTP/1.0 200 " . \app\inc\Util::httpCodeText("200"));
    echo $data;
    print("\n" . $sessionComment);
    Log::write($data);
    die();
}

print("<!-- Memory used: " . round(memory_get_peak_usage() / 1024) . " KB -->\n");
print($sessionComment);
print ("<!--\n");
print ("\n-->\n");