<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Util;

/**
 * Class Classification
 * @package app\models
 */
class Classification extends \app\inc\Model
{
    private $layer;
    private $table;
    private $def;
    private $geometryType;
    private $tile;

    /**
     * Classification constructor.
     * @param string $table
     */
    function __construct($table)
    {
        parent::__construct();
        $this->layer = $table;
        $bits = explode(".", $this->layer);
        $this->table = new \app\models\Table($bits[0] . "." . $bits[1]);
        $this->tile = new \app\models\Tile($table);
        // Check if geom type is overridden
        $def = new \app\models\Tile($table);
        $this->def = $def->get();
        if (($this->def['data'][0]['geotype']) && $this->def['data'][0]['geotype'] != "Default") {
            $this->geometryType = $this->def['data'][0]['geotype'];
        }
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
            $arrNew = array();
            $sortedArr = array();
            $response['success'] = true;
            $row = $this->fetchRow($result, "assoc");
            $arr = $arr2 = (array)json_decode($row['class']);
            for ($i = 0; $i < sizeof($arr); $i++) {
                $last = 10000;
                foreach ($arr2 as $key => $value) {
                    if ($value->sortid < $last) {
                        $temp = $value;
                        $del = $key;
                        $last = $value->sortid;
                    }
                }
                array_push($sortedArr, $temp);
                unset($arr2[$del]);
                $temp = null;
            }
            for ($i = 0; $i < sizeof($arr); $i++) {
                $arrNew[$i] = (array)Util::casttoclass('stdClass', $arr[$i]);
                $arrNew[$i]['id'] = $i;
            }
            $response['data'] = $arrNew;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 400;
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
            foreach ($arr as $key => $value) {
                if ($value === null) { // Never send null to client
                    $arr[$key] = "";
                }
            }
            $props = array(
                "name" => "Unnamed Class",
                "label" => false,
                "label_text" => "",
                "label2_text" => "",
                "force_label" => false,
                "color" => "#FF0000",
                "outlinecolor" => "#FF0000",
                "size" => "2",
                "width" => "1");
            foreach ($arr as $value) {
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
        if (empty($tableObj->PDOerror)) {
            $response = true;
        } else {
            $response = false;
        }
        return $response;
    }

    private function storeWizard($data)
    {
        // First we replace unicode escape sequence
        //$data = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $data);
        $tableObj = new Table("settings.geometry_columns_join");
        $obj = new \stdClass;
        $obj->classwizard = $data;
        $obj->_key_ = $this->layer;
        $tableObj->updateRecord($obj, "_key_");
        if (empty($tableObj->PDOerror)) {
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
        if ($this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE))) {
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
        if ($data->expression) {
            urldecode($data->expression);
        }
        $classes = $this->getAll();
        foreach ((array)$data as $k => $v) {
            $classes['data'][$id][$k] = $v;

        }
        if ($this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE))) {
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
        if ($this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE))) {
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

    private function setLayerDef()
    {
        $def = $this->tile->get();
        if (!$def['success']) {
            $response['success'] = false;
            $response['message'] = $def['message'];
            $response['code'] = 400;
            return $response;
        }
        $def["data"][0]["cluster"] = null;
        $defJson = json_encode($def["data"][0], JSON_UNESCAPED_UNICODE);
        $res = $this->tile->update($defJson);
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        return $response;

    }

    public function createSingle($data, $color)
    {
        $res = $this->setLayerDef();
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $this->reset();
        $layer = new \app\models\Layer();
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        $res = $this->update("0", self::createClass($geometryType, $layer->getValueFromKey($this->layer, "f_table_title") ?: $layer->getValueFromKey($this->layer, "f_table_name"), null, 10, "#" . $color, $data));
        if ($res['success']) {
            $response['success'] = true;
            $response['message'] = "Updated one class";
        } else {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
        }
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function createUnique($field, $data)
    {
        $res = $this->setLayerDef();
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $layer = new \app\models\Layer();
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
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
        if (sizeof($rows) > 1000) {
            $response['success'] = false;
            $response['message'] = "Too many classes. Stopped after 1000.";
            $response['code'] = 405;
            return $response;
        }
        if ($data->custom->colorramp !== false && $data->custom->colorramp != "-1") {
            $colorBrewer = \app\inc\ColorBrewer::getQualitative($data->custom->colorramp);
        }
        $cArr = array();
        foreach ($rows as $key => $row) {
            if ($type == "number" || $type == "int") {
                $expression = "[{$field}]={$row['value']}";
            }
            if ($type == "text" || $type == "string") {
                $expression = "'[{$field}]'='{$row['value']}'";
            }
            $name = $row['value'];
            if ($data->custom->colorramp !== false && $data->custom->colorramp != "-1") {
                $c = current($colorBrewer);
                next($colorBrewer);
            } else {
                $c = null;
            }
            $cArr[$key] = self::createClass($geometryType, $name, $expression, ($key * 10) + 10, $c, $data);
        }
        if ($this->store(json_encode($cArr, JSON_UNESCAPED_UNICODE))) {
            $response['success'] = true;
            $response['success'] = true;
            $response['message'] = "Updated " . sizeof($rows) . " classes";
            $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        } else {
            $response['success'] = false;
            $response['message'] = "Error";
            $response['code'] = 400;
        }
        return $response;
    }

    public function createEqualIntervals($field, $num, $startColor, $endColor, $data)
    {
        $res = $this->setLayerDef();
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $layer = new \app\models\Layer();
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        if ($geometryType == "RASTER") {
            $parts = explode(".", $this->layer);
            $setSchema = "set search_path to public,{$parts[0]}";
            $res = $this->prepare($setSchema);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
            $query = "SELECT band, (stats).min, (stats).max FROM (SELECT band, public.ST_SummaryStats('{$parts[1]}','rast', band) As stats FROM generate_series(1,1) As band) As foo;";
        } else {
            $query = "SELECT max({$field}) as max, min({$field}) FROM {$this->table->table}";
        }
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
                $expression = "[{$field}]>=" . $bottom . " AND [{$field}]<=" . $top;
            } else {
                $expression = "[{$field}]>=" . $bottom . " AND [{$field}]<" . $top;
            }
            $name = " < " . round(($top), 2);
            $class = self::createClass($geometryType, $name, $expression, ((($i - 1) * 10) + 10), $grad[$i - 1], $data);
            $res = $this->update(($i - 1), $class);
            if (!$res['success']) {
                $response['success'] = false;
                $response['message'] = $res['message'];
                $response['code'] = 400;
                return $response;
            }
        }
        $response['success'] = true;
        $response['message'] = "Updated " . $num . " classes";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function createQuantile($field, $num, $startColor, $endColor, $data, $update = true)
    {
        $res = $this->setLayerDef();
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $layer = new \app\models\Layer();
        $geometryType = $layer->getValueFromKey($this->layer, "type");
        $query = "SELECT count(*) AS count FROM " . $this->table->table;
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
            if ($i == 1) {
                $bottom = $row[$field];
            }
            if ($i >= $temp || $i == $count) {
                if ($top) {
                    $bottom = $top;
                }
                $top = $row[$field];
                if ($i == $count) {
                    $expression = "[{$field}]>=" . $bottom . " AND [{$field}]<=" . $top;
                } else {
                    $expression = "[{$field}]>=" . $bottom . " AND [{$field}]<" . $top;
                }
                $name = " < " . round(($top), 2);
                $tops[] = array($top, $grad[$u]);
                if ($update) {
                    $class = self::createClass($geometryType, $name, $expression, (($u + 1) * 10), $grad[$u], $data);
                    $r = $this->update($u, $class);
                    if (!$r['success']) {
                        $response['success'] = false;
                        $response['message'] = $r['message'];
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
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function createCluster($distance, $data)
    {
        $layer = new \app\models\Layer();
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        if ($geometryType != "POINT" && $geometryType != "MULTIPOINT") {
            $response['success'] = false;
            $response['message'] = "Only point layers can be clustered";
            $response['code'] = 400;
            return $response;
        }
        $this->reset();

        // Set layer def
        $def = $this->tile->get();
        if (!$def['success']) {
            $response['success'] = false;
            $response['message'] = $def['message'];
            $response['code'] = 400;
            return $response;
        }
        $def["data"][0]["cluster"] = $distance;
        $def["data"][0]["meta_tiles"] = true;
        $def["data"][0]["meta_size"] = 4;
        $defJson = json_encode($def["data"][0], JSON_UNESCAPED_UNICODE);
        $res = $this->tile->update($defJson);
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        //Set single class
        if (\app\conf\App::$param["mapserver_ver_7"]) {
            $ClusterFeatureCount = "Cluster_FeatureCount";
        } else {
            $ClusterFeatureCount = "Cluster:FeatureCount";
        }
        $expression = "[{$ClusterFeatureCount}]=1";
        $name = "Single";
        $res = $this->update(0, self::createClass($geometryType, $name, $expression, 10, "#0000FF", $data));
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }

        //Set cluster class
        $expression = "[{$ClusterFeatureCount}]>1";
        $name = "Cluster";
        $data->labelText = "[{$ClusterFeatureCount}]";
        $data->labelSize = "9";
        $data->labelPosition = "cc";
        $data->symbolSize = "50";
        $data->overlaySize = "35";
        $data->overlayColor = "#00FF00";
        $data->overlaySymbol = "circle";
        $data->symbol = "circle";
        $data->opacity = "25";
        $data->overlayOpacity = "70";
        $data->force = true;

        $res = $this->update(1, self::createClass($geometryType, $name, $expression, 20, "#00FF00", $data));
        if (!$res['success']) {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Updated 2 classes";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function copyClasses($to, $from)
    {
        $query = "SELECT class FROM settings.geometry_columns_join WHERE _key_ =:from";
        $res = $this->prepare($query);
        try {
            $res->execute(array("from" => $from));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $row = $this->fetchRow($res);
        $conf['class'] = $row["class"];
        $conf['_key_'] = $to;


        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf, JSON_UNESCAPED_UNICODE)), "_key_");
        if (!$res["success"]) {
            $response['success'] = false;
            $response['message'] = $res["message"];
            $response['code'] = "406";
            return $response;
        }
        return $res;
    }


    static function createClass($type, $name = "Unnamed class", $expression = null, $sortid = 1, $color = null, $data = null)
    {
        $symbol = ($data->symbol) ?: "";
        $size = ($data->symbolSize) ?: "";
        $outlineColor = ($data->outlineColor) ?: "";
        $color = ($color) ?: Util::randHexColor();
        if ($type == "POINT" || $type == "MULTIPOINT") {
            $symbol = ($data->symbol) ?: "circle";
            $size = ($data->symbolSize) ?: 10;
        }
        return (object)array(
            "sortid" => $sortid,
            "name" => $name,
            "expression" => $expression,
            "label" => !empty($data->labelText) ? true : false,
            "label_size" => !empty($data->labelSize) ? $data->labelSize : "",
            "label_color" => !empty($data->labelColor) ? $data->labelColor : "",
            "color" => $color,
            "outlinecolor" => !empty($outlineColor) ? $outlineColor : "",
            "style_opacity" => !empty($data->opacity) ? $data->opacity : "",
            "symbol" => $symbol,
            "angle" => !empty($data->angle) ? $data->angle : "",
            "size" => $size,
            "width" => !empty($data->lineWidth) ? $data->lineWidth : "",
            "overlaycolor" => !empty($data->overlayColor) ? $data->overlayColor : "",
            "overlayoutlinecolor" => "",
            "overlaysymbol" => !empty($data->overlaySymbol) ? $data->overlaySymbol : "",
            "overlaysize" => !empty($data->overlaySize) ? $data->overlaySize : "",
            "overlaywidth" => "",
            "label_text" => !empty($data->labelText) ? $data->labelText : "",
            "label_position" => !empty($data->labelPosition) ? $data->labelPosition : "",
            "label_font" => !empty($data->labelFont) ? $data->labelFont : "",
            "label_fontweight" => !empty($data->labelFontWeight) ? $data->labelFontWeight : "",
            "label_angle" => !empty($data->labelAngle) ? $data->labelAngle : "",
            "label_backgroundcolor" => !empty($data->labelBackgroundcolor) ? $data->labelBackgroundcolor : "",
            "style_opacity" => !empty($data->opacity) ? $data->opacity : "",
            "overlaystyle_opacity" => !empty($data->overlayOpacity) ? $data->overlayOpacity : "",
            "label_force" => !empty($data->force) ? $data->force : "",
        );
    }
}