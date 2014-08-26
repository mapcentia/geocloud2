<?php
namespace app\models;

use app\inc\Model;

class Tile extends Model {
	var $table;
	function __construct($table) {
		parent::__construct();
		$this->table = $table;
	}
	public function get() {
		$sql = "SELECT def FROM settings.geometry_columns_join WHERE _key_='{$this->table}'";
		$row = $this->fetchRow($this->execQuery($sql),"assoc");
		if (!$this->PDOerror) {
			$response['success'] = true;
			$arr = (array)json_decode($row['def']); // Cast stdclass to array
            foreach ($arr as $key => $value) {
                if ($value === null) { // Never send null to client
                    $arr[$key] = "";
                }
            }
			$response['data'] = array($arr);
		}
		else {
			$response['success'] = false;
			$response['message'] = "Hej hej";
            $response['code'] = 500;
		}
		return $response;
	}
	public function update($data) {
		$sql = "UPDATE settings.geometry_columns_join SET def='{$data}' WHERE _key_='{$this->table}'";
		$this->execQuery($sql,"PDO","transaction");
		
		if (!$this->PDOerror) {
			$response['success'] = true;
			$response['message'] = "Def updated";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
			$response['code'] = 500;
		}
		return $response;
	}
}