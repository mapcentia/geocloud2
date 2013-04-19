<?php
class shapefile extends postgis {
	var $safeFile;
	var $srid;
	var $file;
	var $pdo;
	/**
	 *
	 *
	 * @param unknown $safeFile
	 * @param unknown $srid
	 * @param unknown $file
	 * @param unknown $pdo
	 */


	function __construct($safeFile, $srid, $file, $pdo) {
		parent::__construct();
		$this->safeFile = $safeFile;
		$this->srid = $srid;
		$this->file = $file;
		$this->pdo = $pdo;
		fb($pdo);
	}


	/**
	 *
	 *
	 * @return unknown
	 */
	public function loadInDb() {
		$this->connect("PDO");
		$table = new table($this->safeFile);
		if (!$this->pdo) {
			$cmd = "shp2pgsql -g 'the_geom' -W 'WINDOWS-1252' -I -c -s {$this->srid} {$this->file}.shp {$this->safeFile}";
			$result = exec($cmd, $output);

			$sql_total = implode("", $output);

			// Create block begin
			$this->begin();
			if ($table->exits) {
				$table->destroy();
			}
			$this->execQuery($sql_total, "PDO", "transaction");
			
			//Create block end
			if (!$this->PDOerror) {
				$this->commit();
				if (!$table->exits) { // no need to re-init table object if table exits
					$table = new table($this->safeFile);
				}
				else {
					$overWriteTxt = " An exiting layer was overwriten";
				}
				//$table->point2multipoint();
				$response['success'] = true;
				$response['message'] = "Your shape file was uploaded and processed. You can find new layer i your geocloud.".$overWriteTxt;
			}
			else {
				$response['success'] = false;
				$response['message'] = $this->PDOerror;
				$this->rollback();
			}
		}
		else {
			// The psql way
			// Create block begin
			$this->begin();
			if ($table->exits) {
				$table->destroy();
			}
			$cmd = "shp2pgsql -W -g 'the_geom' 'WINDOWS-1252' -I -D -c -s {$this->srid} {$this->file}.shp {$this->safeFile}|psql {$this->postgisdb} postgres";
			$result = exec($cmd);
			if ($result=="COMMIT") {
				if (!$table->exits) { // no need to re-init table object if table exits
					$table = new table($this->safeFile);
				}
				else {
					$overWriteTxt = " An exiting layer was overwriten";
				}
				//$table->point2multipoint();
				$this->commit();

				// rename column 'state' if such exits
				$this->connect("PDO");// Must re-connect after commit
				$this->begin();
				$sql = "ALTER TABLE {$this->safeFile} RENAME state to _state";
				$this->execQuery($sql);
				$this->commit();


				$response['success'] = true;
				$response['message'] = "Your shape file was uploaded and processed. You can find new layer i your geocloud.".$overWriteTxt;
			}
			else {
				$this->rollback();
				$response['success'] = false;
				$response['message'] = "Something went wrong!";
			}
			$table = new table('settings.geometry_columns_join');
			$obj = json_decode('{"data":{"f_table_name":"'.$this->safeFile.'","f_table_title":""}}');
			$response2 = $table->updateRecord($obj->data, 'f_table_name');

			// If layer is new (inserted) then insert a new class for it
			if ($response2['operation'] == "inserted") {
				$class = new _class();
				$class->insert($this->safeFile, array(), "_");
			}
			makeMapFile($_SESSION['screen_name']);
			$response['cmd'] = $cmd;
			
		}
		return $response;
	}


}