<?php
class GeometryColumns extends postgis {
	var $rows;
	function __construct()
	{
		parent::__construct();
			$sql="SELECT * FROM settings.geometry_columns_view order by sort_id";
			$result = $this->execQuery($sql);
			$this->rows = $this->fetchAll($result);
			
			/*
			$geometryColumnsObj = new table("settings.geometry_columns_view");
			$this->rows = $geometryColumnsObj->getRecords();
			$this->rows = $this->rows['data'];
			*/
	}
	function getValueFromKey($_key_,$column) {
		foreach ($this->rows as $row) {
			foreach ($row as $field => $value) {
				if ($field == "_key_" && $value==$_key_) {
					return ($row[$column]);
				}
			}
		}
	}
	function getCartoMobileSettings($_key_) // Only geometry tables
	{
		$response['success'] = true;
		$response['message'] = "Structure loaded";
		$arr = array();
		$keySplit = explode(".",$_key_);
		$table = new table($keySplit[0].".".$keySplit[1]);
		$cartomobileArr = (array)json_decode($this->getValueFromKey($_key_,"cartomobile"));
		//print_r($cartomobileArr);
		foreach($table->metaData as $key=>$value){
			if ($value['type']!="geometry" && $key!=$table->primeryKey['attname']) {
				$arr = $this -> array_push_assoc($arr,"id",$key);
				$arr = $this -> array_push_assoc($arr,"column",$key);
				$arr = $this -> array_push_assoc($arr,"available",$cartomobileArr[$key]->available);
				$arr = $this -> array_push_assoc($arr,"cartomobiletype",$cartomobileArr[$key]->cartomobiletype);
				$arr = $this -> array_push_assoc($arr,"properties",$cartomobileArr[$key]->properties);
				if ($value['typeObj']['type']=="decimal") {
					$arr = $this -> array_push_assoc($arr,"type","{$value['typeObj']['type']} ({$value['typeObj']['precision']} {$value['typeObj']['scale']})");
				}
				else {
					$arr = $this -> array_push_assoc($arr,"type","{$value['typeObj']['type']}");
				}
				$response['data'][] = $arr;
			}
		}
		return $response;
	}
	function updateCartoMobileSettings($data,$_key_) {
		$table = new table("settings.geometry_columns_join");
		$data = $table->makeArray($data);
		$cartomobileArr = (array)json_decode($this->getValueFromKey($_key_,"cartomobile"));
		foreach ($data as $value) {
			$safeColumn = $table->toAscii($value->column,array(),"_");
			if ($value->id != $value->column && ($value->column) && ($value->id)) {
				unset($cartomobileArr[$value->id]);
			}
			$cartomobileArr[$safeColumn] = $value;
		}
		$conf['cartomobile'] = json_encode($cartomobileArr);
		$conf['_key_'] = $_key_;

		 
		$table->updateRecord(json_decode(json_encode($conf)),"_key_");
		$this->execQuery($sql,"PDO","transaction");
		if ((!$this->PDOerror) || (!$sql)) {
			$response['success'] = true;
			$response['message'] = "Column renamed";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
	private function array_push_assoc($array, $key, $value){
		$array[$key] = $value;
		return $array;
	}
}