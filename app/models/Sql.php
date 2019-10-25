<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;


/**
 * Class Sql
 * @package app\models
 */
class Sql extends \app\inc\Model
{
    /**
     * @var string
     */
    private $srs;

    /**
     * Sql constructor.
     * @param string $srs
     */
    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    /**
     * @param string $q
     * @param null $clientEncoding
     * @param string $format
     * @param string $geoformat
     * @param bool $csvAllToStr
     * @param null $aliasesFrom
     * @return mixed
     */
    public function sql($q, $clientEncoding = null, $format = "geojson", $geoformat = "wkt", $csvAllToStr = false, $aliasesFrom = null)
    {
        if ($format == "excel") {
            $limit = 10000;
        } else {
            $limit = 100000;
        }
        $name = "_" . rand(1, 999999999) . microtime();
        $view = self::toAscii($name, null, "_");
        $sqlView = "CREATE TEMPORARY VIEW {$view} as {$q}";
        $res = $this->prepare($sqlView);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $arrayWithFields = $this->getMetaData($view, true); // Temp VIEW
        $postgisVersion = $this->postgisVersion();
        $bits = explode(".", $postgisVersion["version"]);
        if ((int)$bits[1] > 0) {
            $ST_Force2D = "ST_Force2D";
        } else {
            $ST_Force2D = "ST_Force_2D";
        }

        $fieldsArr = [];
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                if ($format == "geojson" || (($format == "csv" || $format == "excel") && $geoformat == "geojson")) {
                    $fieldsArr[] = "ST_asGeoJson(ST_Transform({$ST_Force2D}(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
                } elseif ($format == "csv" || $format == "excel") {
                    $fieldsArr[] = "ST_asText(ST_Transform({$ST_Force2D}(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
                }
            }
            elseif ($arr['type'] == "bytea") {
                $fieldsArr[] = "encode(\"" . $key . "\",'escape') as \"" . $key . "\"";
            }
            else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);
        $sql = "SELECT {$sql} FROM {$view} LIMIT {$limit}";

        $this->begin();
        $this->execQuery('SET LOCAL statement_timeout = 60000');
        if ($clientEncoding) {
            $this->execQuery("set client_encoding='{$clientEncoding}'", "PDO");
        }
        try {
            $result = $this->prepare($sql);
            $result->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return $response;
        }
        $this->commit();

        $geometries = [];
        $fieldsForStore = [];
        $columnsForGrid = [];
        $features = [];

        // GeoJSON output
        // ==============

        if ($format == "geojson") {
            try {
                while ($row = $this->fetchRow($result, "assoc")) {
                    $arr = array();
                    foreach ($row as $key => $value) {
                        if ($arrayWithFields[$key]['type'] == "geometry") {
                            $geometries[] = json_decode($row[$key]);
                        } elseif ($arrayWithFields[$key]['type'] == "json") {
                            $arr = $this->array_push_assoc($arr, $key, json_decode($value));
                        } else {
                            $arr = $this->array_push_assoc($arr, $key, $value);
                        }
                    }
                    if (sizeof($geometries) > 1) {
                        $features[] = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
                    }
                    if (sizeof($geometries) == 1) {
                        $features[] = array("geometry" => $geometries[0], "type" => "Feature", "properties" => $arr);
                    }
                    if (sizeof($geometries) == 0) {
                        $features[] = array("type" => "Feature", "properties" => $arr);
                    }
                    unset($geometries);
                }
            } catch (\Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 410;
                return $response;
            }
            foreach ($arrayWithFields as $key => $value) {
                $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
                $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => !empty($value['typeObj']) ? $value['typeObj'] : null);
            }
            $this->free($result);
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
            $withGeom = $geoformat ? true : false;
            $separator = ";";
            $first = true;
            $lines = array();
            $fieldConf = null;

            if ($aliasesFrom) {
                $fieldConf = json_decode($this->getGeometryColumns($aliasesFrom, "fieldconf"));
            }

            try {
                while ($row = $this->fetchRow($result, "assoc")) {
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
                        $value = str_replace('"', '""', $value);

                        // Any text is quoted
                        if ($csvAllToStr) {
                            $fields[] = "\"{$value}\"";

                        } else {
                            $fields[] = !is_numeric($value) ? "\"{$value}\"" : $value;

                        }
                    }
                    $lines[] = implode($separator, $fields);
                }
                $csv = implode("\n", $lines);

                // Convert to Excel
                // ================

                if ($format == "excel") {
                    include '../app/vendor/phpoffice/phpexcel/Classes/PHPExcel/IOFactory.php';
                    $file = tempnam(sys_get_temp_dir(), 'excel_');
                    $handle = fopen($file, "w");
                    fwrite($handle, $csv);
                    $csv = null;

                    $objReader = new \PHPExcel_Reader_CSV();
                    $objReader->setDelimiter($separator);
                    $objPHPExcel = $objReader->load($file);

                    fclose($handle);
                    unlink($file);

                    $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);

                    // We'll be outputting an excel file
                    header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

                    // It will be called file.xlsx
                    header('Content-Disposition: attachment; filename="file.xlsx"');

                    // Write file to the browser
                    $objWriter->save('php://output');
                    die();
                }
            } catch (\Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 410;
                return $response;
            }
            $this->free($result);
            $response['csv'] = $csv;
            return $response;
        }
    }

    /**
     * @param $q
     * @return mixed
     */
    public function transaction($q)
    {
        $result = $this->execQuery($q, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['affected_rows'] = $result;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        return $response;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }
}

