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

		if ($this -> execQuery($sql)) {
			return true;
		}
		else {
			return false;
		}
	}
}
?>