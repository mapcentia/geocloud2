<?php

namespace app\models;

use \app\conf\Connection;;

class Database extends \app\inc\Model {
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
    public function createdb($screenName,$template,$encoding="UTF8")
	{
        //$this->createUser($screenName);

		$sql = "CREATE DATABASE {$screenName}
			    WITH ENCODING='{$encoding}'
       			OWNER=postgres
       			TEMPLATE={$template}
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
	    $sql = "SELECT 1 as check from pg_database WHERE datname='{$name}'";
		$row = $this->fetchRow($this -> execQuery($sql),"assoc");
		if ($row['check']) {
			$response['success'] = true;
		}
		else {
			$response['success'] = false;
		}
		return $response;
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
		$sql = "SELECT schema_name from information_schema.schemata WHERE schema_name not like 'pg_%' AND schema_name<>'settings' AND schema_name<>'information_schema' AND schema_name<>'sqlapi'";
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
