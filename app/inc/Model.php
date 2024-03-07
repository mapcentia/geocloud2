<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

declare(strict_types=1);

namespace app\inc;

use app\conf\App;
use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\models\Database;
use app\models\Table;
use Error;
use Exception;
use PDO;
use PDOException;
use PDOStatement;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use TypeError;


/**
 * Class Model
 * @package app\inc
 */
class Model
{
    public string $postgishost;
    public string $postgisport;
    public string $postgisuser;
    public string $postgisdb;
    public string $postgispw;
    public ?string $postgisschema;
    public null|PDO|\PgSql\Connection $db;
    public bool $connectionFailed;
    public ?string $theGeometry;

    // If Connection::$params are not set, then set them from environment variables
    function __construct()
    {
        $this->db = null;
        $this->connectionFailed = false;

        // If Connection::$params are not set, when set them from environment variables
        Connection::$param['postgishost'] = Connection::$param['postgishost'] ?? getenv('POSTGIS_HOST');
        Connection::$param['postgisport'] = Connection::$param['postgisport'] ?? getenv('POSTGIS_PORT');
        Connection::$param['postgisuser'] = Connection::$param['postgisuser'] ?? getenv('POSTGIS_USER');
//        Connection::$param['postgisdb'] = Connection::$param['postgisdb'] ?? getenv('POSTGIS_DB');
        Connection::$param['postgispw'] = Connection::$param['postgispw'] ?? getenv('POSTGIS_PW');
        Connection::$param['pgbouncer'] = Connection::$param['pgbouncer'] ?? getenv('POSTGIS_PGBOUNCER') === "true";

        $this->postgishost = Connection::$param['postgishost'];
        $this->postgisport = Connection::$param['postgisport'];
        $this->postgisuser = Connection::$param['postgisuser'];
        $this->postgisdb = Connection::$param['postgisdb'];
        $this->postgispw = Connection::$param['postgispw'];
        $this->postgisschema = Connection::$param['postgisschema'] ?? null;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array|null
     * @throws PDOException
     */
    public function fetchRow(PDOStatement $result, string $result_type = "assoc"): ?array
    {
        $row = [];
        switch ($result_type) {
            case "assoc" :
                $row = $result->fetch(PDO::FETCH_ASSOC);
                break;
            case "both" :
                break;
        }
        return $row ?: null;
    }

    /**
     * @param PDOStatement $result
     * @param string $result_type
     * @return array
     * @throws PDOException
     */
    public function fetchAll(PDOStatement $result, string $result_type = "both"): array
    {
        $rows = [];
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
     * @param mixed $result
     * @return int
     */
    public function numRows(mixed $result): int
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
        $cacheType = "prikey";
        $cacheRel = ($table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $cacheType);
        if (!empty(App::$param["defaultPrimaryKey"])) {
            return ["attname" => App::$param["defaultPrimaryKey"]];
        }
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
            $result = $this->execQuery($query);
            try {
                if (!is_array($row = $this->fetchRow($result))) { // If $table is view we bet on there is a gid field
                    $response = array("attname" => "gid");
                } else {
                    $response = $row;
                }
            } catch (TypeError) {
                return null;
            }
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);
            // $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
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
        $query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '" . $this->doubleQuoteQualifiedName($table) . "'::REGCLASS AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = ANY(pg_index.indkey) AND indisprimary";
        $result = $this->execQuery($query);
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
            $this->connect();
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $this->db->prepare($sql);
    }

    /**
     * @param string $query
     * @param string $conn
     * @param string $queryType
     * @return null|integer|PDOStatement|resource
     * @throws PDOException
     */
    public function execQuery(string $query, string $conn = "PDO", string $queryType = "select"): mixed
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
                    $this->connect();
                }
                if ($this->connectionFailed) {
                    $result = false;
                }
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
                break;
        }
        return !empty($result) ? $result : null;
    }

    /**
     * @param string $q
     * @return array|null
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
     * @param bool $getEnums
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getMetaData(string $table, bool $temp = false, bool $restriction = true, array $restrictions = null, string $cacheKey = null, bool $getEnums = true): array
    {
        $cacheType = "metadata";
        $cacheRel = ($cacheKey ?: $table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $cacheType . "_" . md5((int)$temp . (int)$restriction . serialize($restrictions)));
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
                $_schema = !empty($this->postgisschema) ? $this->postgisschema : "public";
            } else {
                $_schema = str_replace(".", "", $_schema);
            }

            if ($restriction && !$restrictions) {
                $foreignConstrains = $this->getForeignConstrains($_schema, $_table)["data"];
                $primaryKey = $this->getPrimeryKey($table)['attname'];
            }
            $checkConstrains = $this->getConstrains($_schema, $_table, 'c')["data"];
            $sql = "SELECT
                  attname                          AS column_name,
                  attnum                           AS ordinal_position,
                  atttypid :: REGTYPE              AS udt_name,
                  attnotnull                       AS is_nullable,
                  format_type(atttypid, atttypmod) AS full_type,
                  pg_get_expr(d.adbin, d.adrelid) AS default_value,
                  CASE  
                      when atttypid in (1043) then
                  atttypmod-4         
       ELSE null       end       AS character_maximum_length 
       ,
                  CASE atttypid
                     WHEN 21 /*int2*/ THEN 16
                     WHEN 23 /*int4*/ THEN 32
                     WHEN 20 /*int8*/ THEN 64
                     WHEN 1700 /*numeric*/ THEN
                          CASE WHEN atttypmod = -1
                               THEN null
                               ELSE ((atttypmod - 4) >> 16) & 65535     -- calculate the precision
                               END
                     WHEN 700 /*float4*/ THEN 24 /*FLT_MANT_DIG*/
                     WHEN 701 /*float8*/ THEN 53 /*DBL_MANT_DIG*/
                     ELSE null
              END   AS numeric_precision,
              CASE 
                WHEN atttypid IN (21, 23, 20) THEN 0
                WHEN atttypid IN (1700) THEN            
                    CASE 
                        WHEN atttypmod = -1 THEN null       
                        ELSE (atttypmod - 4) & 65535            -- calculate the scale  
                    END
                   ELSE null
              END AS numeric_scale
       ,
        case 
          when attlen <> -1 then attlen
          when atttypid in (1043, 25) then information_schema._pg_char_octet_length(information_schema._pg_truetypid(a.*, t.*), information_schema._pg_truetypmod(a.*, t.*))
       end as max_bytes
                  
                FROM pg_attribute a
                  join pg_type t on atttypid = t.oid
                  left join pg_catalog.pg_attrdef d ON (a.attrelid, a.attnum) = (d.adrelid, d.adnum)

                WHERE attrelid = :table :: REGCLASS
                        AND attnum > 0
                        AND NOT attisdropped";
            $res = $this->prepare($sql);
            if ($temp) {
                $res->execute(array("table" => "\"" . $table . "\""));
            } else {
                $res->execute(array("table" => "\"" . $_schema . "\".\"" . $_table . "\""));
            }
            $index = $this->getIndexes($_schema, $_table);
            while ($row = $this->fetchRow($res)) {
                $foreignValues = [];
                $checkValues = [];
                $references = [];
                if ($restriction && !$restrictions) {
                    foreach ($foreignConstrains as $value) {
                        if ($row["column_name"] == $value["child_column"] /*&& $value["parent_column"] != $primaryKey*/) {
                            $references[] = $value["parent_schema"] . "." . $value["parent_table"] . "." . $value["parent_column"];
                            if ($getEnums) {
                                $sql = "SELECT {$value["parent_column"]} FROM {$value["parent_schema"]}.{$value["parent_table"]}";
                                $resC = $this->prepare($sql);
                                $resC->execute();
                                while ($rowC = $this->fetchRow($resC)) {
                                    $foreignValues[] = ["value" => $rowC[$value["parent_column"]], "alias" => (string)$rowC[$value["parent_column"]]];
                                }
                            }
                        }
                    }
                    if (sizeof($references) == 1) {
                        $references = $references[0];
                    } elseif (sizeof($references) == 0) {
                        $references = null;
                    }
                } elseif (isset($restrictions[$row["column_name"]]->_rel) && $restriction && $restrictions) {
                    $references = $restrictions[$row["column_name"]]->_rel . "." . $restrictions[$row["column_name"]]->_value;
                    if ($getEnums) {
                        $rel = $restrictions[$row["column_name"]];
                        $sql = "SELECT $rel->_value AS value, $rel->_text AS text FROM $rel->_rel";
                        if (!empty($rel->_where)) {
                            $sql .= " WHERE $rel->_where";
                        }
                        $resC = $this->prepare($sql);
                        $resC->execute();
                        while ($rowC = $this->fetchRow($resC)) {
                            $foreignValues[] = ["value" => $rowC["value"], "alias" => (string)$rowC["text"]];
                        }
                    }
                } elseif ($restriction && $restrictions && isset($restrictions[$row["column_name"]]) && $restrictions[$row["column_name"]] != "*" && $getEnums) {
                    if (is_array($restrictions[$row["column_name"]])) {
                        foreach ($restrictions[$row["column_name"]] as $restriction) {
                            $foreignValues[] = ["value" => $restriction, "alias" => (string)$restriction];
                        }
                    } elseif (is_object($restrictions[$row["column_name"]])) {
                        foreach ($restrictions[$row["column_name"]] as $alias => $value) {
                            $foreignValues[] = ["value" => $value, "alias" => (string)$alias];
                        }
                    }
                } elseif (isset($restrictions[$row["column_name"]]) && $restrictions[$row["column_name"]] == "*" && $getEnums) {
                    $t = new Table($table);
                    foreach ($t->getGroupByAsArray($row["column_name"])["data"] as $value) {
                        $foreignValues[] = ["value" => $value, "alias" => (string)$value];
                    }
                }
                foreach ($checkConstrains as $check) {
                    if ($check['column_name'] == $row["column_name"]) {
                        $checkValues[] = $check["con"];
                    }
                }
                $arr[$row["column_name"]] = array(
                    "num" => $row["ordinal_position"],
                    "type" => $row["udt_name"],
                    "full_type" => $row['full_type'],
                    "is_nullable" => !$row['is_nullable'],
                    "default_value" => $row['default_value'],
                    "character_maximum_length" => $row["character_maximum_length"],
                    "numeric_precision" => $row["numeric_precision"],
                    "numeric_scale" => $row["numeric_scale"],
                    "max_bytes" => $row["max_bytes"],
                    "reference" => $references,
                    "restriction" => sizeof($foreignValues) > 0 ? $foreignValues : null,
                    "is_primary" => !empty($index["is_primary"][$row["column_name"]]),
                    "is_unique" => !empty($index["is_unique"][$row["column_name"]]),
                    "index_method" => !empty($index["index_method"][$row["column_name"]]) ? $index["index_method"][$row["column_name"]] : null,
                    "checks" => sizeof($checkValues) > 0 ? $checkValues : null,
                );
                // Get type and srid of geometry
                if ($row["udt_name"] == "geometry") {
                    preg_match("/[A-Z]\w+/", $row["full_type"], $matches);
                    $arr[$row["column_name"]]["geom_type"] = $matches[0];
                    preg_match("/[0-9]+/", $row["full_type"], $matches);
                    $arr[$row["column_name"]]["srid"] = $matches[0];
                }
            }
            $CachedString->set($arr)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //$CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
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
     * @throws PDOException
     */
    function connect(string $type = "PDO"): void
    {
        switch ($type) {
            case "PG" :
                $c = pg_connect($this->connectString());
                $this->db = $c ?: null;
                break;
            case "PDO" :
                $this->db = new PDO("pgsql:dbname=$this->postgisdb;host=$this->postgishost;" . (($this->postgisport) ? "port=$this->postgisport" : ""), "$this->postgisuser", "$this->postgispw");
                $this->execQuery("set client_encoding='UTF8'");
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
     * @return mixed
     * @throws PhpfastcacheInvalidArgumentException
     */
    function getGeometryColumns(string $table, string $field): mixed
    {
        $response = [];

        $_schema = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[0] : "";

        $_table = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[1] : $table;

        if (!$_schema) {
            $_schema = $this->postgisschema ?: "";
        } else {
            $_schema = str_replace(".", "", $_schema);
        }

        $row = $this->getColumns($_schema, $_table)[0] ?? null;

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
            $arr = isset($row['def']) ? json_decode($row['def'], true) : [];
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
            $str = str_replace($replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        return preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
    }

    /**
     * Does NOT work with period in schema name.
     * @param string|null $table
     * @return array<string,string|null>
     */
    public static function explodeTableName(?string $table): array
    {
        if (!$table || !isset(explode(".", $table)[1])) {
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
        if (str_contains($name, '.')) {
            $split = self::explodeTableName($name);
            return "\"" . $split["schema"] . "\".\"" . $split["table"] . "\"";
        } else {
            return "\"" . $name . "\"";

        }
    }

    /**
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return array
     */
    private function array_push_assoc(array $array, string $key, mixed $value): array
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
        $sql = "SELECT count(*) AS count FROM pg_tables WHERE schemaname = '$bits[0]' AND tablename='$bits[1]'";
        $res = $this->prepare($sql);
        $res->execute();
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "TABLE";
            $response['success'] = true;
            return $response;
        }
        // Check if view
        $sql = "SELECT count(*) AS count FROM pg_views WHERE schemaname = '$bits[0]' AND viewname='$bits[1]'";
        $res = $this->prepare($sql);
        $res->execute();
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "VIEW";
            $response['success'] = true;
            return $response;
        }
        // Check if materialized view
        $sql = "SELECT count(*) AS count FROM pg_matviews WHERE schemaname = '$bits[0]' AND matviewname='$bits[1]'";
        $res = $this->prepare($sql);
        $res->execute();
        $row = $this->fetchRow($res);
        if ($row["count"] > 0) {
            $response['data'] = "MATERIALIZED VIEW";
            $response['success'] = true;
            return $response;
        }
        // Check if FOREIGN TABLE
        $sql = "SELECT count(*) FROM information_schema.foreign_tables WHERE foreign_table_schema='$bits[0]' AND  foreign_table_name='$bits[1]'";
        $res = $this->prepare($sql);
        $res->execute();
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
            $res->execute();
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
     * @throws PhpfastcacheInvalidArgumentException|PDOException
     */
    public function doesColumnExist(string $table, string $column): array
    {
        $cacheType = "columnExist";
        $cacheRel = ($table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $column . "_" . $cacheType);
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $response = [];
            $bits = explode(".", $table);
            $sql = "SELECT true AS exists FROM pg_attribute WHERE attrelid = '\"$bits[0]\".\"$bits[1]\"'::regclass AND attname = '$column' AND NOT attisdropped";
            $res = $this->prepare($sql);
            $res->execute();
            $row = $this->fetchRow($res);
            $response['success'] = true;
            $response['exists'] = isset($row["exists"]);
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //$CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string|null $schema
     * @param string $table
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getForeignConstrains(string|null $schema, string $table): array
    {
        $cacheType = "foreignConstrain";
        $cacheRel = ($schema . "." . $table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $cacheType);
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {

            $response = [];
            $sql = "SELECT att2.attname    AS child_column,
                       cl.relname      AS parent_table,
                       ns.nspname    AS parent_schema,
                       att.attname     AS parent_column,
                       conname
                FROM (SELECT unnest(con1.conkey)  AS parent,
                             unnest(con1.confkey) AS child,
                             con1.confrelid,
                             con1.conrelid,
                             con1.conname,
                             ns.nspname
                      FROM pg_class cl
                               JOIN pg_namespace ns ON cl.relnamespace = ns.oid
                               JOIN pg_constraint con1 ON con1.conrelid = cl.oid
                      WHERE cl.relname = :table
                        AND ns.nspname = :schema
                        AND con1.contype = 'f') con
                         JOIN pg_attribute att ON
                    att.attrelid = con.confrelid AND att.attnum = con.child
                         JOIN pg_class cl ON
                    cl.oid = con.confrelid
                         JOIN pg_attribute att2 ON
                    att2.attrelid = con.conrelid AND att2.attnum = con.parent
                        JOIN  pg_namespace ns on cl.relnamespace = ns.oid";

            $res = $this->prepare($sql);
            $res->execute(["table" => $table, "schema" => $schema]);
            $rows = $this->fetchAll($res);
            $response['success'] = true;
            $response['data'] = $rows;
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //$CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            return $response;
        }
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getConstrains(string|null $schema, string $table, ?string $type = null): array
    {
        $cacheType = "checkConstrain";
        $cacheRel = ($schema . "." . $table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $type . "_" . $cacheType);
        $CachedString = Cache::getItem($cacheId);
        $where = '';
        $params = ["table" => $table, "schema" => $schema];
        if ($type) {
            $where = "AND con1.contype = :type";
            $params = ["table" => $table, "schema" => $schema, "type" => $type];
        }
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $response = [];
            $sql = "SELECT relname, nspname as schema, attname as column_name, conname, con
                FROM (SELECT unnest(con1.conkey) AS key,
                             con1.confrelid,
                             con1.conrelid,
                             con1.conname,
                             ns.nspname,
                             cl.relname,
                             pg_get_constraintdef(con1.oid, true) as con
                      FROM pg_class cl
                               JOIN pg_namespace ns ON cl.relnamespace = ns.oid
                               JOIN pg_constraint con1 ON con1.conrelid = cl.oid
                      WHERE cl.relname = :table
                        AND ns.nspname = :schema 
                        $where) con
                         JOIN pg_attribute att ON
                    att.attrelid = con.conrelid AND att.attnum = con.key";

            $res = $this->prepare($sql);
            $res->execute($params);
            $rows = $this->fetchAll($res, 'assoc');

            $response['success'] = true;
            $response['data'] = $rows;

            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //$CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getChildTables(string $schema, string $table): array
    {
        $cacheType = "childTables";
        $cacheRel = ($schema . "." . $table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $cacheType);
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
            $res->execute(["table" => $table, "schema" => $schema]);

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
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //  $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            Cache::save($CachedString);
            return $response;
        }
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getColumns(string $schema, string $table): array
    {
        $cacheType = "columns";
        $cacheRel = ($schema . "." . $table);
        $cacheId = ($this->postgisdb . "_" . $cacheRel . "_" . $cacheType);
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $sql = "SELECT * FROM settings.getColumns('f_table_schema = ''$schema'' AND f_table_name = ''$table''','raster_columns.r_table_schema = ''$schema'' AND raster_columns.r_table_name = ''$table''')";
            $res = $this->prepare($sql);
            $res->execute();
            $rows = $this->fetchAll($res);
            $CachedString->set($rows)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //   $CachedString->addTags([$cacheType, $cacheRel, $this->postgisdb]);
            Cache::save($CachedString);
            return $rows;
        }
    }

    /**
     * Count the rows in a relation
     *
     * @param string $schema
     * @param string $table
     * @return array
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

    /**
     * @param string|null $schema
     * @param string $table
     * @return array
     */
    public function getIndexes(?string $schema, string $table): array
    {
        $response['is_primary'] = [];
        $response['is_unique'] = [];
        $response['indices'] = [];
        $response['index_method'] = [];
        $sql = "SELECT
                    n.nspname AS schema,
                    t.relname AS table,
                    c.relname AS index,
                    a.amname AS index_method,
                    opc.operator_classes,
                    pg_get_indexdef(i.indexrelid) AS index_definition,
                    att.attname AS column_name,
                    i.indisunique                 AS is_unique,
                    i.indisprimary                AS is_primary
                FROM
                    pg_catalog.pg_namespace n
                JOIN
                    pg_catalog.pg_class c ON c.relnamespace = n.oid
                JOIN
                    pg_catalog.pg_index i ON i.indexrelid = c.oid
                JOIN
                    pg_catalog.pg_am a ON a.oid = c.relam
                JOIN
                    pg_catalog.pg_class t ON t.oid = i.indrelid
                CROSS JOIN LATERAL (
                    SELECT ARRAY (SELECT opc.opcname
                                 FROM unnest(i.indclass::oid[]) WITH ORDINALITY o(oid, ord)
                                 JOIN pg_opclass opc ON opc.oid = o.oid
                                 ORDER BY o.ord)
                   ) opc(operator_classes)
                JOIN
                    pg_attribute att ON att.attnum = ANY(i.indkey) AND att.attrelid = t.oid
                LEFT JOIN
                    pg_constraint pkcon ON pkcon.conrelid = t.oid AND pkcon.contype = 'p' AND att.attnum = ANY(pkcon.conkey)
                WHERE
                    n.nspname !~ '^pg_'
                AND
                    c.relkind = 'i'
                    AND n.nspname = :schema
                AND t.relname = :table
                ORDER BY 1, 2, 3, 4";
        $res = $this->prepare($sql);
        $res->execute(["schema" => $schema, "table" => $table]);
        while ($row = $this->fetchRow($res)) {
            $response["index_method"][$row["column_name"]][] = $row["index_method"];
            $response["is_primary"][$row["column_name"]] = !empty($response["is_primary"][$row["column_name"]]) ? $response["is_primary"][$row["column_name"]] : $row["is_primary"];
            $response["is_unique"][$row["column_name"]] = !empty($response["is_unique"][$row["column_name"]]) ? $response["is_unique"][$row["column_name"]] : $row["is_unique"];
            $response["indices"][] = $row;

        }
        return $response;
    }

    public function getTablesFromSchema(string $schema): array
    {
        $response = [];
        $sql = "SELECT tablename as name FROM pg_tables WHERE schemaname = :schema";
        $res = $this->prepare($sql);
        $res->execute(["schema" => $schema]);
        while ($row = $this->fetchRow($res)) {
            $response[] = $row["name"];
        }
        return $response;
    }

    public function getViewsFromSchema(string $schema): array
    {
        $response = [];
        $sql = "SELECT viewname as name, schemaname, viewowner as owner,definition, 'f' as ismat FROM pg_views WHERE schemaname = :schema union ";
        $sql .= "SELECT matviewname as name, schemaname, matviewowner as owner, definition, 't' as ismat FROM pg_matviews WHERE schemaname = :schema";
        $res = $this->prepare($sql);
        $res->execute(['schema' => $schema]);
        while ($row = $this->fetchRow($res)) {
            $tmp = [];
            $tmp['name'] = $row['name'];
            $tmp['owner'] = $row['owner'];
            $tmp['ismat'] = $row['ismat'];
            $tmp['definition'] = $row['definition'];
            $response[] = $tmp;
        }
        return $response;
    }

    /**
     * @param array $schemas
     * @return int
     * @throws GC2Exception
     */
    public function storeViewsFromSchema(array $schemas): int
    {
        $db = new Database();
        $this->connect();
        $this->begin();
        $count = 0;
        foreach ($schemas as $schema) {
            if (!$db->doesSchemaExist($schema)) {
                throw new GC2Exception("Schema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            $views = $this->getViewsFromSchema($schema);
            foreach ($views as $view) {
                $sql = "INSERT INTO settings.views (name,schemaname,owner,definition,ismat,timestamp) VALUES(:name,:schemaname,:owner,:definition,:ismat,:timestamp)" .
                    " ON CONFLICT (name,schemaname) DO UPDATE SET name=:name,schemaname=:schemaname,owner=:owner,definition=:definition,ismat=:ismat,timestamp=:timestamp";
                $result = $this->prepare($sql);
                $result->execute(['name' => $view['name'], 'schemaname' => $schema, 'owner' => $view['owner'], 'definition' => $view['definition'], 'ismat' => $view['ismat'], 'timestamp' => date('Y-m-d H:i:s')]);
            }
            $count += sizeof($views);
        }
        $this->commit();
        return $count;
    }

    /**
     * @param string $schema
     * @return array
     */
    public function getStarViewsFromStore(string $schema): array
    {
        $response = [];
        $sql = "select * from settings.views where schemaname=:schemaname";
        $result = $this->prepare($sql);
        $result->execute(['schemaname' => $schema]);
        $rows = $this->fetchAll($result, 'assoc');
        foreach ($rows as $row) {
            $tmp = [];
            $def = $row['definition'];
            preg_match('#(?<=SELECT)(.|\n)*?(?= FROM)#', $def, $match);
            $tmp['definition'] = $match[0] ? str_replace($match[0], ' *', $def) : $def;
            $tmp['name'] = $row['name'];
            $tmp['schemaname'] = $row['schemaname'];
            $tmp['ismat'] = $row['ismat'];
            $response[] = $tmp;
        }
        return $response;
    }

    /**
     * @param array $schemas
     * @param array|null $targetSchemas
     * @param array|null $relations
     * @return int
     * @throws GC2Exception
     */
    public function createStarViewsFromStore(array $schemas, ?array $targetSchemas = null, ?array $relations = null): int
    {
        if ($targetSchemas && sizeof($schemas) != sizeof($targetSchemas)) {
            throw new GC2Exception("Schemas and targets must have the same number of entries", 500, null, null);
        } elseif (!$targetSchemas) {
            $targetSchemas = $schemas;
        }
        $count = 0;
        $db = new Database();
        $this->connect();
        $this->begin();
        for ($i = 0; $i < sizeof($schemas); $i++) {
            $schema = $schemas[$i];
            $targetSchema = $targetSchemas[$i];
            if (!$db->doesSchemaExist($schema)) {
                throw new GC2Exception("Schema $schema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            if (!$db->doesSchemaExist($targetSchema)) {
                throw new GC2Exception("Schema $targetSchema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            $views = $this->getStarViewsFromStore($schema);
            foreach ($views as $view) {
                if ($relations && !in_array($view['name'], $relations)) {
                    continue;
                }
                $mat = $view['ismat'] ? 'materialized' : '';
                $sql = "drop $mat view if exists \"$targetSchema\".\"{$view['name']}\"";
                $result = $this->prepare($sql);
                $result->execute();
                $sql = "create $mat view \"$targetSchema\".\"{$view['name']}\" as {$view['definition']}";
                $result = $this->prepare($sql);
                $result->execute();
                $count++;
            }
        }
        $this->commit();
        return $count;
    }
}
