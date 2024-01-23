<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Model;
use PDO;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
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
     * @return array
     * @throws Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function sql(string $q, ?string $clientEncoding = null, ?string $format = "geojson", ?string $geoformat = "wkt", ?bool $csvAllToStr = false, ?string $aliasesFrom = null, ?string $nlt = null, ?string $nln = null): array
    {
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
                header("Content-Disposition: attachment; filename=\"{$fileOrFolder}\"");
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
                header("Content-Disposition: attachment; filename=\"{$fileOrFolder}.zip\"");
                readfile($zipPath);
                exit(0);
            }
        }
        $sqlView = "CREATE TEMPORARY VIEW $view as $q";
        $res = $this->prepare($sqlView);
        $res->execute();

        $arrayWithFields = $this->getMetaData($view, true, false, null, $q); // Temp VIEW
        $postgisVersion = $this->postgisVersion();
        $bits = explode(".", $postgisVersion["version"]);
        if ((int)$bits[0] < 3 && (int)$bits[1] === 0) {
            $ST_Force2D = "ST_Force_2D"; // In case of PostGIS 2.0.x
        } else {
            $ST_Force2D = "ST_Force2D";
        }
        $fieldsArr = [];
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                if ($format == "geojson" || (($format == "csv" || $format == "excel") && $geoformat == "geojson")) {
                    $fieldsArr[] = "ST_asGeoJson(ST_Transform($ST_Force2D(\"$key\"),$this->srs)) as \"$key\"";
                } elseif ($format == "csv" || $format == "excel") {
                    $fieldsArr[] = "ST_asText(ST_Transform($ST_Force2D(\"$key\"),$this->srs)) as \"$key\"";
                }
            } elseif ($arr['type'] == "bytea") {
                $fieldsArr[] = "encode(\"$key\",'escape') as \"$key\"";
            } else {
                $fieldsArr[] = "\"$key\"";
            }
        }
        $sql = implode(",", $fieldsArr);
        $sql = "SELECT $sql FROM $view LIMIT $limit";

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

        $this->prepare("DECLARE curs CURSOR FOR $sql")->execute();
        $innerStatement = $this->prepare("FETCH 1 FROM curs");

        $geometries = null;
        $fieldsForStore = [];
        $columnsForGrid = [];
        $features = [];
        $typeConverterFactory = new DefaultTypeConverterFactory();

        // GeoJSON output
        // ==============

        if ($format == "geojson") {
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement)) {
                $arr = array();
                foreach ($row as $key => $value) {
                    if ($arrayWithFields[$key]['type'] == "geometry" && $value !== null) {
                        $geometries[] = json_decode($value);
                    } else {
                        try {
                            $convertedValue = $typeConverterFactory->getConverterForTypeSpecification($arrayWithFields[$key]['type'])->input($value);
                            $arr = $this->array_push_assoc($arr, $key, $convertedValue);
                        } catch (\Exception $e) {
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

            foreach ($arrayWithFields as $key => $value) {
                $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
                $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => !empty($value['typeObj']) ? $value['typeObj'] : null);
            }
//            $this->free($result);
            $response['success'] = true;
            $response['forStore'] = $fieldsForStore;
            $response['forGrid'] = $columnsForGrid;
            $response['type'] = "FeatureCollection";
            $response['features'] = $features;
            return $response;
        }

        // CSV/Excel output
        // ================

        elseif ($format == "csv" || $format == "excel") {
            $withGeom = $geoformat;
            $separator = ";";
            $first = true;
            $lines = array();
            $fieldConf = null;

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
                    if ($arrayWithFields[$key]['type'] == "geometry") {
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

            if ($format == "excel") {
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

                // We'll be outputting an Excel file
                header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

                // It will be called file.xlsx
                header('Content-Disposition: attachment; filename="file.xlsx"');

                // Write file to the browser
                $objWriter->save('php://output');
                die();
            }

            $response['success'] = true;
            $response['csv'] = $csv;
            return $response;
        }
        return [
            "success" => false,
        ];
    }

    /**
     * @param string $q
     * @return array
     */
    public function transaction(string $q): array
    {
        $result = $this->prepare($q);
        $result->execute();
        $response['success'] = true;
        $response['affected_rows'] = $result->rowCount();
        $response['returning'] = $result->fetchAll(PDO::FETCH_NAMED);
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
}

