<?php
namespace app\inc;

use Exception;
use PDO;
use PDOStatement;
use app\conf\Connection;

/**
 * Class Model
 * @package app\inc
 */
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
        $this->postgisschema = isset(Connection::$param['postgisschema']) ? Connection::$param['postgisschema'] : null;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array
     * @throws Exception
     */
    public function fetchRow(PDOStatement $result, $result_type = "assoc")
    {
        $row = [];
        if (isset($this->PDOerror)) {
            throw new Exception($this->PDOerror[0]);
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

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return mixed
     * @throws Exception
     */
    public function fetchAll(PDOStatement $result, $result_type = "both")
    {
        $rows = [];
        if ($this->PDOerror) {
            throw new Exception($this->PDOerror[0]);
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

    /**
     * TODO is it used?
     * @param $result
     * @return int
     */
    public function numRows($result)
    {
        $num = sizeof($result);
        return ($num);
    }

    /**
     * @param $result
     */
    public function free($result)
    {
        $result = NULL;
    }

    /**
     * @param string $table
     * @return array|null
     */
    public function getPrimeryKey($table)
    {
        unset($this->PDOerror);
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
        $result = $this->execQuery($query);

        if (isset($this->PDOerror)) {
            return NULL;
        }
        //if ($featureId = $this->getGeometryColumns($table, "featureid")) {
        //    return array("attname" => $featureId);
        //}

        if (!is_array($row = $this->fetchRow($result))) { // If $table is view we bet on there is a gid field
            return array("attname" => "gid");
        } else {
            return ($row);
        }
    }

    /**
     * @param string $table
     * @return bool|null
     */
    public function hasPrimeryKey($table)
    {
        unset($this->PDOerror);
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
        $result = $this->execQuery($query);

        if (isset($this->PDOerror)) {
            return NULL;
        }
        if (!is_array($row = $this->fetchRow($result))) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     */
    public function begin()
    {
        $this->db->beginTransaction();
    }

    /**
     *
     */
    public function commit()
    {
        $this->db->commit();
        $this->db = NULL;
    }

    /**
     *
     */
    public function rollback()
    {
        $this->db->rollback();
        $this->db = NULL;
    }

    /**
     * @param $sql
     * @return mixed
     * @throws Exception
     */
    public function prepare($sql)
    {
        if (!$this->db) {
            $this->connect("PDO");
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $this->db->prepare($sql);
        } catch (\PDOException $e) {
            $this->PDOerror[] = $e->getMessage();
            throw new Exception($e->getMessage());
        }
        return $stmt;
    }

    /**
     * @param string $query
     * @param string $conn
     * @param string $queryType
     * @return bool|integer|PDOStatement
     */
    public function execQuery($query, $conn = "PDO", $queryType = "select")
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

    /**
     * @param string $q
     * @return array
     */
    public function sql($q)
    {
        $response = [];
        $firstRow = false;
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

    public function getMetaData($table, $temp = false)
    {
        $arr = array();
        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            $_schema = $this->postgisschema;
        } else {
            $_schema = str_replace(".", "", $_schema);
        }
        $sql = "SELECT
                  attname                          AS column_name,
                  attnum                           AS ordinal_position,
                  atttypid :: REGTYPE              AS udt_name,
                  attnotnull                       AS is_nullable,
                  format_type(atttypid, atttypmod) AS full_type
                FROM pg_attribute
                WHERE attrelid = :table :: REGCLASS
                        AND attnum > 0
                        AND NOT attisdropped";

        $res = $this->prepare($sql);
        try {
            if ($temp) {
                $res->execute(array("table" => $table));
            } else {
                $res->execute(array("table" => $_schema . "." . $_table));
            }
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res)) {
            $arr[$row["column_name"]] = array(
                "num" => $row["ordinal_position"],
                "type" => $row["udt_name"],
                "full_type" => $row['full_type'],
                "is_nullable" => $row['is_nullable'] ? false : true,
            );
            // Get type and srid of geometry
            if ($row["udt_name"] == "geometry") {
                preg_match("/[A-Z]\w+/", $row["full_type"], $matches);
                $arr[$row["column_name"]]["geom_type"] = $matches[0];
                preg_match("/[0-9]+/", $row["full_type"], $matches);
                $arr[$row["column_name"]]["srid"] = $matches[0];
            }
        }
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
        $query = "SELECT * FROM settings.getColumns('f_table_name=''{$_table}'' AND f_table_schema=''{$_schema}''',
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
            if (isset($arr['geotype']) && ($arr['geotype']) && $arr['geotype'] != "Default") {
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
        if ($field == 'elasticsearch') {
            return $row['elasticsearch'];
        }
        if ($field == 'featureid') {
            return $row['featureid'];
        }
    }

    public function toAscii($str, $replace = array(), $delimiter = '-')
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

    /**
     * Does NOT work with period in schema name.
     * @param $table
     * @return array
     */
    public function explodeTableName($table)
    {
        if (!isset(explode(".", $table)[1])) {
            return array("schema" => null, "table" => $table);
        }
        preg_match("/[^.]*/", $table, $matches);
        $_schema = $matches[0];
        preg_match("/(?<=\.).*/", $table, $matches);
        $_table = $matches[0];
        return array("schema" => $_schema, "table" => $_table);
    }

    /**
     * Returns a qualified name with double quotes like "schema"."table"
     * @param $name
     * @return string
     */
    public function doubleQuoteQualifiedName($name)
    {
        $split = $this->explodeTableName($name);
        return "\"" . $split["schema"] . "\".\"" . $split["table"] . "\"";
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    public function isTableOrView($table)
    {
        $bits = explode(".", $table);

        // Check if table
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
            $response['data'] = "TABLE";
            $response['success'] = true;
            return $response;
        }

        // Check if view
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
            $response['data'] = "VIEW";
            $response['success'] = true;
            return $response;
        }

        // Check if materialized view
        $sql = "SELECT count(*) AS count FROM pg_matviews WHERE schemaname = '{$bits[0]}' AND matviewname='{$bits[1]}'";
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
            $response['data'] = "MATERIALIZED VIEW";
            $response['success'] = true;
            return $response;
        }

        // Check if FOREIGN TABLE
        $sql = "SELECT count(*) FROM information_schema.foreign_tables WHERE foreign_table_schema='{$bits[0]}' AND  foreign_table_name='{$bits[1]}'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "FOREIGN TABLE";
            $response['success'] = true;
            return $response;
        }

        $response['success'] = false;
        $response['message'] = "Relation doesn't exists";
        $response['code'] = 406;
        return $response;
    }

    /**
     * @return array
     */
    public function postgisVersion()
    {
        $response = [];
        $sql = "SELECT PostGIS_Lib_Version()";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
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

    /**
     * @param string $t
     * @param string $c
     * @return array
     */
    public function doesColumnExist($t, $c)
    {
        $response = [];
        $bits = explode(".", $t);
        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$bits[0]}' AND table_name='{$bits[1]}' and column_name='{$c}'";
        $res = $this->prepare($sql);

        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $row = $this->fetchRow($res);
        $response['success'] = true;
        if ($row) {
            $response['exists'] = true;
        } else {
            $response['exists'] = false;
        }
        return $response;
    }
}
