<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Globals;
use app\inc\Session;
use PDO;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;


/**
 * Class Layer
 * @package app\models
 */
class Layer extends Table
{
    function __construct(public ?Connection $connection = null)
    {
        parent::__construct(table: "settings.geometry_columns_view", connection: $this->connection);;
    }

    /**
     *
     * @throws InvalidArgumentException
     */
    private function clearCacheOnSchemaChanges(): void
    {
        $patterns = [
            $this->postgisdb . '*_meta_*',
        ];
        Cache::deleteByPatterns($patterns);
    }

    /**
     *
     * @throws InvalidArgumentException
     */
    private function clearCacheOfColumns($relName): void
    {
        $patterns = [
            $this->postgisdb . '_' . md5($relName) . '_columns',
        ];
        Cache::deleteByPatterns($patterns);
    }

    /**
     * @param string $schema The schema in which the table resides.
     * @param string $table The name of the table to retrieve geometry columns from.
     * @return array The list of geometry columns from the specified table.
     */
    public function getGeometryColumnsFromTable(string $schema, string $table): array
    {
        $sql = "select f_geometry_column from settings.geometry_columns_view where f_table_name=:table and f_table_schema=:schema";
        $res = $this->prepare($sql);
        $this->execute($res, ['table' => $table, 'schema' => $schema]);
        return $res->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array
     */
    public function getPrivilegesAsArray(string $schema, string $table): array
    {
        $sql = "select distinct privileges from settings.geometry_columns_view where f_table_name=:table and f_table_schema=:schema";
        $res = $this->prepare($sql);
        $this->execute($res, ['table' => $table, 'schema' => $schema]);
        $privileges = $res->fetchAll(PDO::FETCH_COLUMN);
        $response = [];
        foreach ($privileges as $privilege) {
            $p = json_decode($privilege, true);
        }
        foreach ($p as $k => $v) {
            $response[] = ['subuser' => $k, 'privilege' => $v];
        }
        return $response;
    }

    /**
     * @param string $_key_
     * @param string $column
     * @return string|null
     */
    public function getValueFromKey(string $_key_, string $column): ?string
    {
        $sql = "select $column from $this->table where _key_=:key";
        $res = $this->prepare($sql);
        $this->execute($res, ['key' => $_key_]);
        $row = $this->fetchRow($res);
        return $row[$column];
    }

    /**
     * @param string $db
     * @param bool|null $auth
     * @param string|null $query
     * @param bool|null $includeExtent
     * @param bool|null $parse
     * @param bool|null $es
     * @param bool|null $lookupForeignTables
     * @return array
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getAll(string $db, ?bool $auth, ?string $query = null, ?bool $includeExtent = false, ?bool $parse = false, ?bool $es = false, ?bool $lookupForeignTables = true): array
    {
        // If user is signed in with another user than the requested,
        // when consider the user as not signed in.
        if ($db != Session::getUser()) {
            //$auth = null;
        }

        $cacheType = "meta";
        $cacheId = $this->postgisdb . "_" . Session::getUser() . "_" . $cacheType . "_" . md5($query . "_" . "(int)$auth" . "_" . (int)$includeExtent . "_" . (int)$parse . "_" . (int)$es);

        $CachedString = Cache::getItem($cacheId);

        if ($CachedString != null && $CachedString->isHit()) {
            $data = $CachedString->get();
            $response = $data;
            try {
                $response["cache"]["hit"] = $CachedString->getCreationDate();
                $response["cache"]["tags"] = $CachedString->getTags();
            } catch (PhpfastcacheLogicException $exception) {
                $response["cache"] = $exception->getMessage();
            }
            $response["cache"]["signature"] = md5(serialize($data));
            return $response;
        } else {
            $response = [];
            $schemata = [];
            $layers = [];
            $tags = [];
            $sqls = [];

            if ($query) {
                foreach (explode(",", $query) as $part) {
                    // Check for schema qualified rels
                    if (sizeof(explode(".", $part)) > 1) {
                        $layers[] = $part;

                    } // Check for tags
                    elseif (sizeof(explode(":", $part)) > 1 && explode(":", $part)[0] == "tag") {
                        $tags[] = explode(":", $part)[1];
                    } else {
                        $schemata[] = $part;

                    }
                }
            }

            $where = $auth ?
                "(authentication<>''foo'' OR authentication is NULL)" :
                "(authentication=''Write'' OR authentication=''None'')";
            $case = "CASE WHEN ((layergroup = '' OR layergroup IS NULL) AND baselayer != true) THEN 9999999 else sort_id END";

            if (sizeof($schemata) > 0) {
                $schemaStr = "''" . implode("'',''", $schemata) . "''";
                $sqls[] = "(SELECT *, ($case) as sort FROM settings.getColumns('f_table_schema in ($schemaStr) AND $where','raster_columns.r_table_schema in ($schemaStr) AND $where'))";
            }

            if (sizeof($layers) > 0) {
                foreach ($layers as $layer) {
                    $split = explode(".", $layer);
                    $sqls[] = "(SELECT *, ($case) as sort FROM settings.getColumns('f_table_schema = ''$split[0]'' AND f_table_name = ''$split[1]'' AND $where','raster_columns.r_table_schema = ''$split[0]'' AND raster_columns.r_table_name = ''$split[1]'' AND $where'))";
                }
            }

            if (sizeof($tags) > 0) {
                foreach ($tags as $tag) {
                    $tag = urldecode($tag);
                    $sqls[] = "(SELECT *, ($case) as sort FROM settings.getColumns('tags ? ''$tag'' AND $where','tags ? ''$tag'' AND $where'))";
                }
            }

            if (sizeof($schemata) == 0 && sizeof($layers) == 0 && sizeof($tags) == 0) {
                $sqls[] = "(SELECT *, ($case) as sort FROM settings.getColumns('$where','$where'))";
            }

            $sql = implode(" UNION ALL ", $sqls);

            $res = $this->prepare($sql);
            $this->execute($res);

            // Check if Es is online
            // =====================
            $esOnline = false;
            $split = explode(":", App::$param['esHost'] ?? '' ?: "http://127.0.0.1");
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

            while ($row = $this->fetchRow($res)) {
                // TODO Here check privileges and continue loop if user doesn't has access
                //if (isset($_SESSION) && $_SESSION["subuser"]) {
                //    $privileges = (array)json_decode($row["privileges"]);
                //}

                $arr = array();
                $schema = $row['f_table_schema'];
                $rel = $row['f_table_schema'] . "." . $row['f_table_name'];
                $primeryKey = $this->getPrimeryKey($rel); // Is cached
                $resVersioning = $this->doesColumnExist($rel, "gc2_version_gid");
                $relType = $this->isTableOrView($rel)['data'];
                $versioning = $resVersioning["exists"];
                $extent = null;
                if ($row['type'] != "RASTER" && $includeExtent) {
                    $srsTmp = "3857";
                    $sqls = "SELECT ST_Xmin(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS xmin,ST_Xmax(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS xmax, ST_Ymin(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS ymin,ST_Ymax(ST_Extent(public.ST_Transform(\"" . $row['f_geometry_column'] . "\",$srsTmp))) AS ymax  FROM {$row['f_table_schema']}.{$row['f_table_name']}";
                    $resExtent = $this->prepare($sqls);
                    $this->execute($resExtent);
                    $extent = $this->fetchRow($resExtent);
                }
                $restrictions = [];
                foreach ($row as $key => $value) {
                    // Set empty strings to NULL
                    $value = $value == "" ? null : $value;
                    if ($key == "type" && $value == "GEOMETRY") {
                        $def = isset($row['def']) ? json_decode($row['def'], true) : [];
                        if (isset($def['geotype']) && $def['geotype'] != "Default") {
                            $value = "MULTI" . $def['geotype'];
                        }
                    }
                    if ($key == "fieldconf" && ($value)) {
                        $obj = json_decode($value, true);
                        if (is_array($obj)) {
                            foreach ($obj as $k => $val) {
                                $props = null;
                                if (!empty($val["properties"])) {
                                    $props = json_decode($val["properties"]);
                                }
                                // We check if JSON is written with single quotes
                                if ($props == null && !empty($val["properties"])) {
                                    $props = json_decode(str_replace("'", "\"", $val["properties"]));
                                }
                                if ($val["properties"] == "*") {
                                    $restrictions[$k] = "*";
                                } elseif (is_object($props) || is_array($props)) {
                                    $restrictions[$k] = $props;
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
                                $key == "classwizard" ||
                                $key == "meta" ||
                                $key == "tags" ||
                                $key == "privileges"
                            ) && ($value)
                        ) {
                            $value = json_decode($value);
                        }
                    }
                    if ($key == "f_table_abstract") {
                        $value = $this->getTableComment($row['f_table_schema'], $row['f_table_name']) ?? $row['f_table_abstract'];
                    }
                    $arr = $this->array_push_assoc($arr, $key, $value);

                }
                $arr = $this->array_push_assoc($arr, "pkey", $primeryKey['attname']);

                $arr = $this->array_push_assoc($arr, "versioning", $versioning);

                $arr = $this->array_push_assoc($arr, "rel_type", $relType);

                if ($includeExtent) {
                    $arr = $this->array_push_assoc($arr, "extent", $extent);
                }
                // Is indexed?
                if ($es && $esOnline) {
                    $type = $row['f_table_name'];
                    if (mb_substr($type, 0, 1, 'utf-8') == "_") {
                        $type = "a" . $type;
                    }
                    $url = $esUrl . "/{$this->postgisdb}_{$row['f_table_schema']}_$type/_mapping/";
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
                    curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
                    curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpcode == "200") {
                        $arr = $this->array_push_assoc($arr, "indexed_in_es", true);
                        // Get mapping
                        $url = "$esUrl/{$this->postgisdb}_{$row['f_table_schema']}_$type/_mapping/$type/";
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0.1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 0.1);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
                        ));
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
                } else {
                    $arr = $this->array_push_assoc($arr, "indexed_in_es", null);
                }

                // Restrictions
                // Cached
                if (is_object($arr["fieldconf"])) {
                    $fieldConf = json_decode(json_encode($arr["fieldconf"]), true);
                } elseif (!empty($arr["fieldconf"])) {
                    $fieldConf = json_decode($arr["fieldconf"], true);
                } else {
                    $fieldConf = [];
                }
                $fields = $this->getMetaData($rel, false, true, $restrictions, null, true, $lookupForeignTables);

                // If column comment is empty, we output from field conf
                foreach ($fields as $key => $field) {
                    if (empty($field['comment'])) {
                        $fields[$key]['comment'] = $fieldConf[$key]['desc'];
                    }
                    $fields[$key]['alias'] = $fieldConf[$key]['alias'];
                    $fields[$key]['queryable'] = (bool)$fieldConf[$key]['querable'];
                    $fields[$key]['sort_id'] = $fieldConf[$key]['sort_id'];
                }

                // Sort fields
                uksort($fields, function ($a, $b) use ($fieldConf) {
                    if (isset($fieldConf[$a]) && isset($fieldConf[$b])) {
                        $sortIdA = (int)$fieldConf[$a]['sort_id'];
                        $sortIdB = (int)$fieldConf[$b]['sort_id'];
                        return $sortIdA - $sortIdB;
                    }
                    return 0;
                });
                // Filter out ignored fields
                $fields = array_filter($fields, function ($item, $key) use (&$fieldConf) {
                    if (empty($fieldConf[$key]['ignore'])) {
                        return true;
                    }
                    return false;
                }, ARRAY_FILTER_USE_BOTH);
                $arr = $this->array_push_assoc($arr, "fields", $fields);

                // References
                if (!empty($row["meta"]) &&
                    json_decode($row["meta"]) &&
                    isset(json_decode($row["meta"], true)["referenced_by"]) &&
                    json_decode($row["meta"], true)["referenced_by"]
                ) {
                    $refBy = json_decode(json_decode($row["meta"], true)["referenced_by"], true);
                    $arr = $this->array_push_assoc($arr, "children", $refBy);
                } else {
                    // Cached
                    $arr = $this->array_push_assoc($arr, "children", !empty($this->getChildTables($row["f_table_schema"], $row["f_table_name"])["data"]) ? $this->getChildTables($row["f_table_schema"], $row["f_table_name"])["data"] : null);
                }

                // If session is sub-user we always check privileges
                if (isset($_SESSION["subuser"]) && $_SESSION["subuser"]) {
                    $privileges = (array)json_decode($row["privileges"]);
                    if (($privileges[$_SESSION['usergroup'] ?: $_SESSION['screen_name']] != "none" && $privileges[$_SESSION['usergroup'] ?: $_SESSION['screen_name']])) {
                        $response['data'][] = $arr;
                    } elseif ($_SESSION['screen_name'] == $schema || $_SESSION['usergroup'] == $schema) {
                        $response['data'][] = $arr;
                        // Always add layers with Write and None.
                    } elseif ($row["authentication"] == "None" || $row["authentication"] == "Write") {
                        $response['data'][] = $arr;
                    }
                } else {
                    $response['data'][] = $arr;
                }
            }
            $response['data'] = $response['data'] ?? array();

            // Remove dups
            $response['data'] = array_unique($response['data'], SORT_REGULAR);

            // Reindex array
            $response['data'] = array_values($response['data']);

            // Resort data, because a mix of schema and tags search will not be sorted right
            usort($response['data'], function ($a, $b) {
                if ($a['sort_id'] === $b['sort_id']) {
                    $a['f_table_name'] = strtolower($a['f_table_title'] ?? $a['f_table_name']);
                    $b['f_table_name'] = strtolower($b['f_table_title'] ?? $b['f_table_name']);
                    if (App::$param["reverseLayerOrder"]) {
                        return $a['f_table_name'] <=> $b['f_table_name'];
                    } else {
                        return $b['f_table_name'] <=> $a['f_table_name'];
                    }
                }
                return $a['sort_id'] <=> $b['sort_id'];
            });

            if (App::$param["reverseLayerOrder"]) {
                $response['data'] = array_reverse($response['data']);
            }
            $response['auth'] = $auth ?: false;
            $response['success'] = true;
            $response['message'] = "geometry_columns_view fetched";
            $CachedString->set($response)->expiresAfter(Globals::$cacheTtl);//in seconds, also accepts Datetime
            //   $CachedString->addTags([$cacheType, $this->postgisdb]);
            Cache::save($CachedString);
            $response["cache"]["hit"] = false;
        }
        return $response;
    }

    /**
     * @param string $_key_
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getElasticsearchMapping(string $_key_): array
    {
        $elasticsearch = new Elasticsearch();
        $response['success'] = true;
        $response['message'] = "Map loaded";

        $arr = [];
        $keySplit = explode(".", $_key_);
        $table = new Table($keySplit[0] . "." . $keySplit[1], false);
        $elasticsearchArr = (array)json_decode($this->getGeometryColumns($keySplit[0] . "." . $keySplit[1], "elasticsearch"));
        foreach ($table->metaData as $key => $value) {
            $esType = $elasticsearch->mapPg2EsType($value['type'], !empty($value['geom_type']) && $value['geom_type'] == "POINT");
            $arr = $this->array_push_assoc($arr, "id", $key);
            $arr = $this->array_push_assoc($arr, "column", $key);
            $arr = $this->array_push_assoc($arr, "elasticsearchtype", $elasticsearchArr[$key]->elasticsearchtype ?: $esType["type"]);
            $arr = $this->array_push_assoc($arr, "format", !empty($elasticsearchArr[$key]->format) ? $elasticsearchArr[$key]->format : (!empty($esType["format"]) ? $esType["format"] : ""));
            $arr = $this->array_push_assoc($arr, "index", $elasticsearchArr[$key]->index);
            $arr = $this->array_push_assoc($arr, "analyzer", !empty($elasticsearchArr[$key]->analyzer) ? $elasticsearchArr[$key]->analyzer : null);
            $arr = $this->array_push_assoc($arr, "index_analyzer", !empty($elasticsearchArr[$key]->index_analyzer) ? $elasticsearchArr[$key]->index_analyzer : null);
            $arr = $this->array_push_assoc($arr, "search_analyzer", !empty($elasticsearchArr[$key]->search_analyzer) ? $elasticsearchArr[$key]->search_analyzer : null);
            $arr = $this->array_push_assoc($arr, "boost", !empty($elasticsearchArr[$key]->boost) ? $elasticsearchArr[$key]->boost : null);
            $arr = $this->array_push_assoc($arr, "null_value", !empty($elasticsearchArr[$key]->null_value) ? $elasticsearchArr[$key]->null_value : null);
            $arr = $this->array_push_assoc($arr, "fielddata", !empty($elasticsearchArr[$key]->fielddata) ? $elasticsearchArr[$key]->fielddata : null);
            if ($value['typeObj']['type'] == "decimal") {
                $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']} ({$value['typeObj']['precision']} {$value['typeObj']['scale']})");
            } else {
                $arr = $this->array_push_assoc($arr, "type", "{$value['typeObj']['type']}");
            }
            $response['data'][] = $arr;
        }
        return $response;
    }

    /**
     * @param $data
     * @param $_key_
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
     */
    public function updateElasticsearchMapping($data, $_key_): array
    {
        $this->clearCacheOnSchemaChanges();
        $table = new Table("settings.geometry_columns_join");
        $data = $table->makeArray($data);
        $elasticsearchArr = (array)json_decode($this->getValueFromKey($_key_, "elasticsearch"));
        foreach ($data as $value) {
            //$safeColumn = self::toAscii($value->column, array(), "_");
            $safeColumn = $value->column;
            if ($value->id != $value->column && ($value->column) && ($value->id)) {
                unset($elasticsearchArr[$value->id]);
            }
            $elasticsearchArr[$safeColumn] = $value;
        }
        $conf['elasticsearch'] = json_encode($elasticsearchArr);
        $conf['_key_'] = $_key_;
        $table->updateRecord($conf, "_key_");
        $response['success'] = true;
        $response['message'] = "Map updated";
        return $response;
    }

    /**
     * Helper method
     * @param $array array
     * @param $key string
     * @param $value mixed
     * @return array
     */
    private function array_push_assoc(array $array, string $key, mixed $value): array
    {
        $array[$key] = $value;
        return $array;
    }

    /**
     * Renames a database table to a new specified name.
     *
     * @param string $tableName The current name of the table, including the schema (e.g., "schema.table").
     * @param string $newTableName The desired new name of the table.
     * @return array<string, mixed> Returns an array containing the success status, a message, and the new table name.
     * @throws GC2Exception|InvalidArgumentException If the rename operation or any related query execution fails due to a database error.
     */
    public function rename(string $tableName, string $newTableName): array
    {
        $this->clearCacheOnSchemaChanges();
        $split = explode(".", $tableName);
        $newName = self::toAscii($newTableName, [], "_");
        if (is_numeric(mb_substr($newName, 0, 1, 'utf-8'))) {
            $newName = "_" . $newName;
        }
        $whereClauseG = "f_table_schema=''$split[0]'' AND f_table_name=''$split[1]''";
        $whereClauseR = "r_table_schema=''$split[0]'' AND r_table_name=''$split[1]''";
        $query = "SELECT * FROM settings.getColumns('$whereClauseG','$whereClauseR') ORDER BY sort_id";
        $res = $this->prepare($query);
        try {
            $this->execute($res);
            while ($row = $this->fetchRow($res)) {
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '{$row['f_table_schema']}.$newName.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                try {
                    $this->execute($resUpdate);
                } catch (PDOException $e) {
                    throw new GC2Exception($e->getMessage(), 400, null);
                }
            }
            $sql = "ALTER TABLE " . $this->doubleQuoteQualifiedName($tableName) . " RENAME TO $newName";
            $res = $this->prepare($sql);
            try {
                $this->execute($res);
            } catch (PDOException $e) {
                throw new GC2Exception($e->getMessage(), 400, null);
            }
        } catch (PDOException $e) {
            throw new GC2Exception($e->getMessage(), 400, null);
        }
        $response['success'] = true;
        $response['message'] = "Layer renamed";
        $response['name'] = $newName;
        return $response;
    }

    /**
     * Updates the schema of the specified tables and performs necessary data migrations.
     *
     * @param array<string> $tables List of fully qualified table names to move, in the format "schema.table".
     * @param string $schema The target schema to which the tables should be moved.
     * @return array<string, mixed> An array containing the success status and a message.
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function setSchema(array $tables, string $schema): array
    {
        $this->clearCacheOnSchemaChanges();
        foreach ($tables as $table) {
            $bits = explode(".", $table);
            $whereClauseG = "f_table_schema=''$bits[0]'' AND f_table_name=''$bits[1]''";
            $whereClauseR = "r_table_schema=''$bits[0]'' AND r_table_name=''$bits[1]''";
            $query = "SELECT * FROM settings.getColumns('$whereClauseG','$whereClauseR') ORDER BY sort_id";
            $res = $this->prepare($query);
            $this->execute($res);
            while ($row = $this->fetchRow($res)) {
                // First, delete keys from destination schema if they exist
                $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ = '$schema.$bits[1].{$row['f_geometry_column']}'";
                $resDelete = $this->prepare($query);
                $this->execute($resDelete);
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '$schema.$bits[1].{$row['f_geometry_column']}' WHERE _key_ ='$bits[0].$bits[1].{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                $this->execute($resUpdate);
            }
            $query = "ALTER TABLE " . $this->doubleQuoteQualifiedName($table) . " SET SCHEMA $schema";
            $res = $this->prepare($query);
            $this->execute($res);
        }
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables moved to $schema";
        return $response;
    }

    /**
     * Deletes the specified tables or views from the schema.
     *
     * @param array<string> $tables An array of table or view names to be deleted.
     * @return array<string, mixed> An associative array containing the success status and a message.
     * @throws GC2Exception|InvalidArgumentException
     */
    public function delete(array $tables): array
    {
        $this->clearCacheOnSchemaChanges();
        $response = [];
        $this->begin();
        foreach ($tables as $table) {
            $check = $this->isTableOrView($table);
            $type = $check["data"];
            $query = "DROP $type " . $this->doubleQuoteQualifiedName($table) . " CASCADE";
            $res = $this->prepare($query);
            $this->execute($res);
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables deleted";
        return $response;
    }

    /**
     * @param string $_key_ The key used to fetch privileges, potentially modified based on configuration settings.
     * @return array<string, mixed> An associative array containing success flag, privileges data, and optional message.
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getPrivileges(string $_key_): array
    {
        if (!empty(App::$param['dontUseGeometryColumnInJoin'])) {
            $split = explode('.', $_key_);
            $_key_ = $split[0] . '.' . $split[1];
        }
        $privileges = json_decode($this->getValueFromKey($_key_, "privileges") ?: "{}");
        if (!empty(Session::get())) {
            $arr = Session::getByKey('subusers');
        } else {
            $arr = [];
            foreach ((array)$privileges as $key => $value) {
                $arr[] = $key;
            }
        }
        foreach ($arr as $subuser) {
            $privileges->$subuser = $privileges->$subuser ?? "none";
            if ($subuser != $this->schema) {
                $response['data'][] = array("subuser" => $subuser, "privileges" => $privileges->$subuser, "group" => Session::getByKey("usergroups")[$subuser]);
            }
        }
        if (!isset($response['data'])) {
            $response['data'] = [];
        }
        $response['success'] = true;
        $response['message'] = "Privileges fetched";
        return $response;
    }

    /**
     * @param object $data
     * @param Table|null $table
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function updatePrivileges(object $data, ?Table $table = null): array
    {
        (new User($data->subuser))->doesUserExist();
        $this->clearCacheOnSchemaChanges();
        $LayerKeys = explode(',', $data->_key_);
        foreach ($LayerKeys as $layerKey) {
            $this->clearCacheOfColumns(explode(".", $layerKey)[0] . "." . explode(".", $layerKey)[1]);
            $table = $table ?? new Table("settings.geometry_columns_join");
            $split = explode(".", $layerKey);
            $geomCols = $this->getGeometryColumnsFromTable($split[0], $split[1]);
            if (sizeof($geomCols) == 0) {
                throw new GC2Exception('columns not found');
            }
            foreach ($geomCols as $geomCol) {
                $key = $split[0] . '.' . $split[1] . '.' . $geomCol;
                $jsonStr = $this->getValueFromKey($key, "privileges");
                $privilege = !empty($jsonStr) ? json_decode($jsonStr, true) : [];
                $privilege[$data->subuser] = $data->privileges;
                $privileges['privileges'] = json_encode($privilege);
                $privileges['_key_'] = $key;
                $table->updateRecord($privileges, "_key_");
            }
        }

        $response['success'] = true;
        $response['message'] = "Privileges updates";
        return $response;
    }

    /**
     * Updates the last modified timestamp for geometry columns in a specific schema and table.
     *
     * @param string $schema The name of the schema containing the table.
     * @param string $table The name of the table whose geometry columns need to be updated.
     * @return void
     * @throws GC2Exception If no geometry columns are found in the specified table.
     */
    public function updateLastmodified(string $schema, string $table): void
    {
        $date = date('Y-m-d H:i:s');
        $geomCols = $this->getGeometryColumnsFromTable($schema, $table);
        if (sizeof($geomCols) == 0) {
            throw new GC2Exception('columns not found');
        }
        foreach ($geomCols as $geomCol) {
            $k = $schema . '.' . $table . '.' . $geomCol;
            $sql = "UPDATE settings.geometry_columns_join set lastmodified=:date WHERE _key_=:key";
            $res = $this->prepare($sql);
            $this->execute($res, ["date" => $date, "key" => $k]);
        }
    }

    /**
     * @param string $_key_ The key used to retrieve roles from the storage.
     * @return array<string, mixed> An array containing the success status, message, and a list of subuser roles.
     */
    public function getRoles(string $_key_): array
    {
        $roles = json_decode($this->getValueFromKey($_key_, "roles") ?: "{}");
        foreach ($_SESSION['subusers'] as $subuser) {
            $roles->$subuser = $roles->$subuser ?? "none";
            if ($subuser != $this->schema) {
                $response['data'][] = array("subuser" => $subuser, "roles" => $roles->$subuser);
            }
        }
        if (!isset($response['data'])) {
            $response['data'] = array();
        }
        $response['success'] = true;
        $response['message'] = "Roles fetched";
        return $response;
    }

    /**
     * Updates roles for a given key and subuser.
     *
     * @param object $data Data object containing the key, subuser, and roles to update.
     * @return array<string, mixed> Response array with success status and message.
     * @throws InvalidArgumentException
     * @throws GC2Exception
     */
    public function updateRoles(object $data): array
    {
        $this->clearCacheOnSchemaChanges();
        $table = new Table("settings.geometry_columns_join");
        $role = json_decode($this->getValueFromKey($data->_key_, "roles") ?: "{}");
        $role->{$data->subuser} = $data->roles;
        $roles['roles'] = json_encode($role);
        $roles['_key_'] = $data->_key_;
        $table->updateRecord(json_decode(json_encode($roles), true), "_key_");
        $response['success'] = true;
        $response['message'] = "Roles updates";
        return $response;
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getEstExtent($_key_, $srs = "4326"): array
    {
        $split = explode(".", $_key_);
        $nativeSrs = $this->getGeometryColumns($split[0] . "." . $split[1], "srid");
        $sql = "WITH bb AS (SELECT ST_astext(ST_Transform(ST_setsrid(ST_EstimatedExtent('" . $split[0] . "', '" . $split[1] . "', '" . $split[2] . "')," . $nativeSrs . ")," . $srs . ")) as geom) ";
        $sql .= "SELECT ST_Xmin(ST_Extent(geom)) AS TXMin,ST_Xmax(ST_Extent(geom)) AS TXMax, ST_Ymin(ST_Extent(geom)) AS TYMin,ST_Ymax(ST_Extent(geom)) AS TYMax  FROM bb";
        $res = $this->prepare($sql);
        $this->execute($res);
        $row = $this->fetchRow($res);
        $extent = array("xmin" => $row['txmin'], "ymin" => $row['tymin'], "xmax" => $row['txmax'], "ymax" => $row['tymax']);
        $response['success'] = true;
        $response['extent'] = $extent;
        return $response;
    }

    /**
     * @param string $_key_ The key used to determine the table and schema in the format "schema.table".
     * @return array<string, mixed> An associative array containing a success flag and the count of records from the specified table.
     */
    public function getCount(string $_key_): array
    {
        $split = explode(".", $_key_);
        $sql = "SELECT count(*) AS count FROM " . $split[0] . "." . $split[1];
        $res = $this->prepare($sql);
        $this->execute($res);
        $row = $this->fetchRow($res);
        $count = $row['count'];
        $response['success'] = true;
        $response['count'] = $count;
        return $response;
    }

    /**
     * Copies metadata from a specified source key to multiple target keys in the database.
     *
     * @param string $from The source key from which metadata will be copied.
     * @param object $data An object containing metadata fields and target keys.
     *                     The `fields` property is an array specifying which fields to copy,
     *                     and the `keys` property is an array of target keys.
     * @return array<bool|string> Returns an array indicating success with a boolean value.
     *                            If the operation fails, it includes an error message and a status code.
     */
    public function copyMeta(string $from, object $data): array
    {
        $this->clearCacheOnSchemaChanges();
        $query = "SELECT * FROM settings.geometry_columns_join WHERE _key_ =:from";
        $res = $this->prepare($query);
        $this->execute($res, array("from" => $from));
        $row = $this->fetchRow($res);
        foreach ($data->keys as $to) {
            $booleanFields = array("editable", "baselayer", "tilecache", "not_querable", "single_tile", "enablesqlfilter", "skipconflict", "enableows");
            foreach ($row as $k => $v) {
                if (in_array($k, $data->fields)) {
                    if (in_array($k, $booleanFields)) {
                        $conf[$k] = $v ?: "0";
                    } else {
                        $conf[$k] = $v;
                    }
                }
            }
            $conf['_key_'] = $to;
            $geometryColumnsObj = new table("settings.geometry_columns_join");
            $res = $geometryColumnsObj->updateRecord($conf, "_key_", true);
            if (!$res["success"]) {
                $response['success'] = false;
                $response['message'] = $res["message"];
                $response['code'] = "406";
                return $response;
            }
        }
        return [
            'success' => true,
        ];
    }

    /**
     * @param string $schema
     * @param string $table
     * @return array<bool|array<string>>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getRole(string $schema, string $table): array
    {
        $row = $this->getColumns($schema, $table);
        $response['success'] = true;
        $response['data'] = isset($row[0]['roles']) ? json_decode($row[0]['roles'], true) : null;
        return $response;
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        $sql = "SELECT tags FROM settings.geometry_columns_join WHERE tags NOTNULL AND tags <> 'null' AND tags <> '\"null\"'";
        $res = $this->prepare($sql);
        $this->execute($res);
        $arr = array();
        while ($row = $this->fetchRow($res)) {
            if (isset($row["tags"]) && json_decode($row["tags"])) {
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

    /**
     * @param string $field The database field used for grouping the results.
     * @return array<bool|array<array<string, mixed>>> An array containing the success status and the grouped data retrieved from the database.
     * @throws DatabaseException If there is an error executing the database query.
     */
    public function getGroups(string $field): array
    {
        $arr = [];
        $sql = "SELECT $field AS $field FROM settings.geometry_columns_join WHERE $field IS NOT NULL GROUP BY $field";
        $res = $this->prepare($sql);
        $this->execute($res);
        while ($row = $this->fetchRow($res)) {
            $arr[] = array("group" => $row[$field]);
        }
        $response['success'] = true;
        $response['data'] = $arr;
        return $response;
    }

    /**
     * Inserts default metadata into the `settings.geometry_columns_join` table for rows
     * in the `settings.geometry_columns_view` where the `_key_` field is null and the key
     * does not begin with an underscore.
     *
     * This method prepares and executes an SQL statement to perform the insertion operation
     * and returns the operation's success status and the number of affected rows.
     *
     * @return array<string, mixed> Associative array containing the success status as a boolean
     *                              and the count of rows affected by the insertion.
     */
    public function insertDefaultMeta(): array
    {
        $sql = "with t as (select f_table_schema || '.' || f_table_name || '.' || f_geometry_column as key
                    from settings.geometry_columns_view
                    where _key_ isnull)
                insert into settings.geometry_columns_join(_key_) select * from t where left(key, 1) != '_'";

        $res = $this->prepare($sql);
        $this->execute($res);
        $response['success'] = true;
        $response['count'] = $res->rowCount();
        return $response;
    }

    /**
     * Installs or replaces a database trigger to emit real-time notifications for a specified table.
     *
     * @param string $_key_ The full identifier of the table in the format "schema.table".
     * @return void
     * @throws GC2Exception If the table does not have a primary key or has a primary key with multiple columns.
     */
    public function installNotifyTrigger(string $_key_): void
    {
        $explodedKey = self::explodeTableName($_key_);
        $con = $this->getConstrains($explodedKey['schema'], $explodedKey['table'], 'p')['data'];
        if (count($con) == 0) {
            throw new GC2Exception("Table must have a primary key for emitting real time events", 401);
        }
        if (count($con) > 1) {
            throw new GC2Exception("Table has primary key with multiple columns", 401);
        }
        $sql = "DROP TRIGGER IF EXISTS _gc2_notify_transaction_trigger ON \"{$explodedKey['schema']}\".\"{$explodedKey['table']}\"";
        $res = $this->prepare($sql);
        $this->execute($res);
        $sql = "CREATE TRIGGER _gc2_notify_transaction_trigger AFTER INSERT OR UPDATE OR DELETE ON \"{$explodedKey['schema']}\".\"{$explodedKey['table']}\" FOR EACH ROW EXECUTE PROCEDURE _gc2_notify_transaction('{$con[0]['column_name']}', '{$explodedKey['schema']}','{$explodedKey['table']}')";
        $res = $this->prepare($sql);
        $this->execute($res);
    }

    /**
     * Removes a notification trigger from the specified table in a schema.
     *
     * @param string $_key_ The composite key in the format "schema.table" used to identify the schema and table.
     * @return void
     */
    public function removeNotifyTrigger(string $_key_): void
    {
        $explodedKey = self::explodeTableName($_key_);
        $sql = "DROP TRIGGER IF EXISTS _gc2_notify_transaction_trigger ON \"{$explodedKey['schema']}\".\"{$explodedKey['table']}\"";
        $res = $this->prepare($sql);
        $this->execute($res);
    }
}
