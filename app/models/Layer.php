<?php
namespace app\models;

use \app\conf\App;
use \app\models\Database;


class Layer extends \app\models\Table
{
    function __construct()
    {
        parent::__construct("settings.geometry_columns_view");
    }

    /**
     * @param string $_key_
     * @param string $column
     * @return mixed
     */
    // Secure. Using nu user input.
    public function getValueFromKey($_key_, $column)
    {
        $rows = $this->getRecords();
        $rows = $rows['data'];
        foreach ($rows as $row) {
            foreach ($row as $field => $value) {
                if ($field == "_key_" && $value == $_key_) {
                    return ($row[$column]);
                }
            }
        }
        return false;
    }

    /**
     * @param bool $schema
     * @param bool $layer
     * @param bool $auth
     * @param bool $includeExtent
     * @param bool $parse
     * @param bool $es
     * @return mixed
     */
    // Secure. Using prepared statements.
    public function getAll($schema = false, $layer = false, $auth, $includeExtent = false, $parse = false, $es = false)
    {
        // TODO use the function settings.getColumns() instead
        $where = ($auth) ?
            "(authentication<>'foo' OR authentication is NULL)" :
            "(authentication='Write' OR authentication='None')";
        $case = "CASE WHEN ((layergroup = '' OR layergroup IS NULL) AND baselayer != true) THEN 9999999 else sort_id END";
        if ($schema) {
            $ids = explode(",", $schema);
            $qMarks = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "SELECT *, ({$case}) as sort FROM settings.geometry_columns_view WHERE {$where} AND f_table_schema in ($qMarks) ORDER BY sort";
        } elseif ($layer) {
            $sql = "SELECT *, ({$case}) as sort FROM settings.geometry_columns_view WHERE {$where} AND f_table_schema = :sSchema AND f_table_name = :sName ORDER BY sort";
        } else {
            $sql = "SELECT *, ({$case}) as sort FROM settings.geometry_columns_view WHERE {$where} ORDER BY sort";
        }
        $sql .= (\app\conf\App::$param["reverseLayerOrder"]) ? " DESC" : " ASC";
        $res = $this->prepare($sql);
        try {
            if ($schema) {
                $res->execute($ids);
            } elseif ($layer) {
                $split = explode(".", $layer);
                $res->execute(array("sSchema" => $split[0], "sName" => $split[1]));
            } else {
                $res->execute();
            }
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $arr = array();
            $primeryKey = $this->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");
            $resVersioning = $this->doesColumnExist("{$row['f_table_schema']}.{$row['f_table_name']}", "gc2_version_gid");
            $versioning = $resVersioning["exists"];

            if ($row['type'] != "RASTER" && $includeExtent == true) {

                $srsTmp = "900913";
                $sql = "SELECT ST_Xmin(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS xmin,ST_Xmax(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS xmax, ST_Ymin(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS ymin,ST_Ymax(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS ymax  FROM {$row['f_table_schema']}.{$row['f_table_name']}";
                $resExtent = $this->prepare($sql);
                try {
                    $resExtent->execute();
                } catch (\PDOException $e) {
                    //print_r($e);
                }
                $extent = $this->fetchRow($resExtent, "assoc");
            }
            foreach ($row as $key => $value) {
                if ($key == "type" && $value == "GEOMETRY") {
                    $def = json_decode($row['def']);
                    if (isset($def->geotype) && $def->geotype != "Default") {
                        $value = "MULTI" . $def->geotype;
                    }
                }
                if ($key == "layergroup" && (!$value)) {
                    $value = "<font color='red'>[Ungrouped]</font>";
                }
                if ($key == "fieldconf" && ($value)) {
                    $obj = json_decode($value, true);
                    if (is_array($obj)) {
                        foreach ($obj as $k => $val) {
                            if ($obj[$k]["properties"] == "*") {
                                $table = new \app\models\Table($row['f_table_schema'] . "." . $row['f_table_name']);
                                $distinctValues = $table->getGroupByAsArray($k);
                                $obj[$k]["properties"] = json_encode($distinctValues["data"], JSON_NUMERIC_CHECK);
                            }
                        }
                        $value = json_encode($obj);
                    } else {
                        $value = null;
                    }
                }
                if ($parse) {
                    if (
                        ($key == "fieldconf" ||
                            $key == "def" ||
                            $key == "class" ||
                            $key == "classwizard"
                        ) && ($value)
                    ) {
                        $value = json_decode($value);
                    }
                }
                $arr = $this->array_push_assoc($arr, $key, $value);

            }
            $arr = $this->array_push_assoc($arr, "pkey", $primeryKey['attname']);

            $arr = $this->array_push_assoc($arr, "versioning", $versioning);

            if ($includeExtent == true) {
                $arr = $this->array_push_assoc($arr, "extent", $extent);
            }
            // Is indexed?
            if ($es) {
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
                curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpcode == "200") {
                    $arr = $this->array_push_assoc($arr, "indexed_in_es", true);
                    // Get mapping
                    $url = (App::$param['esHost'] ?: "http://127.0.0.1") . ":9200/{$this->postgisdb}_{$row['f_table_schema']}/_mapping/{$type}/";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    $output = curl_exec($ch);
                    curl_close($ch);
                    if ($parse) {
                        $output = json_decode($output, true);
                    }
                    $arr = $this->array_push_assoc($arr, "es_mapping", $output);

                    // Set type name
                    if (mb_substr($type, 0, 1, 'utf-8') == "_") {
                        $typeName = "a" . $type;
                    } else {
                        $typeName = $type;
                    }
                    $arr = $this->array_push_assoc($arr, "es_type_name", $typeName);


                } else {
                    $arr = $this->array_push_assoc($arr, "indexed_in_es", false);
                }
            }
            // Session is sub-user we always check privileges
            if (isset($_SESSION) && $_SESSION['subuser']) {
                $privileges = (array)json_decode($row["privileges"]);
                if ($_SESSION['subuser'] == false || ($_SESSION['subuser'] != false && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != "none" && $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']] != false)) {
                    $response['data'][] = $arr;
                } elseif ($schema != false && $_SESSION['subuser'] == $schema) {
                    $response['data'][] = $arr;
                }
            } else {
                $response['data'][] = $arr;
            }

        }
        $response['data'] = isset($response['data']) ? $response['data'] : array();
        if (!isset($this->PDOerror)) {
            $response['auth'] = $auth ?: false;
            $response['success'] = true;
            $response['message'] = "geometry_columns_view fetched";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 401;
        }
        return $response;
    }

    /**
     * @return array
     */
    // Secure. Using nu user input.
    public function getSchemas() // All tables
    {
        $sql = "SELECT f_table_schema AS schemas FROM settings.geometry_columns_view WHERE f_table_schema IS NOT NULL AND f_table_schema!='sqlapi' GROUP BY f_table_schema";
        $result = $this->execQuery($sql);
        if (!$this->PDOerror) {
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr[] = array("schema" => $row["schemas"], "desc" => null);
            }
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    public function getCartoMobileSettings($_key_) // Only geometry tables
    {
        $response['success'] = true;
        $response['message'] = "Structure loaded";
        $arr = array();
        $keySplit = explode(".", $_key_);
        $table = new Table($keySplit[0] . "." . $keySplit[1]);
        $cartomobileArr = (array)json_decode($this->getValueFromKey($_key_, "cartomobile"));
        foreach ($table->metaData as $key => $value) {
            if ($value['type'] != "geometry" && $key != $table->primeryKey['attname']) {
                $arr = $this->array_push_assoc($arr, "id", $key);
                $arr = $this->array_push_assoc($arr, "column", $key);
                $arr = $this->array_push_assoc($arr, "available", $cartomobileArr[$key]->available);
                $arr = $this->array_push_assoc($arr, "cartomobiletype", $cartomobileArr[$key]->cartomobiletype);
                $arr = $this->array_push_assoc($arr, "properties", $cartomobileArr[$key]->properties);
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

    public function updateCartoMobileSettings($data, $_key_)
    {
        $table = new Table("settings.geometry_columns_join");
        $data = $table->makeArray($data);
        $cartomobileArr = (array)json_decode($this->getValueFromKey($_key_, "cartomobile"));
        foreach ($data as $value) {
            $safeColumn = $table->toAscii($value->column, array(), "_");
            if ($value->id != $value->column && ($value->column) && ($value->id)) {
                unset($cartomobileArr[$value->id]);
            }
            $cartomobileArr[$safeColumn] = $value;
        }
        $conf['cartomobile'] = json_encode($cartomobileArr);
        $conf['_key_'] = $_key_;

        $table->updateRecord(json_decode(json_encode($conf)), "_key_");
        //$this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror)) {
            $response['success'] = true;
            $response['message'] = "Column renamed";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    public function getElasticsearchMapping($_key_) // Only geometry tables
    {
        $hasGeom = false;
        $elasticsearch = new \app\models\Elasticsearch();
        $response['success'] = true;
        $response['message'] = "Map loaded";

        $checkForGeom = $this->getMetaData($_key_);
        foreach ($checkForGeom as $key => $value) {
            if ($value["type"] == "geometry") {
                $hasGeom = true;
                break;
            } else {
                $hasGeom = false;
            }
        }
        $arr = array();
        $keySplit = explode(".", $_key_);
        $table = new Table($keySplit[0] . "." . $keySplit[1], false, $hasGeom ?: false); // Add geometry types (or not)
        $elasticsearchArr = (array)json_decode($this->getGeometryColumns($keySplit[0] . "." . $keySplit[1], "elasticsearch"));
        foreach ($table->metaData as $key => $value) {
            $esType = $elasticsearch->mapPg2EsType($value['type'], $value['geom_type'] == "POINT" ? true : false);
            $arr = $this->array_push_assoc($arr, "id", $key);
            $arr = $this->array_push_assoc($arr, "column", $key);
            $arr = $this->array_push_assoc($arr, "elasticsearchtype", $elasticsearchArr[$key]->elasticsearchtype ?: $esType["type"]);
            $arr = $this->array_push_assoc($arr, "format", $elasticsearchArr[$key]->format ?: $esType["format"] ?: "");
            $arr = $this->array_push_assoc($arr, "index", $elasticsearchArr[$key]->index);
            $arr = $this->array_push_assoc($arr, "analyzer", $elasticsearchArr[$key]->analyzer);
            $arr = $this->array_push_assoc($arr, "index_analyzer", $elasticsearchArr[$key]->index_analyzer);
            $arr = $this->array_push_assoc($arr, "search_analyzer", $elasticsearchArr[$key]->search_analyzer);
            $arr = $this->array_push_assoc($arr, "boost", $elasticsearchArr[$key]->boost);
            $arr = $this->array_push_assoc($arr, "null_value", $elasticsearchArr[$key]->null_value);
            if ($value['typeObj']['type'] == "decimal") {
                $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']} ({$value['typeObj']['precision']} {$value['typeObj']['scale']})");
            } else {
                $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']}");
            }
            $response['data'][] = $arr;

        }
        return $response;
    }

    public function updateElasticsearchMapping($data, $_key_)
    {
        $table = new Table("settings.geometry_columns_join");
        $data = $table->makeArray($data);
        $elasticsearchArr = (array)json_decode($this->getValueFromKey($_key_, "elasticsearch"));
        foreach ($data as $value) {
            //$safeColumn = $table->toAscii($value->column, array(), "_");
            $safeColumn = $value->column;
            if ($value->id != $value->column && ($value->column) && ($value->id)) {
                unset($elasticsearchArr[$value->id]);
            }
            $elasticsearchArr[$safeColumn] = $value;
        }
        $conf['elasticsearch'] = json_encode($elasticsearchArr);
        $conf['_key_'] = $_key_;

        $table->updateRecord(json_decode(json_encode($conf)), "_key_");
        //$this->execQuery($sql, "PDO", "transaction");
        if ((!$this->PDOerror)) {
            $response['success'] = true;
            $response['message'] = "Map updated";
        } else {
            $response['code'] = 400;
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    public function rename($tableName, $data)
    {
        $split = explode(".", $tableName);
        $newName = \app\inc\Model::toAscii($data->name, array(), "_");
        if (is_numeric(mb_substr($newName, 0, 1, 'utf-8'))) {
            $newName = "_" . $newName;
        }
        $this->begin();
        $whereClauseG = "f_table_schema=''{$split[0]}'' AND f_table_name=''{$split[1]}''";
        $whereClauseR = "r_table_schema=''{$split[0]}'' AND r_table_name=''{$split[1]}''";
        $query = "SELECT * FROM settings.getColumns('{$whereClauseG}','{$whereClauseR}') ORDER BY sort_id";
        $res = $this->prepare($query);
        try {
            $res->execute();
            while ($row = $this->fetchRow($res)) {
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '{$row['f_table_schema']}.{$newName}.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                try {
                    $resUpdate->execute();
                } catch (\PDOException $e) {
                    $this->rollback();
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 400;
                    return $response;
                }
            }
            $sql = "ALTER TABLE {$tableName} RENAME TO {$newName}";
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
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "Layer renamed";
        return $response;
    }

    public function setSchema($tables, $schema)
    {
        $this->begin();
        foreach ($tables as $table) {
            $bits = explode(".", $table);
            $whereClauseG = "f_table_schema=''{$bits[0]}'' AND f_table_name=''{$bits[1]}''";
            $whereClauseR = "r_table_schema=''{$bits[0]}'' AND r_table_name=''{$bits[1]}''";
            $query = "SELECT * FROM settings.getColumns('{$whereClauseG}','{$whereClauseR}') ORDER BY sort_id";
            $res = $this->prepare($query);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }
            while ($row = $this->fetchRow($res)) {
                // First delete keys from destination schema if they exists
                $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ = '{$schema}.{$bits[1]}.{$row['f_geometry_column']}'";
                $resDelete = $this->prepare($query);
                try {
                    $resDelete->execute();
                } catch (\PDOException $e) {
                    $this->rollback();
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 400;
                    return $response;
                }
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '{$schema}.{$bits[1]}.{$row['f_geometry_column']}' WHERE _key_ ='{$bits[0]}.{$bits[1]}.{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                try {
                    $resUpdate->execute();
                } catch (\PDOException $e) {
                    $this->rollback();
                    $response['success'] = false;
                    $response['message'] = $e->getMessage();
                    $response['code'] = 400;
                    return $response;
                }
            }
            $query = "ALTER TABLE {$table} SET SCHEMA {$schema}";
            $res = $this->prepare($query);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables moved to {$schema}";
        return $response;
    }

    public function delete($tables)
    {
        $this->begin();
        foreach ($tables as $table) {
            $bits = explode(".", $table);
            $check = $this->isTableOrView($table);
            if (!$check["success"]) {
                $response['success'] = false;
                $response['message'] = $check["message"];
                $response['code'] = 500;
                return $response;
            }
            $type = $check["data"];
            $query = "DROP {$type} \"{$bits[0]}\".\"{$bits[1]}\" CASCADE";

            $res = $this->prepare($query);

            // Delete package from CKAN
            if (isset(App::$param["ckan"])) {
                $uuid = $this->getUuid($table);
                $ckanRes = $this->deleteCkan($uuid["uuid"]);
                $response['ckan_delete'] = $ckanRes["success"];
            }
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables deleted";
        return $response;
    }

    public function getPrivileges($_key_)
    {
        $privileges = (array)json_decode($this->getValueFromKey($_key_, "privileges"));

        foreach ($_SESSION['subusers'] as $subuser) {
            $privileges[$subuser] = ($privileges[$subuser]) ?: "none";
            if ($subuser != \app\conf\Connection::$param['postgisschema']) {
                $response['data'][] = array("subuser" => $subuser, "privileges" => $privileges[$subuser]);
            }
        }

        if (!isset($response['data'])) {
            $response['data'] = array();
        }
        $response['success'] = true;
        $response['message'] = "Privileges fetched";
        return $response;
    }

    public function updatePrivileges($data)
    {
        $data = (array)$data;
        $table = new Table("settings.geometry_columns_join");
        $privilege = (array)json_decode($this->getValueFromKey($data['_key_'], "privileges"));
        $privilege[$data['subuser']] = $data['privileges'];
        $privileges['privileges'] = json_encode($privilege);
        $privileges['_key_'] = $data['_key_'];
        $res = $table->updateRecord(json_decode(json_encode($privileges)), "_key_");
        if ($res['success'] == true) {
            $response['success'] = true;
            $response['message'] = "Privileges updates";
        } else {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 403;
        }
        return $response;
    }

    public function getRoles($_key_)
    {
        $roles = (array)json_decode($this->getValueFromKey($_key_, "roles"));

        foreach ($_SESSION['subusers'] as $subuser) {
            $roles[$subuser] = ($roles[$subuser]) ?: "none";
            if ($subuser != \app\conf\Connection::$param['postgisschema']) {
                $response['data'][] = array("subuser" => $subuser, "roles" => $roles[$subuser]);
            }
        }

        if (!isset($response['data'])) {
            $response['data'] = array();
        }
        $response['success'] = true;
        $response['message'] = "Roles fetched";
        return $response;
    }

    public function updateRoles($data)
    {
        $data = (array)$data;
        $table = new Table("settings.geometry_columns_join");
        $role = (array)json_decode($this->getValueFromKey($data['_key_'], "roles"));
        $role[$data['subuser']] = $data['roles'];
        $roles['roles'] = json_encode($role);
        $roles['_key_'] = $data['_key_'];
        $res = $table->updateRecord(json_decode(json_encode($roles)), "_key_");
        if ($res['success'] == true) {
            $response['success'] = true;
            $response['message'] = "Roles updates";
        } else {
            $response['success'] = false;
            $response['message'] = $res['message'];
            $response['code'] = 403;
        }
        return $response;
    }

    public function getExtent($_key_, $srs = "4326")
    {
        $split = explode(".", $_key_);
        $srsTmp = $srs;
        $sql = "SELECT ST_Xmin(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS xmin,ST_Xmax(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS xmax, ST_Ymin(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS ymin,ST_Ymax(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS ymax  FROM {$split[0]}.{$split[1]}";
        $resExtent = $this->prepare($sql);
        try {
            $resExtent->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e;
            $response['code'] = 403;
            return $response;
        }
        $extent = $this->fetchRow($resExtent, "assoc");
        $response['success'] = true;
        $response['extent'] = $extent;
        return $response;
    }

    public function getEstExtent($_key_, $srs = "4326")
    {
        $split = explode(".", $_key_);
        $sql = "WITH bb AS (SELECT ST_astext(ST_Transform(ST_setsrid(ST_EstimatedExtent('" . $split[0] . "', '" . $split[1] . "', '" . $split[2] . "')," . $srs . ")," . $srs . ")) as geom) ";
        $sql .= "SELECT ST_Xmin(ST_Extent(geom)) AS TXMin,ST_Xmax(ST_Extent(geom)) AS TXMax, ST_Ymin(ST_Extent(geom)) AS TYMin,ST_Ymax(ST_Extent(geom)) AS TYMax  FROM bb";
        $result = $this->prepare($sql);
        try {
            $result->execute();
            $row = $this->fetchRow($result);
            $extent = array("minx" => $row['txmin'], "miny" => $row['tymin'], "maxx" => $row['txmax'], "maxy" => $row['tymax']);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e;
            $response['code'] = 403;
            return $response;
        }
        $response['success'] = true;
        $response['extent'] = $extent;
        return $response;
    }

    public function getCount($_key_)
    {
        $split = explode(".", $_key_);
        $sql = "SELECT count(*) AS count FROM " . $split[0] . "." . $split[1];
        $result = $this->prepare($sql);
        try {
            $result->execute();
            $row = $this->fetchRow($result);
            $count = $row['count'];
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e;
            $response['code'] = 403;
            return $response;
        }
        $response['success'] = true;
        $response['count'] = $count;
        return $response;
    }

    public function copyMeta($to, $from)
    {
        $query = "SELECT * FROM settings.geometry_columns_join WHERE _key_ =:from";
        $res = $this->prepare($query);
        try {
            $res->execute(array("from" => $from));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $booleanFields = array("editable", "baselayer", "tilecache", "not_querable", "single_tile", "enablesqlfilter", "skipconflict");
        $row = $this->fetchRow($res);
        foreach ($row as $k => $v) {
            if (in_array($k, $booleanFields)) {
                $conf[$k] = $v ?: "0";
            } else {
                $conf[$k] = $v;
            }
        }
        //print_r($conf);
        //die();
        $conf['_key_'] = $to;


        $geometryColumnsObj = new table("settings.geometry_columns_join");
        $res = $geometryColumnsObj->updateRecord(json_decode(json_encode($conf)), "_key_");
        if (!$res["success"]) {
            $response['success'] = false;
            $response['message'] = $res["message"];
            $response['code'] = "406";
            return $response;
        }
        return $res;
    }

    public function getRole($schema, $table, $user)
    {
        $sql = "SELECT * FROM settings.getColumns('f_table_schema=''{$schema}'' AND f_table_name=''{$table}''','raster_columns.r_table_schema=''{$schema}'' AND raster_columns.r_table_name=''{$table}''') ORDER BY sort_id";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e;
            $response['code'] = 403;
            return $response;
        }
        $row = $this->fetchRow($res, "assoc");
        $response['success'] = true;
        $response['data'] = json_decode($row['roles'], true);
        return $response;
    }

    public function registerWorkflow($schema, $_table, $gid, $status, $user)
    {
        $sql = "INSERT INTO settings.workflow(schema, _table, gid, status, user) VALUES(':schema',':_table',:gid,:status,':user')";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("schema" => $schema, "_table" => $_table, "gid" => $gid, "status" => $status, "user" => $user,));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e;
            $response['code'] = 403;
            return $response;
        }
        $response['success'] = true;
        return $response;
    }

    public function updateCkan($key, $gc2Host)
    {
        $gc2Host = $gc2Host ?: \app\conf\App::$param["host"];
        $metaConfig = \app\conf\App::$param["metaConfig"];
        $ckanApiUrl = App::$param["ckan"]["host"];

        $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_ =:key";
        $res = $this->prepare($sql);
        try {

            $res->execute(array("key" => $key));

        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res, "assoc");

        //$id = Database::getDb() . "-" . str_replace(".", "-", $row["_key_"]);
        $id = $row["uuid"];

        // Check if dataset already exists
        $ch = curl_init($ckanApiUrl . "/api/3/action/package_show?id=" . $id);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        $datasetExists = ($info["http_code"] == 200) ? true : false;
        curl_close($ch);

        // Create the CKAN package object
        $arr = array();

        if ($row["tags"]) {
            foreach (json_decode($row["tags"]) as $v) {
                $arr[] = array("name" => $v);
            }
        }

        // Get the default "ckan_org_id" value
        $ckanOrgIdDefault = null;
        foreach ($metaConfig as $value) {
            if ($value["name"] == "ckan_org_id") {
                $ckanOrgIdDefault = $value["default"];
            }
        }

        // Get the default "update" flag
        $updateDefault = null;
        foreach ($metaConfig as $value) {
            if ($value["name"] == "ckan_update") {
                $updateDefault = $value["default"];
            }
        }

        if (isset(json_decode($row["meta"], true)["ckan_update"])) {
            $update = json_decode($row["meta"], true)["ckan_update"];
        } else {
            $update = $updateDefault;
        }

        if (!$update) {
            $response['success'] = false;
            $response['message'] = "Dataset not flagged for CKAN";
            $response['code'] = 401;
            return $response;
        }

        $ownerOrg = (json_decode($row["meta"], true)["ckan_org_id"]) ?: $ckanOrgIdDefault;

        $widgetUrl = $gc2Host . "/apps/widgets/gc2map/" . Database::getDb() . "/" . $row["f_table_schema"] . "/" . App::$param["ckan"]["widgetState"] . "/" . $row["f_table_schema"] . "." . $row["f_table_name"];
        $response = array();
        $response["id"] = $id;
        $response["name"] = $id;
        $response["title"] = $row["f_table_title"];
        $response["notes"] = $row["f_table_abstract"];
        if (sizeof($arr) > 0) $response["tags"] = $arr;
        $response["owner_org"] = $ownerOrg;
        $response["resources"] = array(
            array(
                "id" => $id . "-html",
                "name" => "Web widget",
                "description" => "Html side til indlejring eller link",
                "format" => "html",
                "url" => $widgetUrl,
            ),
            array(
                "id" => $id . "-geojson",
                "name" => "GeoJSON",
                "description" => "JSON format",
                "format" => "geojson",
                "url" => $gc2Host . "/api/v1/sql/" . Database::getDb() . "?q=SELECT * FROM " . $row["f_table_schema"] . "." . $row["f_table_name"] . " LIMIT 100&srs=4326"
            ),
            array(
                "id" => $id . "-wms",
                "name" => "WMS",
                "description" => "OGC WMS op til version 1.3.0",
                "format" => "wms",
                "url" => $gc2Host . "/ows/" . Database::getDb() . "/" . $row["f_table_schema"] . "?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetCapabilities"
            ),
            array(
                "id" => $id . "-wfs",
                "name" => "WFS",
                "description" => "OGC WFS op til version 2.0.0",
                "format" => "wfs",
                "url" => $gc2Host . "/ows/" . Database::getDb() . "/" . $row["f_table_schema"] . "?SERVICE=WFS&VERSION=2.0&REQUEST=GetCapabilities"
            ),
            array(
                "id" => $id . "_wmts",
                "name" => "WMTS",
                "description" => "OGC WMTS version 1.0",
                "format" => "wmts",
                "url" => $gc2Host . "/mapcache/" . Database::getDb() . "/wmts?SERVICE=WMTS&REQUEST=GetCapabilities"
            ),
            array(
                "id" => $id . "-xyz",
                "name" => "XYZ",
                "description" => "Google XYZ service",
                "format" => "xyz",
                "url" => $gc2Host . "/mapcache/" . Database::getDb() . "/gmaps/" . $row["f_table_schema"] . "." . $row["f_table_name"] . "@g"
            )
        );

        // Get extent
        $extent = $this->getEstExtent($key);
        $extentStr = "";
        if ($extent["success"]) {
            $extentStr = "minx: " . $extent["extent"]["minx"] . ", miny: " . $extent["extent"]["miny"] . ", maxx: " . $extent["extent"]["maxx"] . ", maxy: " . $extent["extent"]["maxy"];
        }

        // Get count
        $count = $this->getCount($key);
        $countStr = "";
        if ($count["success"]) {
            $countStr = $count["count"];
        }

        $response["extras"] = array(
            array(
                "key" => "Extent",
                "value" => $extentStr
            ),
            array(
                "key" => "Antal objekter",
                "value" => $countStr
            ),
            array(
                "key" => "Data oprettet",
                "value" => $row["created"]
            ),
        );

        $requestJson = json_encode($response);
        $ch = curl_init($ckanApiUrl . "/api/3/action/package_" . ($datasetExists ? "patch" : "create"));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($requestJson),
                'Authorization: ' . App::$param["ckan"]["apiKey"]
            )
        );
        $packageBuffer = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($info["http_code"] != 200) {
            $response['json'] = $packageBuffer;
            return $response;
        } else {
            // Get list of resource views, so we can see if the views already exists
            $ch = curl_init($ckanApiUrl . "/api/3/action/resource_view_list?id=" . $id . "-html");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $viewArr = json_decode(curl_exec($ch), true);
            curl_close($ch);

            // Set flags
            $webViewId1 = (isset($viewArr["result"][0]["id"])) ? $viewArr["result"][0]["id"] : null;

            // Webpage view for widget
            $response = array();
            if ($webViewId1) {
                $response["id"] = $webViewId1;
            }
            $response["resource_id"] = $id . "-html";
            $response["title"] = $row["f_table_title"] ?: $row["f_table_name"];
            $response["description"] = $row["f_table_abstract"];
            $response["view_type"] = "webpage_view";
            $response["page_url"] = $widgetUrl;
            $requestJson = json_encode($response);
            $ch = curl_init($ckanApiUrl . "/api/3/action/resource_view_" . ($webViewId1 ? "update" : "create"));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestJson);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($requestJson),
                    'Authorization: ' . App::$param["ckan"]["apiKey"]
                )
            );
            $buffer = curl_exec($ch);
            curl_close($ch);


            $response['json'] = $packageBuffer;
            return $response;
        }
    }


    public function deleteCkan($key)
    {
        $ckanApiUrl = App::$param["ckan"]["host"];
        $requestJson = json_encode(array("id" => $key));
        $ch = curl_init($ckanApiUrl . "/api/3/action/package_delete");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($requestJson),
                'Authorization: ' . App::$param["ckan"]["apiKey"]
            )
        );
        $buffer = curl_exec($ch);
        curl_close($ch);
        return json_decode($buffer, true);
    }

    public function getTags()
    {

        $sql = "SELECT tags FROM settings.geometry_columns_view";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $arr = array();
        while ($row = $this->fetchRow($res, "assoc")) {
            if ($row["tags"]) {
                $arr[] = implode(",", json_decode($row["tags"]));
            }
        }
        $arr = array_unique(explode(",", implode(",", $arr)));
        $res = array();
        foreach ($arr as $v) {
            $res[]["tag"] = $v;
        }
        $response["data"] = $res;
        $response["success"] = true;
        return $response;
    }
}
