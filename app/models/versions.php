<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\model;

use app\inc\Model;

class version extends Model {
	var $userObj;
	function __construct($userObj) {
		parent::__construct();
		$this->userObj = $userObj;
	}
	function set($table,$operation,$text) {
		switch($operation) {
			case "insert":
			$operationText = "inserted new feature in ";
			break;
			case "update":
			$operationText = "updated feature in";
			break;
		}
		if ($this->getGeometryColumns($table,"tweet")) {
			$this->tweet("Just {$operationText} {$table} near {$text} #mygeocloud");
		}
		$sql = "UPDATE geometry_columns SET lastmodified=current_timestamp(0) WHERE f_table_name='{$table}'";
		$this -> execQuery($sql);
	}
	private function tweet($text){
		$this->userObj->tweet($text);
	}
}
?>