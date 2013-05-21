<?php
class Sql_to_es extends postgis
{
    var $srs;

    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    function sql($q, $index, $type, $id)
    {
        $name = "_" . rand(1, 999999999) . microtime();
        $name = $this->toAscii($name, null, "_");
        $view = "public.{$name}";
        $sqlView = "CREATE VIEW {$view} as {$q}";
        $this->execQuery($sqlView);
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
            $i = 0;
            $json = "";
            $ch = curl_init("http://localhost:9200/_bulk");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
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
                if (sizeof($geometries) == 0) {
                    $features = array("geometry" => null, "type" => "Feature", "properties" => $arr);
                }
                unset($geometries);
                $json .= json_encode(array("index" => array("_index" => $index, "_type" => $type, "_id" => $arr[$id])));
                $json .= "\n";
                $json .= json_encode($features);
                $json .= "\n";
                //echo $json;

                if (is_int($i / 1000)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                    curl_exec($ch);
                    //curl_close($ch);
                    $json="";
                    echo $i."\n";
                }
                $i++;
            }
            // Index the last bulk
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_exec($ch);
            curl_close($ch);
            echo $i."\n";

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
