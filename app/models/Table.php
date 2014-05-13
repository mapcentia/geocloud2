<?php
namespace app\models;

use app\inc\Model;
use app\inc\log;
use \app\conf\Connection;

class Table extends Model
{
    public $table;
    var $tableWithOutSchema;
    var $metaData;
    var $geomField;
    var $geomType;
    var $exits;

    function __construct($table, $temp = false)
    {
        parent::__construct();

        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            // If temp, then don't prefix with schema. Used when table/view is temporary
            if (!$temp) {
                $table = Connection::$param['postgisschema'] . "." . $table;
            }
        } else {
            $table = str_replace(".", "", $_schema) . "." . $_table;
        }
        $this->tableWithOutSchema = $_table;
        $this->table = $table;
        $sql = "SELECT 1 FROM {$table}";
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->exits = false;
        } else {
            $this->metaData = $this->getMetaData($this->table);
            $this->geomField = $this->getGeometryColumns($this->table, "f_geometry_column");
            $this->geomType = $this->getGeometryColumns($this->table, "type");
            $this->primeryKey = $this->getPrimeryKey($this->table);
            $this->setType();
            $this->exits = true;
        }
    }

    private function setType()
    {
        $this->metaData = array_map(array($this, "getType"), $this->metaData);
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

    function getRecords($createKeyFrom = NULL, $fields = "*", $whereClause = NULL) // All tables
    {
        $response['success'] = true;
        $response['message'] = "Layers loaded";
        $response['data'] = array();
        $sql = "SELECT {$fields} FROM {$this->table}";

        if ($whereClause) {
            $sql .= " WHERE {$whereClause}";
        }
        $result = $this->execQuery($sql);
        while ($row = $this->fetchRow($result, "assoc")) {
            $arr = array();
            foreach ($row as $key => $value) {
                if ($key == "type" && $value == "GEOMETRY") {
                    $def = json_decode($row['def']);
                    if (($def->geotype) && $def->geotype != "Default") {
                        $value = "MULTI" . $def->geotype;
                    }
                }
                $arr = $this->array_push_assoc($arr, $key, $value);
            }

            if ($createKeyFrom) {
                $arr = $this->array_push_assoc($arr, "_key_", "{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}");
                $primeryKey = $this->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
                $arr = $this->array_push_assoc($arr, "pkey", $primeryKey['attname']);
            }
            $response['data'][] = $arr;
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

    function destroy()
    {
        $sql = "DROP TABLE {$this->table} CASCADE;";
        $this->execQuery($sql, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    function updateRecord($data, $keyName) // All tables
    {
        $data = $this->makeArray($data);
        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                if ($value === false) {
                    $value = null;
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
                //$response['code'] = 406;
            }
            unset($pairArr);
            unset($keyArr);
            unset($valueArr);
        }
        return $response;
    }

    function getColumnsForExtGridAndStore($createKeyFrom = NULL) // All tables
    {
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
            if ($value['type'] != "geometry" && $key != $this->primeryKey['attname']) {
                $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
                $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => $value['typeObj']);
            }
        }
        if ($createKeyFrom) {
            $fieldsForStore[] = array("name" => "_key_", "type" => "string");
            $fieldsForStore[] = array("name" => "pkey", "type" => "string");
        }
        $response["forStore"] = $fieldsForStore;
        $response["forGrid"] = $columnsForGrid;
        $response["type"] = $type;
        $response["multi"] = $multi;
        return $response;
    }

    function getTableStructure() // Only geometry tables
    {
        $response['success'] = true;
        $response['message'] = "Structure loaded";
        $arr = array();
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($this->metaData as $key => $value) {
            if ($key != $this->primeryKey['attname']) {
                $arr = $this->array_push_assoc($arr, "id", $key);
                $arr = $this->array_push_assoc($arr, "column", $key);
                $arr = $this->array_push_assoc($arr, "sort_id", (int)$fieldconfArr[$key]->sort_id);
                $arr = $this->array_push_assoc($arr, "querable", $fieldconfArr[$key]->querable);
                $arr = $this->array_push_assoc($arr, "alias", $fieldconfArr[$key]->alias);
                $arr = $this->array_push_assoc($arr, "link", $fieldconfArr[$key]->link);
                $arr = $this->array_push_assoc($arr, "linkprefix", $fieldconfArr[$key]->linkprefix);
                $arr = $this->array_push_assoc($arr, "properties", $fieldconfArr[$key]->properties);
                if ($value['typeObj']['type'] == "decimal") {
                    $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']} ({$value['typeObj']['precision']} {$value['typeObj']['scale']})");
                } else {
                    $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']}");
                }
                $response['data'][] = $arr;
            }
        }
        return $response;
    }

    function updateColumn($data, $key) // Only geometry tables
    {
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($data as $value) {
            $safeColumn = $this->toAscii($value->column, array(), "_");
            if ($value->id != $value->column && ($value->column) && ($value->id)) {

                if ($safeColumn == "state") {
                    $safeColumn = "_state";
                }
                if (is_numeric(mb_substr($safeColumn, 0, 1, 'utf-8'))) {
                    $safeColumn = "_" . $safeColumn;
                }
                $sql .= "ALTER TABLE {$this->table} RENAME \"{$value->id}\" TO \"{$safeColumn}\";";
                $value->column = $safeColumn;
                unset($fieldconfArr[$value->id]);
            }
            $fieldconfArr[$safeColumn] = $value;
        }
        $conf['fieldconf'] = json_encode($fieldconfArr);
        $conf['_key_'] = $key;

        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $geometryColumnsObj->updateRecord(json_decode(json_encode($conf)), "_key_");
        $this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror) || (!$sql)) {
            $response['success'] = true;
            $response['message'] = "Column renamed";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    function deleteColumn($data, $whereClause = NULL) // Only geometry tables
    {
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($data as $value) {
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
            case "Geometry":
                $type = "geometry";
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
        }
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

    function makeArray($notArray)
    {
        if (!is_array($notArray)) {
            $nowArray = array(0 => $notArray);
        } else {
            $nowArray = $notArray; // Input was array. Return it unaltered
        }
        return $nowArray;
    }
}

