<?php

namespace app\model;

use app\inc\Model;

class Dbcheck extends Model {
	function __construct()
	{
		parent::__construct();
	}
	public function isSchemaInstalled() {
		$sql = "select 1 from settings.viewer";
		$result = $this->execQuery($sql);
		if (!$this->PDOerror) {
			$response['success'] = true;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function isPostGISInstalled() {
		$sql = "select postgis_version()";
		$result = $this->execQuery($sql);
		if (!$this->PDOerror) {
			$response['success'] = true;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function isViewInstalled() {
		$sql = "select * from settings.geometry_columns_view";
		$result = $this->execQuery($sql);
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