<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;

/**
 * Class Database
 * @package app\models
 */
class Database extends \app\inc\Model
{
    private function createUser($name)
    {
        $sql = "CREATE USER {$name}";
        $this->execQuery($sql);
        try {
            $this->execQuery($sql);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createSchema($name)
    {
        $sql = "CREATE SCHEMA " . self::toAscii($name, NULL, "_");
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
       			TEMPLATE={$template}
       			CONNECTION LIMIT=-1;
			";
        $this->execQuery($sql);

        $sql = "GRANT ALL PRIVILEGES ON DATABASE {$screenName} to {$screenName}";
        $this->execQuery($sql);

        $postgisUser = explode('@', $this->postgisuser)[0];
        $sql = "GRANT {$screenName} to {$postgisUser}";
        $this->execQuery($sql);

        $this->changeOwner($screenName, $screenName);

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
        $arr = [];
        $sql = "SELECT count(*) AS count,f_table_schema FROM geometry_columns GROUP BY f_table_schema";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $count[$row['f_table_schema']] = $row['count'];
        }

        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'settings' AND schema_name<>'information_schema' AND schema_name<>'sqlapi'";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $arr[] = array("schema" => $row['schema_name'], "count" => isset($count[$row['schema_name']]) ? $count[$row['schema_name']] : 0);
        }
        $response['success'] = true;
        $response['data'] = $arr;
        return $response;
    }

    public function changeOwner($db, $newOwner)
    {
        $this->db = null;
        $this->postgisdb = $db;

        $this->connect();
        $this->begin();

        //Database
        $sql = "ALTER DATABASE {$db} OWNER TO {$newOwner}";
        $res = $this->execQuery($sql);

        // Schema
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows1 = $this->fetchAll($res);

        // tables
        $sql = "SELECT '\"'||schemaname||'\".\"'||tablename||'\"' AS table FROM pg_tables WHERE schemaname NOT LIKE 'pg_%' AND schemaname<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows2 = $this->fetchAll($res);


        $sql = "SELECT '\"'||table_schema||'\".\"'||table_name||'\"' AS table FROM information_schema.views WHERE table_schema NOT LIKE 'pg_%' AND table_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows3 = $this->fetchAll($res);


        $sql = "SELECT '\"'||sequence_schema||'\".\"'||sequence_name||'\"' AS table FROM information_schema.sequences WHERE sequence_schema NOT LIKE 'pg_%' AND sequence_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows4 = $this->fetchAll($res);


        $this->execQuery($sql);
        foreach ($rows1 as $row) {
            $sql = "ALTER SCHEMA {$row["schema_name"]} OWNER TO {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows1 as $row) {
            $sql = "GRANT USAGE ON SCHEMA {$row["schema_name"]} TO {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows2 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows3 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
            $this->execQuery($sql);
        }
        foreach ($rows4 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
            $this->execQuery($sql);
        }

        $this->commit();

        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['message'] = "Owner changed";
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
        }
        return $response;
    }

    static function setDb($db)
    {
        \app\conf\Connection::$param["postgisdb"] = $db;
    }

    static function getDb()
    {
        return \app\conf\Connection::$param["postgisdb"];
    }

    public function renameSchema($schema, $name)
    {
        if ($schema == "public") {
            $response['success'] = false;
            $response['message'] = "You can't rename 'public'";
            $response['code'] = 401;
            return $response;
        }
        $newName = self::toAscii($name, array(), "_");
        $this->connect();
        $this->begin();
        $whereClauseG = "f_table_schema=''{$schema}''";
        $whereClauseR = "r_table_schema=''{$schema}''";
        $query = "SELECT * FROM settings.getColumns('{$whereClauseG}','{$whereClauseR}') ORDER BY sort_id";
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
        while ($row = $this->fetchRow($res)) {
            $query = "UPDATE settings.geometry_columns_join SET _key_ = '{$newName}.{$row['f_table_name']}.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
            $resUpdate = $this->prepare($query);
            try {
                $resUpdate->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
        }
        $query = "ALTER SCHEMA {$schema} RENAME TO {$newName}";
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
        $setObj = new \app\models\Setting();
        $settings = $setObj->getArray();
        $extents = $settings->extents->$schema;
        $center = $settings->center->$schema;
        $zoom = $settings->zoom->$schema;
        if ($extents) {
            $settings->extents->$newName = $extents;
            $settings->center->$newName = $center;
            $settings->zoom->$newName = $zoom;
            if (\app\conf\App::$param["encryptSettings"]) {
                $pubKey = file_get_contents(\app\conf\App::$param["path"] . "app/conf/public.key");
                $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($settings) . "', dearmor('{$pubKey}'))";
            } else {
                $sql = "UPDATE settings.viewer SET viewer='" . json_encode($settings) . "'";
            }
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 401;
                return $response;
            }
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "{$schema} renamed to {$newName}";
        $response['data']['name'] = $newName;
        return $response;
    }

    public function deleteSchema($schema)
    {
        if ($schema == "public") {
            $response['success'] = false;
            $response['message'] = "You can't delete 'public'";
            $response['code'] = 401;
            return $response;
        }
        $this->connect();
        $this->begin();
        $query = "DROP SCHEMA {$schema} CASCADE";
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
        $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ LIKE '{$schema}.%'";
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
        $response['message'] = "{$schema} dropped";
        return $response;
    }
}
