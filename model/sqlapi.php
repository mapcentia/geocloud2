<?php
class sqlapi extends postgis {
	function __construct()
	{
		parent::__construct();
	}
	function sql($q) {
		$name = "_".rand(1,999999999).microtime();
		$name = $this->toAscii($name,null,"_");
		$view = "public.{$name}";
		$sqlView = "CREATE VIEW {$view} as {$q}";
		$result = $this->execQuery($sqlView);
		if (!$this->PDOerror) {
			$arrayWithFields = $this->getMetaData($view);

			foreach($arrayWithFields as $key=>$arr) {
				if ($arr['type']=="geometry"){
					$fieldsArr[] = "ST_asGeoJson(transform(".$key.",900913)) as ".$key;
				}
				else {
					$fieldsArr[] = $key;
				}
			}
			$sql = implode(",",$fieldsArr);
			$sql = "SELECT {$sql} FROM {$view}";
			//echo $sql;
			$result = $this->execQuery($sql);
			while ($row = $this->fetchRow($result,"assoc")) {
				$arr = array();
				foreach ($row as $key => $value) {
					$arr = $this -> array_push_assoc($arr,$key,$value);
					if ($arrayWithFields[$key]['type'] == "geometry") {
						$geometries[] = json_decode($row[$key]);
					}
				}
				$features[] = array("geometry"=>array("type"=>"GeometryCollection","geometries"=>$geometries),"type"=>"Feature","properties"=>$arr);
				unset($geometries);
			}
			foreach($arrayWithFields as $key=>$value){
				$fieldsForStore[]  = array("name"=>$key,"type"=>$value['type']);
				$columnsForGrid[]  =  array("header"=>$key,"dataIndex"=>$key,"type"=>$value['type'],"typeObj"=>$value['typeObj']);
			}

			$response['success'] = true;
			$response["forStore"] = $fieldsForStore;
			$response["forGrid"] = $columnsForGrid;
			$response['type'] = "FeatureCollection";
			$response['features'] = $features;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		$sql = "DROP VIEW {$view}";
		$result = $this->execQuery($sql);
		return $response;
	}
	private function array_push_assoc($array, $key, $value){
		$array[$key] = $value;
		return $array;
	}
}