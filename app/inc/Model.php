<?php
namespace app\inc;

use PDO;
use app\conf\Connection;

class Model
{
    var $postgishost;
    var $postgisuser;
    var $postgisdb;
    var $postgispw;
    var $connectString;
    var $PDOerror;
    var $db;
    var $postgisschema;
    var $connectionFailed;

    function __construct()
    {
        $this->postgishost = Connection::$param['postgishost'];
        $this->postgisport = Connection::$param['postgisport'];
        $this->postgisuser = Connection::$param['postgisuser'];
        $this->postgisdb = Connection::$param['postgisdb'];
        $this->postgispw = Connection::$param['postgispw'];
        $this->postgisschema = Connection::$param['postgisschema'];
    }

    function fetchRow($result, $result_type = "assoc")
    {
        if ($this->PDOerror) {
            //throw new \Exception($this->PDOerror[0]);
        }
        switch ($result_type) {
            case "assoc" :
                $row = $result->fetch(PDO::FETCH_ASSOC);
                break;
            case "both" :
                break;
        }
        return ($row);
    }

    function fetchAll($result, $result_type = "both")
    {
        if ($this->PDOerror) {
            throw new \Exception($this->PDOerror[0]);
        }
        switch ($result_type) {
            case "assoc" :
                $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                break;
            case "both" :
                $rows = $result->fetchAll();
                break;
        }

        return ($rows);
    }

    function numRows($result)
    {
        //$num=pg_numrows($result);
        $num = sizeof($result);
        return ($num);
    }

    function free($result)
    {
        //$test=pg_free_result($result);
        $result = NULL;
        //PDO
    }

    function getPrimeryKey($table)
    {
        unset($this->PDOerror);
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '{$table}'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
        $result = $this->execQuery($query);

        if ($this->PDOerror) {
            return NULL;
        }
        if (!is_array($row = $this->fetchRow($result))) { // If $table is view we bet on there is a gid field
            return array("attname" => "gid");
        } else {
            return ($row);
        }
    }

    function begin()
    {
        $this->db->beginTransaction();
    }

    function commit()
    {
        $this->db->commit();
        $this->db = NULL;
    }

    function rollback()
    {
        $this->db->rollback();
        $this->db = NULL;
    }

    function prepare($sql)
    {
        if (!$this->db) {
            $this->connect("PDO");
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $this->db->prepare($sql);
        } catch (\PDOException $e) {
            $this->PDOerror[] = $e->getMessage();
        }
        return $stmt;
    }

    function execQuery($query, $conn = "PDO", $queryType = "select")
    {
        $result = null;
        switch ($conn) {
            case "PG" :
                if (!$this->db) {
                    $this->connect("PG");
                }
                $result = pg_query($this->db, $query);
                return ($result);
                break;
            case "PDO" :
                if (!$this->db) {
                    $this->connect("PDO");
                }
                if ($this->connectionFailed) {
                    return false;
                }
                try {
                    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    switch ($queryType) {
                        case "select" :
                            // Return PDOStatement object
                            $result = $this->db->query($query);
                            break;
                        case "transaction" :
                            // Return interger
                            $result = $this->db->exec($query);
                    }
                } catch (\PDOException $e) {
                    $this->PDOerror[] = $e->getMessage();
                }
                return ($result);
                break;
        }
    }

    function sql($q)
    {
        $result = $this->execQuery($q);
        while ($row = $this->fetchRow($result, "assoc")) {
            if (!$firstRow) {
                $firstRow = $row;
            }
            $arr = array();
            foreach ($row as $key => $value) {
                $arr = $this->array_push_assoc($arr, $key, $value);
            }
            $response['data'][] = $arr;
        }
        foreach ($firstRow as $key => $value) {
            $fieldsForStore[] = array("name" => $key, "type" => "string");
            $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => "string", "typeObj" => array("type" => "string"));
        }
        $response["forStore"] = $fieldsForStore;
        $response["forGrid"] = $columnsForGrid;
        return $response;
    }

    function getMetaData($table, $temp = false)
    {
        $this->connect("PG");

        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            $_schema = $this->postgisschema;
        }
        if (!$temp) {
            $arr = pg_meta_data($this->db, str_replace(".", "", $_schema) . "." . $_table);
        } else {
            $arr = pg_meta_data($this->db, $_table);
        }
        $this->close();
        return ($arr);
    }

    function connectString()
    {
        if ($this->postgishost != "")
            $connectString = "host=" . $this->postgishost;
        if ($this->postgisport != "")
            $connectString = $connectString . " port=" . $this->postgisport;
        if ($this->postgisuser != "")
            $connectString = $connectString . " user=" . $this->postgisuser;
        if ($this->postgispw != "")
            $connectString = $connectString . " password=" . $this->postgispw;
        if ($this->postgisdb != "")
            $connectString = $connectString . " dbname=" . $this->postgisdb;
        return ($connectString);
    }

    function connect($type = "PDO")
    {
        switch ($type) {
            case "PG" :
                $this->db = pg_connect($this->connectString());
                break;
            case "PDO" :
                try {

                    $this->db = new PDO("pgsql:dbname={$this->postgisdb};host={$this->postgishost};" . (($this->postgisport) ? "port={$this->postgisport}" : ""), "{$this->postgisuser}", "{$this->postgispw}");
                    $this->execQuery("set client_encoding='UTF8'", "PDO");
                } catch (\PDOException $e) {
                    $this->db = NULL;
                    $this->connectionFailed = true;
                    $this->PDOerror[] = "Could not connect to database";
                }
                break;
        }
    }

    function close()
    {
        $this->db = NULL;
    }

    function quote($str)
    {
        if (!$this->db) {
            $this->connect("PDO");
        }
        $str = $this->db->quote($str);
        return ($str);
    }

    function getGeometryColumns($table, $field)
    {
        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            $_schema = $this->postgisschema;
        } else {
            $_schema = str_replace(".", "", $_schema);
        }
        $query = "SELECT * FROM settings.getColumns('geometry_columns.f_table_name=''{$_table}'' AND geometry_columns.f_table_schema=''{$_schema}''',
                    'raster_columns.r_table_name=''{$_table}'' AND raster_columns.r_table_schema=''{$_schema}''')";

        $result = $this->execQuery($query);
        $row = $this->fetchRow($result);
        if (!$row)
            return false;
        elseif ($row)
            $this->theGeometry = $row['type'];
        if ($field == 'f_geometry_column') {
            return $row['f_geometry_column'];
        }
        if ($field == 'srid') {
            return $row['srid'];
        }
        if ($field == 'type') {
            $arr = (array)json_decode($row['def']);
            if (($arr['geotype']) && $arr['geotype'] != "Default") {
                return $arr['geotype'];
            } else {
                return $row['type'];
            }
        }
        if ($field == 'tweet') {
            return $row['tweet'];
        }
        if ($field == 'editable') {
            return $row['editable'];
        }
        if ($field == 'authentication') {
            return $row['authentication'];
        }
        if ($field == 'fieldconf') {
            return $row['fieldconf'];
        }
        if ($field == 'def') {
            return $row['def'];
        }
        if ($field == 'id') {
            return $row['id'];
        }
    }

    function toAscii($str, $replace = array(), $delimiter = '-')
    {
        if (!empty($replace)) {
            $str = str_replace((array)$replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

        return $clean;
    }

    function explodeTableName($table)
    {
        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if ($_schema) {
            $_schema = str_replace(".", "", $_schema);
        }
        return array("schema" => $_schema, "table" => $_table);

    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    public function isTableOrView($table)
    {
        $bits = explode(".", $table);
        $sql = "SELECT count(*) AS count FROM pg_tables WHERE schemaname = '{$bits[0]}' AND tablename='{$bits[1]}'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "table";
            $response['success'] = true;
            return $response;
        }
        $sql = "SELECT count(*) AS count FROM pg_views WHERE schemaname = '{$bits[0]}' AND viewname='{$bits[1]}'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "view";
            $response['success'] = true;
            return $response;
        }
        $response['data'] = null;
        $response['success'] = true;
        return $response;
    }

    public function postgisVersion()
    {
        $sql = "SELECT PostGIS_Lib_Version()";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
        $response['success'] = true;
        $response['version'] = $row["postgis_lib_version"];
        return $response;
    }
    public function doesColumnExist(){
        ;

        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='your_schema' AND table_name='your_table' and column_name='your_column'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
    }

}
