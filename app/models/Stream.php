<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */



namespace app\models;

ini_set('max_execution_time', 0);

use app\inc\Model;

/**
 * Class Stream
 * @package app\models
 */
class Stream extends Model
{
    /**
     * @var string
     */
    private $srs;

    /**
     * Sql_to_es constructor.
     * @param string $srs
     */
    function __construct($srs = "4326")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    /**
     * @param $q
     * @return string
     */
    public function runSql($q): string
    {
        $i = 0;
        $geometries = [];
        $features = [];
        $name = "_" . rand(1, 999999999) . microtime();
        $view = self::toAscii($name, null, "_");
        $sqlView = "CREATE TEMPORARY VIEW {$view} as {$q}";

        try {
            $res = $this->prepare($sqlView);
            $res->execute();
        } catch (\PDOException $e) {
            //$this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return serialize($response);
        }

        $arrayWithFields = $this->getMetaData($view, true); // Temp VIEW
        $fieldsArr = [];
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                $fieldsArr[] = "ST_asGeoJson(ST_Transform(ST_Force2D(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);

        $sql = "SELECT {$sql} FROM {$view}";

        $this->begin();

        try {
            $this->prepare("DECLARE curs CURSOR FOR {$sql}")->execute();
            $innerStatement = $this->prepare("FETCH 1 FROM curs");
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return serialize($response);
        }

        header('Content-type: text/plain; charset=utf-8');
        try {
            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement, "assoc")) {
                $arr = [];
                foreach ($row as $key => $value) {
                    if ($arrayWithFields[$key]['type'] == "geometry") {
                        $geometries[] = json_decode($row[$key]);
                    } elseif ($arrayWithFields[$key]['type'] == "json" || $arrayWithFields[$key]['type'] == "jsonb") {
                        $arr = $this->array_push_assoc($arr, $key, json_decode($value));
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }
                if (sizeof($geometries) > 1) {
                    $features = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
                }
                if (sizeof($geometries) == 1) {
                    $features = array("type" => "Feature", "properties" => $arr, "geometry" => $geometries[0]);
                }
                if (sizeof($geometries) == 0) {
                    $features = array("type" => "Feature", "properties" => $arr);
                }
                unset($geometries);
                $json = json_encode($features);
                $json .= "\n";
                $json .= $i . " " . memory_get_usage(true) ."\n";
                echo $json;
                flush();
                ob_flush();
                $i++;
            }
            $this->execQuery("CLOSE curs");
            $this->commit();

        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return $response;
        }
        die();
    }

    /**
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }
}
