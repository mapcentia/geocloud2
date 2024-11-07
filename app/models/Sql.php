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
use app\inc\Model;
use DateInterval;
use DateMalformedStringException;
use DateTimeImmutable;
use JsonSerializable;
use PDO;
use PDOException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use sad_spirit\pg_wrapper\types\ArrayRepresentable;
use sad_spirit\pg_wrapper\types\DateTimeRange;
use sad_spirit\pg_wrapper\types\Range;
use ZipArchive;
use sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory;


/**
 * Class Sql
 * @package app\models
 */
class Sql extends Model
{
    /**
     * @var string
     */
    private string $srs;

    public Sql $model;

    /**
     * Sql constructor.
     * @param string $srs
     */
    function __construct(string $srs = "3857")
    {
        parent::__construct();

        $this->model = $this;
        $this->srs = $srs;
    }

    /**
     * @param string $q
     * @param string|null $clientEncoding
     * @param string|null $format
     * @param string|null $geoformat
     * @param bool|null $csvAllToStr
     * @param string|null $aliasesFrom
     * @param string|null $nlt
     * @param string|null $nln
     * @param bool|null $convertTypes
     * @param array|null $parameters
     * @param array|null $typeHints
     * @return array
     * @throws Exception
     * @throws GC2Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function sql(string $q, ?string $clientEncoding = null, ?string $format = "geojson", ?string $geoformat = "wkt", ?bool $csvAllToStr = false, ?string $aliasesFrom = null, ?string $nlt = null, ?string $nln = null, ?bool $convertTypes = false, array $parameters = null, array $typeHints = null): array
    {
        // Check params
        if (is_array($parameters) && array_key_exists(0, $parameters) && is_array($parameters[0])) {
            throw new GC2Exception("Only JSON objects are accepted in SELECT statements. Not arrays", 406);
        }

        if ($format == "excel") {
            $limit = !empty(App::$param["limits"]["sqlExcel"]) ? App::$param["limits"]["sqlExcel"] : 10000;
        } elseif (in_array($format, ['csv', 'geojson', 'json'])) {
            $limit = !empty(App::$param["limits"]["sqlJson"]) ? App::$param["limits"]["sqlJson"] : 100000;
        } else {
            $limit = 1000000000000;
        }
        $name = "_" . md5(rand(1, 999999999) . microtime());
        $view = self::toAscii($name, null, "_");
        $formatSplit = explode("/", $format);
        if (sizeof($formatSplit) == 2 && $formatSplit[0] == "ogr") {
            $fileOrFolder = $nln ? $nln . $name : $view;
            $fileOrFolder .= "." . self::toAscii($formatSplit[1], null, "_");
            $path = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__vectors/" . $fileOrFolder;
            $cmd = "ogr2ogr " .
                "-mapFieldType Time=String " .
                "-f \"" . explode("/", $format)[1] . "\" " . $path . " " .
                "-t_srs \"EPSG:" . $this->srs . "\" " .
                "-a_srs \"EPSG:" . $this->srs . "\" " .
                ($nlt ? "-nlt " . $nlt . " " : "") .
                ($nln ? "-nln " . $nln . " " : "") .
                "-preserve_fid " .
                ($format == "ogr/GPX" ? "-lco 'FORCE_GPX_ROUTE=YES' " : "") .
                "PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "' " .
                "-sql \"" . $q . "\"";
//            die($cmd);
            exec($cmd . ' 2>&1', $out);
            if ($out) {
                foreach ($out as $str) {
                    if (str_contains($str, 'ERROR')) {
                        return [
                            'success' => false,
                            "message" => $out,
                            "code" => 440,
                        ];
                    }
                }
            }
            if ($format == "ogr/GPX") {
                header("Content-type: application/gpx, application/octet-stream");
                header("Content-Disposition: attachment; filename=\"$fileOrFolder\"");
                readfile($path);
            } else {
                $zip = new ZipArchive();
                $zipPath = $path . ".zip";
                if (!$zip->open($zipPath, ZIPARCHIVE::CREATE)) {
                    error_log("Could not open ZIP archive");
                }
                if (is_dir($path)) {
                    $zip->addGlob($path . "/*", 0, ["remove_all_path" => true]);
                } else {
                    $zip->addFile($path, $fileOrFolder);
                }
                if ($zip->status != ZIPARCHIVE::ER_OK) {
                    error_log("Failed to write files to zip archive");
                }
                $zip->close();
                header("Content-type: application/zip, application/octet-stream");
                header("Content-Disposition: attachment; filename=\"$fileOrFolder.zip\"");
                readfile($zipPath);
            }
            exit(0);
        }

        $postgisVersion = $this->postgisVersion();
        $bits = explode(".", $postgisVersion["version"]);
        if ((int)$bits[0] < 3 && (int)$bits[1] === 0) {
            $ST_Force2D = "ST_Force_2D"; // In case of PostGIS 2.0.x
        } else {
            $ST_Force2D = "ST_Force2D";
        }

        // Get column types
        $this->execQuery("SET LOCAL session_replication_role  = replica");
        $select = $this->prepare("select * from ($q) as foo LIMIT 1");
        $convertedParameters = [];
        if ($parameters) {
            foreach ($parameters as $field => $value) {
                $nativeType = $typeHints[$field] ?? 'json';
                $convertedParameters[$field] = $this->convertToNative($nativeType, $value);
            }
            $select->execute($convertedParameters);
        } else {
            $select->execute();
        }
        foreach (range(0, $select->columnCount() - 1) as $column_index) {
            $meta = $select->getColumnMeta($column_index);
            $columnTypes[$meta['name']] = $meta['native_type'];
        }
        $this->execQuery("SET LOCAL session_replication_role  = DEFAULT");

        $fieldsArr = [];
        foreach ($columnTypes as $key => $type) {
            if ($type == "geometry") {
                if (in_array($format , ["geojson", "ndjson", "json"]) || (($format == "csv" || $format == "excel") && $geoformat == "geojson")) {
                    $fieldsArr[] = "ST_asGeoJson(ST_Transform($ST_Force2D(\"$key\"),$this->srs)) as \"$key\"";
                } elseif ($format == "csv" || $format == "excel") {
                    $fieldsArr[] = "ST_asText(ST_Transform($ST_Force2D(\"$key\"),$this->srs)) as \"$key\"";
                }
            } elseif ($type == "bytea") {
                $fieldsArr[] = "encode(\"$key\",'escape') as \"$key\"";
            } else {
                $fieldsArr[] = "\"$key\"";
            }
        }
        $fieldsStr = implode(",", $fieldsArr);
        $sql = "SELECT $fieldsStr FROM ($q) AS foo LIMIT $limit";
        $this->begin();
        // Settings from App.php
        if (!empty(App::$param["SqlApiSettings"]["work_mem"])) {
            $this->execQuery("SET work_mem TO '" . App::$param["SqlApiSettings"]["work_mem"] . "'");
        }
        if (!empty(App::$param["SqlApiSettings"]["statement_timeout"])) {
            $this->execQuery("SET LOCAL statement_timeout = " . App::$param["SqlApiSettings"]["statement_timeout"]);
        } else {
            $this->execQuery("SET LOCAL statement_timeout = 60000");
        }
        if ($clientEncoding) {
            $this->execQuery("set client_encoding='$clientEncoding'");
        }

        $this->prepare("DECLARE curs CURSOR FOR $sql")->execute($convertedParameters);
        $innerStatement = $this->prepare("FETCH 1 FROM curs");

        if ($format == "json") {

            // JSON output
            // ==============

            $arr = [];
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                foreach ($row as $key => $rowValue) {
                    $nativeType = $columnTypes[$key];
                    if ($nativeType == "geometry" && $rowValue !== null) {
                        $rowValue = json_decode($rowValue);
                    } else {
                        if ($convertTypes) {
                            try {
                                $rowValue = $this->convertFromNative($nativeType, $rowValue);
                            } catch (\Exception) {
                                // Pass
                            }
                        }
                    }
                    $arr = $this->array_push_assoc($arr, $key, $rowValue);
                }
            }
            $this->execQuery("CLOSE curs");
            $this->commit();
            foreach ($columnTypes as $key => $type) {
                $schema[$key] = [
                    "type" => ltrim($type, '_'),
                    "array" => str_starts_with($type, '_'),
                    ];
            }
            $response['success'] = true;
            $response['schema'] = $schema;
            $response['data'] = $arr;
            return $response;

        } elseif ($format == "geojson") {

            // GeoJSON output
            // ==============

            $geometries = null;
            $fieldsForStore = [];
            $columnsForGrid = [];
            $features = [];

            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = array();
                foreach ($row as $key => $rowValue) {
                    $nativeType = $columnTypes[$key];
                    if ($nativeType == "geometry" && $rowValue !== null) {
                        $geometries[] = json_decode($rowValue);
                    } else {
                        if ($convertTypes) {
                            try {
                                $rowValue = $this->convertFromNative($nativeType, $rowValue);
                            } catch (\Exception) {
                                // Pass
                            }
                        }
                        $arr = $this->array_push_assoc($arr, $key, $rowValue);
                    }
                }
                if ($geometries == null) {
                    $features[] = array("type" => "Feature", "properties" => $arr);
                } elseif (count($geometries) == 1) {
                    $features[] = array("type" => "Feature", "geometry" => $geometries[0], "properties" => $arr);
                } else {
                    $features[] = array("type" => "Feature", "properties" => $arr, "geometry" => array("type" => "GeometryCollection", "geometries" => $geometries));
                }
                $geometries = null;
            }
            $this->execQuery("CLOSE curs");
            $this->commit();
            foreach ($columnTypes as $key => $type) {
                $fieldsForStore[] = array("name" => $key, "type" => $type);
                $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $type, "typeObj" => !empty($value['typeObj']) ? $value['typeObj'] : null);
            }
            $response['success'] = true;
            $response['forStore'] = $fieldsForStore;
            $response['forGrid'] = $columnsForGrid;
            $response['type'] = "FeatureCollection";
            $response['features'] = $features;
            return $response;

        } elseif ($format == "ndjson") {

            // NDJSON output
            // ==============

            header('Content-type: text/plain; charset=utf-8');
            $i = 0;
            $json = "";
            $bulkSize = 1000;
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = [];
                foreach ($row as $key => $value) {
                    if ($columnTypes[$key] == "geometry" && $value !== null) {
                        $geometries[] = json_decode($value);
                    } elseif ($columnTypes[$key] == "json" || $columnTypes[$key] == "jsonb" && $value !== null) {
                        $arr = $this->array_push_assoc($arr, $key, json_decode($value));
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }
                if ($geometries == null) {
                    $features = array("type" => "Feature", "properties" => $arr);
                } elseif (count($geometries) == 1) {
                    $features = array("type" => "Feature", "properties" => $arr, "geometry" => $geometries[0]);
                } else {
                    $features = array("type" => "Feature", "properties" => $arr, "geometry" => array("type" => "GeometryCollection", "geometries" => $geometries));
                }
                $geometries = null;
                $json .= json_encode($features);
                $json .= "\n";
                $i++;
                if (is_int($i / $bulkSize)) {
                    echo str_pad($json, 4096);
                    $json = "";
                }
                flush();
                ob_flush();
            }
            if ($json) {
                echo str_pad($json, 4096);
            }
            $this->execQuery("CLOSE curs");
            $this->commit();
            exit();

        } elseif ($format == "ccsv") {

            // CSV output
            // ================

            header('Content-type: text/plain; charset=utf-8');
            $withGeom = $geoformat;
            $separator = ";";
            $first = true;
            $fieldConf = null;
            $lines = "";
            $i = 0;
            $bulkSize = 1000;

            if ($aliasesFrom) {
                $c = $this->getGeometryColumns($aliasesFrom, "fieldconf");
                if ($c) {
                    $fieldConf = json_decode($this->getGeometryColumns($aliasesFrom, "fieldconf"));
                }
            }
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = array();
                $fields = array();
                foreach ($row as $key => $value) {
                    if ($columnTypes[$key] == "geometry") {
                        if ($withGeom) {
                            $arr = $this->array_push_assoc($arr, $key, $value);
                        }
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }
                // Create first lines with field names
                if ($first) {
                    foreach ($arr as $key => $value) {
                        $fields[] = ($fieldConf && isset($fieldConf->$key->alias) && $fieldConf->$key->alias != "") ? "\"{$fieldConf->$key->alias}\"" : $key;
                    }
                    echo implode($separator, $fields) . "\n";
                    $first = false;
                    $fields = [];
                    flush();
                    ob_flush();
                }

                foreach ($arr as $value) {
                    // Each embedded double-quote characters must be represented by a pair of double-quote characters.
                    $value = $value !== null ? str_replace('"', '""', $value) : null;
                    // Any text is quoted
                    if ($csvAllToStr) {
                        $fields[] = "\"$value\"";
                    } else {
                        $fields[] = !is_numeric($value) ? "\"$value\"" : $value;
                    }
                }
                $lines .= implode($separator, $fields);
                $lines .= "\n";
                $i++;
                if (is_int($i / $bulkSize)) {
                    echo $lines;
                    $lines = "";
                }
                flush();
                ob_flush();
            }
            if ($lines) {
                echo $lines . "\n";
            }
            $this->execQuery("CLOSE curs");
            $this->commit();
            exit();

        } elseif ($format == "excel" || $format == "csv") {

            // Excel output
            // ================

            $withGeom = $geoformat;
            $separator = ";";
            $first = true;
            $lines = [];
            $fieldConf = null;

            if ($aliasesFrom) {
                $c = $this->getGeometryColumns($aliasesFrom, "fieldconf");
                if ($c) {
                    $fieldConf = json_decode($this->getGeometryColumns($aliasesFrom, "fieldconf"));
                }
            }

            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = [];
                $fields = [];
                foreach ($row as $key => $value) {
                    if ($columnTypes[$key] == "geometry") {
                        if ($withGeom) {
                            $arr = $this->array_push_assoc($arr, $key, $value);
                        }
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }
                // Create first lines with field names
                if ($first) {
                    foreach ($arr as $key => $value) {
                        $fields[] = ($fieldConf && isset($fieldConf->$key->alias) && $fieldConf->$key->alias != "") ? "\"{$fieldConf->$key->alias}\"" : $key;
                    }
                    $lines[] = implode($separator, $fields);
                    $first = false;
                    $fields = array();
                }

                foreach ($arr as $value) {
                    // Each embedded double-quote characters must be represented by a pair of double-quote characters.
                    $value = $value !== null ? str_replace('"', '""', $value) : null;

                    // Any text is quoted
                    if ($csvAllToStr) {
                        $fields[] = "\"$value\"";

                    } else {
                        $fields[] = !is_numeric($value) ? "\"$value\"" : $value;

                    }
                }
                $lines[] = implode($separator, $fields);
            }
            $this->execQuery("CLOSE curs");
            $this->commit();
            $csv = implode("\n", $lines);

            if ($format == "csv") {
                header("Content-Type: text/csv");
                header('Content-Disposition: attachment; filename="file.csv"');
                ob_clean();
                flush();
                echo $csv;
                exit();
            }

            // Convert to Excel
            // ================

            $file = tempnam(sys_get_temp_dir(), 'excel_');
            $handle = fopen($file, "w");
            fwrite($handle, $csv);
            $objReader = new Csv();
            $objReader->setDelimiter($separator);
            $objReader->setTestAutoDetect(false);
            $objPHPExcel = $objReader->load($file);
            fclose($handle);
            unlink($file);
            $objWriter = new Xlsx($objPHPExcel);
            header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="file.xlsx"');
            $objWriter->save('php://output');
            exit();
        }
        throw new GC2Exception("$format is not an acceptable format", 406, null, 'NOT_ACCEPTABLE');
    }

    /**
     * @param string $q
     * @param array|null $parameters
     * @param array|null $typeHints
     * @param bool $convertReturning
     * @return array
     * @throws GC2Exception
     */
    public function transaction(string $q, array $parameters = null, array $typeHints = null, bool $convertReturning = true): array
    {
        $factory = new DefaultTypeConverterFactory();
        $columnTypes = [];
        $convertedParameters = [];
        // Convert JSON to native types
        if ($parameters) {
            foreach ($parameters as $parameter) {
                $paramTmp = [];
                foreach ($parameter as $field => $value) {
                    $nativeType = $typeHints[$field] ?? 'json';
                    $paramTmp[$field] = $this->convertToNative($nativeType, $value);
                }
                $convertedParameters[] = $paramTmp;
            }
        }
        $returning = null;
        $affectedRows = 0;
        $result = $this->prepare($q);
        if (sizeof($convertedParameters) > 0) {
            $this->begin();
            foreach ($convertedParameters as $parameter) {
                $result->execute($parameter);
                if (count($columnTypes) == 0) {
                    foreach (range(0, $result->columnCount() - 1) as $column_index) {
                        $meta = $result->getColumnMeta($column_index);
                        if (!$meta) {
                            break;
                        }
                        $columnTypes[$meta['name']] = $meta['native_type'];
                    }
                }
                $row = $this->fetchRow($result);
                $tmp = null;
                foreach ($row as $field => $value) {
                    try {
                        $convertedValue = $this->convertFromNative($columnTypes[$field], $value);
                        $tmp[$field] = $convertedValue;
                    } catch (\Exception) {
                        if ($columnTypes[$field] == 'geometry') {
                            $resultGeom = $this->prepare("select ST_AsGeoJSON(:v) as json");
                            $resultGeom->execute(["v" => $value]);
                            $json = $this->fetchRow($resultGeom)['json'];
                            $value = !empty($json) ? json_decode($json) : null;
                        }
                        $tmp[$field] = $value;
                    }
                }
                if ($tmp && sizeof($tmp) > 0) {
                    $returning[] = $tmp;
                }
                $affectedRows += $result->rowCount();
            }
            $this->commit();
        } else {
            $result->execute();
            foreach (range(0, $result->columnCount() - 1) as $column_index) {
                $meta = $result->getColumnMeta($column_index);
                if (!$meta) {
                    break;
                }
                $columnTypes[$meta['name']] = $meta['native_type'];
            }
            $affectedRows += $result->rowCount();
            $returningRaw = $result->fetchAll(PDO::FETCH_NAMED);
            if (!empty($returningRaw[0])) {
                foreach ($returningRaw as $row) {
                    $tmp = null;
                    foreach ($row as $field => $value) {
                        try {
                            $convertedValue = $this->convertFromNative($columnTypes[$field], $value);
                            $tmp[$field] = $convertedValue;
                        } catch (\Exception) {
                            if ($columnTypes[$field] == 'geometry') {
                                $resultGeom = $this->prepare("select ST_AsGeoJSON(:v) as json");
                                $resultGeom->execute(["v" => $value]);
                                $json = $this->fetchRow($resultGeom)['json'];
                                $value = !empty($json) ? json_decode($json) : null;
                            }
                            $tmp[$field] = $value;
                        }
                    }
                    if ($tmp && sizeof($tmp) > 0) {
                        $returning[] = $tmp;
                    }
                }
            }
        }
        $response['success'] = true;
        $response['affected_rows'] = $affectedRows;
        $response['returning'] = $returning;
        return $response;
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
     * @param string $q
     * @param string $username
     * @return void
     * @throws PDOException
     */
    public function insertCost(string $q, string $username): void
    {
        // Get total cost and insert in cost
        $cost = 0;
        $ex = "EXPLAIN (format json) $q";
        $res = $this->execQuery($ex);
        $plan = $res->fetchAll();
        if (isset($plan[0]['QUERY PLAN'])) {
            $cost = json_decode($plan[0]['QUERY PLAN'], true)[0]['Plan']['Total Cost'];
        }
        $ex = "INSERT INTO settings.cost (username, statement, cost) VALUES 
                   (:username, :statement, :cost)";
        $res = $this->prepare($ex);
        $q = str_replace("\n", " ", $q);
        $res->execute(['username' => $username, 'statement' => $q, 'cost' => $cost]);
    }


    /**
     * Converts a native type value into a corresponding PHP type.
     *
     * @param string $nativeType The native type specification to convert from.
     * @param string $value The value in the native type format.
     * @return mixed.
     */
    private function convertFromNative(string $nativeType, string $value): mixed
    {
        $newValue = (new DefaultTypeConverterFactory())->getConverterForTypeSpecification($nativeType)->input($value);
        if (is_array($newValue)) {
            $newValue = self::processArray($newValue, fn($i) => $this->convertPhpTypes($i));
        } else {
            $newValue = $this->convertPhpTypes($newValue);
        }
        return $newValue;
    }


    /**
     * Converts a given value to its native equivalent based on the specified native type.
     *
     * @param string $nativeType The native type to which the value needs to be converted.
     * @param mixed $value The value to be converted.
     * @return string The converted value in its native type.
     * @throws GC2Exception
     */
    private function convertToNative(string $nativeType, mixed $value): string
    {
        $factory = new DefaultTypeConverterFactory();
        $type = gettype($value);
        if ($type == 'array' || $type == 'object') {
            try {
                if (in_array($nativeType, ['daterange', 'tsrange', 'tstzrange'])) {
                    $value = $this->convertDateTimeRange($value);
                }
                if (in_array($nativeType, ['daterange[]', 'tsrange[]', 'tstzrange[]'])) {
                    $value = self::processArray($value, fn($i) => $this->convertDateTimeRange($i));
                }
                if ($nativeType == 'interval') {
                    $value = $this->convertInterval($value);
                }
                if ($nativeType == 'interval[]') {
                    $value = self::processArray($value, fn($i) => $this->convertInterval($i));
                }
                if (in_array($nativeType, ['numrange[]', 'int4range[]', 'int8range[]'])) {
                    $value = self::processArray($value, fn($i) => new Range(...$i));
                }
                $nativeValue = $factory->getConverterForTypeSpecification($nativeType)->output($value);
            } catch (\Exception $e) {
                throw new GC2Exception($e->getMessage(), 406, null, "VALUE_PARSE_ERROR");
            }
            $paramTmp = $nativeValue;
        } elseif ($type == 'boolean') {
            $nativeValue = $factory->getConverterForTypeSpecification($type)->output($value);
            $paramTmp = $nativeValue;
        } else {
            $paramTmp = $value;
        }
        return $paramTmp;
    }

    /**
     * Converts an associative array of time components to a DateInterval object.
     *
     * @param array $value An associative array containing keys 'y', 'm', 'd', 'h', 'i', 's', 'f' representing years, months, days, hours, minutes, seconds, and microseconds respectively.
     * @return DateInterval Returns a DateInterval object with the specified time components.
     */
    private function convertInterval(array $value): DateInterval
    {
        $tmp = new DateInterval("P0D");
        foreach (['y', 'm', 'd', 'h', 'i', 's', 'f'] as $key) {
            $tmp->$key = $value[$key] ?? 0;
        }
        return $tmp;
    }

    /**
     * @param array $value Array containing 'lower' and 'upper' keys with date and time strings
     * @return Range An instance of the Range class initialized with datetime objects
     * @throws DateMalformedStringException
     */
    private function convertDateTimeRange(array $value): Range
    {
        $value['lower'] = new \DateTimeImmutable($value['lower']);
        $value['lower']->format("l jS \of F Y h:i:s A");
        $value['upper'] = new \DateTimeImmutable($value['upper']);
        $value['upper']->format("l jS \of F Y h:i:s A");
        return new Range(...$value);
    }

    /**
     * Converts specific PHP types to a more manageable format.
     *
     * @param mixed $value The value to be converted which could be an instance of DateTimeImmutable, DateInterval, or DateTimeRange.
     * @return mixed The converted value in a standardized format.
     */
    private function convertPhpTypes(mixed $value): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            $value = $value->format('l jS \of F Y h:i:s A');
        }
        if ($value instanceof DateInterval) {
            $tmp = [];
            foreach ($value as $key => $n) {
                if ($n) {
                    $tmp[$key] = $n;
                }
            }
            $value = $tmp;
        }
        if ($value instanceof DateTimeRange) {
            $tmp['lower'] = $value->lower->format('l jS \of F Y h:i:s A');
            $tmp['upper'] = $value->upper->format('l jS \of F Y h:i:s A');
            $tmp['lowerInclusive'] = $value->lowerInclusive;
            $tmp['upperInclusive'] = $value->upperInclusive;
            $value = $tmp;
        }
        return $value;
    }

    /**
     * Processes an array recursively with a given callback function.
     *
     * @param array|object $item The array to be processed.
     * @param callable $func The callback function to apply to the array or its sub-arrays.
     * @return array The processed array.
     */
    private static function processArray(array|object $item, callable $func): mixed
    {
        $hasSubArray = false;
        foreach ($item as $subItem) {
            if (is_array($subItem) || is_object($subItem)) {
                $hasSubArray = true;
                break;
            }
        }
        if ($hasSubArray) {
            $result = [];
            foreach ($item as $key => $subItem) {
                $result[$key] = self::processArray($subItem, $func);
            }
            return $result;
        } else {
            return $func($item);
        }
    }
}

