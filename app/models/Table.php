<?php
namespace app\models;

use app\inc\Model;
use app\conf\Connection;
use app\conf\App;
use app\inc\Util;

class Table extends Model
{
    // TODO Set access on all vars
    public $table;
    public $schema;
    var $tableWithOutSchema;
    var $metaData;
    var $geomField;
    var $geomType;
    var $exits;
    var $versioning;
    var $sysCols;
    var $primeryKey;
    var $specialChars;

    /**
     * Table constructor.
     * @param string $table
     * @param bool $temp
     * @param bool $addGeomType
     */
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
        $this->specialChars = "/['^£$%&*()}{@#~?><>,|=+¬]/";
    }

    /**
     * Sets the metaData property
     */
    private function setType()
    {
        $this->metaData = array_map(array($this, "getType"), $this->metaData);
    }

    /**
     * @param array $field
     * @return array
     */
    private function getType(array $field)
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
        } elseif (preg_match("/json/", $field['type'])) {
            $field['typeObj'] = array("type" => "json");
            $field['type'] = "json";
        } elseif (preg_match("/timestamp/", $field['type'])) {
            $field['typeObj'] = array("type" => "timestamp");
            $field['type'] = "timestamp";
        } else {
            $field['typeObj'] = array("type" => "string");
            $field['type'] = "string";
        }
        return $field;
    }

    /**
     * Helper method
     * @param array $array
     * @param string $key
     * @param string $value
     * @return array
     */
    private function array_push_assoc(array $array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    // TODO Move to layer model. This may belong to the Layer class
    /**
     * @param null $createKeyFrom
     * @return mixed
     */
    public function getRecords($createKeyFrom = NULL) //
    {
        $response['success'] = true;
        $response['message'] = "Layers loaded";
        $response['data'] = array();
        $views = array();
        $matViews = array();
        $viewDefinitions = array();
        $matViewDefinitions = array();

        $whereClause = Connection::$param["postgisschema"];
        if ($whereClause) {
            $sql = "SELECT * FROM settings.getColumns('f_table_schema=''{$whereClause}''','raster_columns.r_table_schema=''{$whereClause}''') ORDER BY sort_id";
        } else {
            $sql = "SELECT * FROM settings.getColumns('1=1','1=1') ORDER BY sort_id";

        }
        $sql .= (\app\conf\App::$param["reverseLayerOrder"]) ? " DESC" : " ASC";
        $result = $this->execQuery($sql);

        // Check if VIEW
        $sql = "SELECT schemaname,viewname,definition FROM pg_views WHERE schemaname = :sSchema";
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
            $views[$row["viewname"]] = true;
            $viewDefinitions[$row["viewname"]] = $row["definition"];
        }

        // Check if FOREIGN TABLE
        $sql = "SELECT foreign_table_schema,foreign_table_name,foreign_server_name FROM information_schema.foreign_tables WHERE foreign_table_schema = :sSchema";
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
            $foreignTables[$row["foreign_table_name"]] = true;
        }

        // Check if materialized view
        $sql = "SELECT schemaname,matviewname,definition FROM pg_matviews WHERE schemaname = :sSchema";
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
            $matViews[$row["matviewname"]] = true;
            $matViewDefinitions[$row["matviewname"]] = $row["definition"];
        }
        while ($row = $this->fetchRow($result, "assoc")) {
            $privileges = (array)json_decode($row["privileges"]);
            $arr = array();
            if ($_SESSION['subuser'] == Connection::$param['postgisschema'] || $_SESSION['subuser'] == false || ($_SESSION['subuser'] != false && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != "none" && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != false)) {
                $relType = "t"; // Default
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

                // IS VIEW
                if (isset($views[$row['f_table_name']])) {
                    $arr = $this->array_push_assoc($arr, "isview", true);
                    $arr = $this->array_push_assoc($arr, "viewdefinition", $viewDefinitions[$row['f_table_name']]);
                    $relType = "v";
                } else {
                    $arr = $this->array_push_assoc($arr, "isview", false);
                    $arr = $this->array_push_assoc($arr, "viewdefinition", null);
                }

                // IS MATVIEW
                if (isset($matViews[$row['f_table_name']])) {
                    $arr = $this->array_push_assoc($arr, "ismatview", true);
                    $arr = $this->array_push_assoc($arr, "matviewdefinition", $matViewDefinitions[$row['f_table_name']]);
                    $relType = "mv";
                } else {
                    $arr = $this->array_push_assoc($arr, "ismatview", false);
                    $arr = $this->array_push_assoc($arr, "matviewdefinition", null);
                }

                // IS FOREIGN
                if (isset($foreignTables[$row['f_table_name']])) {
                    $arr = $this->array_push_assoc($arr, "isforeign", true);
                    $relType = "ft";
                } else {
                    $arr = $this->array_push_assoc($arr, "isforeign", false);
                }
                $arr = $this->array_push_assoc($arr, "reltype", $relType);

                // Is indexed?
                if (1 == 1) {
                    $type = $row['f_table_name'];
                    if (mb_substr($type, 0, 1, 'utf-8') == "_") {
                        $type = "a" . $type;
                    }
                    $url = (App::$param['esHost'] ?: "http://127.0.0.1") . ":9200/{$this->postgisdb}_{$row['f_table_schema']}_{$type}/_mapping/{$type}";
                    //print($url);
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
                    curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
                    ));
                    curl_exec($ch);
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

    /**
     * SQL Group
     * @param string $field
     * @return array
     */
    function getGroupBy($field)
    {
        $arr = [];
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

    /**
     * What is the difference to the above?
     * @param string $field
     * @return array
     */
    public function getGroupByAsArray($field)
    {
        $arr = [];
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

    /**
     * Is it used?
     * @return array
     */
    public function destroy()
    {
        $response = [];
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

    /**
     * Get the UUID of layer. Belongs in Layer class
     * @param $key
     * @return array
     */
    public function getUuid($key)
    {
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

    /**
     * Makes a UPSERT
     * @param $data
     * @param $keyName
     * @return array
     */
    public function updateRecord($data, $keyName)
    {
        $response = [];
        $data = $this->makeArray($data);
        foreach ($data as $set) {
            $set = $this->makeArray($set);
            foreach ($set as $row) {
                if (isset(App::$param["ckan"])) {
                    // Delete package from CKAN if "Update" is set to false
                    if (isset($row->meta->ckan_update) AND $row->meta->ckan_update === false) {
                        $uuid = $this->getUuid($row->_key_);
                        \app\models\Layer::deleteCkan($uuid["uuid"]);
                    } else {
                        $url = "http://127.0.0.1/api/v1/ckan/" . Database::getDb() . "?id=" . $row->_key_ . "&host=" . "http://172.17.0.5";
                        Util::asyncRequest($url);
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
                // If row does not exits, insert instead.
                if ((!$result) && (!$this->PDOerror)) {
                    $sql = "INSERT INTO {$this->table} ({$keyName}," . implode(",", $keyArr) . ") VALUES({$keyValue}," . implode(",", $valueArr) . ")";
                    $this->execQuery($sql, "PDO", "transaction");
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

    /**
     * Creates an array with layers
     * @param null $createKeyFrom
     * @param bool $includePriKey
     * @return array
     */
    public function getColumnsForExtGridAndStore($createKeyFrom = NULL, $includePriKey = false)
    {
        $response = [];
        $fieldsArr = [];
        $fieldsForStore = [];
        $columnsForGrid = [];
        $type = "";
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
                $fieldsArr[] = $key;
        }
        // Start sorting the fields by sort_id
        $isSorted = false;
        $arr = [];
        foreach ($fieldsArr as $value) {
            if (!$isSorted) {
                $isSorted = ($fieldconfArr[$value]->sort_id) ? true : false;
            }
            $arr[] = array($fieldconfArr[$value]->sort_id, $value);
        }
        if ($isSorted) {
            usort($arr, function ($a, $b) {
                return $a[0] - $b[0];
            });
        }
        $fieldsArr = []; // Reset
        foreach ($arr as $value) {
            $fieldsArr[] = $value[1];
        }
        foreach ($fieldsArr as $key) {
            $value = $this->metaData[$key];
            if ($value['type'] != "geometry" && ($key != $this->primeryKey['attname'] || $includePriKey)) {
                if (!preg_match($this->specialChars, $key)) {
                    $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
                    $columnsForGrid[] = array("header" => $key,
                        "dataIndex" => $key,
                        "type" => $value['type'],
                        "typeObj" => $value['typeObj'],
                        "properties" => $fieldconfArr[$key]->properties ?: null,
                        "editable" => ($value['type'] == "bytea" || $key == $this->primeryKey['attname']) ? false : true);
                }
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

    /**
     * Get the schema of the relation. Works only on geometry tables
     * @param bool $includePriKey
     * @return array
     */
    public function getTableStructure($includePriKey = false)
    {
        $response = [];
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
        $response['flowflow'] = $this->workflow; //TODO ?
        return $response;
    }

    /**
     * @param string $_key_
     * @return array
     */
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

    /**
     * @param mixed $data
     * @param string $key
     * @return array
     */
    public function updateColumn($data, $key) // Only geometry tables
    {
        $response = [];
        $this->purgeFieldConf($key); // TODO What?
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->getGeometryColumns($this->table, "fieldconf"));
        foreach ($data as $value) {
            $safeColumn = $this->toAscii($value->column, array(), "_");
            if ($this->metaData[$value->id]["is_nullable"] != $value->is_nullable) {
                $sql = "ALTER TABLE {$this->table} ALTER {$value->column} " . ($value->is_nullable ? "DROP" : "SET") . " NOT NULL";
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

    /**
     * @param mixed $data
     * @param null $whereClause
     * @param $_key_
     * @return array
     */
    public function deleteColumn($data, $whereClause = NULL, $_key_) // Only geometry tables
    {
        $response = [];
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
        $this->purgeFieldConf($_key_);
        return $response;
    }

    /**
     * Add a column. Works on all tables
     * @param array $data
     * @return array
     */
    public function addColumn(array $data)
    {
        $response = [];
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
            default:
                $type = "varchar(255)";
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

    /**
     * @return array
     */
    public function addVersioning()
    {
        $response = [];
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

    /**
     * @return array
     */
    public function removeVersioning()
    {
        $response = [];
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

    /**
     * @return array
     */
    public function addWorkflow()
    {
        $response = [];
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

    /**
     * Is this used?
     */
    public function point2multipoint()
    {
        $sql = "BEGIN;";
        $sql .= "ALTER TABLE {$this->table} DROP CONSTRAINT enforce_geotype_the_geom;";
        $sql .= "UPDATE {$this->table} SET {$this->geomField} = ST_Multi({$this->geomField});";
        $sql .= "UPDATE geometry_columns SET type = 'MULTIPOINT' WHERE f_table_name = '{$this->table}';";
        $sql .= "ALTER TABLE {$this->table} ADD CONSTRAINT enforce_geotype_the_geom CHECK (geometrytype({$this->geomField}) = 'MULTIPOINT'::TEXT OR {$this->geomField} IS NULL);";
        $sql .= "COMMIT";
        $this->execQuery($sql, "PDO", "transaction");
    }

    /**
     * Creates a geometry table
     * @param string $table
     * @param string $type
     * @param int $srid
     * @return array
     */
    public function create($table, $type, $srid = 4326)
    {
        $response = [];
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

    /**
     * @param int $srid
     * @return array
     */
    public function createAsRasterTable($srid = 4326)
    {
        $response = [];
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

    /**
     * @param mixed $notArray
     * @return array
     */
    public function makeArray($notArray)
    {
        if (!is_array($notArray)) {
            $nowArray = array(0 => $notArray);
        } else {
            $nowArray = $notArray; // Input was array. Return it unaltered
        }
        return $nowArray;
    }

    /**
     * @return array
     */
    public function getMapForEs()
    {
        $schema = $this->metaData;
        return $schema;
    }

    /**
     * @param string $field
     * @return array
     */
    public function checkcolumn($field)
    {
        $response = [];
        $res = $this->doesColumnExist($this->table, $field);
        if ($this->PDOerror) {
            $response['success'] = true;
            $response['message'] = $res;
            return $response;
        }
        return $res;
    }

    /**
     * @param string $table
     * @param string $offset
     * @param string $limit
     * @return array
     */
    public function getData($table, $offset, $limit) //
    {
        $response = [];
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
                if (!preg_match($this->specialChars, $key)) {
                    $arr = $this->array_push_assoc($arr, $key, $value);
                }
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

    /**
     * @return array
     */
    public function insertRecord()
    {
        $response = [];
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

    /**
     * @param mixed $data
     * @param string $keyName
     * @return array
     */
    public function deleteRecord($data, $keyName) // All tables
    {
        $response = [];
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

    /**
     * Works on all tables
     * @param string $pkey
     * @return array
     */
    public function getRecordByPri($pkey)
    {
        $response = [];
        foreach ($this->metaData as $key => $value) {
            $fieldsArr[] = $key;
        }
        // We add "" around field names in sql, so sql keywords don't mess things up
        foreach ($fieldsArr as $key => $value) {
            $fieldsArr[$key] = "\"{$value}\"";
        }
        $sql = "SELECT " . implode(",", $fieldsArr);
        foreach ($this->metaData as $key => $arr) {
            if ($arr['type'] == "geometry") {
                // $sql = str_replace("\"{$key}\"", "ST_AsGml(public.ST_Transform(\"" . $key . "\"," . $srs . ")) as " . $key, $sql);
            }
            if ($arr['type'] == "bytea") {
                $sql = str_replace("\"{$key}\"", "encode(\"" . $key . "\",'escape') as " . $key, $sql);
            }
        }
        $sql .= " FROM " . $this->table . " WHERE " . $this->primeryKey['attname'] . "=:pkey";
        //die($sql);
        $res = $this->prepare($sql);
        try {
            $res->execute(array("pkey" => $pkey));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            die($e->getMessage());
        }
        $row = $this->fetchRow($res, "assoc");
        $response['success'] = true;
        $response['data'] = $row;
        return $response;
    }
}