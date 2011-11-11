<?php
class databases extends postgis {
	function __construct()
	{
		parent::__construct();
	}
    private function createUser($name)
    {
        $sql = "create user {$name} with password '1234'";
        $this -> execQuery($sql);
		if (!$this->PDOerror) {
			return true;
		}
		else {
			return false;
		}
    }
	public function createSchema($name)
	{
		$sql = "CREATE SCHEMA ".$this->toAscii($name,NULL,"_");
		$this -> execQuery($sql);
		if (!$this->PDOerror) {
			$response['success'] = true;
			$response['message'] = "Schema created";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror[0];
		}
		return $response;
	}
    public function createdb($screenName)
	{
        //$this->createUser($screenName);

		$sql = "CREATE DATABASE {$screenName}
			    WITH ENCODING='UTF8'
       			OWNER=postgres
       			TEMPLATE=template_mygeocloud
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
		$result = $this->execQuery($sql);
		if (!$this->PDOerror) {
			while ($row = $this->fetchRow($result,"assoc")) {
				$arr[] = $row['datname'];
	 		}
	 		$response['success'] = true;
	 		$response['data'] = $arr;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function listAllSchemas(){
		$sql = "SELECT schema_name from information_schema.schemata WHERE schema_name not like 'pg_%' AND schema_name<>'settings' AND schema_name<>'information_schema'";
		$result = $this->execQuery($sql);
		if (!$this->PDOerror) {
			while ($row = $this->fetchRow($result,"assoc")) {
				$arr[] = array("schema"=>$row['schema_name']);
	 		}
	 		$response['success'] = true;
	 		$response['data'] = $arr;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
}
