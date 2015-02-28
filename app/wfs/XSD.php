<?php
$atts["targetNamespace"] = $gmlNameSpaceUri;
$atts["xmlns:xsd"] = "http://www.w3.org/2001/XMLSchema";
$atts["xmlns:gml"] = "http://www.opengis.net/gml";
$atts["xmlns:{$gmlNameSpace}"] = $gmlNameSpaceUri;
$atts["elementFormDefault"] = "qualified";
$atts["version"] = "1.0";
writeTag("open", "xsd", "schema", $atts, True, True);
$atts = null;
$depth++;
$atts["namespace"] = "http://www.opengis.net/gml";
$atts["schemaLocation"] = "http://schemas.opengis.net/gml/2.1.2/feature.xsd";
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
    foreach ($fieldsArr[$table] as $hello) {
        $atts["nillable"] = "true";
        $atts["name"] = $hello;
        if ($gmlUseAltFunctions[$table]['changeFieldName']) {
            $atts["name"] = changeFieldName($atts["name"]);
        }
        $atts["maxOccurs"] = "1";
        $selfclose = true;
        if ($tableObj->metaData[$atts["name"]]['type'] == "geometry") {
            $sql = "SELECT * FROM settings.getColumns('geometry_columns.f_table_name=''{$table}'' AND geometry_columns.f_table_schema=''{$postgisschema}'' AND f_geometry_column=''{$atts["name"]}''',
                    'raster_columns.r_table_name=''{$table}'' AND raster_columns.r_table_schema=''{$postgisschema}''')";
            $typeRow = $postgisObject->fetchRow($postgisObject->execQuery($sql));
            $def = json_decode($typeRow['def']);
            $fieldConf = json_decode($typeRow['fieldconf']);
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
        } else {
            unset($atts["type"]);
        }
        $atts["minOccurs"] = "0";
        writeTag("open", "xsd", "element", $atts, True, True);

        if ($tableObj->metaData[$atts["name"]]['type'] != "geometry") {
            if ($tableObj->metaData[$atts["name"]]['type'] == "number") {
                $tableObj->metaData[$atts["name"]]['type'] = "decimal";
            }
            if ($tableObj->metaData[$atts["name"]]['type'] == "text") {
                $tableObj->metaData[$atts["name"]]['type'] = "string";
            }
            if ($tableObj->metaData[$atts["name"]]['type'] == "uuid") {
                $tableObj->metaData[$atts["name"]]['type'] = "string";
            }
            if ($tableObj->metaData[$atts["name"]]['type'] == "timestamptz") {
                $tableObj->metaData[$atts["name"]]['type'] = "date";
            }
            if ($atts["name"] == $primeryKey['attname']) {
                $tableObj->metaData[$atts["name"]]['type'] = "string";
            }
            echo '<xsd:simpleType><xsd:restriction base="xsd:' . $tableObj->metaData[$atts["name"]]['type'] . '">';
                if ($fieldConf->$atts["name"]->properties){
                    foreach(json_decode($fieldConf->$atts["name"]->properties) as $prop){
                        echo "<xsd:enumeration value=\"{$prop}\"/>";
                    }
                }
            echo '</xsd:restriction></xsd:simpleType>';
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

