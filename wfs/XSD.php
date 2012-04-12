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
	$tableObj = new table($postgisschema.".".$table);

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
		//$nillable=$meta->not_null;
		//if($nillable==1){
		//$atts["nillable"]="false";
		//$atts["minOccurs"]="1";
		//}
		//else{
		//}
		$selfclose=true;
		if($tableObj->metaData[$atts["name"]]['type']=="geometry")
		{
			//$geomType = $postgisObject -> getGeometryColumns($postgisschema.".".$table, "type");
			$geomType = $geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$atts["name"]}","type");
			fb($geomType);
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
		//else $atts["type"]="xs:string";
		else unset($atts["type"]);
		
		$atts["minOccurs"]="0";
		writeTag("open","xs","element",$atts,True,True);
		if($atts["name"] != $geometryColumnsObj->getValueFromKey("{$postgisschema}.{$table}.{$atts["name"]}","f_geometry_column")) {
			if ($tableObj->metaData[$atts["name"]]['type']=="number") {
					$tableObj->metaData[$atts["name"]]['type']="decimal";
			}
			echo '<xs:simpleType><xs:restriction base="xs:'.$tableObj->metaData[$atts["name"]]['type'].'"></xs:restriction></xs:simpleType>';
		}
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

