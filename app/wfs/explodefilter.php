<?php
function parseFilter($filter, $table, $operator = "=")
{
    global $postgisObject;
    global $postgisschema;
    global $srs;

    $table = dropAllNameSpaces($table);
    //die($table);
    $st = \app\inc\Model::explodeTableName($table);
    if (!$st['schema']) {
        $st['schema'] = $postgisschema;
    }
    $primeryKey = $postgisObject->getPrimeryKey($st['schema'] . "." . $st['table']);
    $serializer_options = array(
        'indent' => '  ',
    );
    $Serializer = &new XML_Serializer($serializer_options);
    if (!is_array($filter[0]) && isset($filter) && !(isset($filter['And']) OR isset($filter['Or']) OR isset($filter['Not']))) {
        $filter = array(0 => $filter);
    }

    $sridOfTable = $postgisObject->getGeometryColumns($table, "srid");

    $i = 0;
    foreach ($filter as $key => $arr) {
        if ($key == "And" || $key == "Or") $BoolOperator = $key;

        if (isset($arr['Not'])) {
            //$where[] = parseFilter($arr['Not'],$table,"<>");
        }
        if (isset($arr['And']) || isset($arr['Or'])) {
            // Recursive call
            $where[] = parseFilter($arr, $table);
        }
        // PropertyIsEqualTo
        $arr['PropertyIsEqualTo'] = addDiminsionOnArray($arr['PropertyIsEqualTo']);
        if (is_array($arr['PropertyIsEqualTo'])) foreach ($arr['PropertyIsEqualTo'] as $value) {
            $where[] = $value['PropertyName'] . "=" . $postgisObject->quote($value['Literal']);
        }
        // PropertyIsNotEqualTo
        $arr['PropertyIsNotEqualTo'] = addDiminsionOnArray($arr['PropertyIsNotEqualTo']);
        if (is_array($arr['PropertyIsNotEqualTo'])) foreach ($arr['PropertyIsNotEqualTo'] as $value) {
            $where[] = $value['PropertyName'] . "<>'" . $value['Literal'] . "'";
        }
        // PropertyIsLessThan
        $arr['PropertyIsLessThan'] = addDiminsionOnArray($arr['PropertyIsLessThan']);
        if (is_array($arr['PropertyIsLessThan'])) foreach ($arr['PropertyIsLessThan'] as $value) {
            $where[] = $value['PropertyName'] . "<'" . $value['Literal'] . "'";
        }
        // PropertyIsGreaterThan
        $arr['PropertyIsGreaterThan'] = addDiminsionOnArray($arr['PropertyIsGreaterThan']);
        if (is_array($arr['PropertyIsGreaterThan'])) foreach ($arr['PropertyIsGreaterThan'] as $value) {
            $where[] = $value['PropertyName'] . ">'" . $value['Literal'] . "'";
        }
        // PropertyIsLessThanOrEqualTo
        $arr['PropertyIsLessThanOrEqualTo'] = addDiminsionOnArray($arr['PropertyIsLessThanOrEqualTo']);
        if (is_array($arr['PropertyIsLessThanOrEqualTo'])) foreach ($arr['PropertyIsLessThanOrEqualTo'] as $value) {
            $where[] = $value['PropertyName'] . "<='" . $value['Literal'] . "'";
        }
        //PropertyIsGreaterThanOrEqualTo
        $arr['PropertyIsGreaterThanOrEqualTo'] = addDiminsionOnArray($arr['PropertyIsGreaterThanOrEqualTo']);
        if (is_array($arr['PropertyIsGreaterThanOrEqualTo'])) foreach ($arr['PropertyIsGreaterThanOrEqualTo'] as $value) {
            $where[] = $value['PropertyName'] . ">='" . $value['Literal'] . "'";
        }
        //PropertyIsLike
        $arr['PropertyIsLike'] = addDiminsionOnArray($arr['PropertyIsLike']);
        if (is_array($arr['PropertyIsLike'])) foreach ($arr['PropertyIsLike'] as $value) {
            $where[] = $value['PropertyName'] . " LIKE '%" . $value['Literal'] . "%'";
        }
        //PropertyIsBetween
        $arr['PropertyIsBetween'] = addDiminsionOnArray($arr['PropertyIsBetween']);
        if (is_array($arr['PropertyIsBetween'])) {
            foreach ($arr['PropertyIsBetween'] as $value) {
                if ($value['LowerBoundary'])
                    $w[] = $value['PropertyName'] . " > '" . $value['LowerBoundary']['Literal'] . "'";
                if ($value['UpperBoundary'])
                    $w[] = $value['PropertyName'] . " < '" . $value['UpperBoundary']['Literal'] . "'";
            }
            $where[] = implode(" AND ", $w);
        }
        // FeatureID
        if (!is_array($arr['FeatureId'][0]) && isset($arr['FeatureId'])) {
            $arr['FeatureId'] = array(0 => $arr['FeatureId']);
        }
        if (is_array($arr['FeatureId'])) foreach ($arr['FeatureId'] as $value) {
            $value['fid'] = preg_replace("/{$table}\./", "", $value['fid']); // remove table name
            $where[] = "{$primeryKey['attname']}=" . $value['fid'];
        }
        // GmlObjectId
        $arr['GmlObjectId'] = addDiminsionOnArray($arr['GmlObjectId']);
        if (is_array($arr['GmlObjectId'])) foreach ($arr['GmlObjectId'] as $value) {
            $value['id'] = preg_replace("/{$table}\./", "", $value['id']); // remove table name
            $where[] = "{$primeryKey['attname']}=" . $value['id'];
        }
        //Intersects
        $arr['Intersects'] = addDiminsionOnArray($arr['Intersects']);
        if (is_array($arr['Intersects'])) foreach ($arr['Intersects'] as $value) {
            $status = $Serializer->serialize($value);

            $gmlCon = new gmlConverter();
            //logfile::write($Serializer->getSerializedData()."\n\n");
            $wktArr = $gmlCon->gmlToWKT($Serializer->getSerializedData(), array());

            $sridOfFilter = $wktArr[1][0];
            if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
            if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs

            $g = "public.ST_Transform(public.ST_GeometryFromText('" . $wktArr[0][0] . "',"
                . $sridOfFilter
                . "),$sridOfTable)";

            $where[] =
                "({$g} && {$value['PropertyName']}) AND "

                . "ST_Intersects"
                . "({$g},"
                . $value['PropertyName'] . ")";

            unset($gmlCon);
            unset($wktArr);
        }
        //BBox
        if ($arr['BBOX']) {

            if (is_array($arr['BBOX']['Box']['coordinates'])) {
                $arr['BBOX']['Box']['coordinates']['_content'] = str_replace(" ", ",", $arr['BBOX']['Box']['coordinates']['_content']);
                $coordsArr = explode(",", $arr['BBOX']['Box']['coordinates']['_content']);
            } else {
                $arr['BBOX']['Box']['coordinates'] = str_replace(" ", ",", $arr['BBOX']['Box']['coordinates']);
                $coordsArr = explode(",", $arr['BBOX']['Box']['coordinates']);

            }
            if (is_array($arr['BBOX']['Box'])) {
                $sridOfFilter = gmlConverter::parseEpsgCode($arr['BBOX']['Box']['srsName']);
                $axisOrder = gmlConverter::getAxisOrderFromEpsg($arr['BBOX']['Box']['srsName']);
                if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
                if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
            }
            if (is_array($arr['BBOX']['Envelope'])) {
                $coordsArr = array_merge(explode(" ", $arr['BBOX']['Envelope']['lowerCorner']), explode(" ", $arr['BBOX']['Envelope']['upperCorner']));
                ob_start();
                print_r($arr['BBOX']['Envelope']);
                print_r($coordsArr);
                $data = ob_get_clean();
                //logfile::write($data);
                $sridOfFilter = gmlConverter::parseEpsgCode($arr['BBOX']['Envelope']['srsName']);
                $axisOrder = gmlConverter::getAxisOrderFromEpsg($arr['BBOX']['Envelope']['srsName']);
                if (!$sridOfFilter) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
                if (!$sridOfFilter) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
            }
            if ($axisOrder == "longitude") {
                $where[] = "ST_Intersects"
                    . "(public.ST_Transform(public.ST_GeometryFromText('POLYGON((" . $coordsArr[0] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[1] . "))',"
                    . $sridOfFilter
                    . "),$sridOfTable),"
                    . "\"" . (($arr['BBOX']['PropertyName']) ?: $postgisObject->getGeometryColumns($table, "f_geometry_column")) . "\")";
            } else {
                $where[] = "ST_Intersects"
                    . "(public.ST_Transform(public.ST_GeometryFromText('POLYGON((" . $coordsArr[1] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[0] . "))',"
                    . $sridOfFilter
                    . "),$sridOfTable),"
                    . "\"" . (($arr['BBOX']['PropertyName']) ?: $postgisObject->getGeometryColumns($table, "f_geometry_column")) . "\")";
            }
            /*$where[] = "public.ST_Transform(public.ST_GeometryFromText('POLYGON((".$coordsArr[0]." ".$coordsArr[1].",".$coordsArr[0]." ".$coordsArr[3].",".$coordsArr[2]." ".$coordsArr[3].",".$coordsArr[2]." ".$coordsArr[1].",".$coordsArr[0]." ".$coordsArr[1]."))',"
                .$sridOfFilter
                ."),$sridOfTable) && ".$arr['BBOX']['PropertyName'];*/
        }
        // End of filter parsing
        $i++;
    }
    ob_start();
    print_r($where);
    ob_get_clean();

    if (!$BoolOperator) $BoolOperator = "OR";
    return "(" . implode(" " . $BoolOperator . " ", $where) . ")";
}

function addDiminsionOnArray($array)
{
    if (!is_array($array[0]) && isset($array)) {
        $array = array(0 => $array);
    }
    return $array;
}