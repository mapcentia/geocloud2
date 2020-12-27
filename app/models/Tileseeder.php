<?php

/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

class Tileseeder extends Model
{
    /**
     * Tileseeder constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param array $data
     * @return array
     */
    public function insert(array $data): array
    {
        $response = [];
        $sql = "INSERT INTO settings.seed_jobs (uuid, name, pid, host) VALUES (:uuid, :name, :pid, :host) RETURNING *";
        $res = $this->prepare($sql);
        $arr = ["uuid" => $data["uuid"], "name" => $data["name"], "pid" => $data["pid"], "host" => $_SERVER["SERVER_ADDR"]];
        try {
            $res->execute($arr);
        } catch (\PDOException $e) {
            $response["success"] = false;
            $response["message"] = $e->getMessage();
            return $response;
        }

        $response["success"] = true;
        $response["data"] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAll(): array
    {
        $response = [];
        $sql = "SELECT * FROM settings.seed_jobs";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response["success"] = false;
            $response["message"] = $e->getMessage();
            return $response;
        }
        $response["success"] = true;
        $response["data"] = $this->fetchAll($res);
        return $response;
    }

    /**
     * @param int $pid
     * @return array
     */
    public function getByPid(int $pid): array
    {
        $response = [];
        $sql = "SELECT * FROM settings.seed_jobs WHERE pid=:pid";
        $res = $this->prepare($sql);
        try {
            $res->execute(["pid" => $pid]);
        } catch (\PDOException $e) {
            $response["success"] = false;
            $response["message"] = $e->getMessage();
            return $response;
        }
        $response["success"] = true;
        $response["data"] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param string|null $uuid
     * @return array<mixed>
     */
    public function getByUuid(string $uuid = null): array
    {
        $response = [];
        $sql = "SELECT * FROM settings.seed_jobs WHERE uuid=:uuid";
        $res = $this->prepare($sql);
        try {
            $res->execute(["uuid" => $uuid]);
        } catch (\PDOException $e) {
            $response["success"] = false;
            $response["message"] = $e->getMessage();
            return $response;
        }
        $response["success"] = true;
        $response["data"] = $this->fetchRow($res);
        return $response;
    }
}