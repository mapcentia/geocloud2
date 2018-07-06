<?php

namespace app\models;

use \app\conf\App;
use \app\inc\Model;

class Qgis extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function insert(array $data): array
    {
        $response = [];
        $sql = "INSERT INTO settings.qgis_files(id, xml, db) VALUES (:id, :xml, :db)";
        $res = $this->prepare($sql);
        try {
            $res->execute($data);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "QGIS file stored";
        return $response;
    }

    public function writeAll($db): array
    {
        $response = [];
        $files = [];
        $sql = "SELECT * FROM settings.qgis_files WHERE db=:db";
        $res = $this->prepare($sql);
        try {
            $res->execute(["db" => $db]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $path = App::$param['path'] . "/app/wms/qgsfiles/";

        while ($row = $this->fetchRow($res, "assoc")) {
            @unlink($path . $row["id"]);
            @$fh = fopen($path . $row["id"], 'w');
            if (!$fh) {
                $response['success'] = false;
                $response['message'] = "Couldn't open file for writing: ". $row["id"];
                $response['code'] = 401;
                return $response;
            }
            @$w = fwrite($fh, $row["xml"]);
            if (!$w) {
                $response['success'] = false;
                $response['message'] = "Couldn't write the file: " . $row["id"];
                $response['code'] = 401;
                return $response;
            }
            fclose($fh);
            $files[] = $row["id"];

        }

        $response['success'] = true;
        $response['data'] = $files;
        return $response;
    }


}