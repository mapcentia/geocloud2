<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use \app\conf\App;
use \app\conf\Connection;
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

    public function flagAsOld($id): array
    {
        $response = [];
        $sql = "UPDATE settings.qgis_files SET old=TRUE WHERE id=:id";
        $res = $this->prepare($sql);
        try {
            $res->execute(["id" => $id]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "QGIS file flagged";
        return $response;
    }

    public function writeAll($db): array
    {
        $response = [];
        $files = [];
        $sql = "SELECT *,extract(EPOCH FROM timestamp) AS unixtimestamp FROM settings.qgis_files WHERE db=:db AND old !=TRUE ORDER BY timestamp";
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
                $response['message'] = "Couldn't open file for writing: " . $row["id"];
                $response['code'] = 401;
                return $response;
            }
            @$w = fwrite($fh, $this->parse($row["xml"]));
            if (!$w) {
                $response['success'] = false;
                $response['message'] = "Couldn't write the file: " . $row["id"];
                $response['code'] = 401;
                return $response;
            } else {
                touch($path . $row["id"], $row["unixtimestamp"]);
            }
            fclose($fh);
            $files[] = $row["id"];
        }

        $response['success'] = true;
        $response['data'] = $files;
        return $response;
    }

    private function parse($xml)
    {
        $xml = preg_replace("/port='?[0-9]*'?/", "port=" . Connection::$param["postgisport"] . "", $xml);
        $xml = preg_replace("/user=\'?[^\s\\\\]*\'?/", "user=" . Connection::$param["postgisuser"] . "", $xml);
        $xml = preg_replace("/password=\'?[^\s\\\\]*\'?/", "password=" . Connection::$param["postgispw"] . "", $xml);
        $xml = preg_replace("/host=\'?[^\s\\\\]*\'?/", "host=" . Connection::$param["postgishost"] . "", $xml);
        $xml = preg_replace("/dbname=\'?[^\s\\\\]*\'?/", "dbname=" . Database::getDb() . "", $xml);
        return $xml;
    }
}