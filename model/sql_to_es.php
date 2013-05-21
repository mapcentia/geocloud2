<?php
class Sql_to_es extends postgis
{
    var $srs;
    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    function sql($q,$index,$type,$id)
    {
        $name = "_" . rand(1, 999999999) . microtime();
        $name = $this->toAscii($name, null, "_");
        $view = "public.{$name}";
        $sqlView = "CREATE VIEW {$view} as {$q}";
        $result = $this->execQuery($sqlView);
        if (!$this->PDOerror) {
            $arrayWithFields = $this->getMetaData($view);
            foreach ($arrayWithFields as $key => $arr) {
                if ($arr['type'] == "geometry") {
                    $fieldsArr[] = "ST_asGeoJson(ST_Transform(" . $key . "," . $this->srs . ")) as " . $key;
                } else {
                    $fieldsArr[] = $key;
                }
            }
            $sql = implode(",", $fieldsArr);
            $sql = "SELECT {$sql} FROM {$view}";
            //echo $sql;
            $result = $this->execQuery($sql);
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr = array();
                foreach ($row as $key => $value) {

                    if ($arrayWithFields[$key]['type'] == "geometry") {
                        $geometries[] = json_decode($row[$key]);
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
                unset($geometries);
                echo json_encode(array("index"=>array("_index"=>$index,"_type"=>$type,"_id"=>$arr[$id])));
                echo "\n";
                echo json_encode($features);
                echo "\n";
                flush();
            }
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            echo json_encode($response);
        }
        $sql = "DROP VIEW {$view}";
        $result = $this->execQuery($sql);
    }
    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

}
