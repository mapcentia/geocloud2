<?php
namespace app\models;

class Layer extends \app\models\Table
{
    function __construct()
    {
        parent::__construct("settings.geometry_columns_view");
    }

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
    }

    function getAll($schema = false, $auth)
    {
        $where = ($auth) ?
            "(authentication<>'foo')" :
            "(authentication='Write' OR authentication='None')";
        if ($schema) {
            $sql = "SELECT * FROM settings.geometry_columns_view WHERE {$where} AND _key_ LIKE :schema ORDER BY sort_id";
        } else {
            $sql = "SELECT * FROM settings.geometry_columns_view WHERE {$where} ORDER BY sort_id";
        }
        $sql .= (\app\conf\App::$param["reverseLayerOrder"]) ? " DESC" : " ASC";
        $res = $this->prepare($sql);
        try {
            if ($schema) {
                $res->execute(array("schema" => $schema . "%"));
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
            $versioning = true;
            $sql = "SELECT gc2_version_gid,gc2_version_start_date,gc2_version_end_date,gc2_version_uuid,gc2_version_user FROM \"{$row['f_table_schema']}\".\"{$row['f_table_name']}\" LIMIT 1";
            $resVersioning = $this->prepare($sql);
            try {
                $resVersioning->execute();
            } catch (\PDOException $e) {
                $versioning = false;
            }
            foreach ($row as $key => $value) {
                if ($key == "type" && $value == "GEOMETRY") {
                    $def = json_decode($row['def']);
                    if (($def->geotype) && $def->geotype != "Default") {
                        $value = "MULTI" . $def->geotype;
                    }
                }
                $value = ($key == "layergroup" && (!$value)) ? "Default group" : $value;
                $arr = $this->array_push_assoc($arr, $key, $value);
                $arr = $this->array_push_assoc($arr, "pkey", $primeryKey['attname']);
                $arr = $this->array_push_assoc($arr, "versioning", $versioning);
            }
            if ($row["authentication"] == "Read/write"){
                $privileges = (array)json_decode($row["privileges"]);
                if ($_SESSION['subuser'] == false || ($_SESSION['subuser'] != false && $privileges[$_SESSION['subuser']] != "none" && $privileges[$_SESSION['subuser']] != false)) {
                    $response['data'][] = $arr;
                }
            }
            else {
                $response['data'][] = $arr;
            }

        }
        $response['data'] = ($response['data']) ? : array();
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "geometry_columns_view fetched";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 401;
        }
        return $response;
    }

    function getSchemas() // All tables
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

    function getCartoMobileSettings($_key_) // Only geometry tables
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

    function updateCartoMobileSettings($data, $_key_)
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
        if ((!$this->PDOerror) || (!$sql)) {
            $response['success'] = true;
            $response['message'] = "Column renamed";
        } else {
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

    public function setSchema($tables, $schema)
    {
        $this->begin();
        foreach ($tables as $table) {
            $bits = explode(".", $table);

            $query = "SELECT * FROM geometry_columns WHERE f_table_schema='{$bits[0]}' AND f_table_name='{$bits[1]}'";
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
            $query = "DROP TABLE \"{$bits[0]}\".\"{$bits[1]}\" CASCADE";
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
        $response['message'] = sizeof($tables) . " tables deleted";
        return $response;
    }

    public function getPrivileges($_key_)
    {
        $privileges = (array)json_decode($this->getValueFromKey($_key_, "privileges"));

        foreach ($_SESSION['subusers'] as $subuser) {
            $privileges[$subuser] = ($privileges[$subuser]) ? : "none";
            $response['data'][] = array("subuser" => $subuser, "privileges" => $privileges[$subuser]);
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

}
