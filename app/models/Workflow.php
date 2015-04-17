<?php

namespace app\models;

use app\inc\Model;

class Workflow extends Model
{
    public function getRecords($subuser, $showAll)
    {
        if ($subuser && $showAll == false) {
            $sql = "SELECT * FROM (
                    SELECT DISTINCT ON (version_gid) version_gid,* FROM settings.workflow WHERE exist(roles,:user1) AND roles->:user1 !='none'
                    ORDER BY version_gid,created DESC

                    ) AS foo WHERE
                    (
                        workflow @> :user2 = FALSE AND
                        workflow @> :user3 = FALSE AND
                        workflow @> :user4 = FALSE
                    ) AND operation !='delete'";
            $args = array(
                "user1" => $subuser,
                "user2" => "author=>{$subuser}",
                "user3" => "reviewer=>{$subuser}",
                "user4" => "publisher=>{$subuser}",
            );
        } else {
            $sql = "SELECT DISTINCT ON (version_gid) version_gid AS x,* FROM settings.workflow ORDER BY version_gid,created DESC";
            $args = array();
        }

        $this->connect();
        $this->db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        $res = $this->prepare($sql);
        try {
            $res->execute($args);
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $arr[] = $row;
        }
        $response['success'] = true;
        $response['message'] = "Work fetched";
        $response['data'] = (sizeof($arr) > 0) ? $arr : array();
        return $response;
    }
}