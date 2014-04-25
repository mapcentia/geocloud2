<?php
//$atts["targetNamespace"]="http://www.opengis.net/gml";
$atts["targetNamespace"]=$gmlNameSpaceUri;
$atts["xmlns:xs"]="http://www.w3.org/2001/XMLSchema";
//$atts["xmlns:wfs"]="http://www.opengis.net/wfs";
$atts["xmlns:gml"]="http://www.opengis.net/gml";
$atts["xmlns:{$gmlNameSpace}"]=$gmlNameSpaceUri;
$atts["elementFormDefault"]="qualified";
//$atts["attributeFormDefault"]="unqualified";
$atts["version"]="1.0";
writeTag("open","xs","schema",$atts,True,True);
$atts=null;
$depth++;
$atts["namespace"]="http://www.opengis.net/gml";
$atts["schemaLocation"]="http://schemas.opengis.net/gml/2.1.2/feature.xsd";
writeTag("selfclose","xs","import",$atts,True,True);
$atts=null;
if (!$tables[0]){
    $tables = array();
    $sql="SELECT f_table_name,f_geometry_column,srid FROM public.geometry_columns WHERE f_table_schema='{$postgisschema}'";
    $result = $postgisObject->execQuery($sql);
	if($postgisObject->PDOerror){
		makeExceptionReport($postgisObject->PDOerror);
	}
    while ($row = $postgisObject->fetchRow($result)) {
	    $tables[] = $row['f_table_name'];
    }
}

foreach($tables as $table)
{
	$tableObj = new \app\models\table($postgisschema.".".$table);

	$primeryKey = $tableObj->primeryKey;

	foreach($tableObj->metaData as $key=>$value) {
	 	if ($key!=$primeryKey['attname']) {
			$fieldsArr[$table][] = $key;
		}
	}
	$fields = implode(",",$fieldsArr[$table]);
	$sql="SELECT '{$fields}' FROM " . $postgisschema.".".$table;
	$result = $postgisObject->execQuery($sql);
	if($postgisObject->PDOerror){
		makeExceptionReport($postgisObject->PDOerror);
	}

	$atts["name"]=$table."_Type";
	writeTag("open","xs","complexType",$atts,True,True);
	$atts=null;
	$depth++;
	writeTag("open","xs","complexContent",Null,True,True);
	$depth++;
	$atts["base"]="gml:AbstractFeatureType";

	writeTag("open","xs","extension",$atts,True,True);
	$depth++;
	writeTag("open","xs","sequence",NULL,True,True);

	$atts=null;
	$depth++;
	//print_r($fieldsArr[$table]);
	foreach($fieldsArr[$table] as $hello) {
		//$meta = pg_fetch_field ($result);
		$atts["nillable"]="true";
		$atts["name"]=$hello;
		if ($gmlUseAltFunctions[$table]['changeFieldName']) {
			$atts["name"] = changeFieldName($atts["name"]);
		}
		$atts["maxOccurs"]="1";
		$selfclose=true;
		if($tableObj->metaData[$atts["name"]]['type']=="geometry")
		{
			$geomType = $geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$atts["name"]}","type");
			switch ($geomType) {
				case "POINT":
				$atts["type"]="gml:PointPropertyType";
				break;
				case "LINESTRING":
				$atts["type"]="gml:LineStringPropertyType";
				break;
				case "POLYGON":
				$atts["type"]="gml:PolygonPropertyType";
				break;
				case "MULTIPOINT":
				$atts["type"]="gml:MultiPointPropertyType";
				break;
				case "MULTILINESTRING":
				$atts["type"]="gml:MultiLineStringPropertyType";
				break;
				case "MULTIPOLYGON":
				$atts["type"]="gml:MultiPolygonPropertyType";
				break;
			}
		}
		$atts["minOccurs"]="0";

		if($atts["name"] != $geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$atts["name"]}","f_geometry_column")) {
			if ($tableObj->metaData[$atts["name"]]['type']=="number") {
				$tableObj->metaData[$atts["name"]]['type']="decimal";
			}
			if ($tableObj->metaData[$atts["name"]]['type']=="text") {
				$tableObj->metaData[$atts["name"]]['type']="string";
			}
			if ($atts["name"] == $primeryKey['attname']) {
				$tableObj->metaData[$atts["name"]]['type']="string";
			}
		}
        $atts["type"] = "xs:".$tableObj->metaData[$atts["name"]]['type'];
        writeTag("open","xs","element",$atts,True,True);
		writeTag("close","xs","element",NULL,False,True);
		$atts=Null;
	}
	$depth--;
	writeTag("close","xs","sequence",Null,True,True);
	$depth--;
	writeTag("close","xs","extension",Null,True,True);
	$depth--;
	writeTag("close","xs","complexContent",Null,True,True);
	$depth--;
	writeTag("close","xs","complexType",Null,True,True);
	//$depth--;
}
$postgisObject->close();
foreach($tables as $table){
	$atts["name"] = $table;
	$atts["type"] = $table."_Type";
        if ($gmlNameSpace) $atts["type"] = $gmlNameSpace.":".$atts["type"];

	$atts["substitutionGroup"]="gml:_Feature";
	writeTag("selfclose","xs","element",$atts,True,True);
}
$depth--;
writeTag("close","xs","schema",Null,True,True);

