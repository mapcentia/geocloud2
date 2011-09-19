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
    public function createdb($screenName)
	{
        //$this->createUser($screenName);

		$sql = "CREATE DATABASE {$screenName}
			WITH ENCODING='SQL_ASCII'
       			OWNER=postgres
       			TEMPLATE=mhoegh
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
}
