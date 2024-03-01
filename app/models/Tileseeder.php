<?php

/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use Exception;
use PDOException;

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
     * @return void
     */
    public function insert(array $data): void
    {
        $sql = "INSERT INTO settings.seed_jobs (uuid, name, pid, host) VALUES (:uuid, :name, :pid, :host) RETURNING *";
        $res = $this->prepare($sql);
        $arr = ["uuid" => $data["uuid"], "name" => $data["name"], "pid" => $data["pid"], "host" => $_SERVER["SERVER_ADDR"]];
        $res->execute($arr);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getAll(): array
    {
        $response = [];
        $sql = "SELECT * FROM settings.seed_jobs";
        $res = $this->prepare($sql);
        $res->execute();
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
        $res->execute(["pid" => $pid]);
        $response["success"] = true;
        $response["data"] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param string|null $uuid
     * @return array
     */
    public function getByUuid(string $uuid = null): array
    {
        $response = [];
        $sql = "SELECT * FROM settings.seed_jobs WHERE uuid=:uuid";
        $res = $this->prepare($sql);
        $res->execute(["uuid" => $uuid]);
        $response["success"] = true;
        $response["data"] = $this->fetchRow($res);
        return $response;
    }
}