<?php
namespace app\models;

use app\inc\Model;
use app\inc\log;
use \app\conf\Connection;
use \app\conf\App;

class Table extends Model
{
    public $table;
    public $schema;
    var $tableWithOutSchema;
    var $metaData;
    var $geomField;
    var $geomType;
    var $exits;
    var $versioning;
    var $sysCols;

    function __construct($table, $temp = false, $addGeomType = false)
    {
        parent::__construct();

        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            // If temp, then don't prefix with schema. Used when table/view is temporary
            if (!$temp) {
                $_schema = Connection::$param['postgisschema'];
                $table = $_schema . "." . $table;
            }
        } else {
            $table = str_replace(".", "", $_schema) . "." . $_table;
        }
        $this->tableWithOutSchema = $_table;
        $this->schema = str_replace(".", "", $_schema);
        $this->table = $table;
        $sql = "SELECT 1 FROM {$table} LIMIT 1";
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->exits = false;
        } else {
            $this->metaData = $this->getMetaData($this->table, $temp, $addGeomType);
            $this->geomField = $this->getGeometryColumns($this->table, "f_geometry_column");
            $this->geomType = $this->getGeometryColumns($this->table, "type");
            $this->primeryKey = $this->getPrimeryKey($this->table);
            $this->setType();
            $this->exits = true;
            $res = $this->doesColumnExist($this->table, "gc2_version_gid");
            $this->versioning = $res["exists"];
            $res = $this->doesColumnExist($this->table, "gc2_status");
            $this->workflow = $res["exists"];
        }
        $this->sysCols = array("gc2_version_gid", "gc2_version_start_date", "gc2_version_end_date", "gc2_version_uuid", "gc2_version_user");
    }

    private function setType()
    {
        $this->metaData = array_map(array($this, "getType"), $this->metaData);
        //die(print_r($this->metaData, true));

    }

    private function getType($field)
    {
        if (preg_match("/smallint/", $field['type']) ||
            preg_match("/integer/", $field['type']) ||
            preg_match("/bigint/", $field['type']) ||
            preg_match("/int2/", $field['type']) ||
            preg_match("/int4/", $field['type']) ||
            preg_match("/int8/", $field['type'])
        ) {
            $field['typeObj'] = array("type" => "int");
            $field['type'] = "int";
        } elseif (preg_match("/numeric/", $field['type']) ||
            preg_match("/real/", $field['type']) ||
            preg_match("/double/", $field['type']) ||
            preg_match("/float/", $field['type'])
        ) {
            $field['typeObj'] = array("type" => "decimal", "precision" => 3, "scale" => 10);
            $field['type'] = "number"; // SKAL ændres
        } elseif (preg_match("/bool/", $field['type'])) {
            $field['typeObj'] = array("type" => "boolean");
            $field['type'] = "boolean";
        } elseif (preg_match("/geometry/", $field['type'])) {
            $field['typeObj'] = array("type" => "geometry");
            $field['type'] = "geometry";
        } elseif (preg_match("/raster/", $field['type'])) {
            $field['typeObj'] = array("type" => "raster");
            $field['type'] = "raster";
        } elseif (preg_match("/text/", $field['type'])) {
            $field['typeObj'] = array("type" => "text");
            $field['type'] = "text";
        } elseif (preg_match("/text/", $field['type'])) {
            $field['typeObj'] = array("type" => "text");
            $field['type'] = "text";
        } elseif (preg_match("/timestamptz/", $field['type'])) {
            $field['typeObj'] = array("type" => "timestamptz");
            $field['type'] = "timestamptz";

        } elseif (preg_match("/date/", $field['type'])) {
            $field['typeObj'] = array("type" => "date");
            $field['type'] = "date";

        } elseif (preg_match("/uuid/", $field['type'])) {
            $field['typeObj'] = array("type" => "uuid");
            $field['type'] = "uuid";
        } elseif (preg_match("/hstore/", $field['type'])) {
            $field['typeObj'] = array("type" => "hstore");
            $field['type'] = "hstore";
        } elseif (preg_match("/bytea/", $field['type'])) {
            $field['typeObj'] = array("type" => "bytea");
            $field['type'] = "bytea";
        } else {
            $field['typeObj'] = array("type" => "string");
            $field['type'] = "string";
        }
        return $field;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    // Move to layer model
    function getRecords($createKeyFrom = NULL, $fields = "*", $whereClause = NULL) //
    {
        $response['success'] = true;
        $response['message'] = "Layers loaded";
        $response['data'] = array();
        $views = array();
        $viewDefinitions = array();

        $whereClause = Connection::$param["postgisschema"];
        if ($whereClause) {
            $sql = "SELECT * FROM settings.getColumns('f_table_schema=''{$whereClause}''','raster_columns.r_table_schema=''{$whereClause}''') ORDER BY sort_id";
        } else {
            $sql = "SELECT * FROM settings.getColumns('1=1','1=1') ORDER BY sort_id";

        }
        if (strpos(strtolower($whereClause), strtolower("order by")) !== false) {
            $sql .= (\app\conf\App::$param["reverseLayerOrder"]) ? " DESC" : " ASC";
        }
        $result = $this->execQuery($sql);
        $sql = "SELECT table_schema,table_name,view_definition FROM information_schema.views WHERE table_schema = :sSchema";
        $resView = $this->prepare($sql);
        try {
            $resView->execute(array("sSchema" => $whereClause));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($resView, "assoc")) {
            $views[$row["table_name"]] = true;
            $viewDefinitions[$row["table_name"]] = $row["view_definition"];
        }
        while ($row = $this->fetchRow($result, "assoc")) {
            $privileges = (array)json_decode($row["privileges"]);
            $arr = array();
            if ($_SESSION['subuser'] == Connection::$param['postgisschema'] || $_SESSION['subuser'] == false || ($_SESSION['subuser'] != false && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != "none" && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != false)) {
                foreach ($row as $key => $value) {
                    if ($key == "type" && $value == "GEOMETRY") {
                        $def = json_decode($row['def']);
                        if (($def->geotype) && $def->geotype != "Default") {
                            $value = "MULTI" . $def->geotype;
                        }
                    }
                    if ($key == "layergroup") {
                        if (!$value && \app\conf\App::$param['hideUngroupedLayers'] == true) {
                            //$value = "_gc2_hide_in_viewer";
                        }
                    }
                    $arr = $this->array_push_assoc($arr, $key, $value);
                }
                if ($createKeyFrom) {
                    $arr = $this->array_push_assoc($arr, "_key_", "{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}");
                    $primeryKey = $this->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
                    $arr = $this->array_push_assoc($arr, "pkey", $primeryKey['attname']);
                    $arr = $this->array_push_assoc($arr, "hasPkey", $this->hasPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}"));
                }
                if (isset($views[$row['f_table_name']])) {
                    $arr = $this->array_push_assoc($arr, "isview", true);
                    $arr = $this->array_push_assoc($arr, "viewdefinition", $viewDefinitions[$row['f_table_name']]);

                } else {
                    $arr = $this->array_push_assoc($arr, "isview", false);
                    $arr = $this->array_push_assoc($arr, "viewdefinition", null);

                }
                // Is indexed?
                if (1 == 1) {
                    $type = $row['f_table_name'];
                    if (mb_substr($type, 0, 1, 'utf-8') == "_") {
                        $type = "a" . $type;
                    }
                    $url = (App::$param['esHost'] ?: "http://127.0.0.1") . ":9200/{$this->postgisdb}_{$row['f_table_schema']}/{$type}/";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
                    curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $output = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpcode == "200") {
                        $arr = $this->array_push_assoc($arr, "indexed_in_es", true);
                    } else {
                        $arr = $this->array_push_assoc($arr, "indexed_in_es", false);
                    }
                }
                $response['data'][] = $arr;
            }
        }
        return $response;
    }

    function getGroupBy($field) // All tables
    {
        $sql = "SELECT {$field} AS {$field} FROM {$this->table} WHERE {$field} IS NOT NULL GROUP BY {$field}";
        $result = $this->execQuery($sql);
        if (!$this->PDOerror) {
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr[] = array("group" => $row[$field]);
            }
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    function getGroupByAsArray($field) // All tables
    {
        $sql = "SELECT DISTINCT({$field}) as distinct FROM {$this->table} ORDER BY {$field}";
        $res = $this->prepare($sql);
        try {
            $res->execute();
            while ($row = $this->fetchRow($res, "assoc")) {
                $arr[] = $row["distinct"];
            }
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['data'] = $arr;
        return $response;
    }

    function destroy()
    {
        $sql = "DROP TABLE {$this->table} CASCADE;";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $sql = "DROP VIEW {$this->table} CASCADE;";
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
        }
        $response['success'] = true;
        return $response;
    }
    public function getUuid($key) {
        $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_=:key";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("key" => $key));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $row = $this->fetchRow($res, "assoc");
        $response['success'] = true;
        $response['uuid'] = $row["uuid"];
        return $response;
    }

    public function updateRecord($data, $keyName) // All tables
    {
        $response = null;
        $data = $this->makeArray($data);
        foreach ($data as $set) {
            $set = $this->makeArray($set);
            foreach ($set as $row) {
                // Delete package from CKAN if "Update" is set to false
                if (isset($row->meta->ckan_update) AND $row->meta->ckan_update === false){
                    $uuid = $this->getUuid($row->_key_);
                    if (isset(App::$param["ckan"])) {
                        $ckanRes = \app\models\Layer::deleteCkan($uuid["uuid"]);
                    }
                }
                foreach ($row as $key => $value) {
                    if ($value === false) {
                        $value = null;
                    }
                    if ($key == "editable" || $key == "skipconflict") {
                        $value = $value ?: "0";
                    }
                    if ($key == "tags" || $key == "meta") {
                        $value = json_encode($value);
                    }
                    $value = $this->db->quote($value);
                    if ($key != $keyName) {
                        $pairArr[] = "{$key}={$value}";
                        $keyArr[] = $key;
                        $valueArr[] = $value;
                    } else {
                        $where = "{$key}={$value}";
                        $keyValue = $value;
                    }
                }
                $sql = "UPDATE {$this->table} SET ";
                $sql .= implode(",", $pairArr);
                $sql .= " WHERE {$where}";
                $result = $this->execQuery($sql, "PDO", "transaction");
                // If row does not exits, insert instead. Move to an insert method
                if ((!$result) && (!$this->PDOerror)) {
                    $sql = "INSERT INTO {$this->table} ({$keyName}," . implode(",", $keyArr) . ") VALUES({$keyValue}," . implode(",", $valueArr) . ")";
                    $result = $this->execQuery($sql, "PDO", "transaction");
                    $response['operation'] = "Row inserted";
                }
                if (!$this->PDOerror) {
                    $response['success'] = true;
                    $response['message'] = "Row updated";
                } else {
                    $response['success'] = false;
                    $response['message'] = $this->PDOerror;
                    $response['code'] = 406;
                    return $response;
                }
                unset($pairArr);
                unset($keyArr);
                unset($valueArr);
            }

        }
        return $response;
    }

    function getColumnsForExtGridAndStore($createKeyFrom = NULL, $includePriKey = false) // All tables
    {
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($fieldconfArr as $key => $value) {
            if ($value->properties == "*") {
                $table = new \app\models\Table($this->table);
                $distinctValues = $table->getGroupByAsArray($key);
                $fieldconfArr[$key]->properties = json_encode($distinctValues["data"], JSON_NUMERIC_CHECK);;
            }
        }
        if ($this->geomType == "POLYGON" || $this->geomType == "MULTIPOLYGON") {
            $type = "Polygon";
        } elseif ($this->geomType == "POINT" || $this->geomType == "MULTIPOINT") {
            $type = "Point";
        } elseif ($this->geomType == "LINESTRING" || $this->geomType == "MULTILINESTRING" || $this->geomType == "LINE") {
            $type = "Path";
        }
        if (substr($this->geomType, 0, 5) == "MULTI") {
            $multi = true;
        } else {
            $multi = false;
        }
        foreach ($this->metaData as $key => $value) {
            if ($value['type'] != "geometry" && ($key != $this->primeryKey['attname'] || $includePriKey)) {
                $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
                $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => $value['typeObj'], "properties" => $fieldconfArr[$key]->properties ?: null, "editable" => ($value['type'] == "bytea" || $key == $this->primeryKey['attname']) ? false : true);
            }

        }
        if ($createKeyFrom) {
            $fieldsForStore[] = array("name" => "_key_", "type" => "string");
            $fieldsForStore[] = array("name" => "pkey", "type" => "string");
            $fieldsForStore[] = array("name" => "hasPkey", "type" => "bool");
        }
        $response["forStore"] = $fieldsForStore;
        $response["forGrid"] = $columnsForGrid;
        $response["type"] = $type;
        $response["multi"] = $multi;
        return $response;
    }

    function getTableStructure($includePriKey = false) // Only geometry tables
    {

        $arr = array();
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        if (!$this->metaData) {
            $response['data'] = array();
        }
        foreach ($this->metaData as $key => $value) {
            if ($key != $this->primeryKey['attname'] || $includePriKey == true) {
                $arr = $this->array_push_assoc($arr, "id", $key);
                $arr = $this->array_push_assoc($arr, "column", $key);
                $arr = $this->array_push_assoc($arr, "sort_id", (int)$fieldconfArr[$key]->sort_id);
                $arr = $this->array_push_assoc($arr, "querable", $fieldconfArr[$key]->querable);
                $arr = $this->array_push_assoc($arr, "mouseover", $fieldconfArr[$key]->mouseover);
                $arr = $this->array_push_assoc($arr, "filter", $fieldconfArr[$key]->filter);
                $arr = $this->array_push_assoc($arr, "searchable", $fieldconfArr[$key]->searchable);
                $arr = $this->array_push_assoc($arr, "conflict", $fieldconfArr[$key]->conflict);
                $arr = $this->array_push_assoc($arr, "alias", $fieldconfArr[$key]->alias);
                $arr = $this->array_push_assoc($arr, "link", $fieldconfArr[$key]->link);
                $arr = $this->array_push_assoc($arr, "image", $fieldconfArr[$key]->image);
                $arr = $this->array_push_assoc($arr, "linkprefix", $fieldconfArr[$key]->linkprefix);
                $arr = $this->array_push_assoc($arr, "properties", $fieldconfArr[$key]->properties);
                $arr = $this->array_push_assoc($arr, "is_nullable", $value['is_nullable']);
                if ($value['typeObj']['type'] == "decimal") {
                    $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']} ({$value['typeObj']['precision']} {$value['typeObj']['scale']})");
                } else {
                    $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']}");
                }
                $response['data'][] = $arr;
            }
        }
        $response['success'] = true;
        $response['message'] = "Structure loaded";
        $response['versioned'] = $this->versioning;
        $response['flowflow'] = $this->workflow;
        return $response;
    }

    private function purgeFieldConf($_key_)
    {
        // Set metaData again in case of a column was dropped
        $this->metaData = $this->getMetaData($this->table);
        $this->setType();
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($fieldconfArr as $key => $value) {
            if (!$this->metaData[$key]) {
                unset($fieldconfArr[$key]);
            }
        }
        $conf['fieldconf'] = json_encode($fieldconfArr);
        $conf['_key_'] = $_key_;
        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf)), "_key_");
        return $res;
    }

    function updateColumn($data, $key) // Only geometry tables
    {
        $res = $this->purgeFieldConf($key);

        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($data as $value) {
            $safeColumn = $this->toAscii($value->column, array(), "_");
            if ($this->metaData[$value->id]["is_nullable"] != $value->is_nullable) {
                $sql = "ALTER TABLE {$this->table} ALTER {$value->column} " . ($value->is_nullable ? "DROP" : "SET") . " NOT NULL";
                //die($sql);
                $res = $this->prepare($sql);
                try {
                    $res->execute();
                } catch (\PDOException $e) {
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 400;
                    return $response;
                }
                $response['success'] = true;
                return $response;
            }
            // Case of renaming column
            if ($value->id != $value->column && ($value->column) && ($value->id)) {
                if ($safeColumn == "state") {
                    $safeColumn = "_state";
                }
                if (is_numeric(mb_substr($safeColumn, 0, 1, 'utf-8'))) {
                    $safeColumn = "_" . $safeColumn;
                }
                if (in_array($value->id, $this->sysCols)) {
                    $response['success'] = false;
                    $response['message'] = "You can't rename a system column";
                    $response['code'] = 400;
                    return $response;
                }
                $sql .= "ALTER TABLE {$this->table} RENAME \"{$value->id}\" TO \"{$safeColumn}\";";
                $value->column = $safeColumn;
                unset($fieldconfArr[$value->id]);
                $response['message'] = "Renamed";

            } else {
                $response['message'] = "Updated";
            }

            $fieldconfArr[$safeColumn] = $value;
        }
        $conf['fieldconf'] = json_encode($fieldconfArr);
        $conf['_key_'] = $key;

        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf)), "_key_");
        if (!$res["success"]) {
            $response['success'] = false;
            $response['message'] = $res["message"];
            $response['code'] = "406";
            return $response;
        }
        $this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror) || (!$sql)) {
            $response['success'] = true;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    function deleteColumn($data, $whereClause = NULL, $_key_) // Only geometry tables
    {
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($data as $value) {
            if (in_array($value, $this->sysCols)) {
                $response['success'] = false;
                $response['message'] = "You can't drop a system column";
                $response['code'] = 400;
                return $response;
            }
            $sql .= "ALTER TABLE {$this->table} DROP COLUMN {$value};";
            unset($fieldconfArr[$value]);
        }
        $this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror) || (!$sql)) {
            $conf['fieldconf'] = json_encode($fieldconfArr);
            $conf['f_table_name'] = $this->table;
            $geometryColumnsObj = new table("settings.geometry_columns_join");
            $geometryColumnsObj->updateRecord(json_decode(json_encode($conf)), "f_table_name", $whereClause);
            $response['success'] = true;
            $response['message'] = "Column deleted";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        $res = $this->purgeFieldConf($_key_);
        return $response;
    }

    function addColumn($data) // All tables
    {
        $safeColumn = $this->toAscii($data['column'], array(), "_");
        $sql = "";
        if (is_numeric(mb_substr($safeColumn, 0, 1, 'utf-8'))) {
            $safeColumn = "_" . $safeColumn;
        }
        if ($safeColumn == "state") {
            $safeColumn = "_state";
        }
        if (in_array($safeColumn, $this->sysCols)) {
            $response['success'] = false;
            $response['message'] = "The name is reserved. Choose another.";
            $response['code'] = 400;
            return $response;
        }
        // We set the data type
        switch ($data['type']) {
            case "Integer":
                $type = "integer";
                break;
            case "Decimal":
                $type = "double precision";
                break;
            case "String":
                $type = "varchar(255)";
                break;
            case "Text":
                $type = "text";
                break;
            case "Date":
                $type = "date";
                break;
            case "Boolean":
                $type = "bool";
                break;
            case "Bytea":
                $type = "bytea";
                break;
            case "Hstore":
                $type = "hstore";
                break;
            case "Geometry":
                $type = "geometry(Geometry,4326)";
                break;
        }
        $sql .= "ALTER TABLE {$this->table} ADD COLUMN {$safeColumn} {$type};";
        $this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror) || (!$sql)) {
            $response['success'] = true;
            $response['message'] = "Column added";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = "400";
        }
        return $response;
    }

    public function addVersioning()
    {
        $this->begin();
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_version_gid SERIAL NOT NULL";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_version_start_date TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_version_end_date TIMESTAMP WITH TIME ZONE DEFAULT NULL";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_version_uuid UUID NOT NULL DEFAULT uuid_generate_v4()";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_version_user VARCHAR(255)";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "Table is now versioned";
        return $response;
    }

    public function removeVersioning()
    {
        $this->begin();
        $sql = "ALTER TABLE {$this->table} DROP COLUMN gc2_version_gid";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} DROP COLUMN gc2_version_start_date";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} DROP COLUMN gc2_version_end_date";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} DROP COLUMN gc2_version_uuid";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} DROP COLUMN gc2_version_user";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "Versioning is removed";
        return $response;
    }

    public function addWorkflow()
    {
        $this->begin();
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_status integer";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "ALTER TABLE {$this->table} ADD COLUMN gc2_workflow hstore";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $sql = "UPDATE {$this->table} SET gc2_status = 3";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $this->commit();
        $response['success'] = true;
        $response['message'] = "Table has now workflow";
        return $response;
    }

    function point2multipoint()
    {
        $sql = "BEGIN;";
        $sql .= "ALTER TABLE {$this->table} DROP CONSTRAINT enforce_geotype_the_geom;";
        $sql .= "UPDATE {$this->table} SET {$this->geomField} = ST_Multi({$this->geomField});";
        $sql .= "UPDATE geometry_columns SET type = 'MULTIPOINT' WHERE f_table_name = '{$this->table}';";
        $sql .= "ALTER TABLE {$this->table} ADD CONSTRAINT enforce_geotype_the_geom CHECK (geometrytype({$this->geomField}) = 'MULTIPOINT'::TEXT OR {$this->geomField} IS NULL);";
        $sql .= "COMMIT";
        $this->execQuery($sql, "PDO", "transaction");
    }

    function create($table, $type, $srid = 4326)
    {
        $this->PDOerror = NULL;
        $table = $this->toAscii($table, array(), "_");
        if (is_numeric(mb_substr($table, 0, 1, 'utf-8'))) {
            $table = "_" . $table;
        }
        $sql = "BEGIN;";
        $sql .= "CREATE TABLE {$this->postgisschema}.{$table} (gid SERIAL PRIMARY KEY,id INT) WITH OIDS;";
        $sql .= "SELECT AddGeometryColumn('" . $this->postgisschema . "','{$table}','the_geom',{$srid},'{$type}',2);"; // Must use schema prefix cos search path include public
        $sql .= "COMMIT;";
        $this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror)) {
            $response['success'] = true;
            $response['tableName'] = $table;
            $response['message'] = "Layer created";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    function createAsRasterTable($srid = 4326)
    {
        $this->PDOerror = NULL;
        $table = $this->tableWithOutSchema;
        $table = $this->toAscii($table, array(), "_");
        if (is_numeric(mb_substr($table, 0, 1, 'utf-8'))) {
            $table = "_" . $table;
        }
        $sql = "CREATE TABLE {$this->postgisschema}.{$table}(rast raster);";
        $this->execQuery($sql, "PDO", "transaction");
        $sql = "INSERT INTO {$this->postgisschema}.{$table}(rast)
                SELECT ST_AddBand(ST_MakeEmptyRaster(1000, 1000, 0.3, -0.3, 2, 2, 0, 0,{$srid}), 1, '8BSI'::TEXT, -129, NULL);";
        $this->execQuery($sql, "PDO", "transaction");
        $sql = "SELECT AddRasterConstraints('{$this->postgisschema}'::name,'{$table}'::name, 'rast'::name);";
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['tableName'] = $table;
            $response['message'] = "Layer created";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    function makeArray($notArray)
    {
        if (!is_array($notArray)) {
            $nowArray = array(0 => $notArray);
        } else {
            $nowArray = $notArray; // Input was array. Return it unaltered
        }
        return $nowArray;
    }

    public function getMapForEs()
    {
        $schema = $this->metaData;
        return $schema;
    }

    public function checkcolumn($field)
    {
        $res = $this->doesColumnExist($this->table, $field);
        if ($this->PDOerror) {
            $response['success'] = true;
            $response['message'] = $res;
            return $response;
        }
        return $res;
    }

    function getData($table, $offset, $limit) //
    {
        $arrayWithFields = $this->getMetaData($table);
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                //pass

            } elseif ($arr['type'] == "bytea") {
                $fieldsArr[] = "'binary' AS \"{$key}\"";

            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        if (isset($fieldsArr)) {
            $sql = implode(",", $fieldsArr);
        } else {
            $sql = "*";
        }
        $sql = "SELECT {$sql} FROM {$table} LIMIT {$limit} OFFSET {$offset}";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        while ($row = $this->fetchRow($res, "assoc")) {
            $arr = array();
            foreach ($row as $key => $value) {
                $arr = $this->array_push_assoc($arr, $key, $value);
            }
            $response['data'][] = $arr;

        }
        if (!isset($response['data'])) {
            $response['data'] = array();
        }
        // Get the total count
        $sql = "SELECT count(*) AS count FROM {$table}";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res, "assoc");

        $response['success'] = true;
        $response['message'] = "Data fetched";
        $response['total'] = $row["count"];
        return $response;
    }

    public function insertRecord()
    {
        $sql = "INSERT INTO " . $this->table . " DEFAULT VALUES";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Record inserted";
        return $response;
    }

    public function deleteRecord($data, $keyName) // All tables
    {
        if (!$this->hasPrimeryKey($this->table)) {
            $response['success'] = false;
            $response['message'] = "You can't edit a relation without a primary key";
            $response['code'] = 401;
            return $response;
        }
        $data = $this->makeArray($data);

        $sql = "DELETE FROM " . $this->table . " WHERE \"{$keyName}\" =:key";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("key" => $data["data"]));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Record deleted";
        return $response;
    }
}

