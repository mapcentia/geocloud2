<?php
include '../../inc/controller.php';
include '../../inc/common.php';

/**
 *
 */
 
class ClearTileCache_c extends Controller {
	function __construct() {
		global $basePath;
		$respons = array();
		parent::__construct();
		$parts = $this -> getUrlParts();
		$this->auth($parts[3]);
		$dir = $basePath."/tmp/".$parts[3]."/".$parts[4];
		$dir = str_replace("..", "", $dir);
		$dirReal = realpath($dir);
		//echo $dir;
		if ($dirReal) {
			rrmdir($dirReal);
			$respons['success'] = true;
			$respons['message'] = "Tile cache invalidated";
		}
		else {
			$respons['success'] = false;
			$respons['message'] = "No tile cache to invalidate.";
		}
		echo $this -> toJSON($respons);
	}
}
new ClearTileCache_c();