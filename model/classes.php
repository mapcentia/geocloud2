<?php
class _class extends postgis {
	var $table;
	function __construct($table) {
		parent::__construct();
		$this -> table = $table;
	}

	private function array_push_assoc($array, $key, $value) {
		$array[$key] = $value;
		return $array;
	}

	public function getAll() {
		$sql = "SELECT class FROM settings.geometry_columns_join WHERE _key_='{$this->table}'";
		$result = $this -> execQuery($sql);
		if (!$this -> PDOerror) {
			$response['success'] = true;
			$row = $this -> fetchRow($result, "assoc");
			$arr = (array)json_decode($row['class']);
			for ($i = 0; $i < sizeof($arr); $i++) {
				$arrNew[$i] = (array)casttoclass('stdClass', $arr[$i]);
				$arrNew[$i]['id'] = $i;
			}
			$response['data'] = $arrNew;
		} else {
			$response['success'] = false;
			$response['message'] = $this -> PDOerror[0];
		}
		return $response;
	}

	public function get($id) {
		$classes = $this -> getAll();
		if (!$this -> PDOerror) {
			$response['success'] = true;
			$arr = $classes['data'][$id];
			unset($arr['id']);
			//print_r($arr);
			$props = array("name" => "New style", "expression" => "", "label" => false, "color" => "#FF0000", "outlinecolor" => "#FF0000", "symbol" => "", "size" => "2", "width" => "1");
			foreach ($classes['data'][$id] as $key => $value) {

				foreach ($props as $key2 => $value2) {
					if (!isset($arr[$key2])) {
						$arr[$key2] = $value2;
					}
				}

			}

			$response['data'] = array($arr);
		} else {
			$response['success'] = false;
			$response['message'] = $this -> PDOerror[0];
		}
		return $response;
	}

	public function store($data) {
		// First we replace unicode escape sequence
		$data = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $data);

		$sql = "UPDATE settings.geometry_columns_join SET class=" . $this -> quote($data) . " WHERE _key_='{$this->table}';";
		$this -> execQuery($sql, "PDO", "transaction");
		if (!$this -> PDOerror) {
			$response['success'] = true;
			$response['message'] = "Class updated";
		} else {
			$response['success'] = false;
			$response['message'] = $this -> PDOerror[0];
		}
		return $response;
	}

	public function insert() {
		$classes = $this -> getAll();
		$classes['data'][] = array("name" => "New style");
		$response = $this -> store(json_encode($classes['data']));
		return $response;
	}

	public function update($id, $data) {
		$classes = $this -> getAll();
		$classes['data'][$id] = $data;
		//print_r($classes['data']);
		$response = $this -> store(json_encode($classes['data']));
		return $response;
	}

	public function destroy($id)// Geometry columns
	{
		$classes = $this -> getAll();
		unset($classes['data'][$id]);
		foreach ($classes['data'] as $key => $value) {// Reindex array
			unset($value['id']);
			$arr[] = $value;
		}
		$classes['data'] = $arr;
		//print_r($classes);
		$response = $this -> store(json_encode($classes['data']));
		return $response;
	}

}

function casttoclass($class, $object) {
	return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
}

function replace_unicode_escape_sequence($match) {
	return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}
