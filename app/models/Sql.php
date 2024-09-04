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
use PDO;
use PDOException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws GC2Exception
     */
    public function sql(string $q, ?string $clientEncoding = null, ?string $format = "geojson", ?string $geoformat = "wkt", ?bool $csvAllToStr = false, ?string $aliasesFrom = null, ?string $nlt = null, ?string $nln = null, ?bool $convertTypes = false, array $parameters = null): array
    {
        // Check params
        if ($parameters && is_array($parameters[0])) {
            throw new GC2Exception("Only JSON objects are accepted in SELECT statements. Not arrays", 406);
        }

        if ($format == "excel") {
            $limit = !empty(App::$param["limits"]["sqlExcel"]) ? App::$param["limits"]["sqlExcel"] : 10000;
        } else {
            $limit = !empty(App::$param["limits"]["sqlJson"]) ? App::$param["limits"]["sqlJson"] : 100000;
        }
        $name = "_" . md5(rand(1, 999999999) . microtime());
        $view = self::toAscii($name, null, "_");
        $formatSplit = explode("/", $format);
        if (sizeof($formatSplit) == 2 && $formatSplit[0] == "ogr") {
            $fileOrFolder = $nln ? $nln . $name : $view;
            $fileOrFolder .= "." . self::toAscii($formatSplit[1], null, "_");
            $path = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__vectors/" . $fileOrFolder;
            $cmd = "ogr2ogr " .
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
        $sqlView = "CREATE TEMPORARY VIEW $view as $q";
        $res = $this->prepare($sqlView);
        $res->execute();
        $arrayWithFields = $this->getMetaData($view, true, false, null, md5($q)); // Temp VIEW
        $columnTypes = [];
        foreach ($arrayWithFields as $key => $value) {
            $columnTypes[$key] = $value['typname'];
        }

        $fieldsArr = [];
        foreach ($columnTypes as $key => $type) {
            if ($type == "geometry") {
                if ($format == "geojson" || (($format == "csv" || $format == "excel") && $geoformat == "geojson")) {
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

        $this->prepare("DECLARE curs CURSOR FOR $sql")->execute($parameters);
        $innerStatement = $this->prepare("FETCH 1 FROM curs");

        $geometries = null;
        $fieldsForStore = [];
        $columnsForGrid = [];
        $features = [];

        if ($format == "geojson") {

            // GeoJSON output
            // ==============

            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = array();
                foreach ($row as $key => $value) {
                    if ($columnTypes[$key] == "geometry" && $value !== null) {
                        $geometries[] = json_decode($value);
                    } else {
                        if ($convertTypes) {
                            try {
                                $convertedValue = (new DefaultTypeConverterFactory())->getConverterForTypeSpecification($columnTypes[$key])->input($value);
                                $arr = $this->array_push_assoc($arr, $key, $convertedValue);
                            } catch (\Exception) {
                                $arr = $this->array_push_assoc($arr, $key, $value);
                            }
                        } else {
                            $arr = $this->array_push_assoc($arr, $key, $value);
                        }
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
                    if ($columnTypes[$key] == "geometry") {
                        $geometries[] = json_decode($value);
                    } elseif ($columnTypes[$key] == "json" || $columnTypes[$key] == "jsonb") {
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
        } elseif ($format == "csv") {

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

        } elseif ($format == "excel") {

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
        return [
            "success" => false,
        ];
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
                    $type = gettype($value);
                    if ($type == 'array' || $type == 'object') {
                        $nativeType = $typeHints[$field] ?? 'json';
                        try {
                            $nativeValue = $factory->getConverterForTypeSpecification($nativeType)->output($value);
                        } catch (\Exception) {
                            throw new GC2Exception("The value couldn't be parsed as $nativeType", 406, null, "VALUE_PARSE_ERROR");
                        }
                        $paramTmp[$field] = $nativeValue;
                    } elseif ($type == 'boolean') {
                        $nativeValue = $factory->getConverterForTypeSpecification($type)->output($value);
                        $paramTmp[$field] = $nativeValue;
                    } else {
                        $paramTmp[$field] = $value;
                    }
                }
                $convertedParameters[] = $paramTmp;
            }
        }
        // Get types from returning if any
        if ($convertReturning) {
            $this->begin();
            $result = $this->prepare($q);
            $result->execute($convertedParameters[0]);
            foreach (range(0, $result->columnCount() - 1) as $column_index) {
                $meta = $result->getColumnMeta($column_index);
                if (!$meta) {
                    break;
                }
                $columnTypes[$meta['name']] = $meta['native_type'];
            }
            $this->rollback(); // Roll back test
        }

        $returning = null;
        $affectedRows = 0;
        $result = $this->prepare($q);
        if (sizeof($convertedParameters) > 0) {
            $this->begin();
            foreach ($convertedParameters as $parameter) {
                $result->execute($parameter);
                $row = $this->fetchRow($result);
                $tmp = null;
                foreach ($row as $field => $value) {
                    try {
                        $convertedValue = $factory->getConverterForTypeSpecification($columnTypes[$field])->input($value);
                        $tmp[] = [$field => $convertedValue];
                    } catch (\Exception) {
                        if ($columnTypes[$field] == 'geometry') {
                            $resultGeom = $this->prepare("select ST_AsGeoJSON(:v) as json");
                            $resultGeom->execute(["v" => $value]);
                            $json = $this->fetchRow($resultGeom)['json'];
                            $value = !empty($json) ? json_decode($json) : null;
                        }
                        $tmp[] = [$field => $value];
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
            $affectedRows += $result->rowCount();
            $returning = $result->fetchAll(PDO::FETCH_NAMED);
            if (empty($returning[0])) {
                $returning = null;
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
        $res->execute(['username' => $username, 'statement' => $q, 'cost' => $cost]);
    }
}

