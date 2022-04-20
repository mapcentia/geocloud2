<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\conf\Connection;
use app\models\Table;
use Error;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use phpDocumentor\Reflection\Types\Resource;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use TypeError;


/**
 * Class Model
 * @package app\inc
 */
class Model
{
    /**
     * @var string
     */
    public $postgishost;

    /**
     * @var string
     */
    public $postgisport;

    /**
     * @var string
     */
    public $postgisuser;

    /**
     * @var string
     */
    public $postgisdb;

    /**
     * @var string
     */
    public $postgispw;

    /**
     * @var string
     */
    public $connectString;

    /**
     * @var array<string>|null
     */
    public $PDOerror;

    /**
     * @var PDO|resource|null
     */
    public $db;

    /**
     * @var string
     */
    public $postgisschema;

    /**
     * @var bool
     */
    public $connectionFailed;

    /**
     * @var string
     */
    public $theGeometry;

    // If Connection::$params are not set, then set them from environment variables
    function __construct()
    {
        // If Connection::$params are not set, when set them from environment variables
        Connection::$param['postgishost'] = Connection::$param['postgishost'] ?? getenv('POSTGIS_HOST');
        Connection::$param['postgisport'] = Connection::$param['postgisport'] ?? getenv('POSTGIS_PORT');
        Connection::$param['postgisuser'] = Connection::$param['postgisuser'] ?? getenv('POSTGIS_USER');
        Connection::$param['postgisdb'] = Connection::$param['postgisdb'] ?? getenv('POSTGIS_DB');
        Connection::$param['postgispw'] = Connection::$param['postgispw'] ?? getenv('POSTGIS_PW');
        Connection::$param['pgbouncer'] = Connection::$param['pgbouncer'] ?? getenv('POSTGIS_PGBOUNCER') === "true";

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
     * @return array<mixed>|null
     * @throws PDOException
     */
    public function fetchRow(PDOStatement $result, string $result_type = "assoc"): ?array
    {
        $row = [];
        switch ($result_type) {
            case "assoc" :
                try {
                    $row = $result->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    throw new PDOException($e->getMessage());
                }
                break;
            case "both" :
                break;
        }
        return $row ?: null;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array<mixed>
     * @throws PDOException
     */
    public function fetchAll(PDOStatement $result, string $result_type = "both"): array
    {
        $rows = [];
        if (isset($this->PDOerror)) {
            throw new PDOException($this->PDOerror[0]);
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
     * @param mixed $result
     * @return int
     */
    public function numRows($result): int
    {
        if ($result instanceof PDOStatement) {
            return $result->rowCount();
        } else {
            return sizeof($result);
        }
    }

    /**
     * @param PDOStatement $result
     */
    public function free(PDOStatement &$result): void
    {
        $result = NULL;
    }

    /**
     * @param string $table
     * @return array|string[]|null
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getPrimeryKey(string $table): ?array
    {
        $response = null;
        $cacheType = "prikey";
        $cacheRel = $table;
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . $cacheRel);
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

            try {
                if (!is_array($row = $this->fetchRow($result))) { // If $table is view we bet on there is a gid field
                    $response = array("attname" => "gid");
                } else {
                    $response = $row;
                }
            } catch (TypeError $e) {
                return null;
            }

            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);
                $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);

            } catch (Error $exception) {
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
    public function hasPrimeryKey(string $table): ?bool
    {
        unset($this->PDOerror);
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
        $result = $this->execQuery($query);

        if (isset($this->PDOerror)) {
            return NULL;
        }
        $row = $this->fetchRow($result);
        if (!is_array($row)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     */
    public function begin(): void
    {
        $this->db->beginTransaction();
    }

    /**
     *
     */
    public function commit(): void
    {
        $this->db->commit();
        $this->db = NULL;
    }

    /**
     *
     */
    public function rollback(): void
    {
        $this->db->rollback();
        $this->db = NULL;
    }

    /**
     * @param string $sql
     * @return PDOStatement
     * @throws PDOException
     */
    public function prepare(string $sql): PDOStatement
    {
        if (!$this->db) {
            try {
                $this->connect();
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage());
            }
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $this->db->prepare($sql);
        } catch (PDOException $e) {
            $this->PDOerror[] = $e->getMessage();
            throw new PDOException($e->getMessage());
        }
        return $stmt;
    }

    /**
     * @param string $query
     * @param string $conn
     * @param string $queryType
     * @return null|integer|PDOStatement|resource
     */
    public function execQuery(string $query, string $conn = "PDO", string $queryType = "select")
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
                        $this->connect();
                    } catch (PDOException $e) {
                        throw new PDOException($e->getMessage());
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
                } catch (PDOException $e) {
                    $this->PDOerror[] = $e->getMessage();
                }
                break;
        }
        return !empty($result) ? $result : null;
    }

    /**
     * @param string $q
     * @return array<mixed>
     */
    public function sql(string $q): ?array
    {
        $response = [];
        $fieldsForStore = [];
        $columnsForGrid = [];
        $firstRow = false;
        $result = $this->execQuery($q);
        while ($row = $this->fetchRow($result)) {
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

    /**
     * @param string $table
     * @param bool $temp
     * @param bool $restriction
     * @param array<array>|null $restrictions
     * @param string|null $cacheKey
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getMetaData(string $table, bool $temp = false, bool $restriction = false, array $restrictions = null, string $cacheKey = null): array
    {
        $cacheType = "metadata";
        $cacheRel = md5($cacheKey ?: $table);
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . md5($cacheRel . (int)$temp . (int)$restriction . serialize($restrictions)));
        $CachedString = Cache::getItem($cacheId);
        $primaryKey = null;
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $arr = [];
            $foreignConstrains = [];

            $_schema = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[0] : null;

            $_table = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[1] : $table;

            if (!$_schema) {
                $_schema = $this->postgisschema;
            } else {
                $_schema = str_replace(".", "", $_schema);
            }

            if ($restriction == true && !$restrictions) {
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
                    $res->execute(array("table" => "\"" . $table . "\""));
                } else {
                    $res->execute(array("table" => "\"" . $_schema . "\".\"" . $_table . "\""));
                }
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }
            while ($row = $this->fetchRow($res)) {
                $foreignValues = [];
                if ($restriction == true && $restrictions == false) {
                    foreach ($foreignConstrains as $value) {
                        if ($row["column_name"] == $value["child_column"] && $value["parent_column"] != $primaryKey) {
                            $sql = "SELECT {$value["parent_column"]} FROM {$value["parent_schema"]}.{$value["parent_table"]}";
                            try {
                                $resC = $this->prepare($sql);
                                $resC->execute();

                            } catch (PDOException $e) {
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
                } elseif ($restriction == true && $restrictions != false && isset($restrictions[$row["column_name"]]) && isset($restrictions[$row["column_name"]]->_rel)) {
                    $rel = $restrictions[$row["column_name"]];
                    $sql = "SELECT {$rel->_value} AS value, {$rel->_text} AS text FROM {$rel->_rel}";
                    try {
                        $resC = $this->prepare($sql);
                        $resC->execute();

                    } catch (PDOException $e) {
                        $response['success'] = false;
                        $response['message'] = $e->getMessage();
                        $response['code'] = 401;
                        return $response;
                    }
                    while ($rowC = $this->fetchRow($resC)) {
                        $foreignValues[] = ["value" => $rowC["value"], "alias" => (string)$rowC["text"]];
                    }
                } elseif ($restriction == true && $restrictions != false && isset($restrictions[$row["column_name"]]) && $restrictions[$row["column_name"]] != "*") {
                    if (is_array($restrictions[$row["column_name"]])) {
                        foreach ($restrictions[$row["column_name"]] as $restriction) {
                            $foreignValues[] = ["value" => $restriction, "alias" => (string)$restriction];
                        }
                    } elseif (is_object($restrictions[$row["column_name"]])) {
                        foreach ($restrictions[$row["column_name"]] as $alias => $value) {
                            $foreignValues[] = ["value" => (string)$value, "alias" => (string)$alias];
                        }
                    }
                } elseif ($restrictions[$row["column_name"]] == "*") {
                    $t = new Table($table);
                    foreach ($t->getGroupByAsArray($row["column_name"])["data"] as $value) {
                        $foreignValues[] = ["value" => (string)$value, "alias" => (string)$value];
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
            } catch (Error $exception) {
                //die($exception->getMessage());
            }
            Cache::save($CachedString);
            return $arr;
        }
    }

    /**
     * @return string
     */
    public function connectString(): string
    {
        $connectString = "";
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

    /**
     * @param string $type
     */
    function connect(string $type = "PDO"): void
    {
        switch ($type) {
            case "PG" :
                $c = pg_connect($this->connectString());
                $this->db = $c ?: null;
                break;
            case "PDO" :
                try {
                    $this->db = new PDO("pgsql:dbname={$this->postgisdb};host={$this->postgishost};" . (($this->postgisport) ? "port={$this->postgisport}" : ""), "{$this->postgisuser}", "{$this->postgispw}");
                    $this->execQuery("set client_encoding='UTF8'");
                } catch (PDOException $e) {
                    $this->db = null;
                    $this->connectionFailed = true;
                    throw new PDOException($e->getMessage());
                }
                break;
        }
    }

    /**
     *
     */
    function close(): void
    {
        $this->db = null;
    }

    /**
     * @param string $str
     * @return string
     */
    function quote(string $str): string
    {
        if (!$this->db) {
            $this->connect();
        }
        $str = $this->db->quote($str);
        return ($str);
    }

    /**
     * @param string $table
     * @param string $field
     * @return string|array<mixed>|null
     * @throws PhpfastcacheInvalidArgumentException
     */
    function getGeometryColumns(string $table, string $field) // : string|array|null
    {
        $response = [];

        $_schema = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[0] : null;

        $_table = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[1] : $table;

        if (!$_schema) {
            $_schema = $this->postgisschema;
        } else {
            $_schema = str_replace(".", "", $_schema);
        }

        $row = $this->getColumns($_schema, $_table)[0];

        if (!$row) {
            return null;
        } else {
            $this->theGeometry = $row['type'];
        }
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

    /**
     * @param string $str
     * @param array<string>|null $replace
     * @param string $delimiter
     * @return string
     */
    public static function toAscii(string $str, ?array $replace = [], string $delimiter = '-'): string
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
     * @param string|null $table
     * @return array<string,string|null>
     */
    public static function explodeTableName(?string $table): array
    {
        if (!isset(explode(".", $table)[1])) {
            return ["schema" => null, "table" => $table];
        }
        preg_match("/[^.]*/", $table, $matches);
        $_schema = $matches[0];
        preg_match("/(?<=\.).*/", $table, $matches);
        $_table = $matches[0];
        return ["schema" => $_schema, "table" => $_table];
    }

    /**
     * Returns a qualified name with double quotes like "schema"."table"
     * @param string $name
     * @return string
     */
    public function doubleQuoteQualifiedName(string $name): string
    {
        $split = self::explodeTableName($name);
        return "\"" . $split["schema"] . "\".\"" . $split["table"] . "\"";
    }

    /**
     * @param array<mixed> $array
     * @param string $key
     * @param mixed $value
     * @return array<mixed>
     */
    private function array_push_assoc(array $array, string $key, $value): array
    {
        $array[$key] = $value;
        return $array;
    }

    /**
     * @param string $table
     * @return array<string, string|int|bool>
     */
    public function isTableOrView(string $table): array
    {
        $bits = explode(".", $table);

        // Check if table
        $sql = "SELECT count(*) AS count FROM pg_tables WHERE schemaname = '{$bits[0]}' AND tablename='{$bits[1]}'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
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
     * @return array<string, int|string|bool>
     */
    public function postgisVersion(): array
    {
        $cacheType = "postgisVersion";
        $cacheId = $cacheType;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {

            $response = [];
            $sql = "SELECT PostGIS_Lib_Version()";
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }
            $row = $this->fetchRow($res);
            $response['success'] = true;
            $response['version'] = $row["postgis_lib_version"];
            try {
                $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            } catch (Error $exception) {
                error_log($exception->getMessage());
            }
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @return array<string, int|string|bool>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function doesColumnExist(string $table, string $column): array
    {
        $cacheType = "columnExist";
        $cacheRel = $table;
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . $cacheRel . "_" . $column);
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
            } catch (PDOException $e) {
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
            } catch (Error $exception) {
                error_log($exception->getMessage());
            }
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getForeignConstrains(string $schema, string $table): array
    {
        $cacheType = "foreignConstrain";
        $cacheRel = $schema . "." . $table;
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . $cacheRel);
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
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

            try {
                $rows = $this->fetchAll($res);
            } catch (Exception $e) {
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
            } catch (Error $exception) {
                // Pass
            }
            Cache::save($CachedString);

            return $response;
        }
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getChildTables(string $schema, string $table): array
    {
        $cacheType = "childTables";
        $cacheRel = $schema . "." . $table;
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . $cacheRel);
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
            } catch (PDOException $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }

            while ($row = $this->fetchRow($res)) {
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

            } catch (Error $exception) {
                // Pass
            }
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getColumns(string $schema, string $table): array
    {
        $cacheType = "columns";
        $cacheRel = $schema . "." . $table;
        $cacheId = md5($this->postgisdb . "_" . $cacheType . "_" . $cacheRel);
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $sql = "SELECT * FROM settings.getColumns('f_table_schema = ''{$schema}'' AND f_table_name = ''{$table}''','raster_columns.r_table_schema = ''{$schema}'' AND raster_columns.r_table_name = ''{$table}''')";
            $res = $this->prepare($sql);
            try {
                $res->execute();
                $rows = $this->fetchAll($res);
            } catch (Exception $e) {
                die($e->getMessage());
            }
            $CachedString->set($rows)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            Cache::save($CachedString);
            return $rows;
        }
    }

    /**
     * Count the rows in a relation
     *
     * @param string $schema
     * @param string $table
     * @return array<mixed>
     */
    public function countRows(string $schema, string $table): array
    {
        $sql = "SELECT count(*) AS count FROM " . $this->doubleQuoteQualifiedName($schema . "." . $table);
        $res = $this->prepare($sql);
        try {
            $res->execute();
            $row = $this->fetchRow($res);
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        return [
            "success" => true,
            "data" => $row["count"],
        ];
    }
}
