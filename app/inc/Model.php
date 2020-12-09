<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use \app\conf\App;
use Exception;
use PDO;
use PDOStatement;
use \app\conf\Connection;

/**
 * Class Model
 * @package app\inc
 */
class Model
{
    public $postgishost;
    public $postgisport;
    public $postgisuser;
    public $postgisdb;
    public $postgispw;
    public $connectString;
    public $PDOerror;
    public $db;
    public $postgisschema;
    public $connectionFailed;


    function __construct()
    {
        $this->postgishost = !empty(Connection::$param['postgishost']) ? Connection::$param['postgishost'] : getenv('POSTGIS_HOST');
        $this->postgisport = !empty(Connection::$param['postgisport']) ? Connection::$param['postgisport'] : getenv('POSTGIS_PORT');
        $this->postgisuser = !empty(Connection::$param['postgisuser']) ? Connection::$param['postgisuser'] : getenv('POSTGIS_USER');
        $this->postgisdb = !empty(Connection::$param['postgisdb']) ? Connection::$param['postgisdb'] : getenv('POSTGIS_DB');
        $this->postgispw = !empty(Connection::$param['postgispw']) ? Connection::$param['postgispw'] : getenv('POSTGIS_PW');
        $this->postgisschema = isset(Connection::$param['postgisschema']) ? Connection::$param['postgisschema'] : null;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array
     * @throws \PDOException
     */
    public function fetchRow(PDOStatement $result, $result_type = "assoc")
    {
        $row = [];
        switch ($result_type) {
            case "assoc" :
                try {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                } catch (\PDOException $e) {
                    throw new \PDOException($e->getMessage());
                }
                break;
            case "both" :
                break;
        }
        return $row;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array
     * @throws Exception
     */
    public function fetchAll(PDOStatement $result, $result_type = "both")
    {
        $rows = [];
        if (isset($this->PDOerror)) {
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
        return $rows;
    }

    /**
     * TODO is it used?
     * @param $result
     * @return int
     */
    public function numRows($result)
    {
        $num = sizeof($result);
        return $num;
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
        $cacheType = "prikey";
        $cacheRel = $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $cacheRel;
        if (!empty(App::$param["defaultPrimaryKey"])) {
            return ["attname" => App::$param["defaultPrimaryKey"]];
        }
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            unset($this->PDOerror);
            $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
            $result = $this->execQuery($query);

            if (isset($this->PDOerror)) {
                $response = NULL;
            }

            if (!is_array($row = $this->fetchRow($result))) { // If $table is view we bet on there is a gid field
                $response = array("attname" => "gid");
            } else {
                $response = $row;
            }

            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);

            } catch (\Error $exception) {
                die($exception->getMessage());
            }
            Cache::save($CachedString);
            return $response;
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
     * @throws \PDOException
     */
    public function prepare($sql)
    {
        if (!$this->db) {
            try {
                $this->connect("PDO");
            } catch (\PDOException $e) {
                throw new \PDOException($e->getMessage());
            }
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $this->db->prepare($sql);
        } catch (\PDOException $e) {
            $this->PDOerror[] = $e->getMessage();
            throw new \PDOException($e->getMessage());
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
                break;

            case "PDO" :
                if (!$this->db) {
                    try {
                        $this->connect("PDO");
                    } catch (\PDOException $e) {
                        throw new \PDOException($e->getMessage());
                    }
                }
                if ($this->connectionFailed) {
                    $result = false;
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
                break;
        }
        return $result;
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

    public function getMetaData(string $table, bool $temp = false, bool $restriction = false, $restrictionTable = false): array
    {
        $cacheType = "metadata";
        $cacheRel = $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . md5($cacheRel . (int)$temp . (int)$restriction . serialize($restrictionTable));
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $arr = [];
            $foreignConstrains = [];
            preg_match("/^[\w'-]*\./", $table, $matches);
            $_schema = !empty($matches[0]) ? $matches[0] : null;

            preg_match("/[\w'-]*$/", $table, $matches);
            $_table = $matches[0];

            if (!$_schema) {
                $_schema = $this->postgisschema;
            } else {
                $_schema = str_replace(".", "", $_schema);
            }

            if ($restriction == true && $restrictionTable == false) {
                $foreignConstrains = $this->getForeignConstrains($_schema, $_table)["data"];
                $primaryKey = $this->getPrimeryKey($table)['attname'];
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

            try {
                $res = $this->prepare($sql);
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
                $foreignValues = [];
                if ($restriction == true && $restrictionTable == false) {
                    foreach ($foreignConstrains as $value) {
                        if ($row["column_name"] == $value["child_column"] && $value["parent_column"] != $primaryKey) {
                            $sql = "SELECT {$value["parent_column"]} FROM {$value["parent_schema"]}.{$value["parent_table"]}";
                            try {
                                $resC = $this->prepare($sql);
                                $resC->execute();

                            } catch (\PDOException $e) {
                                $response['success'] = false;
                                $response['message'] = $e->getMessage();
                                $response['code'] = 401;
                                return $response;
                            }
                            while ($rowC = $this->fetchRow($resC)) {
                                $foreignValues[] = ["value" => $rowC[$value["parent_column"]], "alias" => (string)$rowC[$value["parent_column"]]];
                            }
                        }
                    }
                } elseif ($restriction == true && $restrictionTable != false && isset($restrictionTable[$row["column_name"]])) {
                    $rel = $restrictionTable[$row["column_name"]];
                    //print_r($rel);
                    $sql = "SELECT {$rel["_value"]} AS value, {$rel["_text"]} AS text FROM {$rel["_rel"]}";
                    try {
                        $resC = $this->prepare($sql);
                        $resC->execute();

                    } catch (\PDOException $e) {
                        $response['success'] = false;
                        $response['message'] = $e->getMessage();
                        $response['code'] = 401;
                        return $response;
                    }
                    while ($rowC = $this->fetchRow($resC)) {
                        $foreignValues[] = ["value" => $rowC["value"], "alias" => (string)$rowC["text"]];
                    }
                }

                $arr[$row["column_name"]] = array(
                    "num" => $row["ordinal_position"],
                    "type" => $row["udt_name"],
                    "full_type" => $row['full_type'],
                    "is_nullable" => $row['is_nullable'] ? false : true,
                    "restriction" => sizeof($foreignValues) > 0 ? $foreignValues : null
                );
                // Get type and srid of geometry
                if ($row["udt_name"] == "geometry") {
                    preg_match("/[A-Z]\w+/", $row["full_type"], $matches);
                    $arr[$row["column_name"]]["geom_type"] = $matches[0];
                    preg_match("/[0-9]+/", $row["full_type"], $matches);
                    $arr[$row["column_name"]]["srid"] = $matches[0];
                }
            }
            try {
                $CachedString->set($arr)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            } catch (\Error $exception) {
                //die($exception->getMessage());
            }
            Cache::save($CachedString);
            return $arr;
        }
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
                    throw new \PDOException($e->getMessage());
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

    function getGeometryColumns(string $table, string $field)
    {
        $response = [];
        preg_match("/^[\w'-]*\./", $table, $matches);
        $_schema = $matches[0];

        preg_match("/[\w'-]*$/", $table, $matches);
        $_table = $matches[0];

        if (!$_schema) {
            $_schema = $this->postgisschema;
        } else {
            $_schema = str_replace(".", "", $_schema);
        }

        $row = $this->getColumns($_schema, $_table)[0];

        if (!$row)
            return false;
        elseif ($row)
            $this->theGeometry = $row['type'];
        if ($field == 'f_geometry_column') {
            $response = $row['f_geometry_column'];
        }
        if ($field == 'srid') {
            $response = $row['srid'];
        }
        if ($field == 'type') {
            $arr = (array)json_decode($row['def']);
            if (isset($arr['geotype']) && ($arr['geotype']) && $arr['geotype'] != "Default") {
                $response = $arr['geotype'];
            } else {
                $response = $row['type'];
            }
        }
        if ($field == 'tweet') {
            $response = $row['tweet'];
        }
        if ($field == 'editable') {
            $response = $row['editable'];
        }
        if ($field == 'authentication') {
            $response = $row['authentication'];
        }
        if ($field == 'fieldconf') {
            $response = $row['fieldconf'];
        }
        if ($field == 'def') {
            $response = $row['def'];
        }
        if ($field == 'id') {
            $response = $row['id'];
        }
        if ($field == 'elasticsearch') {
            $response = $row['elasticsearch'];
        }
        if ($field == 'featureid') {
            $response = $row['featureid'];
        }
        if ($field == '*') {
            $response = $row;
        }
        return $response;
    }

    public static function toAscii($str, $replace = array(), $delimiter = '-')
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
     * @param $table
     * @param $column
     * @return array
     */
    public function doesColumnExist($table, $column)
    {
        $cacheType = "columnExist";
        $cacheRel = $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $cacheRel . "_" . $column;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {

            $response = [];
            $bits = explode(".", $table);
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$bits[0]}' AND table_name='{$bits[1]}' and column_name='{$column}'";
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

            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            } catch (\Error $exception) {
                // Pass
            }
            Cache::save($CachedString);
            return $response;
        }
    }

    public function getForeignConstrains(string $schema, string $table): array
    {
        $cacheType = "foreignConstrain";
        $cacheRel = $schema . "." . $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $cacheRel;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {

            $response = [];
            $sql = "SELECT 
                    att2.attname AS \"child_column\", 
                    cl.relname AS \"parent_table\", 
                    nspname AS \"parent_schema\", 
                    att.attname AS \"parent_column\",
                    conname
                FROM
                   (SELECT 
                        unnest(con1.conkey) AS \"parent\", 
                        unnest(con1.confkey) AS \"child\", 
                        con1.confrelid, 
                        con1.conrelid,
                        con1.conname,
                        ns.nspname
                    FROM 
                        pg_class cl
                        JOIN pg_namespace ns ON cl.relnamespace = ns.oid
                        JOIN pg_constraint con1 ON con1.conrelid = cl.oid
                    WHERE
                        cl.relname = :table
                        AND ns.nspname = :schema
                        AND con1.contype = 'f'
                   ) con
                   JOIN pg_attribute att ON
                       att.attrelid = con.confrelid AND att.attnum = con.child
                   JOIN pg_class cl ON
                       cl.oid = con.confrelid
                   JOIN pg_attribute att2 ON
                       att2.attrelid = con.conrelid AND att2.attnum = con.parent";

            $res = $this->prepare($sql);
            try {
                $res->execute(["table" => $table, "schema" => $schema]);
            } catch (\PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

            try {
                $rows = $this->fetchAll($res);
            } catch (\Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

            $response['success'] = true;
            $response['data'] = $rows;

            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            } catch (\Error $exception) {
                // Pass
            }
            Cache::save($CachedString);

            return $response;
        }
    }

    public function getChildTables(string $schema, string $table): array
    {
        $cacheType = "childTables";
        $cacheRel = $schema . "." . $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $cacheRel;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $response = [];
            $sql = "SELECT tc.*, ccu.column_name
                    FROM information_schema.table_constraints tc 
                    RIGHT JOIN information_schema.constraint_column_usage ccu 
                          ON tc.constraint_catalog=ccu.constraint_catalog 
                         AND tc.constraint_schema = ccu.constraint_schema 
                         AND tc.constraint_name = ccu.constraint_name 
                    AND (ccu.table_schema, ccu.table_name) IN ((:schema, :table))
                    WHERE lower(tc.constraint_type) IN ('foreign key') AND constraint_type='FOREIGN KEY'";

            $res = $this->prepare($sql);
            try {
                $res->execute(["table" => $table, "schema" => $schema]);
            } catch (\PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

            while ($row = $this->fetchRow($res, "assoc")) {
                $arr = [];
                $foreignConstrains = $this->getForeignConstrains($row["table_schema"], $row["table_name"])["data"];
                foreach ($foreignConstrains as $value) {
                    if ($schema == $value["parent_schema"] && $table == $value["parent_table"]) {
                        $arr = $value;
                        break;
                    }
                }
                $response['data'][] = [
                    "rel" => $row["table_schema"] . "." . $row["table_name"],
                    "parent_column" => $row["column_name"],
                    "child_column" => $arr["child_column"],
                ];
            }

            $response['success'] = true;

            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);

            } catch (\Error $exception) {
                // Pass
            }
            Cache::save($CachedString);
            return $response;
        }
    }

    public function getColumns(string $schema, string $table): array
    {
        $cacheType = "columns";
        $cacheRel = $schema . "." . $table;
        $cacheId = $this->postgisdb . "_" . $cacheType . "_" . $cacheRel;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $sql = "SELECT * FROM settings.getColumns('f_table_schema = ''{$schema}'' AND f_table_name = ''{$table}''','raster_columns.r_table_schema = ''{$schema}'' AND raster_columns.r_table_name = ''{$table}''')";
            $res = $this->prepare($sql);
            try {
                $res->execute();
                $rows = $this->fetchAll($res);
            } catch (\PDOException $e) {
                die($e->getMessage());
            }
            try {
                $CachedString->set($rows)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);

            } catch (\Error $exception) {
                // Pass
            }
            Cache::save($CachedString);
            return $rows;
        }
    }
}
