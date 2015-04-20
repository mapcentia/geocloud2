<?php

namespace app\models;

use app\inc\Model;

class Workflow extends Model
{
    public function getRecords($subuser, $showAll)
    {
        $select = "SELECT DISTINCT ON (version_gid) version_gid AS x,*,workflow->'author' as author,workflow->'reviewer' as reviewer,workflow->'publisher' as publisher, (case when status = 1 then 'Draft (1)'  when status = 2 then 'Reviewed (2)' when status =3 then 'Published (3)' END) as status_text FROM settings.workflow";

        if ($subuser && $showAll == false) {
            $sql = "SELECT * FROM (
                    {$select} WHERE exist(roles,:user1) AND roles->:user1 !='none'
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
            $sql = "{$select} ORDER BY version_gid,created DESC";
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

    public function touch($schema, $table, $gid, $user)
    {
        $primeryKey = $this->getPrimeryKey("{$schema}.{$table}");
        $layerObj = new Layer();
        $roleObj = $layerObj->getRole($schema, $table, $user);
        $roles = $roleObj["data"];
        $role = $roles[$user];

        if (!$role) {
            $response['success'] = false;
            $response['message'] = "You don't have a role in the workflow";
            $response['code'] = 401;
            return $response;
        }
        $this->begin();
        $query = "SELECT * FROM \"{$schema}\".\"{$table}\" WHERE {$primeryKey['attname']}=:gid";
        $res = $this->prepare($query);
        try {
            $res->execute(array("gid" => $gid));
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $originalFeature = $this->fetchRow($res);
        $gc2_version_gid = $originalFeature["gc2_version_gid"];
        $gc2_status = $originalFeature["gc2_status"];
        $gc2_workflow = $originalFeature["gc2_workflow"];
        switch ($role) {
            case "author":
                $workflow = "'{$gc2_workflow}'::hstore || hstore('author', '{$user}')";
                if ($gc2_status > 1) {
                    $this->rollback();
                    $response['success'] = false;
                    $response['message'] = "This feature has been " . ($gc2_status == 2 ? "reviewed" : "published") . ", so an author can't update it.";
                    $response['code'] = 401;
                    return $response;
                }
                $status = 1;
                break;
            case "reviewer":
                $workflow = "'{$gc2_workflow}'::hstore || hstore('reviewer', '{$user}')";
                if ($gc2_status > 2) {
                    $this->rollback();
                    $response['success'] = false;
                    $response['message'] = "This feature has been published so a reviewer can't update it.";
                    $response['code'] = 401;
                    return $response;
                }
                $status = 2;
                break;
            case "publisher":
                $workflow = "'{$gc2_workflow}'::hstore || hstore('publisher', '{$user}')";
                $status = 3;
                break;
            default:
                $workflow = "'{$gc2_workflow}'::hstore";
                $status = $gc2_status;
                break;
        }

        // Check if feature is ended
        if ($originalFeature["gc2_version_end_date"]) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = "You can't change the history!";
            $response['code'] = 401;
            return $response;
        }
        // Clone original feature for ended version
        $intoArr = array();
        $selectArr = array();
        foreach ($originalFeature as $k => $v) {
            if ($k != $primeryKey['attname']) {
                if ($k == "gc2_version_end_date") {
                    $intoArr[] = $k;
                    $selectArr[] = "now()";
                } else {
                    $intoArr[] = $selectArr[] = $k;
                }
            }
        }
        $sql = "INSERT INTO \"{$schema}\".\"{$table}\"(";
        $sql .= implode(",", $intoArr);
        $sql .= ")";
        $sql .= " SELECT ";
        $sql .= implode(",", $selectArr);
        $sql .= " FROM \"{$schema}\".\"{$table}\"";
        $sql .= " WHERE {$primeryKey['attname']}=:gid";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("gid" => $gid));
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        // Update feature
        $query = "UPDATE \"{$schema}\".\"{$table}\" SET gc2_status = {$status}, gc2_workflow = {$workflow} WHERE {$primeryKey['attname']}=:gid";
        $res = $this->prepare($query);
        try {
            $res->execute(array("gid" => $gid));
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $query = "INSERT INTO settings.workflow (f_schema_name,f_table_name,gid,status,gc2_user,roles,workflow,version_gid,operation)";
        $query .= " VALUES('{$schema}','{$table}',{$gid},{$status},'{$user}'," . \app\inc\PgHStore::toPg($roles) . ",{$workflow},{$gc2_version_gid},'update')";
        //die($query);

        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $this->commit();

        $response['success'] = true;
        $response['message'] = "Workflow updated";
        return $response;
    }
}