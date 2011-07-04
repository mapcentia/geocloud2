<?php
class wmslayers extends postgis {
	function __construct() {
		parent::__construct();
	}
	public function get($id) {
		$sql ="SELECT def FROM wmslayers WHERE layer='{$id}';";
		$row = $this->fetchRow($this->execQuery($sql),"assoc");
		if (!$this->PDOerror) {
			$response['success'] = true;
			$arr = (array)json_decode($row['def']); // Cast stdclass to array
			$props = array("label_column","theme_column");
			foreach($props as $field){
				if (!$arr[$field]){
					$arr[$field] = "";
				}
			}
			$response['data'] = array($arr);
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
	public function update($id,$data) {
		$sql ="SELECT * FROM wmslayers WHERE layer='{$id}';";
		$result = $this->execQuery($sql);
		if (!$result->rowCount()){
			$sql = "INSERT INTO wmslayers (layer,def) VALUES('{$id}','{$data}');";
			$this->execQuery($sql,"PDO","transaction");
		}
		else {
			$sql = "UPDATE wmslayers SET def='{$data}' WHERE layer='{$id}';";
			$this->execQuery($sql,"PDO","transaction");
		}
		if (!$this->PDOerror) {
			$response['success'] = true;
			$response['message'] = "Def updated";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
}