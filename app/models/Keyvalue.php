<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use Exception;
use PDOException;


/**
 * Class Keyvalue
 * @package app\models
 */
class Keyvalue extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string|null $key
     * @param array<string> $urlVars
     * @return array<mixed>
     * @throws Exception
     */

    public function get(?string $key, array $urlVars): array
    {
        $params = [];
        $tmp = [];

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

        $sql .= " ORDER BY updated DESC, id DESC"; // Newest first in output

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

        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        if ($fetchingAll) {
            $response["data"] = $this->fetchAll($res, "assoc") ?: [];
        } else {
            $response["data"] = $this->fetchRow($res) ?: [];
        }
        // HACK get rid of unnecessary meta in Vidi snapshots
        if (isset($urlVars["like"]) && $urlVars["like"] == "state_snapshot_%") {
            if (!is_array($response["data"][0])) {
                $parsed = json_decode($response["data"]["value"], true);
                unset($parsed["snapshot"]);
                if ($parsed)
                    $response["data"]["value"] = json_encode($parsed);
                else
                    $response["data"] = [];
            } else {
                foreach ($response["data"] as $key => $value) {
                    $parsed = json_decode($value["value"], true);
                    unset($parsed["snapshot"]);
                    if ($parsed)
                        $response["data"][$key]["value"] = json_encode($parsed);
                    else
                        $response["data"][$key] = [];
                }
            }
        }
        // HACK end

        $response["success"] = true;
        return $response;
    }

    /**
     * @param string $key
     * @param string $json
     * @return array<mixed>
     */
    public function insert(string $key, string $json): array
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
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res);
        $response["success"] = true;
        return $response;
    }

    /**
     * @param string|null $key
     * @param string $json
     * @return array<mixed>
     */
    public function update(?string $key, string $json): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "UPDATE settings.key_value SET value=:value, updated=default WHERE key=:key RETURNING *";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key, "value" => $json]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res);
        $response["success"] = true;
        return $response;
    }

    /**
     * @param string|null $key
     * @return array<mixed>
     */
    public function delete(?string $key): array
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
        } catch (PDOException $e) {
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