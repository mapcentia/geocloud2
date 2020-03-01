<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Keyvalue extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function get($key, $urlVars): array
    {
        $params = [];

        $fetchingAll = true;

        if ($key) {
            $fetchingAll = false;
        }

        if (isset($urlVars["paths"])) {
            $paths = explode(";", $urlVars["paths"]);

            foreach ($paths as $path) {
                $tmp[] = "'{$path}'::text,value#>'{{$path}}'";
            }
            $value = "json_build_object(" . implode(",", $tmp) . ") as value";

        } else {
            $value = "value";
        }

        if ($fetchingAll) {
            $sql = "SELECT id,key,{$value} FROM settings.key_value WHERE 1=1";
        } else {
            $sql = "SELECT id,key,{$value} FROM settings.key_value WHERE key=:key";
            $params["key"] = $key;
        }

        if (isset($urlVars["like"])) {
            $sql .= " AND key LIKE :where";
            $params["where"] = $urlVars["like"];
        }

        if (isset($urlVars["filter"])) {
            $parsedFilter = preg_replace("/'{\w+}'/", 'value#>>${0}', $urlVars["filter"]);
            $sql .= " AND {$parsedFilter}";
        }

        if (strpos($sql, ';') !== false) {
            $response['success'] = false;
            $response['code'] = 403;
            $response['message'] = "You can't use ';'";
            return $response;
        }
        if (strpos($sql, '--') !== false) {
            $response['success'] = false;
            $response['code'] = 403;
            $response['message'] = "SQL comments '--' are not allowed";
            return $response;
        }
        $response = [];
        try {
            $res = $this->prepare($sql);

            $res->execute($params);

        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        if ($fetchingAll) {
            $response["data"] = $this->fetchAll($res, "assoc");
        } else {
            $response["data"] = $this->fetchRow($res, "assoc");
        }

        $response["success"] = true;
        return $response;
    }

    public function insert($key, $json): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "INSERT INTO settings.key_value(key, value) VALUES (:key, :value) RETURNING *";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key, "value" => $json]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res, "assoc");
        $response["success"] = true;
        return $response;
    }

    public function update($key, $json): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "UPDATE settings.key_value SET value=:value WHERE key=:key RETURNING *";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key, "value" => $json]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res, "assoc");
        $response["success"] = true;
        return $response;
    }

    public function delete($key): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "DELETE FROM settings.key_value WHERE key=:key";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key]);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["success"] = true;
        $response["data"] = $key;
        return $response;
    }
}