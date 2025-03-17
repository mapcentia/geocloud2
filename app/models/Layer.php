<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\inc\Cache;
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
    function __construct()
    {
        parent::__construct("settings.geometry_columns_view");
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
            $this->postgisdb . '_' . $relName . '_columns',
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
        $res->execute(['table' => $table, 'schema' => $schema]);
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
        $res->execute(['table' => $table, 'schema' => $schema]);
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
        $res->execute(['key' => $_key_]);
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
        $cacheId = ($this->postgisdb . "_" . Session::getUser() . "_" . $cacheType . "_" . md5($query . "_" . "(int)$auth" . "_" . (int)$includeExtent . "_" . (int)$parse . "_" . (int)$es));

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
            $res->execute();

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
                    $resExtent->execute();
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
     * Secure. Using now user input.
     * @return array
     */
    public function getSchemas(): array
    {
        $response = [];
        $arr = [];
        $sql = "SELECT f_table_schema AS schemas FROM settings.geometry_columns_view WHERE f_table_schema IS NOT NULL AND f_table_schema!='sqlapi' GROUP BY f_table_schema";
        $result = $this->execQuery($sql);
        while ($row = $this->fetchRow($result)) {
            $arr[] = array("schema" => $row["schemas"], "desc" => null);
        }
        $response['success'] = true;
        $response['data'] = $arr;
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
     * @param $tableName
     * @param $data
     * @return array
     * @throws GC2Exception|InvalidArgumentException
     */
    public function rename($tableName, $data): array
    {
        $this->clearCacheOnSchemaChanges();
        $split = explode(".", $tableName);
        $newName = self::toAscii($data->name, array(), "_");
        if (is_numeric(mb_substr($newName, 0, 1, 'utf-8'))) {
            $newName = "_" . $newName;
        }
        $whereClauseG = "f_table_schema=''$split[0]'' AND f_table_name=''$split[1]''";
        $whereClauseR = "r_table_schema=''$split[0]'' AND r_table_name=''$split[1]''";
        $query = "SELECT * FROM settings.getColumns('$whereClauseG','$whereClauseR') ORDER BY sort_id";
        $res = $this->prepare($query);
        try {
            $res->execute();
            while ($row = $this->fetchRow($res)) {
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '{$row['f_table_schema']}.$newName.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                try {
                    $resUpdate->execute();
                } catch (PDOException $e) {
                    throw new GC2Exception($e->getMessage(), 400, null);
                }
            }
            $sql = "ALTER TABLE " . $this->doubleQuoteQualifiedName($tableName) . " RENAME TO $newName";
            $res = $this->prepare($sql);
            try {
                $res->execute();
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
     * @param $tables
     * @param $schema
     * @return array
     * @throws InvalidArgumentException
     */
    public function setSchema($tables, $schema): array
    {
        $this->clearCacheOnSchemaChanges();
        foreach ($tables as $table) {
            $bits = explode(".", $table);
            $whereClauseG = "f_table_schema=''$bits[0]'' AND f_table_name=''$bits[1]''";
            $whereClauseR = "r_table_schema=''$bits[0]'' AND r_table_name=''$bits[1]''";
            $query = "SELECT * FROM settings.getColumns('$whereClauseG','$whereClauseR') ORDER BY sort_id";
            $res = $this->prepare($query);
            $res->execute();
            while ($row = $this->fetchRow($res)) {
                // First delete keys from destination schema if they exists
                $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ = '$schema.$bits[1].{$row['f_geometry_column']}'";
                $resDelete = $this->prepare($query);
                $resDelete->execute();
                $query = "UPDATE settings.geometry_columns_join SET _key_ = '$schema.$bits[1].{$row['f_geometry_column']}' WHERE _key_ ='$bits[0].$bits[1].{$row['f_geometry_column']}'";
                $resUpdate = $this->prepare($query);
                $resUpdate->execute();
            }
            $query = "ALTER TABLE " . $this->doubleQuoteQualifiedName($table) . " SET SCHEMA $schema";
            $res = $this->prepare($query);
            $res->execute();
        }
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables moved to $schema";
        return $response;
    }

    /**
     * @param array $tables
     * @return array
     * @throws InvalidArgumentException
     * @throws GC2Exception
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
            // Delete package from CKAN
            if (isset(App::$param["ckan"])) {
                $uuid = $this->getUuid($table);
                $ckanRes = $this->deleteCkan($uuid["uuid"]);
                $response['ckan_delete'] = $ckanRes["success"];
            }
            $res->execute();
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = sizeof($tables) . " tables deleted";
        return $response;
    }

    /**
     * @param string $_key_
     * @return array
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
            if ($subuser != Connection::$param['postgisschema']) {
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
        $this->clearCacheOfColumns(explode(".", $data->_key_)[0] . "." . explode(".", $data->_key_)[1]);
        $this->clearCacheOnSchemaChanges();
        $table = $table ?? new Table("settings.geometry_columns_join");
        $split = explode(".", $data->_key_);
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
        $response['success'] = true;
        $response['message'] = "Privileges updates";
        return $response;
    }

    /**
     * @param string $key
     * @return array
     */
    public function updateLastmodified(string $key): array
    {
        $response = [];
        $date = date('Y-m-d H:i:s');
        $sql = "UPDATE settings.geometry_columns_join set lastmodified=:date WHERE _key_=:key";
        try {
            $result = $this->prepare($sql);
            $result->execute(["date" => $date, "key" => $key]);
            $response['success'] = true;
            $response['message'] = "Last modified value updated";
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        return $response;
    }

    /**
     * @param string $_key_
     * @return array<array|bool|string>
     */
    public function getRoles(string $_key_): array
    {
        $roles = json_decode($this->getValueFromKey($_key_, "roles") ?: "{}");
        foreach ($_SESSION['subusers'] as $subuser) {
            $roles->$subuser = $roles->$subuser ?? "none";
            if ($subuser != Connection::$param['postgisschema']) {
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
     * @param object $data
     * @return array<bool|string|int>
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
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
     * @param string $_key_
     * @param string $srs
     * @return array
     */
    public function getExtent(string $_key_, string $srs = "4326"): array
    {
        $split = explode(".", $_key_);
        $srsTmp = $srs;
        $sql = "SELECT ST_Xmin(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS xmin,ST_Xmax(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS xmax, ST_Ymin(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS ymin,ST_Ymax(ST_Extent(public.ST_Transform(\"" . $split[2] . "\",$srsTmp))) AS ymax  FROM $split[0].$split[1]";
        $resExtent = $this->prepare($sql);
        $resExtent->execute();
        $extent = $this->fetchRow($resExtent);
        $response['success'] = true;
        $response['extent'] = $extent;
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
        $result = $this->prepare($sql);
        $result->execute();
        $row = $this->fetchRow($result);
        $extent = array("xmin" => $row['txmin'], "ymin" => $row['tymin'], "xmax" => $row['txmax'], "ymax" => $row['tymax']);
        $response['success'] = true;
        $response['extent'] = $extent;
        return $response;
    }

    /**
     * @param string $_key_
     * @param string $srs
     * @return array
     */
    public function getEstExtentAsGeoJSON(string $_key_, string $srs = "4326"): array
    {
        $split = explode(".", $_key_);
        $nativeSrs = $this->getGeometryColumns($split[0] . "." . $split[1], "srid");
        $sql = "SELECT ST_asGeojson(ST_Transform(ST_setsrid(ST_EstimatedExtent('" . $split[0] . "', '" . $split[1] . "', '" . $split[2] . "')," . $nativeSrs . ")," . $srs . ")) as geojson";
        $result = $this->prepare($sql);
        $result->execute();
        $row = $this->fetchRow($result);
        $extent = $row["geojson"];
        $response['success'] = true;
        $response['extent'] = $extent;
        return $response;
    }

    /**
     * @param string $_key_
     * @return array
     */
    public function getCount(string $_key_): array
    {
        $split = explode(".", $_key_);
        $sql = "SELECT count(*) AS count FROM " . $split[0] . "." . $split[1];
        $result = $this->prepare($sql);
        $result->execute();
        $row = $this->fetchRow($result);
        $count = $row['count'];
        $response['success'] = true;
        $response['count'] = $count;
        return $response;
    }

    /**
     * @param string $to
     * @param string $from
     * @return array
     * @throws InvalidArgumentException
     */
    public function copyMeta(string $to, string $from): array
    {
        $this->clearCacheOnSchemaChanges();
        $query = "SELECT * FROM settings.geometry_columns_join WHERE _key_ =:from";
        $res = $this->prepare($query);
        $res->execute(array("from" => $from));
        $booleanFields = array("editable", "baselayer", "tilecache", "not_querable", "single_tile", "enablesqlfilter", "skipconflict", "enableows");
        $row = $this->fetchRow($res);
        foreach ($row as $k => $v) {
            if (in_array($k, $booleanFields)) {
                $conf[$k] = $v ?: "0";
            } else {
                $conf[$k] = $v;
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
        return $res;
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
     * @param string $key
     * @param string $gc2Host
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function updateCkan(string $key, string $gc2Host): array
    {
        $gc2Host = $gc2Host ?: App::$param["host"];
        $metaConfig = App::$param["metaConfig"];
        $ckanApiUrl = App::$param["ckan"]["host"];

        $sql = "SELECT * FROM settings.geometry_columns_view WHERE _key_ =:key";
        $res = $this->prepare($sql);
        $res->execute(array("key" => $key));
        $row = $this->fetchRow($res);
        $id = $row["uuid"];
        // Check if dataset already exists
        $ch = curl_init($ckanApiUrl . "/api/3/action/package_show?id=" . $id);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        $datasetExists = $info["http_code"] == 200;
        curl_close($ch);

        // Create the CKAN package objectuu
        $arr = array();

        if ($row["tags"]) {
            foreach (json_decode($row["tags"]) as $v) {
                $arr[] = array("name" => $v);
            }
        }

        // Get the default "ckan_org_id" value
        $ckanOrgIdDefault = null;
        foreach ($metaConfig as $value) {
            if (!empty($value["name"]) == "ckan_org_id") {
                $ckanOrgIdDefault = $value["default"];
            }
        }

        // Get the default "update" flag
        $updateDefault = null;
        foreach ($metaConfig as $value) {
            if (!empty($value["name"]) == "ckan_update") {
                $updateDefault = $value["default"];
            }
        }

        // Get the default "update" value
        $licenseIdDefault = null;
        foreach ($metaConfig as $value) {
            if (!empty($value["name"]) == "license_id") {
                $licenseIdDefault = $value["default"];
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

        $ownerOrg = json_decode($row["meta"], true)["ckan_org_id"] ?: $ckanOrgIdDefault;

        $qualifiedName = $row["f_table_schema"] . "." . $row["f_table_name"];

        $widgetUrl = $gc2Host . "/apps/widgets/gc2map/" . Database::getDb() . "/" . $row["f_table_schema"] . "/" . App::$param["ckan"]["widgetState"] . "/" . $qualifiedName;
        $response = array();
        if ($datasetExists) {
            $response["id"] = $id;
        }
        $response["name"] = $id;
        $response["title"] = $row["f_table_title"];
        $response["license_id"] = json_decode($row["meta"], true)["license_id"] ?: $licenseIdDefault;
        $response["notes"] = (isset(json_decode($row["meta"])->meta_desc) && trim(json_decode($row["meta"])->meta_desc) != "") ? json_decode($row["meta"])->meta_desc : $row["f_table_abstract"];
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
                "description" => App::$param["ckan"]["descForGeoJson"],
                "format" => "geojson",
                "url" => $gc2Host . "/api/v2/sql/" . Database::getDb() . "?q=SELECT * FROM " . $qualifiedName . " LIMIT 1000&srs=4326"
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
                "url" => $gc2Host . "/mapcache/" . Database::getDb() . "/wmts/1.0.0/WMTSCapabilities.xml"
            ),
            array(
                "id" => $id . "-xyz",
                "name" => "XYZ",
                "description" => "Google XYZ service",
                "format" => "xyz",
                "url" => $gc2Host . "/mapcache/" . Database::getDb() . "/gmaps/" . $qualifiedName . "@g"
            ),
            array(
                "id" => $id . "-csv",
                "name" => "CSV",
                "description" => App::$param["ckan"]["descForCSV"],
                "format" => "csv",
                "url" => $gc2Host . "/api/v2/sql/" . Database::getDb() . "?q=SELECT * FROM " . $qualifiedName . " LIMIT 1000&srs=4326&format=csv&allstr=1&alias=" . $qualifiedName
            ),
            array(
                "id" => $id . "-excel",
                "name" => "XLSX",
                "description" => App::$param["ckan"]["descForExcel"],
                "format" => "xlsx",
                "url" => $gc2Host . "/api/v2/sql/" . Database::getDb() . "?q=SELECT * FROM " . $qualifiedName . " LIMIT 1000&srs=4326&format=excel&alias=" . $qualifiedName
            ),
        );

        // Get extent
        $extent = $this->getEstExtentAsGeoJSON($key);
        //die(print_r($extent, true));
        $extentStr = "";
        if ($extent["success"]) {
            $extentStr = $extent["extent"];
        }

        // Get count
        $count = $this->getCount($key);
        $countStr = "";
        if ($count["success"]) {
            $countStr = $count["count"];
        }

        $response["extras"] = array(
            array(
                "key" => "spatial",
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
        if ($info["http_code"] == 200) {
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
            curl_exec($ch);
            curl_close($ch);

        }
        $response['json'] = $packageBuffer;
        return $response;
    }

    public static function deleteCkan($key)
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

    /**
     * @return array
     */
    public function getTags(): array
    {
        $sql = "SELECT tags FROM settings.geometry_columns_join WHERE tags NOTNULL AND tags <> 'null' AND tags <> '\"null\"'";
        $res = $this->prepare($sql);
        $res->execute();

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
     * @param string $field
     * @return array
     */
    public function getGroups(string $field): array
    {
        $arr = [];
        $sql = "SELECT $field AS $field FROM settings.geometry_columns_join WHERE $field IS NOT NULL GROUP BY $field";
        $res = $this->prepare($sql);
        $res->execute();

        while ($row = $this->fetchRow($res)) {
            $arr[] = array("group" => $row[$field]);
        }
        $response['success'] = true;
        $response['data'] = $arr;

        return $response;
    }

    public function insertDefaultMeta(): array
    {
        $sql = "with t as (select f_table_schema || '.' || f_table_name || '.' || f_geometry_column as key
                    from settings.geometry_columns_view
                    where _key_ isnull)
                insert into settings.geometry_columns_join(_key_) select * from t where left(key, 1) != '_'";

        $res = $this->prepare($sql);
        $res->execute();
        $response['success'] = true;
        $response['count'] = $res->rowCount();
        return $response;
    }
}
