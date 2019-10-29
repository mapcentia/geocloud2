<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

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
    public $geometryColumns;
    protected $InstanceCache;
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
     * @param $table
     * @param bool $temp
     * @param bool $addGeomType
     */
    function __construct($table, $temp = false, $addGeomType = false)
    {
        global $globalInstanceCache;

        parent::__construct();

        $this->InstanceCache = $globalInstanceCache;

        $_schema = $this->explodeTableName($table)["schema"];
        $_table = $this->explodeTableName($table)["table"];
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

        try {
            $this->execQuery($sql);
        } catch (\PDOException $e) {

        }

        if ($this->PDOerror) {
            $this->exits = false;
        } else {
            $this->geometryColumns = $this->getGeometryColumns($this->table, "*");
            $this->metaData = $this->getMetaData($this->table, $temp);
            $this->geomField = $this->geometryColumns["f_geometry_column"];
            $this->geomType = $this->geometryColumns["type"];
            $this->primeryKey = $this->getPrimeryKey($this->table);
            $this->setType();
            $this->exits = true;
            $res = $this->doesColumnExist($this->table, "gc2_version_gid");
            $this->versioning = $res["exists"];
            $res = $this->doesColumnExist($this->table, "gc2_status");
            $this->workflow = $res["exists"];
        }
        $this->sysCols = array("gc2_version_gid", "gc2_version_start_date", "gc2_version_end_date", "gc2_version_uuid", "gc2_version_user");
        $this->specialChars = "/['^£$%&*()}{@#~?><>,|=+¬.]/";
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
        } elseif (preg_match("/timestamp with time zone/", $field['type'])) {
            $field['typeObj'] = array("type" => "timestamptz");
            $field['type'] = "timestamptz";
        } elseif (preg_match("/timestamp/", $field['type'])) {
            $field['typeObj'] = array("type" => "timestamp");
            $field['type'] = "timestamp";
        } elseif (preg_match("/time with time zone/", $field['type'])) {
            $field['typeObj'] = array("type" => "timetz");
            $field['type'] = "timetz";
        } elseif (preg_match("/time/", $field['type'])) {
            $field['typeObj'] = array("type" => "time");
            $field['type'] = "time";
        } elseif (preg_match("/date/", $field['type'])) {
            $field['typeObj'] = array("type" => "date");
            $field['type'] = "timestamp"; // So Extjs renderer becomes string
        } elseif (preg_match("/uuid/", $field['type'])) {
            $field['typeObj'] = array("type" => "uuid");
            $field['type'] = "uuid";
        } elseif (preg_match("/hstore/", $field['type'])) {
            $field['typeObj'] = array("type" => "hstore");
            $field['type'] = "hstore";
        } elseif (preg_match("/json/", $field['type'])) {
            $field['typeObj'] = array("type" => "json");
            $field['type'] = "json";
        } elseif (preg_match("/bytea/", $field['type'])) {
            $field['typeObj'] = array("type" => "bytea");
            $field['type'] = "bytea";
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

        // Check if Es is online
        // =====================
        $esOnline = false;
        $split = explode(":", App::$param['esHost'] ?: "http://127.0.0.1");
        if (!empty($split[2])) {
            $port = $split[2];
        } else {
            $port = "9200";
        }
        $esUrl = $split[0] . ":" . $split[1] . ":" . $port;
        $ch = curl_init($esUrl);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode == "200") {
            $esOnline = true;
        }

        while ($row = $this->fetchRow($result, "assoc")) {
            $privileges = json_decode($row["privileges"]);
            $arr = [];
            $prop = !empty($_SESSION['usergroup']) ? $_SESSION['usergroup'] : !empty($_SESSION['screen_name']) ? $_SESSION['screen_name'] : null;
            if (empty($_SESSION["subuser"]) || (!empty($_SESSION["subuser"]) && $_SESSION['screen_name'] == Connection::$param['postgisschema']) || (!empty($_SESSION["subuser"]) && !empty($privileges->$prop) && $privileges->$prop != "none")) {
                $relType = "t"; // Default
                foreach ($row as $key => $value) {
                    if ($key == "type" && $value == "GEOMETRY") {
                        $def = json_decode($row['def']);
                        if (($def->geotype) && $def->geotype != "Default") {
                            $value = "MULTI" . $def->geotype;
                        }
                    }
                    // Set empty strings to NULL
                    $value = $value == "" ? null : $value;
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

                if ($esOnline) {
                    $type = $row['f_table_name'];
                    if (\mb_substr($type, 0, 1, 'utf-8') == "_") {
                        $type = "a" . $type;
                    }
                    $url = $esUrl . "/{$this->postgisdb}_{$row['f_table_schema']}_{$type}/_mapping/";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
                    curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpcode == "200") {
                        $arr = $this->array_push_assoc($arr, "indexed_in_es", true);
                    } else {
                        $arr = $this->array_push_assoc($arr, "indexed_in_es", false);
                    }
                } else {
                    $arr = $this->array_push_assoc($arr, "indexed_in_es", null);
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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }
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
     * @param $data
     * @param $keyName
     * @param bool $raw
     * @param bool $append
     * @return array
     */
    public function updateRecord($data, $keyName, $raw = false, $append = false)
    {
        try {
            $this->InstanceCache->clear();
        } catch (\Error $exception) {
            error_log($exception->getMessage());
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

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
                        $gc2host = isset(App::$param["ckan"]["gc2host"]) ? App::$param["ckan"]["gc2host"] : App::$param["host"];
                        $url = "http://127.0.0.1/api/v1/ckan/" . Database::getDb() . "?id=" . $row->_key_ . "&host=" . $gc2host;
                        Util::asyncRequest($url);
                    }
                }

                // Get key value
                foreach ($row as $key => $value) {
                    if ($key == $keyName) {
                        $pKeyValue = $value;
                    }
                }

                foreach ($row as $key => $value) {
                    if ($value === false) {
                        $value = null;
                    }
                    if ($this->table == "settings.geometry_columns_join") {
                        if ($key == "editable" || $key == "skipconflict") {
                            $value = $value ?: "0";
                        }
                        if ($key == "tags") {
                            $value = $value ?: [];
                            if (!$raw) {
                                $rec = json_decode($this->getRecordByPri($pKeyValue)["data"]["tags"], true) ?: [];

                                if ($append) {
                                    $value = array_merge($rec, $value);
                                }

                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            }
                        }
                        // If Meta when update the existing object, so not changed values persist
                        if ($key == "meta") {
                            $value = $value ?: "null";
                            if ($raw == false) {
                                $rec = json_decode($this->getRecordByPri($pKeyValue)["data"]["meta"], true);

                                foreach ($value as $fKey => $fValue) {
                                    $rec[$fKey] = $fValue;
                                }
                                $value = json_encode($rec, JSON_UNESCAPED_UNICODE);
                                $stripSlashes = false;
                            }

                        } else {
                            if (is_object($value) || is_array($value)) {
                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                $stripSlashes = false;
                            } else {
                                $stripSlashes = true;
                            }
                        }
                    } else {
                        $stripSlashes = true;
                    }

                    $value = $this->db->quote($value);

                    // TODO why are we stripping slashes?
                    if ($stripSlashes) {
                        $value = stripcslashes($value);
                    }

                    if ($key != $keyName) {
                        $pairArr[] = "\"{$key}\"={$value}";
                        $keyArr[] = "\"{$key}\"";
                        $valueArr[] = $value;
                    } else {
                        $where = "\"{$key}\"={$value}";
                        $keyValue = $value;
                    }
                }

                $sql = "UPDATE " . $this->doubleQuoteQualifiedName($this->table) . " SET ";
                $sql .= implode(",", $pairArr);
                $sql .= " WHERE {$where}";
                $result = $this->execQuery($sql, "PDO", "transaction");

                // If row does not exits, insert instead.
                // ======================================

                if ((!$result) && (!isset($this->PDOerror)) && (!$this->PDOerror)) {
                    $sql = "INSERT INTO " . $this->doubleQuoteQualifiedName($this->table) . " ({$keyName}," . implode(",", $keyArr) . ") VALUES({$keyValue}," . implode(",", $valueArr) . ")";

                    $this->execQuery($sql, "PDO", "transaction");
                    $response['operation'] = "Row inserted";
                }
                if (!isset($this->PDOerror) || !$this->PDOerror) {
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
        $fieldconfArr = (array)json_decode($this->geometryColumns["fieldconf"]);
        foreach ($fieldconfArr as $key => $value) {
            if ($value->properties == "*") {
                $table = new \app\models\Table($this->table);
                $distinctValues = $table->getGroupByAsArray($key);
                $fieldconfArr[$key]->properties = json_encode($distinctValues["data"], JSON_NUMERIC_CHECK, JSON_UNESCAPED_UNICODE);
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
            if (isset($fieldconfArr[$value]) && is_object($fieldconfArr[$value])) {
                if (!$isSorted) {
                    $isSorted = $fieldconfArr[$value]->sort_id ? true : false;
                }
            } else {
                $fieldconfArr[$value] = new \stdClass();
                $fieldconfArr[$value]->sort_id = false;
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
                        "properties" => isset($fieldconfArr[$key]->properties) ? $fieldconfArr[$key]->properties : null,
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
        $fieldconfArr = (array)json_decode($this->geometryColumns["fieldconf"]);
        if (!$this->metaData) {
            $response['data'] = array();
        }
        foreach ($this->metaData as $key => $value) {
            if ($key != $this->primeryKey['attname'] || $includePriKey == true) {
                $arr = $this->array_push_assoc($arr, "id", $key);
                $arr = $this->array_push_assoc($arr, "column", $key);
                $arr = $this->array_push_assoc($arr, "sort_id", !empty($fieldconfArr[$key]->sort_id) ? (int)$fieldconfArr[$key]->sort_id : 0);
                $arr = $this->array_push_assoc($arr, "querable", !empty($fieldconfArr[$key]->querable) ? (bool)$fieldconfArr[$key]->querable : false);
                $arr = $this->array_push_assoc($arr, "mouseover", !empty($fieldconfArr[$key]->querable) ? (bool)$fieldconfArr[$key]->querable : false);;
                $arr = $this->array_push_assoc($arr, "filter", !empty($fieldconfArr[$key]->filter) ? (bool)$fieldconfArr[$key]->filter : false);
                $arr = $this->array_push_assoc($arr, "searchable", !empty($fieldconfArr[$key]->searchable) ? (bool)$fieldconfArr[$key]->searchable : false);
                $arr = $this->array_push_assoc($arr, "conflict", !empty($fieldconfArr[$key]->conflict) ? (bool)$fieldconfArr[$key]->conflict : false);
                $arr = $this->array_push_assoc($arr, "alias", !empty($fieldconfArr[$key]->alias) ? $fieldconfArr[$key]->alias : "");
                $arr = $this->array_push_assoc($arr, "link", !empty($fieldconfArr[$key]->link) ? (bool)$fieldconfArr[$key]->link : false);
                $arr = $this->array_push_assoc($arr, "image", !empty($fieldconfArr[$key]->image) ? (bool)$fieldconfArr[$key]->image : false);
                $arr = $this->array_push_assoc($arr, "content", !empty($fieldconfArr[$key]->content) ? $fieldconfArr[$key]->content : null);
                $arr = $this->array_push_assoc($arr, "linkprefix", !empty($fieldconfArr[$key]->linkprefix) ? $fieldconfArr[$key]->linkprefix : null);
                $arr = $this->array_push_assoc($arr, "properties", !empty($fieldconfArr[$key]->properties) ? $fieldconfArr[$key]->properties : null);
                $arr = $this->array_push_assoc($arr, "is_nullable", !empty($value['is_nullable']) ? (bool)$value['is_nullable'] : false);
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
    public function purgeFieldConf($_key_)
    {
        // Set metaData again in case of a column was dropped
        $this->metaData = $this->getMetaData($this->table);
        $this->setType();
        $fieldconfArr = (array)json_decode($this->geometryColumns["fieldconf"]);
        foreach ($fieldconfArr as $key => $value) {
            if (!$this->metaData[$key]) {
                unset($fieldconfArr[$key]);
            }
        }
        $conf['fieldconf'] = json_encode($fieldconfArr, JSON_UNESCAPED_UNICODE);
        $conf['_key_'] = $_key_;
        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf, JSON_UNESCAPED_UNICODE)), "_key_");
        return $res;
    }

    /**
     * @param $data
     * @param $key
     * @return array
     */
    public function updateColumn($data, $key) // Only geometry tables
    {
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        $response = [];
        $this->purgeFieldConf($key); // TODO What?
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->geometryColumns["fieldconf"]);
        foreach ($data as $value) {
            $safeColumn = $value->column;
            if ($this->metaData[$value->id]["is_nullable"] != $value->is_nullable) {
                $sql = "ALTER TABLE " . $this->doubleQuoteQualifiedName($this->table) . " ALTER \"{$value->column}\" " . ($value->is_nullable ? "DROP" : "SET") . " NOT NULL";
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
                if (is_numeric(\mb_substr($safeColumn, 0, 1, 'utf-8'))) {
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
        $conf['fieldconf'] = json_encode($fieldconfArr, JSON_UNESCAPED_UNICODE);
        $conf['_key_'] = $key;

        $geometryColumnsObj = new table("settings.geometry_columns_join");

        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf, JSON_UNESCAPED_UNICODE)), "_key_");
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
            $response['code'] = "406";

        }
        return $response;
    }

    /**
     * @param $data
     * @param null $whereClause
     * @param $_key_
     * @return array
     */
    public function deleteColumn($data, $whereClause = NULL, $_key_) // Only geometry tables
    {
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        $response = [];
        $data = $this->makeArray($data);
        $sql = "";
        $fieldconfArr = (array)json_decode($this->geometryColumns["fieldconf"]);
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
            $conf['fieldconf'] = json_encode($fieldconfArr, JSON_UNESCAPED_UNICODE);
            $conf['f_table_name'] = $this->table;
            $geometryColumnsObj = new table("settings.geometry_columns_join");
            $geometryColumnsObj->updateRecord(json_decode(json_encode($conf, JSON_UNESCAPED_UNICODE)), "f_table_name", $whereClause);
            $response['success'] = true;
            $response['message'] = "Column deleted";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = "406";
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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        $response = [];
        $safeColumn = self::toAscii($data['column'], array(), "_");
        $sql = "";
        if (is_numeric(\mb_substr($safeColumn, 0, 1, 'utf-8'))) {
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
            case "Timestamp":
                $type = "Timestamp";
                break;
            case "Timestamptz":
                $type = "Timestamptz";
                break;
            case "Time":
                $type = "Time";
                break;
            case "Timetz":
                $type = "Timetz";
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
            case "Json":
                $type = "jsonb";
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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        $response = [];
        $this->PDOerror = NULL;
        $table = self::toAscii($table, array(), "_");
        if (is_numeric(\mb_substr($table, 0, 1, 'utf-8'))) {
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
        try {
            $this->InstanceCache->clear();
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
        }

        $response = [];
        $this->PDOerror = NULL;
        $table = $this->tableWithOutSchema;
        //$table = self::toAscii($table, array(), "_");
        if (is_numeric(mb_substr($table, 0, 1, 'utf-8'))) {
            $table = "_" . $table;
        }
        $sql = "CREATE TABLE \"{$this->postgisschema}\".\"{$table}\"(rast raster);";
        $this->execQuery($sql, "PDO", "transaction");
        $sql = "INSERT INTO \"{$this->postgisschema}\".\"{$table}\"(rast)
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
    public function getRecordByPri($pkey): array
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

    public function getDependTree(): array
    {
        $response = [];
        $response["data"] = [];
        if (!$this->exits) {
            $response['success'] = false;
            $response['message'] = "Relation doesn't exists";
            $response['code'] = 401;
            return $response;
        }

        $sql = "
            WITH RECURSIVE dep_recursive AS (

                -- Recursion: Initial Query
                SELECT
                    0 AS \"level\",
                    :relName AS \"dep_name\",   --  <- define dependent object HERE
                    '' AS \"dep_table\",
                    '' AS \"dep_type\",
                    '' AS \"ref_name\",
                    '' AS \"ref_type\"
            
                UNION ALL
            
                -- Recursive Query
                SELECT
                    level + 1 AS \"level\",
                    depedencies.dep_name,
                    depedencies.dep_table,
                    depedencies.dep_type,
                    depedencies.ref_name,
                    depedencies.ref_type
                FROM (
            
                    -- This function defines the type of any pg_class object
                    WITH classType AS (
                        SELECT
                            oid,
                            CASE relkind
                                WHEN 'r' THEN 'TABLE'::text
                                WHEN 'i' THEN 'INDEX'::text
                                WHEN 'S' THEN 'SEQUENCE'::text
                                WHEN 'v' THEN 'VIEW'::text
                                WHEN 'c' THEN 'TYPE'::text      -- note: COMPOSITE type
                                WHEN 't' THEN 'TABLE'::text     -- note: TOAST table
                                WHEN 'm' THEN 'MATERIALIZED VIEW'::text
                            END AS \"type\"
                        FROM pg_class
                    )
            
                    -- Note: In pg_depend, the triple (classid,objid,objsubid) describes some object that depends
                    -- on the object described by the tuple (refclassid,refobjid).
                    -- So to drop the depending object, the referenced object (refclassid,refobjid) must be dropped first
                    SELECT DISTINCT
                        -- dep_name: Name of dependent object
                        CASE classid
                            WHEN 'pg_class'::regclass THEN objid::regclass::text
                            WHEN 'pg_type'::regclass THEN objid::regtype::text
                            WHEN 'pg_proc'::regclass THEN objid::regprocedure::text
                            WHEN 'pg_constraint'::regclass THEN (SELECT conname FROM pg_constraint WHERE OID = objid)
                            WHEN 'pg_attrdef'::regclass THEN 'default'
                            WHEN 'pg_rewrite'::regclass THEN (SELECT ev_class::regclass::text FROM pg_rewrite WHERE OID = objid)
                            WHEN 'pg_trigger'::regclass THEN (SELECT tgname FROM pg_trigger WHERE OID = objid)
                            ELSE objid::text
                        END AS \"dep_name\",
                        -- dep_table: Name of the table that is associated with the dependent object (for default values, triggers, rewrite rules)
                        CASE classid
                            WHEN 'pg_constraint'::regclass THEN (SELECT conrelid::regclass::text FROM pg_constraint WHERE OID = objid)
                            WHEN 'pg_attrdef'::regclass THEN (SELECT adrelid::regclass::text FROM pg_attrdef WHERE OID = objid)
                            WHEN 'pg_trigger'::regclass THEN (SELECT tgrelid::regclass::text FROM pg_trigger WHERE OID = objid)
                            ELSE ''
                        END AS \"dep_table\",
                        -- dep_type: Type of the dependent object (TABLE, FUNCTION, VIEW, TYPE, TRIGGER, ...)
                        CASE classid
                            WHEN 'pg_class'::regclass THEN (SELECT TYPE FROM classType WHERE OID = objid)
                            WHEN 'pg_type'::regclass THEN 'TYPE'
                            WHEN 'pg_proc'::regclass THEN 'FUNCTION'
                            WHEN 'pg_constraint'::regclass THEN 'TABLE CONSTRAINT'
                            WHEN 'pg_attrdef'::regclass THEN 'TABLE DEFAULT'
                            WHEN 'pg_rewrite'::regclass THEN (SELECT TYPE FROM classType WHERE OID = (SELECT ev_class FROM pg_rewrite WHERE OID = objid))
                            WHEN 'pg_trigger'::regclass THEN 'TRIGGER'
                            ELSE objid::text
                        END AS \"dep_type\",
                        -- ref_name: Name of referenced object (the object that depends on the dependent object)
                        CASE refclassid
                            WHEN 'pg_class'::regclass THEN refobjid::regclass::text
                            WHEN 'pg_type'::regclass THEN refobjid::regtype::text
                            WHEN 'pg_proc'::regclass THEN refobjid::regprocedure::text
                            ELSE refobjid::text
                        END AS \"ref_name\",
                        -- ref_type: Type of the referenced object (TABLE, FUNCTION, VIEW, TYPE, TRIGGER, ...)
                        CASE refclassid
                            WHEN 'pg_class'::regclass THEN (SELECT TYPE FROM classType WHERE OID = refobjid)
                            WHEN 'pg_type'::regclass THEN 'TYPE'
                            WHEN 'pg_proc'::regclass THEN 'FUNCTION'
                            ELSE refobjid::text
                        END AS \"ref_type\",
                        -- dependency type: Only 'normal' dependencies are relevant for DROP statements
                        CASE deptype
                            WHEN 'n' THEN 'normal'
                            WHEN 'a' THEN 'automatic'
                            WHEN 'i' THEN 'internal'
                            WHEN 'e' THEN 'extension'
                            WHEN 'p' THEN 'pinned'
                        END AS \"dependency type\"
                    FROM pg_catalog.pg_depend
                    WHERE deptype = 'n'                 -- look at normal dependencies only
                    AND refclassid NOT IN (2615, 2612)  -- schema and language are ignored as dependencies
            
                ) depedencies
                -- Recursion: Join with results of last query, search for dependencies recursively
                JOIN dep_recursive ON (dep_recursive.dep_name = depedencies.ref_name)
                WHERE depedencies.ref_name NOT IN(depedencies.dep_name, depedencies.dep_table) -- no self-references
            
            )
            
            -- Select and filter the results of the recursive query
            SELECT
                MAX(level) AS \"level\",          -- drop highest level first, so no other objects depend on it
                dep_name,                       -- the object to drop
                MIN(dep_table) AS \"dep_table\",  -- the table that is associated with this object (constraints, triggers)
                MIN(dep_type) AS \"dep_type\",    -- the type of this object
                string_agg(ref_name, ', ') AS \"ref_names\",   -- list of objects that depend on this (just FYI)
                string_agg(ref_type, ', ') AS \"ref_types\",   -- list of their respective types (just FYI)
                CASE MIN(dep_type)
                  WHEN 'VIEW' THEN (SELECT definition FROM pg_views WHERE schemaname = split_part(dep_name, '.', 1) AND viewname = split_part(dep_name, '.', 2))
                  WHEN 'MATERIALIZED VIEW' THEN (SELECT definition FROM pg_matviews WHERE schemaname = split_part(dep_name, '.', 1) AND matviewname = split_part(dep_name, '.', 2))
                END AS \"definition\"
            FROM dep_recursive
            --  WHERE level > 0                  -- ignore the initial object (level 0)
            GROUP BY dep_name               -- ignore multiple references to dependent objects, dropping them once is enough
            ORDER BY level, dep_name;   -- level descending: deepest dependency first
        ";

        $res = $this->prepare($sql);

        // If rel is in public, when don't use schema qualified name
        $relName = explode(".", $this->table)[0] == "public" ? explode(".", $this->table)[1] : $this->table;

        try {
            $res->execute(["relName" => $relName]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $response["data"][] = $row;
        }

        $response['success'] = true;
        return $response;

    }
}