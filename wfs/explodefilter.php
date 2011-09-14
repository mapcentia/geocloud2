<?php
/**
 *
 *
 * @param unknown $arr
 * @return unknown
 */
function parseFilter($filter,$table,$operator="=") {
	global $postgisObject;
	//global $forUseInSpatialFilter;
	global $srs;
	$serializer_options = array ( 
	   'indent' => '  ', 
	); 
	$Serializer = &new XML_Serializer($serializer_options); 
	if (!is_array($filter[0]) && isset($filter) && !(isset($filter['And']) OR isset($filter['Or']) OR isset($filter['Not']))) {
	  	$filter = array(0 => $filter);
	}
	
		$sridOfTable = $postgisObject -> getGeometryColumns($table, "srid");

        $i = 0;
	foreach($filter as $key=>$arr) {
	        if ($key == "And" || $key == "Or") $BoolOperator = $key;
		
		if (isset($arr['Not'])) {
			//$where[] = parseFilter($arr['Not'],$table,"<>");
		}
		if (isset($arr['And']) || isset($arr['Or'])) {
			// Recursive call
			$where[] = parseFilter($arr,$table);
		}
		// PropertyIsEqualTo
		$arr['PropertyIsEqualTo'] = addDiminsionOnArray($arr['PropertyIsEqualTo']);
		if (is_array($arr['PropertyIsEqualTo'])) foreach ($arr['PropertyIsEqualTo'] as $value) {
			$where[] = $value['PropertyName']."='".$value['Literal']."'";
		}
		// PropertyIsNotEqualTo
		$arr['PropertyIsNotEqualTo'] = addDiminsionOnArray($arr['PropertyIsNotEqualTo']);
		if (is_array($arr['PropertyIsNotEqualTo'])) foreach ($arr['PropertyIsNotEqualTo'] as $value) {
			$where[] = $value['PropertyName']."<>'".$value['Literal']."'";
		}
		// FeatureID
		if (!is_array($arr['FeatureId'][0]) && isset($arr['FeatureId'])) {
			 $arr['FeatureId'] = array(0 => $arr['FeatureId']);
		}
		if (is_array($arr['FeatureId'])) foreach ($arr['FeatureId'] as $value) {
			$value['fid'] = preg_replace("/{$table}\./","",$value['fid']); // remove table name
			$where[] = "gid=".$value['fid'];
		}
		// GmlObjectId
		$arr['GmlObjectId'] = addDiminsionOnArray($arr['GmlObjectId']);
		if (is_array($arr['GmlObjectId'])) foreach ($arr['GmlObjectId'] as $value) {
			$value['id'] = preg_replace("/{$table}\./","",$value['id']); // remove table name
			$where[] = "gid=".$value['id'];
		}
		//Intersects
		$arr['Intersects'] = addDiminsionOnArray($arr['Intersects']);
		if (is_array($arr['Intersects'])) foreach ($arr['Intersects'] as $value) {
			$status = $Serializer->serialize($value);
									
			$gmlCon = new gmlConverter();
			//logfile::write($Serializer->getSerializedData()."\n\n");
			$wktArr = $gmlCon -> gmlToWKT($Serializer->getSerializedData(),array());
			
			$sridOfFilter = $wktArr[1][0];
			if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
			if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs

			$where[] = "intersects"
				."(transform(GeometryFromText('".$wktArr[0][0]."',"
				.$sridOfFilter
				."),$sridOfTable),"
				.$value['PropertyName'].")";

			unset($gmlCon);
			unset($wktArr);
		}
		//BBox
		if ($arr['BBOX']) {
			if (is_array($arr['BBOX']['Box']['coordinates'])) {
				$arr['BBOX']['Box']['coordinates']['_content'] = str_replace(" ",",",$arr['BBOX']['Box']['coordinates']['_content']);
				$coordsArr = explode(",",$arr['BBOX']['Box']['coordinates']['_content']);
			}

			else {
				$arr['BBOX']['Box']['coordinates'] = str_replace(" ",",",$arr['BBOX']['Box']['coordinates']);
				$coordsArr = explode(",",$arr['BBOX']['Box']['coordinates']);
				
			}
			$sridOfFilter = gmlConverter::parseEpsgCode($arr['BBOX']['Box']['srsName']);
			if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
			if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs

			/*
			$coordsArr[0] = floor($coordsArr[0]/1000)*1000;
			$coordsArr[1] = floor($coordsArr[1]/1000)*1000;
			$coordsArr[2] = ceil($coordsArr[2]/1000)*1000;
			$coordsArr[3] = ceil($coordsArr[3]/1000)*1000;
			 */

			$where[] = "intersects"
				."(transform(GeometryFromText('POLYGON((".$coordsArr[0]." ".$coordsArr[1].",".$coordsArr[0]." ".$coordsArr[3].",".$coordsArr[2]." ".$coordsArr[3].",".$coordsArr[2]." ".$coordsArr[1].",".$coordsArr[0]." ".$coordsArr[1]."))',"
				.$sridOfFilter
				."),$sridOfTable),"
				.$arr['BBOX']['PropertyName'].")";

		}
		// End of filter parsing
	$i++;
	}
	if (!$BoolOperator) $BoolOperator = "OR";
	return "(".implode(" ".$BoolOperator." " ,$where).")";
}
function addDiminsionOnArray($array) {
	if (!is_array($array[0]) && isset($array)) {
			 $array = array(0 => $array);
		}
	return $array;
}