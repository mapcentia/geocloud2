<?php
namespace app\models;

use app\inc\Model;

class Drawing extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function load($username)
    {
        $sql = "SELECT ST_astext(the_geom) AS the_geom FROM drawings.drawings WHERE username=:username";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("username" => $username));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res)["the_geom"];
        return $response;
    }

    public function save($wktStr, $username)
    {
        // Test if user already have a record
        $sql = "SELECT username FROM drawings.drawings WHERE username=:username";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("username" => $username));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        if ($this->fetchRow($res)) {
            $sql = "UPDATE drawings.drawings SET the_geom = St_geomfromtext(:geom,4326) WHERE username=:username";

        } else {
            $sql = "INSERT INTO drawings.drawings (username, the_geom) VALUES(:username, St_geomfromtext(:geom,4326))";
        }
        $res = $this->prepare($sql);
        try {
            $res->execute(array("username" => $username, "geom" => $wktStr));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        return $response;
    }
}