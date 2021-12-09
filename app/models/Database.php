<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Model;
use Exception;
use PDOException;


/**
 * Class Database
 * @package app\models
 */
class Database extends Model
{
    /**
     * @param string $name
     * @return void
     * @throws PDOException
     */
    private function createUser(string $name): void
    {
        $sql = "CREATE USER {$name}";
        $this->db->query($sql);
    }

    /**
     * @param string $name
     * @throws PDOException
     */
    public function dropUser(string $name): void
    {
        $sql = "DROP USER {$name}";
        $this->db->query($sql);
    }

    /**
     * @param string $name
     * @throws PDOException
     */
    public function dropDatabase(string $name): void
    {
        $sql = "DROP DATABASE {$name}";
        $this->db->query($sql);
    }

    /**
     * @param string $name
     * @return array<bool|string>
     */
    public function createSchema(string $name): array
    {
        $sql = "CREATE SCHEMA " . self::toAscii($name, null, "_");
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

    /**
     * @param string $screenName
     * @param string $template
     * @param string $encoding
     * @return bool
     * @throws Exception
     */
    public function createdb(string $screenName, string $template, string $encoding = "UTF8"): bool
    {
        $this->createUser($screenName);

        $sql = "CREATE DATABASE {$screenName}
			    WITH ENCODING='{$encoding}'
       			TEMPLATE={$template}
       			CONNECTION LIMIT=-1;
			";
        $this->db->query($sql);

        $sql = "GRANT ALL PRIVILEGES ON DATABASE {$screenName} to {$screenName}";
        $this->db->query($sql);

        $postgisUser = explode('@', $this->postgisuser)[0];
        $sql = "GRANT {$screenName} to {$postgisUser}";
        $this->db->query($sql);

        $this->changeOwner($screenName, $screenName);

        if (!$this->PDOerror) {
            return true;
        } else {
            $sql = "DROP DATABASE {$screenName}";
            $this->execQuery($sql);
            $sql = "DROP USER {$screenName}";
            $this->execQuery($sql);
            return false;
        }
    }

    /**
     * @param string $name
     * @return array<bool>
     */
    public function doesDbExist(string $name): array
    {
        $sql = "SELECT 1 AS check FROM pg_database WHERE datname='{$name}'";
        $row = $this->fetchRow($this->execQuery($sql));
        if ($row['check']) {
            $response['success'] = true;
        } else {
            $response['success'] = false;
        }
        return $response;
    }

    /**
     * @return array<bool|string|array<string>>
     */
    public function listAllDbs(): array
    {
        $sql = "SELECT datname FROM pg_catalog.pg_database";
        $result = $this->execQuery($sql);
        $arr = [];
        if (!$this->PDOerror) {
            while ($row = $this->fetchRow($result)) {
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

    /**
     * @return array<bool|string|int|array<mixed>>
     */
    public function listAllSchemas(): array
    {
        $arr = [];
        $sql = "SELECT count(*) AS count,f_table_schema FROM geometry_columns GROUP BY f_table_schema";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res)) {
            $count[$row['f_table_schema']] = $row['count'];
        }

        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'settings' AND schema_name<>'information_schema' AND schema_name<>'sqlapi' ORDER BY schema_name";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        while ($row = $this->fetchRow($res)) {
            $arr[] = array("schema" => $row['schema_name'], "count" => isset($count[$row['schema_name']]) ? $count[$row['schema_name']] : 0);
        }
        $response['success'] = true;
        $response['data'] = $arr;
        return $response;
    }

    /**
     * @param string $db
     * @param string $newOwner
     * @return void
     * @throws PDOException
     */
    public function changeOwner(string $db, string $newOwner): void
    {
        $this->db = null;
        $this->postgisdb = $db;

        $this->connect();
        $this->begin();

        //Database
        $sql = "ALTER DATABASE {$db} OWNER TO {$newOwner}";
        $this->db->query($sql);

        // Schema
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'information_schema'";
        $res = $this->db->query($sql);
        $rows1 = $this->fetchAll($res);

        // tables
        $sql = "SELECT '\"'||schemaname||'\".\"'||tablename||'\"' AS table FROM pg_tables WHERE schemaname NOT LIKE 'pg_%' AND schemaname<>'information_schema'";
        $res = $this->db->query($sql);
        $res = $this->execQuery($sql);
        $rows2 = $this->fetchAll($res);


        $sql = "SELECT '\"'||table_schema||'\".\"'||table_name||'\"' AS table FROM information_schema.views WHERE table_schema NOT LIKE 'pg_%' AND table_schema<>'information_schema'";
        $res = $this->db->query($sql);
        $rows3 = $this->fetchAll($res);


        $sql = "SELECT '\"'||sequence_schema||'\".\"'||sequence_name||'\"' AS table FROM information_schema.sequences WHERE sequence_schema NOT LIKE 'pg_%' AND sequence_schema<>'information_schema'";
        $res = $this->db->query($sql);
        $rows4 = $this->fetchAll($res);

        foreach ($rows1 as $row) {
            $sql = "ALTER SCHEMA {$row["schema_name"]} OWNER TO {$newOwner}";
            $this->db->query($sql);
        }
        foreach ($rows1 as $row) {
            $sql = "GRANT USAGE ON SCHEMA {$row["schema_name"]} TO {$newOwner}";
            $this->db->query($sql);
        }
        foreach ($rows2 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
            $this->db->query($sql);
        }
        foreach ($rows3 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
            $this->db->query($sql);
        }
        foreach ($rows4 as $row) {
            $this->db->query($sql);
            $sql = "ALTER TABLE {$row["table"]} OWNER TO {$newOwner}";
        }

        $this->commit();

    }

    /**
     * @param string|null $db
     */
    static function setDb(?string $db): void
    {
        Connection::$param["postgisdb"] = $db;
    }

    /**
     * @return string
     */
    static function getDb(): string
    {
        return Connection::$param["postgisdb"];
    }

    /**
     * @param string $schema
     * @param string $name
     * @return array<bool|int|string|array<string>>
     */
    public function renameSchema(string $schema, string $name): array
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
        } catch (PDOException $e) {
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
            } catch (PDOException $e) {
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
        } catch (PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $setObj = new Setting();
        $settings = $setObj->getArray();
        $extents = $settings->extents->$schema;
        $center = $settings->center->$schema;
        $zoom = $settings->zoom->$schema;
        if ($extents) {
            $settings->extents->$newName = $extents;
            $settings->center->$newName = $center;
            $settings->zoom->$newName = $zoom;
            if (App::$param["encryptSettings"]) {
                $pubKey = file_get_contents(App::$param["path"] . "app/conf/public.key");
                $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($settings) . "', dearmor('{$pubKey}'))";
            } else {
                $sql = "UPDATE settings.viewer SET viewer='" . json_encode($settings) . "'";
            }
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (PDOException $e) {
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

    /**
     * @param string $schema
     * @return array<bool|string|int>
     */
    public function deleteSchema(string $schema): array
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
        } catch (PDOException $e) {
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
        } catch (PDOException $e) {
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
