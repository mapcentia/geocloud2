<?php
namespace app\models;

use app\inc\Model;

class Senti extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function insert($data)
    {
        $response = array();
        $successes = array();
        $sql = "INSERT INTO senti(id, type, signature, data, registered) VALUES (:id, :type, :signature, :data, :registered) RETURNING *";
        $res = $this->prepare($sql);
        foreach($data["records"] as $r) {
            $arr = array("id"=>$r["id"], "type"=>$r["type"], "signature"=>$r["signature"], "data"=>$r["data"], "registered"=>$r["registered"]);
            try {
                $res->execute($arr);
                $successes[] = array("id"=>$r["id"], "status"=>1);
            } catch (\PDOException $e) {
                $successes[] = array("id"=>$r["id"], "status"=>0, "message"=>$e->getMessage());
            }
        }
        $response["json"] = json_encode($successes);
        return $response;
    }


}