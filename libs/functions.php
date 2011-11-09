<?php

class postgis
{
	var $postgishost;
	var $postgisuser;
	var $postgisdb;
	var $postgispw;
	var $connectString;
	var $PDOerror;
	var $db;
	var $postgisschema;
	function postgis() //constructor
	{
		global $postgishost;
		global $postgisport;
		global $postgisuser;
		global $postgisdb;
		global $postgispw;
		global $postgisschema;
		$this -> postgishost = trim($postgishost);
		$this -> postgisport = trim($postgisport);
		$this -> postgisuser = trim($postgisuser);
		$this -> postgisdb = trim($postgisdb);
		$this -> postgispw = trim($postgispw);
		$this -> postgisschema = trim($postgisschema);
	}
	function fetchRow(& $result,$result_type="assoc")
	{
		/*
		switch ($result_type) {
			case "assoc":
				$row=pg_fetch_assoc($result);
			break;
			case "both";
				$row=pg_fetch_array($result);
			break;
		}
		return($row);
		*/
		switch ($result_type) {
			case "assoc":
				$row = $result->fetch(PDO::FETCH_ASSOC);;
			break;
			case "both":
				//$row=pg_fetch_array($result);
			break;
		}
		return($row);
	}
	function numRows($result)
	{
		//$num=pg_numrows($result);
		$num = sizeof($result);
		return ($num);
	}
	function free(& $result)
	{
		//$test=pg_free_result($result);
		$result = NULL; //PDO
	}
	function getPrimeryKey($table)
	{
		$query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '{$table}'::regclass AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = any(pg_index.indkey) AND indisprimary";
		$result = $this->execQuery($query);
		
		if ($this->PDOerror) {
			return NULL;
		}
		if (!is_array($row=$this->fetchRow($result))) { // If $table is view we bet on there is a gid field
			return array("attname"=>"gid");
		}
		else {
			return($row);
		}
	}
	function begin()
	{
		$this->db->beginTransaction();
	}
	function commit()
	{
		$this->db->commit();
		$this->db = NULL;
	}
	function rollback()
	{
		$this->db->rollback();
		$this->db = NULL;
	}
	function execQuery($query,$conn="PDO",$queryType="select")
	{
		switch ($conn){
			case "PG":
				if (!$this->db) {
					 $this -> connect("PG");
				}
				$result = pg_query($this->db,$query);
				return ($result);
				break;
			case "PDO":
				if (!$this->db) {
					 $this -> connect("PDO"); 
				}
				try {
						$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
						switch ($queryType){
							case "select":
								$result = $this->db->query($query); // Return PDOStatement object
								break;
							case "transaction":
								$result = $this->db->exec($query); // Return interger
						}
						//$db = NULL;
				}
				catch(PDOException $e)
				{
					//throw new Exception($e->getMessage());
					$this->PDOerror[] = $e->getMessage();
				}
				return($result);
				break;
		}
	}
	function getMetaData($table)
	{
		$this->connect("PG");

		preg_match ("/^[\w'-]*\./",$table,$matches);
		$_schema = $matches[0];

		preg_match ("/[\w'-]*$/",$table,$matches);
		$_table = $matches[0];

		if (!$_schema) {
			$_schema = $this->postgisschema;
		}
		if (version_compare(PHP_VERSION, '5.3.0') >= 0) { // If running 5.3 then use schema.table
			$arr = pg_meta_data($this->db,str_replace(".","",$_schema).".".$_table);
		}
		else { // if running below 5.3 then set SEARCH_PATH and use just table without schema
			$this->execQuery("SET SEARCH_PATH TO ".str_replace(".","",$_schema),"PG");
			$arr = pg_meta_data($this->db,$_table);
		}
		$this->close();
		return ($arr);
	}
	function connectString()
	{
		if ($this -> postgishost != "")
		$connectString = "host=".$this -> postgishost;
		if ($this -> postgisport != "")
		$connectString = $connectString." port=".$this -> postgisport;
		if ($this -> postgisuser != "")
		$connectString = $connectString." user=".$this -> postgisuser;
		if ($this -> postgispw != "")
		$connectString = $connectString." password=".$this -> postgispw;
		if ($this -> postgisdb != "")
		$connectString = $connectString." dbname=".$this -> postgisdb;
		return ($connectString);
	}
	function connect($type="PDO")
	{
		switch ($type){
			case "PG":
				$this->db = pg_connect($this -> connectString());
				if ($this->postgisschema) {
					$this->execQuery("SET SEARCH_PATH TO public,".$this->postgisschema,"PG");
				}
				break;
			case "PDO":
				try {
					$this->db = new PDO("pgsql:dbname={$this->postgisdb};host={$this->postgishost}", "{$this->postgisuser}", "{$this->postgispw}");
					if ($this->postgisschema) {
						$this->execQuery("SET SEARCH_PATH TO public,".$this->postgisschema,"PDO");
					}
					$this->execQuery("set client_encoding='utf8'","PDO");
				}
				catch(PDOException $e) {
					$this->db=NULL;
					throw new Exception("Could not connect to database {$this->postgisdb}");
				}
				break;
		}		
	}
	function close()
	{
		$this->db = NULL;
	}
	function quote($str)
	{
		if (!$this->db) {
			$this -> connect("PDO");
		}
		$str = $this->db->quote($str);
		return($str);
	}
	function getGeometryColumns($table,$field)
	{
		preg_match ("/^[\w'-]*\./",$table,$matches);
		$_schema = $matches[0];

		preg_match ("/[\w'-]*$/",$table,$matches);
		$_table = $matches[0];

		if (!$_schema) {
			$_schema = $this->postgisschema;
		}
		else {
			$_schema = str_replace(".","",$_schema);
		}
		$query = "select * from settings.geometry_columns_view where f_table_name='$table' AND f_table_schema='$_schema'";
	
		$result = $this -> execQuery($query);
		$row = $this -> fetchRow($result);
		if (!$row)
		return $languageText[selectText];
		elseif ($row) $this -> theGeometry = $row[type];
		if ($field == 'f_geometry_column')
		return $row[f_geometry_column];
		if ($field == 'srid') {
			return $row['srid'];
		}
		if ($field == 'type') {
			return $row['type'];
		}
		if ($field == 'tweet') {
			return $row['tweet'];
		}
		if ($field == 'editable') {
			return $row['editable'];
		}
		if ($field == 'authentication') {
			return $row['authentication'];
		}
		if ($field == 'fieldconf') {
			return $row['fieldconf'];
		}
	}
	function toAscii($str, $replace=array(), $delimiter='-') {
		if( !empty($replace) ) {
			$str = str_replace((array)$replace, ' ', $str);
		}

		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

		return $clean;
	}
}
class logfile {

	/**
	 *
	 *
	 * @param unknown $the_string
	 * @return unknown
	 */
	function write($the_string) {
/*
		if ( $fh = fopen("log.txt", "a+" ) ) {
			fputs( $fh, $the_string, strlen($the_string) );
			fclose( $fh );
			return true;
		}
		else {
			return false;
		}
*/
	}
}
class color {
	public function hex2RGB($hexStr, $returnAsString = false, $seperator = ',') {
    $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
    $rgbArray = array();
    if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
        $colorVal = hexdec($hexStr);
        $rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
        $rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
        $rgbArray['blue'] = 0xFF & $colorVal;
    } elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
        $rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
        $rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
        $rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
    } else {
        return false; //Invalid hex color code
    }
    return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
}
}
