<?php
class _class extends postgis {
	function __construct() {
		parent::__construct();
	}
	private function array_push_assoc($array, $key, $value){
		$array[$key] = $value;
		return $array;
	}
	public function getAll($table) {
		$sql = "SELECT * FROM classes WHERE layer='{$table}'";
		$result = $this->execQuery($sql);
		if (!$this->PDOerror) {
			$response['success'] = true;
			while ($row = $this->fetchRow($result,"assoc")) {
				$arr = array();
				foreach ($row as $key => $value) {
					if ($key=="class") {
						$tmpArr = (array)json_decode($value); // Cast stdclass to array
						$key = "name";
						$value = $tmpArr['name'];
						$key2 = "expression";
						$value2 = $tmpArr['expression'];
					}
					$arr = $this -> array_push_assoc($arr,$key,$value);
				}
			$arr = $this -> array_push_assoc($arr,$key2,$value2);
			$response['data'][] = $arr;
			}
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
	public function get($id) {
		$sql = "SELECT class FROM classes WHERE id='{$id}'";
		$row = $this->fetchRow($this->execQuery($sql),"assoc");
		if (!$this->PDOerror) {
			$response['success'] = true;
			$arr = (array)json_decode($row['class']); // Cast stdclass to array
			$props = array(	"name"=>"New style",
							"expression"=>"",
							"label"=>false,
							"color"=>"#FF0000",
							"outlinecolor"=>"#FF0000",
							"symbol"=>"",
							"size"=>"2",
							"width"=>"1"
							);
			foreach($props as $key=>$value){
				if (!isset($arr[$key])){
					$arr[$key] = $value;
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
		$sql = "UPDATE classes SET class='{$data}' WHERE id='{$id}';";
		$this->execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
			$response['success'] = true;
			$response['message'] = "Class updated";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
	public function insert($table) {
		$sql = "INSERT into classes (layer,class) VALUES('{$table}','{\"name\":\"New style\"}');";
		$this->execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
			$response['success'] = true;
			$response['message'] = "Class inserted";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
	function destroy($id) // Geometry columns
	{
		$sql.= "DELETE FROM classes WHERE id='{$id}';";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
}