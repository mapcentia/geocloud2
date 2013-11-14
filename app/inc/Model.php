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
            throw new \Exception($this->PDOerror[0]);
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
            //throw new Exception($this->PDOerror[0]);
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
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '{$table}'::regclass AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = any(pg_index.indkey) AND indisprimary";
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
        $stmt = $this->db->prepare($sql);
        // Return PDOStatement object
        return $stmt;
    }

    function execQuery($query, $conn = "PDO", $queryType = "select")
    {
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
                    //$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

    function getMetaData($table)
    {
        $this->connect("PG");

        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            $_schema = $this->postgisschema;
        }
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) { // If running 5.3 then use schema.table
            $arr = pg_meta_data($this->db, str_replace(".", "", $_schema) . "." . $_table);
        } else { // if running below 5.3 then set SEARCH_PATH and use just table without schema
            $this->execQuery("SET SEARCH_PATH TO " . str_replace(".", "", $_schema), "PG");
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
                    $this->db = new PDO("pgsql:dbname={$this->postgisdb};host={$this->postgishost}", "{$this->postgisuser}", "{$this->postgispw}");
                    $this->execQuery("set client_encoding='UTF8'", "PDO");
                } catch (PDOException $e) {
                    $this->db = NULL;
                    $this->connectionFailed = true;
                    $this->PDOerror[] = "Could not connect to database";
                    print_r($this->PDOerror);
                    die();
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
        $query = "select * from settings.geometry_columns_view where f_table_name='{$_table}' AND f_table_schema='{$_schema}'";

        $result = $this->execQuery($query);
        $row = $this->fetchRow($result);
        if (!$row)
            return $languageText['selectText'];
        elseif ($row)
            $this->theGeometry = $row['type'];
        if ($field == 'f_geometry_column') {
            return $row['f_geometry_column'];
        }
        if ($field == 'srid') {
            return $row['srid'];
        }
        if ($field == 'type') {
            return $row['type'];
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

}
