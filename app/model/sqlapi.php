<?php

namespace app\model;

use app\inc\postgis;

class sqlapi extends postgis {
	var $srs;
	function __construct($srs = "900913") {
		parent::__construct();
		$this -> srs = $srs;
	}

	function sql($q) {
		$name = "_" . rand(1, 999999999) . microtime();
		$name = $this -> toAscii($name, null, "_");
		$view = "sqlapi.{$name}";
		$sqlView = "CREATE VIEW {$view} as {$q}";
		//echo $sqlView ;
		$result = $this -> execQuery($sqlView);
		if (!$this -> PDOerror) {
			$arrayWithFields = $this -> getMetaData($view);
			//print_r($arrayWithFields);

			foreach ($arrayWithFields as $key => $arr) {
				if ($arr['type'] == "geometry") {
					$fieldsArr[] = "ST_asGeoJson(ST_Transform(" . $key . "," . $this -> srs . ")) as " . $key;
				} else {
					$fieldsArr[] = $key;
				}
			}
			$sql = implode(",", $fieldsArr);
			$sql = "SELECT {$sql} FROM {$view}";
			//echo $sql;
			$result = $this -> execQuery($sql);
			while ($row = $this -> fetchRow($result, "assoc")) {
				$arr = array();
				foreach ($row as $key => $value) {

					if ($arrayWithFields[$key]['type'] == "geometry") {
						$geometries[] = json_decode($row[$key]);
					} else {
						$arr = $this -> array_push_assoc($arr, $key, $value);
					}
				}
				if (sizeof($geometries) > 1) {
					$features[] = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
				}
				if (sizeof($geometries) == 1) {
					$features[] = array("geometry" => $geometries[0], "type" => "Feature", "properties" => $arr);
				}
                if (sizeof($geometries) == 0) {
                    $features[] = array("type" => "Feature", "properties" => $arr);
                }
				unset($geometries);
			}
			foreach ($arrayWithFields as $key => $value) {
				$fieldsForStore[] = array("name" => $key, "type" => $value['type']);
				$columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => $value['typeObj']);
			}

			$response['success'] = true;
			$response['forStore'] = $fieldsForStore;
			$response['forGrid'] = $columnsForGrid;
			$response['type'] = "FeatureCollection";
			$response['features'] = $features;
		} else {
			$response['success'] = false;
			$response['message'] = $this -> PDOerror;
		}
		$sql = "DROP VIEW {$view}";
		$result = $this -> execQuery($sql);
		return $response;
	}

	public function transaction($q) {
		$result = $this -> execQuery($q, "PDO", "transaction");
		if (!$this -> PDOerror) {
			$response['success'] = true;
			$response['affected_rows'] = $result;
		} else {
			$response['success'] = false;
			$response['message'] = $this -> PDOerror;
		}
		$this->free($result);
		return $response;
	}

	private function array_push_assoc($array, $key, $value) {
		$array[$key] = $value;
		return $array;
	}

}
