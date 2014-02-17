<?php

namespace app\models;

use app\inc\Util;

class Classification extends \app\inc\Model
{
    private $layer;
    private $table;

    function __construct($table)
    {
        parent::__construct();
        $this->layer = $table;
        $bits = explode(".", $this->layer);
        $this->table = new \app\models\Table($bits[0] . "." . $bits[1]);
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
        //print_r($classes);
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

    public function createSingle($data)
    {
        $this->reset();
        $layer = new \app\models\Layer();
        $res = $this->update("0", self::createClass($layer->getValueFromKey($this->layer, type), "Single value", null, 10, null, $data));
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

    public function createUnique($field, $data)
    {
        $layer = new \app\models\Layer();
        $geometryType = $layer->getValueFromKey($this->layer, type);
        $fieldObj = $this->table->metaData[$field];
        $query = "SELECT distinct({$field}) as value FROM " . $this->table->table . " ORDER BY {$field}";
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
            $name = $row['value'];
            $res = $this->update($key, self::createClass($geometryType, $name, $expression, ($key * 10) + 10, null, $data));
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

    public function createEqualIntervals($field, $num, $startColor, $endColor, $data)
    {
        $layer = new \app\models\Layer();
        $geometryType = $layer->getValueFromKey($this->layer, type);
        $query = "SELECT max({$field}) as max, min({$field}) FROM " . $this->table->table;
        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $row = $this->fetchRow($res);
        $diff = $row["max"] - $row["min"];
        $interval = $diff / $num;
        $this->reset();

        $grad = Util::makeGradient($startColor, $endColor, $num);
        for ($i = 1; $i <= ($num); $i++) {
            $top = $row['min'] + ($interval * $i);
            $bottom = $top - $interval;
            if ($i == $num) {
                $top++;
            }
            $expression = "[{$field}]>=" . round($bottom, 4) . " AND [{$field}]<" . round($top, 4);
            $name = " < " . round(($top - 1), 2);
            $class = self::createClass($geometryType, $name, $expression, ((($i - 1) * 10) + 10), $grad[$i - 1], $data);
            $res = $this->update(($i - 1), $class);
            if (!$res['success']) {
                $response['success'] = false;
                $response['message'] = "Error";
                $response['code'] = 400;
                return $response;
            }
        }
        $response['success'] = true;
        $response['message'] = "Updated " . $num . " classes";
        return $response;

    }

    public function createQuantile($field, $num, $startColor, $endColor, $data, $update = true)
    {
        $layer = new \app\models\Layer();
        $geometryType = $layer->getValueFromKey($this->layer, type);
        $query = "SELECT count(*) as count FROM " . $this->table->table;
        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $row = $this->fetchRow($res);
        $count = $row["count"];
        $numPerClass = $temp = ($count / $num);
        //echo $numPerClass."\n";
        $query = "SELECT * FROM " . $this->table->table . " ORDER BY {$field}";
        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $this->reset();
        $grad = Util::makeGradient($startColor, $endColor, $num);
        $bottom = null;
        $top = null;
        $u = 0;
        for ($i = 1; $i <= $count; $i++) {
            $row = $res->fetch(\PDO::FETCH_ASSOC);
            //echo $i . " | " . ($temp) . " | " . $row[$field] . "\n";
            if ($i == 1) {
                $bottom = $row[$field];
            }
            if ($i >= $temp || $i == $count) {
                if ($top) {
                    $bottom = $top;
                }
                $top = $row[$field];
                if ($i == $count) {
                    $top++;
                }
                $expression = "[{$field}]>=" . round($bottom, 4) . " AND [{$field}]<" . round($top, 4);
                $name = " < " . round(($top), 2);
                $tops[] = array($top, $grad[$u]);
                //echo $expression . "\n";
                if ($update) {
                    $class = self::createClass($geometryType, $name, $expression, (($u + 1) * 10), $grad[$u], $data);
                    $r = $this->update($u, $class);
                    if (!$r['success']) {
                        $response['success'] = false;
                        $response['message'] = "Error";
                        $response['code'] = 400;
                        return $response;
                    }
                }
                $u++;
                $temp = $temp + $numPerClass;
            }
        }
        $response['success'] = true;
        $response['values'] = $tops;
        $response['message'] = "Updated " . $num . " classes";
        return $response;
    }

    static function createClass($type, $name = "Unnamed class", $expression = null, $sortid = 1, $color = null, $data = null)
    {
        $symbol = ($data->symbol) ? : "";
        $size = ($data->symbolSize) ? : null;
        $color = ($color) ? : Util::randHexColor();
        if ($type == "POINT" || $type == "MULTIPOINT") {
            $symbol = ($data->symbol) ? : "circle";
            $size = ($data->symbolSize) ? : 10;
        }
        return (object)array(
            "sortid" => $sortid,
            "name" => $name,
            "expression" => $expression,
            "label" => ($data->labelText) ? true : false,
            "label_size" => ($data->labelSize) ? : "",
            "label_color" => ($data->labelColor) ? : "",
            "color" => $color,
            "outlinecolor" => ($data->outlineColor) ? : "",
            "style_opacity" => ($data->opacity) ? : "",
            "symbol" => $symbol,
            "angle" => ($data->angle) ? : "",
            "size" => $size,
            "width" => ($data->lineWidth) ? : "",
            "overlaycolor" => "",
            "overlayoutlinecolor" => "",
            "overlaysymbol" => "",
            "overlaysize" => "",
            "overlaywidth" => "",
            "label_text" => $data->labelText
        );
    }


}