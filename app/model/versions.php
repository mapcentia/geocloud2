<?php
class version extends postgis {
	var $userObj;
	function __construct($userObj) {
		parent::__construct();
		$this->userObj = $userObj;
	}
	function set($table,$operation,$text) {
		switch($operation) {
			case "insert":
			$operationText = "inserted new feature in ";
			break;
			case "update":
			$operationText = "updated feature in";
			break;
		}
		if ($this->getGeometryColumns($table,"tweet")) {
			$this->tweet("Just {$operationText} {$table} near {$text} #mygeocloud");
		}
		$sql = "UPDATE geometry_columns SET lastmodified=current_timestamp(0) WHERE f_table_name='{$table}'";
		$this -> execQuery($sql);
	}
	private function tweet($text){
		$this->userObj->tweet($text);
	}
}
?>