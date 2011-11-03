<?php
class wmslayers extends postgis {
	var $table;
	var $schema;
	function __construct($table,$schema) {
		parent::__construct();
		$this->table = $table;
		$this->schema = $schema;
	}
	public function get() {
		$sql = "SELECT def FROM settings.geometry_columns_join WHERE f_table_name='{$this->table}' AND f_table_schema='{$this->schema}'";
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
	public function update($data) {
		//$def = $this->get();
		//print_r($def);
		
		$sql = "UPDATE settings.geometry_columns_join SET def='{$data}' WHERE f_table_name='{$this->table}' AND f_table_schema='{$this->schema}'";
		$this->execQuery($sql,"PDO","transaction");
		
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