<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

$atts["targetNamespace"] = $gmlNameSpaceUri;
$atts["xmlns:xsd"] = "http://www.w3.org/2001/XMLSchema";
$atts["xmlns:gml"] = "http://www.opengis.net/gml";
$atts["xmlns:gc2"] = "http://www.mapcentia.com/gc2";
$atts["xmlns:{$gmlNameSpace}"] = $gmlNameSpaceUri;
$atts["elementFormDefault"] = "qualified";
$atts["version"] = "1.0";
writeTag("open", "xsd", "schema", $atts, True, True);
$atts = null;
$depth++;
$atts["namespace"] = "http://www.opengis.net/gml";
$atts["schemaLocation"] = "http://schemas.opengis.net/gml/2.1.2/feature.xsd";
writeTag("selfclose", "xsd", "import", $atts, True, True);
$atts["namespace"] = "http://www.mapcentia.com/gc2";
$atts["schemaLocation"] = $server . "/xmlschemas/gc2.xsd";
writeTag("selfclose", "xsd", "import", $atts, True, True);
$atts = null;

if (!$tables[0]) {
    $tables = array();
    $sql = "SELECT f_table_name,f_geometry_column,srid FROM public.geometry_columns WHERE f_table_schema='{$postgisschema}'";
    $result = $postgisObject->execQuery($sql);
    if ($postgisObject->PDOerror) {
        makeExceptionReport($postgisObject->PDOerror);
    }
    while ($row = $postgisObject->fetchRow($result)) {
        $tables[] = $row['f_table_name'];
    }
}
foreach ($tables as $table) {
    $tableObj = new \app\models\table($postgisschema . "." . $table);
    $primeryKey = $tableObj->primeryKey;

    $simpleType = false;

    foreach ($tableObj->metaData as $key => $value) {
        if ($key != $primeryKey['attname']) {
            $fieldsArr[$table][] = $key;
        }
    }
    $fields = implode(",", $fieldsArr[$table]);
    $sql = "SELECT '{$fields}' FROM " . $postgisschema . "." . $table . " LIMIT 1";
    $result = $postgisObject->execQuery($sql);
    if ($postgisObject->PDOerror) {
        makeExceptionReport($postgisObject->PDOerror);
    }
    $atts["name"] = $table . "_Type";
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
    $fieldsArr[$table] = array();
    foreach ($arr as $value) {
        $fieldsArr[$table][] = $value[1];
    }
    foreach ($fieldsArr[$table] as $hello) {
        $atts["nillable"] = $tableObj->metaData[$hello]["is_nullable"] ? "true" : "false";
        $atts["name"] = $hello;
        $properties = !empty($fieldConf->{$atts["name"]}) ? $fieldConf->{$atts["name"]} : null;
        $atts["label"] = !empty($properties->alias) ? $properties->alias : $atts["name"];
        if ($gmlUseAltFunctions[$table]['changeFieldName']) {
            $atts["name"] = changeFieldName($atts["name"]);
        }
        $atts["maxOccurs"] = "1";
        $selfclose = true;
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
                    if ($pJson["width"]){
                        $atts["width"] = $pJson["width"];
                    }
                    if ($pJson["quality"]){
                        $atts["quality"] = $pJson["quality"];
                    }
                }
            }
        } else {

            if ($tableObj->metaData[$atts["name"]]['type'] == "number") {
                $atts["type"] = "xsd:decimal";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "text") {
                $atts["type"] = "xsd:string";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "timestamp") {
                $atts["type"] = "xsd:dateTime";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "date") {
                $atts["type"] = "xsd:date";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "time") {
                $atts["type"] = "xsd:time";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "bytea") {
                $atts["type"] = "xsd:base64Binary";
            }
            elseif ($tableObj->metaData[$atts["name"]]['type'] == "json") {
                $atts["type"] = "xsd:string";
            }
            else {
                $atts["type"] = "xsd:" . $tableObj->metaData[$atts["name"]]['type'];
            }
            $simpleType = true;
        }
        $atts["minOccurs"] = "0";
        writeTag("open", "xsd", "element", $atts, True, True);
        if ($simpleType) {
            $minLength = "0";
            $maxLength = "256";

            if ($tableObj->metaData[$atts["name"]]['type'] == "number") {
                $tableObj->metaData[$atts["name"]]['type'] = "decimal";
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

            }
//            if ($tableObj->metaData[$atts["name"]]['type'] == "string") {
//                echo "<xsd:minLength value=\"{$minLength}\"/>";
//                if ($maxLength) echo "<xsd:maxLength value=\"{$maxLength}\"/>";
//            }
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
}
$postgisObject->close();
foreach ($tables as $table) {
    $atts["name"] = $table;
    $atts["type"] = $table . "_Type";
    if ($gmlNameSpace) $atts["type"] = $gmlNameSpace . ":" . $atts["type"];

    $atts["substitutionGroup"] = "gml:_Feature";
    writeTag("selfclose", "xsd", "element", $atts, True, True);
}
$depth--;
writeTag("close", "xsd", "schema", Null, True, True);

