<?php

namespace app\models;

use app\inc\Util;

class Classification extends \app\inc\Model
{
    private $layer;

    function __construct($table)
    {
        parent::__construct();
        $this->layer = $table;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    public function getAll()
    {
        $sql = "SELECT class FROM settings.geometry_columns_join WHERE _key_='{$this->layer}'";
        $result = $this->execQuery($sql);
        if (!$this->PDOerror) {
            $sortedArr = array();
            $response['success'] = true;
            $row = $this->fetchRow($result, "assoc");
            $arr = $arr2 = (array)json_decode($row['class']);
            for ($i = 0; $i < sizeof($arr); $i++) {
                $last = 100;
                foreach ($arr2 as $key => $value) {
                    if ($value->sortid < $last) {
                        $temp = $value;
                        $del = $key;
                        $last = $value->sortid;
                    }
                }
                array_push($sortedArr, $temp);
                unset($arr2[$del]);
                //print_r($arr2);
                $temp = null;
            }
            //$arr = $sortedArr;
            //print_r($arr);
            for ($i = 0; $i < sizeof($arr); $i++) {
                $arrNew[$i] = (array)Util::casttoclass('stdClass', $arr[$i]);
                $arrNew[$i]['id'] = $i;
            }
            $response['data'] = $arrNew;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    public function get($id)
    {
        $classes = $this->getAll();
        if (!$this->PDOerror) {
            $response['success'] = true;
            $arr = $classes['data'][$id];
            unset($arr['id']);
            $props = array(
                "name" => "Unnamed Class",
                "expression" => "",
                "label" => false,
                "force_label" => false,
                "color" => "#FF0000",
                "outlinecolor" => "#FF0000",
                "symbol" => "",
                "size" => "2",
                "width" => "1");
            foreach ($classes['data'][$id] as $value) {
                foreach ($props as $key2 => $value2) {
                    if (!isset($arr[$key2])) {
                        $arr[$key2] = $value2;
                    }
                }
            }
            $response['data'] = array($arr);
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 400;
        }
        return $response;
    }

    private function store($data)
    {

        // First we replace unicode escape sequence
        //$data = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $data);
        $tableObj = new Table("settings.geometry_columns_join");
        $obj = new \stdClass;
        $obj->class = $data;
        $obj->_key_ = $this->layer;
        $tableObj->updateRecord($obj, "_key_");
        if (!$tableObj->PDOerror) {
            $response = true;
        } else {
            $response = false;
        }
        return $response;
    }

    public function insert()
    {
        $classes = $this->getAll();
        $classes['data'][] = array("name" => "Unnamed class");
        if ($this->store(json_encode($classes['data']))) {
            $response['success'] = true;
            $response['message'] = "Inserted one class";
        } else {
            $response['success'] = false;
            $response['message'] = "Error";
            $response['code'] = 400;
        }
        return $response;
    }

    public function update($id, $data)
    {
        $data->expression = urldecode($data->expression);
        $classes = $this->getAll();
        $classes['data'][$id] = $data;
        if ($this->store(json_encode($classes['data']))) {
            $response['success'] = true;
            $response['message'] = "Updated one class";
        } else {
            $response['success'] = false;
            $response['message'] = "Error";
            $response['code'] = 400;
        }
        return $response;
    }

    public function destroy($id) // Geometry columns
    {
        $classes = $this->getAll();
        unset($classes['data'][$id]);
        foreach ($classes['data'] as $key => $value) { // Reindex array
            unset($value['id']);
            $arr[] = $value;
        }
        $classes['data'] = $arr;
        if ($this->store(json_encode($classes['data']))) {
            $response['success'] = true;
            $response['message'] = "Deleted one class";
        } else {
            $response['success'] = false;
            $response['message'] = "Error";
            $response['code'] = 400;
        }
        return $response;
    }

    private function reset()
    {
        $this->store(json_encode(array()));
    }

    public function createSingle($field)
    {
        $this->reset();
        $layer = new \app\models\Layer();
        $res = $this->update("0", self::createClass($layer->getValueFromKey($this->layer, type), "Single value", null, 10));
        if ($res['success']) {
            $response['success'] = true;
            $response['message'] = "Updated one class";
        } else {
            $response['success'] = false;
            $response['message'] = "Error";
            $response['code'] = 400;
        }
        return $response;
    }

    public function createUnique($field)
    {
        $layer = new \app\models\Layer();
        $bits = explode(".", $this->layer);
        $table = new \app\models\Table($bits[0] . "." . $bits[1]);
        $geometryType = $layer->getValueFromKey($this->layer, type);
        $fieldObj = $table->metaData[$field];
        $query = "SELECT distinct({$field}) as value FROM " . $table->table . " ORDER BY {$field}";
        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $rows = $this->fetchAll($res);
        $this->reset();
        $type = $fieldObj['type'];
        if (sizeof($rows) > 20) {
            $response['success'] = false;
            $response['message'] = "Too many classes. Stopped after 20.";
            $response['code'] = 405;
            return $response;
        }
        foreach ($rows as $key => $row) {
            if ($type == "number" || $type == "int") {
                $expression = "[{$field}]={$row['value']}";
            }
            if ($type == "text" || $type == "string") {
                $expression = "'[{$field}]'='{$row['value']}'";
            }
            $name = "{$field} = {$row['value']}";
            $res = $this->update($key, self::createClass($geometryType, $name, $expression, ($key * 10) + 10));
            if (!$res['success']) {
                $response['success'] = false;
                $response['message'] = "Error";
                $response['code'] = 400;
                return $response;
            }
        }
        $response['success'] = true;
        $response['message'] = "Updated " . sizeof($rows) . " classes";
        return $response;
    }

    static function createClass($type, $name = "Unnamed class", $expression = null, $sortid = 1)
    {
        $symbol = "";
        $size = "2";
        $width = "2";
        $color = Util::randHexColor();
        if ($type == "POINT" || $type == "MULTIPOINT") {
            $symbol = "circle";
            $size = "10";
            $width = "1";
        }
        $jsonStr = '{"sortid":' . $sortid . ',"name":"' . $name . '","expression":"' . $expression . '","label":false,"label_size":"","color":"' . $color . '","outlinecolor":"#000000","symbol":"' . $symbol . '","size":"' . $size . '","width":"' . $width . '","overlaycolor":"","overlayoutlinecolor":"","overlaysymbol":"","overlaysize":"","overlaywidth":""}';
        return json_decode($jsonStr);
    }


}