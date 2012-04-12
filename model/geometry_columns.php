<?php
class GeometryColumns extends postgis {
	var $rows;
	function __construct()
	{
		parent::__construct();
			$geometryColumnsObj = new table("settings.geometry_columns_view");
			$this->rows = $geometryColumnsObj->getRecords();
			$this->rows = $this->rows['data'];
	}
	function getValueFromKey($_key_,$column) {
		foreach ($this->rows as $row) {
			foreach ($row as $field => $value) {
				if ($field == "_key_" && $value==$_key_) {
					return ($row[$column]);
				}
			}
		}
	}
}