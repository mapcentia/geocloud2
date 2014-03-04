<?php

namespace app\models;

class Database extends \app\inc\Model
{
    private function createUser($name)
    {
        $sql = "create user {$name} with password '1234'";
        $this->execQuery($sql);

        if (!$this->PDOerror) {
            return true;
        } else {
            return false;
        }
    }

    public function createSchema($name)
    {
        $sql = "CREATE SCHEMA " . $this->toAscii($name, NULL, "_");
        $this->execQuery($sql);
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Schema created";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    public function createdb($screenName, $template, $encoding = "UTF8")
    {
        $this->createUser($screenName);

        $sql = "CREATE DATABASE {$screenName}
			    WITH ENCODING='{$encoding}'
       			OWNER=postgres
       			TEMPLATE={$template}
       			CONNECTION LIMIT=-1;
			";
        $this->execQuery($sql);
        $sql = "GRANT ALL PRIVILEGES ON DATABASE {$screenName} to {$screenName}";
        $this->execQuery($sql);

        $this->changeOwner($screenName,$screenName);

        if (!$this->PDOerror) {
            return true;
        } else {
            $sql = "DROP DATABASE {$screenName}";
            $this->execQuery($sql);
            $sql = "DROP USER {$screenName}";
            $this->execQuery($sql);
            print_r($this->PDOerror);
            return false;
        }
        return $response;
    }

    public function doesDbExist($name)
    {
        $sql = "SELECT 1 AS check FROM pg_database WHERE datname='{$name}'";
        $row = $this->fetchRow($this->execQuery($sql), "assoc");
        if ($row['check']) {
            $response['success'] = true;
        } else {
            $response['success'] = false;
        }
        return $response;
    }

    public function listAllDbs()
    {
        $sql = "SELECT datname FROM pg_catalog.pg_database";
        $result = $this->execQuery($sql);
        if (!$this->PDOerror) {
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr[] = $row['datname'];
            }
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    public function listAllSchemas()
    {
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'settings' AND schema_name<>'information_schema' AND schema_name<>'sqlapi'";
        $result = $this->execQuery($sql);
        if (!$this->PDOerror) {
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr[] = array("schema" => $row['schema_name']);
            }
            $response['success'] = true;
            $response['data'] = $arr;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }
    private function changeOwner($db, $newOwner){
        $this->db = null;
        $this->postgisdb = $db;

        $this->connect();
        $this->begin();

        // Schema
        $sql = "select schema_name from information_schema.schemata where schema_name not like 'pg_%' AND schema_name<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows1 = $this->fetchAll($res);

        // tables
        $sql = "select schemaname||'.'||tablename as table from pg_tables WHERE schemaname not like 'pg_%' AND schemaname<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows2 = $this->fetchAll($res);


        $sql = "select table_schema||'.'||table_name as table from information_schema.views where table_schema not like 'pg_%' AND table_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows3 = $this->fetchAll($res);


        $sql = "select sequence_schema||'.'||sequence_name as table from information_schema.sequences where sequence_schema not like 'pg_%' AND sequence_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows4 = $this->fetchAll($res);


        foreach ($rows1 as $row){
            $sql = "alter schema {$row["schema_name"]} owner to {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows2 as $row){
            $sql = "alter table {$row["table"]} owner to {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows3 as $row){
            $sql = "alter table {$row["table"]} owner to {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows4 as $row){
            $sql = "alter table {$row["table"]} owner to {$newOwner}";
            $this->execQuery($sql);
        }

        $this->commit();

        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Owner changed";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            exit();
        }
        return $response;
    }
    static function setDb($db){
        \app\conf\Connection::$param["postgisdb"] = $db;
    }
}
