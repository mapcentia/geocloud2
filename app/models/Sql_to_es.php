<?php
namespace app\models;

use app\inc\Model;
use GuzzleHttp\Client;

/**
 * Class Sql_to_es
 * @package app\models
 */
class Sql_to_es extends Model
{
    /**
     * @var string
     */
    private $srs;

    /**
     * Sql_to_es constructor.
     * @param string $srs
     */
    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    /**
     * @param $obj
     * @return array
     */
    private function checkForErrors(array $obj)
    {
        $res = array();
        foreach ($obj["items"] as $item) {
            if (isset($item["index"])) {
                $key = "index";
            } else {
                $key = "create"; // If no key is given Elasticsearch will use this key
            }
            if ($item[$key]["status"] != "201") {
                $res[] = array(
                    "id" => $item[$key]["_id"],
                    "error" => $item[$key]["error"],
                );
            }
        }
        return $res;
    }

    /**
     * @param string $q
     * @param $index
     * @param $type
     * @param $id
     * @param $db
     * @return array
     */
    public function sql($q, $index, $type, $id, $db)
    {
        $response = [];
        $i = 0;

        $esUrl = \app\conf\App::$param['esHost'] . ":9200/_bulk";
        $client = new Client([
            'timeout' => 10.0,
        ]);
        $bulKCount = 0;
        $bulkSize = 500;
        // We create a unique index name
        $errors = false;
        $errors_in = [];
        $index = $db . "_" . $index . "_" . $type;
        $name = "_" . rand(1, 999999999) . microtime();
        $view = $this->toAscii($name, null, "_");
        $sqlView = "CREATE TEMPORARY VIEW {$view} as {$q}";
        $res = $this->prepare($sqlView);

        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
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
                $fieldsArr[] = "ST_asGeoJson(ST_Transform({$ST_Force2D}(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);

        $sql = "SELECT {$sql} FROM {$view}";


        $this->begin();

        $result = $this->prepare("DECLARE curs CURSOR FOR {$sql}");

        try {
            $result->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }

        $innerStatement = $this->prepare("FETCH 1 FROM curs");

        $geometries = [];
        $features = [];
        $json = "";

        try {

            while ($innerStatement->execute() && $row = $this->fetchRow($innerStatement, "assoc")) {

                $arr = [];
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
                    $features = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
                }
                if (sizeof($geometries) == 1) {
                    $features = array("geometry" => $geometries[0], "type" => "Feature", "properties" => $arr);
                }
                if (sizeof($geometries) == 0) {
                    $features = array("type" => "Feature", "properties" => $arr);
                }

                unset($geometries);

                $json .= json_encode(array("index" => array("_index" => $index, "_type" => $type, "_id" => $arr[$id])));
                $json .= "\n";
                $json .= json_encode($features);
                $json .= "\n";

                if (is_int($i / $bulkSize)) {

                    $esResponse = $client->post($esUrl, ['body' => $json]);
                    $obj = json_decode($esResponse->getBody(), true);

                    if (isset($obj["errors"]) && $obj["errors"] == true) {
                        $errors = true;
                        $errors_in = array_merge($errors_in, $this->checkForErrors($obj));
                    }
                    $json = "";
                    $bulKCount++;
                    error_log($i);
                    error_log(number_format(memory_get_usage()));

                }

                $i++;
            }


            // Index the last bulk
            $esResponse = $client->post($esUrl, ['body' => $json]);
            $obj = json_decode($esResponse->getBody(), true);
            if (isset($obj["errors"]) && $obj["errors"] == true) {
                $errors = true;
                $errors_in = array_merge($errors_in, $this->checkForErrors($obj));
            }

            $this->execQuery("CLOSE curs");
            $this->commit();

        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return $response;
        }


        if ($errors) {
            \app\inc\Session::createLogEs($errors_in);
        }

        $response['success'] = true;
        $response['errors'] = $errors;
        $response['errors_in'] = $errors_in;
        $response['num_of_bulks'] = $bulKCount;
        $response['message'] = "Indexed {$i} documents";

        return $response;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

}
