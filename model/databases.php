<?php
class databases extends postgis {
	function createdb($screenName)
	{
		$sql = "CREATE DATABASE {$screenName}
			WITH ENCODING='SQL_ASCII'
       			OWNER=postgres
       			TEMPLATE=mygeocloudtest
       			LC_CTYPE='C'
       			CONNECTION LIMIT=-1;
			";
		$this -> execQuery($sql);
		if (!$this->PDOerror) {
			return true;
		}
		else {
			print_r($this->PDOerror);
			return false;
		}
	}
	public function doesDbExist($name){
	    $sql = "select count(*) as count from pg_catalog.pg_database where datname = '{$name}'";
		$row = $this->fetchRow($this -> execQuery($sql),"assoc");
		if ($row['count']==1) {
			return true;
		}
		else {
			return false;
		}
	}
	public function listAllDbs(){
		$sql = "SELECT datname from pg_catalog.pg_database";
		$sql = "SELECT * FROM {$this -> table}";
		$result = $this -> execQuery($sql);
		while ($row = $this->fetchRow($result,"assoc")) {
			$arr = array();
			foreach ($row as $key => $value) {
				$arr = $this -> array_push_assoc($arr,$key,$value);
			}
			$response['data'][] = $arr;
		}
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "Databaeses loaded";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
}
