<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

declare(strict_types=1);

namespace app\inc;

use app\api\v4\Constraint;
use app\conf\App;
use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\models\Cost;
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
        Connection::$param['postgisdb'] = Connection::$param['postgisdb'] ?? getenv('POSTGIS_DB');
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
    public function getMetaData(string $table, bool $temp = false, bool $restriction = true, array $restrictions = null, string $cacheKey = null, bool $getEnums = true, bool $lookupForeignTables = true): array
    {
        $cacheType = "metadata";
        $cacheRel = $cacheKey ?: $table;
        $cacheId = $this->postgisdb . "_" . $cacheRel . "_" . $cacheType . "_" . ($temp ? 'temp' : 'notTemp') . "_" . ($restriction ? 'restriction' : 'notRestriction') . "_" . ($getEnums ? 'enums' : 'notEnums') . "_" . ($restrictions ? 'restrictions_' . md5(serialize($restrictions)) : 'noRestrictions');
        $CachedString = Cache::getItem($cacheId);

        if ($CachedString != null && $CachedString->isHit()) {
            return $CachedString->get();
        } else {
            $arr = [];

            $_schema = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[0] : null;

            $_table = sizeof(explode(".", $table)) > 1 ? explode(".", $table)[1] : $table;

            if (!$_schema) {
                $_schema = !empty($this->postgisschema) ? $this->postgisschema : "public";
            } else {
                $_schema = str_replace(".", "", $_schema);
            }
            $primaryKey = $this->getPrimeryKey($_schema . '.' . $_table)['attname'];
            $foreignConstrains = $this->getForeignConstrains($_schema, $_table)["data"];
            $checkConstrains = $this->getConstrains($_schema, $_table, 'c')["data"];
            $sql = "SELECT
                  attname                          AS column_name,
                  attnum                           AS ordinal_position,
                  atttypid :: REGTYPE              AS udt_name,
                  typname,
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
            $comments = $this->getColumnComments($_schema, $_table);

            while ($row = $this->fetchRow($res)) {
                $column = $row["column_name"];
                $foreignValues = [];
                $checkValues = [];
                $references = [];
                if ($restriction && !$restrictions) {
                    foreach ($foreignConstrains as $value) {
                        if ($column == $value["child_column"] && $value["parent_column"] != $primaryKey) {
                            if ($getEnums && $lookupForeignTables) {
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
                    foreach ($t->getGroupByAsArray($column)["data"] as $value) {
                        $foreignValues[] = ["value" => $value, "alias" => (string)$value];
                    }
                }
                foreach ($foreignConstrains as $value) {
                    if ($column == $value["child_column"]) {
                        $references[] = $value["parent_schema"] . "." . $value["parent_table"] . "." . $value["parent_column"];
                    }
                }

                foreach ($checkConstrains as $check) {
                    if ($check['column_name'] == $column) {
                        $checkValues[] = $check["con"];
                    }
                }
                $arr[$row["column_name"]] = array(
                    "type" => $row['full_type'],
                    "is_nullable" => !$row['is_nullable'],
                    "default_value" => $row['default_value'],
                    "comment" => $comments[$column],
                    // Derived
                    "num" => $row["ordinal_position"],
                    "typname" => $row["typname"],
                    "udt_name" => $row["udt_name"],
                    "is_array" => (bool)preg_match("/\[]/", $row["udt_name"]),
                    "character_maximum_length" => $row["character_maximum_length"],
                    "numeric_precision" => $row["numeric_precision"],
                    "numeric_scale" => $row["numeric_scale"],
                    "max_bytes" => $row["max_bytes"],
                    "reference" => $references,
                    "restriction" => sizeof($foreignValues) > 0 ? $foreignValues : null,
                    "is_primary" => !empty($index["is_primary"][$row["column_name"]]),
                    "is_unique" => !empty($index["is_unique"][$row["column_name"]]),
                    "index_method" => !empty($index["index_method"][$row["column_name"]]) ? $index["index_method"][$row["column_name"]] : null,
                    "checks" => sizeof($checkValues) > 0 ? array_map(function ($con) {
                        preg_match('#\((.*?)\)#', $con, $match);
                        return $match[1];
                    }, $checkValues) : null,
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
                $this->db = new PDO("pgsql:dbname=$this->postgisdb;host=$this->postgishost;" . (($this->postgisport) ? "port=$this->postgisport" : ""), "$this->postgisuser", "$this->postgispw", [PDO::ATTR_EMULATE_PREPARES => true]);
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
            $rows = $this->fetchAll($res, 'assoc');
            $response['success'] = true;
            $response['data'] = $rows;
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);
            Cache::save($CachedString);
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
     * Counts the number of rows in a given table.
     *
     * @param string $schema The name of the schema.
     * @param string $table The name of the table.
     * @return array The number of rows in the table.
     * @throws Exception If there is an error while executing the query.
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
     * Retrieves information about indexes for a given schema and table.
     *
     * @param string|null $schema The name of the schema. If null, retrieves indexes for all schemas.
     * @param string $table The name of the table.
     * @return array An array containing information about the indexes. The structure of the array is as follows:
     *     - is_primary: An array that maps column names to boolean values indicating whether the column is part of a primary key.
     *     - is_unique: An array that maps column names to boolean values indicating whether the column is part of a unique index.
     *     - indices: An array containing the details of each index.
     *     - index_method: An array that maps column names to an array of index methods associated with the column.
     *
     * @throws PDOException If there is an error executing the SQL query.
     */
    public function getIndexes(?string $schema, string $table): array
    {
        $response['is_primary'] = [];
        $response['is_unique'] = [];
        $response['indices'] = [];
        $response['index_method'] = [];
        $sql = "SELECT
                    n.nspname AS schema,
                    t.relname AS \"table\",
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

    public function createView(string $statement, string $name): void
    {
        $sql = "CREATE VIEW " . $name . " AS " . $statement;
        $res = $this->prepare($sql);
        $res->execute();
    }

    public function createMatView(string $statement, string $name, bool $withNoData = false): void
    {
        $sql = "CREATE MATERIALIZED VIEW " . $name . " AS " . $statement;
        if ($withNoData) {
            $sql .= " WITH NO DATA";
        }
        $res = $this->prepare($sql);
        $res->execute();
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
            $tmp['schema'] = $row['schemaname'];
            $tmp['owner'] = $row['owner'];
            $tmp['ismat'] = $row['ismat'];
            $tmp['definition'] = $row['definition'];
            $response[] = $tmp;
        }
        return $response;
    }

    public function getForeignTablesFromSchema(string $schema): array
    {
        $response = [];
        $sql = "SELECT foreign_table_name as name FROM information_schema._pg_foreign_tables WHERE foreign_table_schema = :schema";
        $res = $this->prepare($sql);
        $res->execute(["schema" => $schema]);
        while ($row = $this->fetchRow($res)) {
            $response[] = $row["name"];
        }
        return $response;
    }

    /**
     * Stores views from the specified schemas into the settings.views table.
     *
     * @param array $schemas The array of schemas from which to retrieve and store views.
     *
     * @return int The total number of views stored across all schemas.
     * @throws GC2Exception If a specified schema does not exist.
     *
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
     * Retrieves a list of star view definitions from the specified schema.
     *
     * @param string $schema The name of the schema from which to retrieve star view definitions.
     *
     * @return array An array of star view definitions, each including the view name, schema name, modified definition, and materialization status.
     *
     */
    public function getStarViewsFromStore(string $schema): array
    {
        ini_set('pcre.jit', 0); // So longer definitions doesn't raise a PHP PREG_JIT_STACKLIMIT_ERROR
        $response = [];
        $sql = "select * from settings.views where schemaname=:schemaname";
        $result = $this->prepare($sql);
        $result->execute(['schemaname' => $schema]);
        $rows = $this->fetchAll($result, 'assoc');
        foreach ($rows as $row) {
            $tmp = [];
            $def = $row['definition'];
            preg_match('#(?<=FROM )(.|\n)*?((?=,)|(?=\s)|(?=;)|(?=$))#', $def, $matches);
            $replacement = " $matches[0].*";
            $tmp['definition'] = preg_replace('#(?<=SELECT)(.|\n)*?(?= FROM)#', $replacement, $def, 1);
            $tmp['name'] = $row['name'];
            $tmp['schemaname'] = $row['schemaname'];
            $tmp['ismat'] = $row['ismat'];
            $response[] = $tmp;
        }
        return $response;
    }

    /**
     * Creates star views from the store.
     *
     * @param array $schemas The array of schemas to create star views from.
     * @param array|null $targetSchemas Optional. The array of target schemas. If not provided, $schemas will be used as the target schemas.
     * @param array|null $include Optional. The array of view names to include. Only views with these names will be created. If not provided, all views will be created.
     *
     * @return int The number of star views successfully created.
     *
     * @throws GC2Exception If the number of schemas and target schemas is different, or if a schema is not found in the database.
     */
    public function createStarViewsFromStore(array $schemas, ?array $targetSchemas = null, ?array $include = null): int
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
                if ($include && !in_array($view['name'], $include)) {
                    continue;
                }
                $mat = $view['ismat'] ? 'materialized' : '';
                $name = "\"$targetSchema\".\"{$view['name']}\"";
                $sql = "drop $mat view if exists $name";
                $result = $this->prepare($sql);
                $result->execute();
                $sql = "create $mat view $name as {$view['definition']}";
                $result = $this->prepare($sql);
                $result->execute();
                $count++;
            }
        }
        $this->commit();
        return $count;
    }

    /**
     * Imports foreign schemas into target schemas using a given database server.
     *
     * @param array<string> $schemas The list of schemas to be imported.
     * @param array<string> $targetSchemas The list of target schemas where the foreign schemas will be imported.
     * @param string $server The name of the database server.
     * @param array<string>|null $include Optional. The list of schemas to include during import. If null, all schemas will be imported.
     *
     * @return void
     * @throws GC2Exception If the target schema is not found.
     *
     * @throws GC2Exception If the number of schemas and target schemas does not match.
     */
    public function importForeignSchema(array $schemas, array $targetSchemas, string $server, ?array $include): void
    {
        if ($targetSchemas && sizeof($schemas) != sizeof($targetSchemas)) {
            throw new GC2Exception("Schemas and targets must have the same number of entries", 500, null, null);
        } elseif (!$targetSchemas) {
            $targetSchemas = $schemas;
        }

        $db = new Database();
        $this->connect();
        $this->begin();
        for ($i = 0; $i < sizeof($schemas); $i++) {
            $schema = $schemas[$i];
            $targetSchema = $targetSchemas[$i];
            if (!$db->doesSchemaExist($targetSchema)) {
                throw new GC2Exception("Schema $targetSchema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            $limitTo = '';
            if ($include) {
                $limitTo = "LIMIT TO (\"" . implode("\",\"", $include) . "\")";
            }
            $sql = "IMPORT FOREIGN SCHEMA $schema $limitTo FROM SERVER $server INTO $targetSchema";
            $result = $this->prepare($sql);
            $result->execute();
        }
        $this->commit();
    }

    /**
     * Materializes foreign tables by creating materialized views in specified target schemas.
     *
     * @param array $schemas An array of schemas from where the foreign tables will be materialized.
     * @param array|null $targetSchemas An array of target schemas where the materialized views will be created. If null, the target schemas will be the same as the source schemas.
     * @param string|null $prefix Optional prefix to be added to the names of the materialized views.
     * @param string|null $suffix Optional suffix to be added to the names of the materialized views.
     * @param array|null $include An array of table names to be included. If provided, only the tables that are included in this array will be materialized.
     * @return int The number of materialized views created.
     * @throws GC2Exception If schemas and targetSchemas have different number of entries, or if a specified schema is not found.
     */
    public function materializeForeignTables(array $schemas, ?array $targetSchemas, ?string $prefix = '', ?string $suffix = '', ?array $include = null): int
    {
        if ($targetSchemas && sizeof($schemas) != sizeof($targetSchemas)) {
            throw new GC2Exception("Schemas and targets must have the same number of entries", 500, null, null);
        } elseif (!$targetSchemas) {
            $targetSchemas = $schemas;
        }

        $db = new Database();
        $this->connect();
        $this->begin();
        $count = 0;
        for ($i = 0; $i < sizeof($schemas); $i++) {
            $schema = $schemas[$i];
            $targetSchema = $targetSchemas[$i];
            if (!$db->doesSchemaExist($schema)) {
                throw new GC2Exception("Schema $schema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            if (!$db->doesSchemaExist($targetSchema)) {
                throw new GC2Exception("Schema $targetSchema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            if (!$prefix) {
                $prefix = '';
            }
            if (!$suffix) {
                $suffix = '';
            }
            $foreignTables = $this->getForeignTablesFromSchema($schema);
            $count = 0;
            foreach ($foreignTables as $foreignTable) {
                $name = "\"$targetSchema\".\"$prefix$foreignTable$suffix\"";
                if ($include && !in_array($foreignTable, $include)) {
                    continue;
                }
                $sql = "drop materialized view if exists $name";
                $result = $this->prepare($sql);
                $result->execute();
                $sql = "create materialized view $name as select * from \"$schema\".\"$foreignTable\" with no data";
                $result = $this->prepare($sql);
                $result->execute();
                $count++;
            }
        }
        $this->commit();
        return $count;
    }

    public function refreshMatViews(array $schemas, ?array $include = null): int
    {
        $count = 0;
        foreach ($schemas as $schema) {
            $views = $this->getViewsFromSchema($schema);
            foreach ($views as $view) {
                if ($include && !in_array($view['name'], $include)) {
                    continue;
                }
                if ($view['ismat'] == 't') {
                    $sql = "refresh materialized view \"$schema\".\"{$view['name']}\"";
                    $result = $this->prepare($sql);
                    $result->execute();
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Deletes foreign tables from the specified schemas.
     *
     * @param array $schemas The array of schemas from which to delete the foreign tables.
     * @param array|null $include An optional array of foreign tables to include. If provided, only the specified foreign tables will be deleted.
     *
     * @return int The number of foreign tables deleted.
     * @throws GC2Exception If a specified schema does not exist.
     *
     */
    public function deleteForeignTables(array $schemas, ?array $include = null): int
    {
        $db = new Database();
        $this->connect();
        $this->begin();
        $count = 0;
        foreach ($schemas as $schema) {
            if (!$db->doesSchemaExist($schema)) {
                throw new GC2Exception("Schema $schema not found", 404, null, "SCHEMA_NOT_FOUND");
            }
            $foreignTables = $this->getForeignTablesFromSchema($schema);
            $count = 0;
            foreach ($foreignTables as $foreignTable) {
                if ($include && !in_array($foreignTable, $include)) {
                    continue;
                }
                $sql = "drop foreign table \"$schema\".\"$foreignTable\" cascade";
                $result = $this->prepare($sql);
                $result->execute();
                $count++;
            }
        }
        $this->commit();
        return $count;
    }

    /**
     * Retrieves statistics about database tables including their size, row count, and associated costs.
     *
     * The method compiles a list of tables and their respective schema names, sizes in various formats
     * (human-readable and bytes), row counts, and the total cumulative size of all tables.
     * Additionally, it includes the storage cost associated with the database.
     *
     * @return array<string, mixed> An array containing:
     *                              - "tables" (array<int, array<string, mixed>>): List of table statistics.
     *                              - "totalSize" (int): Total size in bytes of all tables.
     *                              - "totalSizePretty" (string): Human-readable total size of all tables.
     *                              - "numberOfTables" (int): Number of tables.
     *                              - "cost" (float): Storage cost of the database.
     */
    public function getStats(): array
    {
        $sql = "SELECT distinct i.relname                                     as \"table_name\",
                                i.schemaname                                  as \"schema_name\",
                                pg_size_pretty(pg_total_relation_size(relid)) as \"total_size\",
                                pg_total_relation_size(relid)                 as \"total_size_bytes\",
                                pg_size_pretty(pg_relation_size(relid))       as \"table_size\",
                                pg_relation_size(relid)                       as \"table_size_bytes\",
                                pg_size_pretty(pg_indexes_size(relid))        as \"indices_size\",
                                pg_indexes_size(relid)                        as \"indices_size_bytes\",
                                reltuples::bigint                                \"row_count\"
                FROM pg_stat_all_tables i
                         join pg_class c ON i.relid = c.oid
                WHERE i.schemaname NOT IN ('information_schema', 'settings')
                  AND i.schemaname NOT LIKE 'pg_%' and i.relname != 'spatial_ref_sys'";

        $res = $this->prepare($sql);
        $res->execute();
        $totalSize = 0;
        $tables = [];
        foreach ($this->fetchAll($res, 'assoc') as $table) {
            $tables[] = $table;
            $totalSize += $table['total_size_bytes'];
        }
        $sql = "select pg_size_pretty($totalSize::bigint) as p";
        $res = $this->prepare($sql);
        $res->execute();
        $row = $res->fetchAll();
        try {
            $cost = (new Cost())->getCost();
        } catch (PDOException) {
            $cost = 0.0;
        }
        return [
            "tables" => $tables,
            "total_size" => $row[0]['p'],
            "total_size_byte" => $totalSize,
            "number_of_tables" => count($tables),
            "cost" => $cost,
        ];
    }

    /**
     * Retrieves the total number of tables in the Postgres database.
     *
     * The method executes a SQL query to retrieve the count of tables from the "pg_tables" system catalog table.
     * The query filters out tables from certain system schemas and excludes specific tables.
     *
     * @return int The total number of tables in the database.
     */
    public function getNumTables(): int
    {
        $sql = "select count(*) as c
                from pg_tables
                WHERE schemaname NOT IN ('information_schema', 'settings')
                  AND schemaname NOT LIKE 'pg_%'
                  and tablename != 'spatial_ref_sys'";

        $res = $this->prepare($sql);
        $res->execute();
        return $res->fetchColumn();
    }

    /**
     * Checks if a relation (table, view, etc.) exists in the database.
     * @param string $rel The name of the relation to check.
     * @return bool True if the relation exists, false otherwise.
     */
    public function doesRelationExists(string $rel): bool
    {
        $sql = "SELECT FROM " . $this->doubleQuoteQualifiedName($rel) . " LIMIT 1";
        try {
            $this->execQuery($sql);
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    private function getColumnComments(string $schema, string $table): false|array
    {
        $sql = "SELECT
                    cols.column_name, (
                    SELECT
                        pg_catalog.col_description(c.oid, cols.ordinal_position::int)
                    FROM
                        pg_catalog.pg_class c
                    WHERE
                        c.oid = (SELECT ('\"' || cols.table_schema || '\".\"' || cols.table_name || '\"')::regclass::oid)
                      AND c.relname = cols.table_name
                ) AS column_comment
                FROM
                    information_schema.columns cols
                WHERE
                   cols.table_name   = :table
                  AND cols.table_schema = :schema";

        $res = $this->prepare($sql);
        $res->execute(['table' => $table, 'schema' => $schema]);
        $comments = [];
        foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $comments[$row['column_name']] = $row['column_comment'];
        }
        return $comments;
    }

    public function getTableComment(string $schema, string $table)
    {
        $sql = "SELECT t.table_name, pg_catalog.obj_description(pgc.oid, 'pg_class')
                FROM information_schema.tables t
                         INNER JOIN pg_catalog.pg_class pgc
                                    ON t.table_name = pgc.relname
                WHERE t.table_type='BASE TABLE'
                  AND t.table_name=:table
                  AND t.table_schema=:schema";

        $res = $this->prepare($sql);
        $res->execute(['table' => $table, 'schema' => $schema]);
        return $res->fetchColumn(1);
    }
}
