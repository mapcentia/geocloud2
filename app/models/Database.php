<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Model;
use PDOException;


/**
 * Class Database
 * @package app\models
 */
class Database extends Model
{
    /**
     * @param string $name
     * @param null $db
     * @param bool $isSuperUser
     * @return void
     */
    public function createUser(string $name, $db = null, bool $isSuperUser = false): void
    {
        $this->connect();
        // First try to create user if not exists
        $sql = "select usename from pg_user where usename=:name";
        $res = $this->prepare($sql);
        $res->execute(['name' => $name]);
        if ($res->rowCount() == 0) {
            $sql = "CREATE USER \"$name\"";
            $this->db->query($sql);
        }
        // Set password
        $sql = "ALTER ROLE \"$name\" PASSWORD '" . Connection::$param['postgispw'] . "'";
        $this->db->query($sql);

        if ($db && !$isSuperUser) {
            // We grant the owner to user
            // This can only be done by a superuser
            try {
                $sql = "GRANT \"$db\" TO \"$name\"";
                $this->db->query($sql);
            } catch (PDOException $e) {
                error_log($e->getMessage());
            }
            // And connect
            $sql = "GRANT CONNECT ON DATABASE \"$db\" TO \"$name\"";
            $this->db->query($sql);
        }
        if ($isSuperUser) {
            $sql = "GRANT \"$name\" to $this->postgisuser";
            $this->db->query($sql);
            $this->db->query($sql);
        }
    }

    /**
     * @param string $name
     * @throws PDOException
     */
    public function dropUser(string $name): void
    {
        $this->connect();
        $sql = "DROP USER \"$name\"";
        $this->db->query($sql);
    }

    /**
     * @param string $name
     * @throws PDOException
     */
    public function dropDatabase(string $name): void
    {
        $this->connect();
        $sql = "DROP DATABASE \"$name\"";
        $this->db->query($sql);
    }

    /**
     * @param string $name
     * @param Model|null $model
     * @return array<bool|string>
     */
    public function createSchema(string $name, ?Model $model = null): array
    {
        $saveName = self::toAscii($name, null, "_");
        $sql = "CREATE SCHEMA \"" . $saveName . "\"";
        if ($model) {
            $res = $model->prepare($sql);
        } else {
            $res = $this->prepare($sql);
        }
        $res->execute();
        $response['success'] = true;
        $response['message'] = "Schema created";
        $response['schema'] = $saveName;
        return $response;
    }

    /**
     * @param string $screenName
     * @param string $template
     * @param string $encoding
     * @return void
     * @throws PDOException
     */
    public function createdb(string $screenName, string $template, string $encoding = "UTF8"): void
    {
        // Create user for the database
        $this->createUser($screenName, null, true);
        // Create the database if not exists
        $sql = "select datname from pg_database where datname=:db";
        $res = $this->prepare($sql);
        $res->execute(['db' => $screenName]);
        if ($res->rowCount() == 0) {
            $sql = "CREATE DATABASE $screenName WITH ENCODING='$encoding' TEMPLATE=$template CONNECTION LIMIT=-1";
            $this->db->query($sql);
        }
        // We revoke connect from public, so other users can't connect to this database
        $sql = "REVOKE connect ON DATABASE $screenName FROM PUBLIC";
        $this->db->query($sql);
        // Change ownership on all objects in the database
        $this->changeOwner($screenName, $screenName);
    }

    /**
     * @param string $name
     * @return array<bool>
     */
    public function doesDbExist(string $name): array
    {
        $sql = "SELECT 1 AS \"check\" FROM pg_database WHERE datname='$name'";
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
        while ($row = $this->fetchRow($result)) {
            $arr[] = $row['datname'];
        }
        $response['success'] = true;
        $response['data'] = $arr;
        return $response;
    }

    /**
     * @return array<bool|string|int|array>
     */
    public function listAllSchemas(): array
    {
        $arr = [];
        $sql = "SELECT count(*) AS count,f_table_schema FROM geometry_columns where f_table_schema not like 'pg_%' GROUP BY f_table_schema";
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

        $sql = "SELECT nspname AS schema_name FROM pg_catalog.pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname<>'settings' AND nspname<>'information_schema' AND nspname<>'sqlapi' ORDER BY nspname";
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
            $arr[] = array("schema" => $row['schema_name'], "count" => $count[$row['schema_name']] ?? 0);
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
        $sql = "ALTER DATABASE $db OWNER TO $newOwner";
        $this->db->query($sql);

        // Schema
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'information_schema'";
        $res = $this->db->query($sql);
        $rows1 = $this->fetchAll($res);

        // tables
        $sql = "SELECT '\"'||schemaname||'\".\"'||tablename||'\"' AS \"table\" FROM pg_tables WHERE schemaname NOT LIKE 'pg_%' AND schemaname<>'information_schema'";
        $this->db->query($sql);
        $res = $this->execQuery($sql);
        $rows2 = $this->fetchAll($res);


        $sql = "SELECT '\"'||table_schema||'\".\"'||table_name||'\"' AS \"table\" FROM information_schema.views WHERE table_schema NOT LIKE 'pg_%' AND table_schema<>'information_schema'";
        $res = $this->db->query($sql);
        $rows3 = $this->fetchAll($res);

        $sql = "SELECT '\"'||sequence_schema||'\".\"'||sequence_name||'\"' AS \"table\" FROM information_schema.sequences WHERE sequence_schema NOT LIKE 'pg_%' AND sequence_schema<>'information_schema'";
        $res = $this->db->query($sql);
        $rows4 = $this->fetchAll($res);

        foreach ($rows1 as $row) {
            $sql = "ALTER SCHEMA {$row["schema_name"]} OWNER TO $newOwner";
            $this->db->query($sql);
        }
        foreach ($rows1 as $row) {
            $sql = "GRANT USAGE ON SCHEMA {$row["schema_name"]} TO $newOwner";
            $this->db->query($sql);
        }
        foreach ($rows2 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
            $this->db->query($sql);
        }
        foreach ($rows3 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
            $this->db->query($sql);
        }
        foreach ($rows4 as $row) {
            $this->db->query($sql);
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
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

    static function setFromJwt(array $jwt): void
    {
        $data = $jwt['data'];
        // Set connection params
        Connection::$param["postgisdb"] = $data['database'];
        Connection::$param["postgisuser"] = $data['uid'];
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
        $whereClauseG = "f_table_schema=''$schema''";
        $whereClauseR = "r_table_schema=''$schema''";
        $query = "SELECT * FROM settings.getColumns('$whereClauseG','$whereClauseR') ORDER BY sort_id";
        $res = $this->prepare($query);
        $res->execute();
        while ($row = $this->fetchRow($res)) {
            $query = "UPDATE settings.geometry_columns_join SET _key_ = '$newName.{$row['f_table_name']}.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
            $resUpdate = $this->prepare($query);
            $resUpdate->execute();
        }
        $query = "ALTER SCHEMA $schema RENAME TO $newName";
        $res = $this->prepare($query);
        $res->execute();
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
                $sql = "UPDATE settings.viewer SET viewer=pgp_pub_encrypt('" . json_encode($settings) . "', dearmor('$pubKey'))";
            } else {
                $sql = "UPDATE settings.viewer SET viewer='" . json_encode($settings) . "'";
            }
            $res = $this->prepare($sql);
            $res->execute();
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "$schema renamed to $newName";
        $response['data']['name'] = $newName;
        return $response;
    }

    /**
     * @param string $schema
     * @return array<bool|string|int>
     */
    public function deleteSchema(string $schema, bool $commit = true): array
    {
        if ($schema == "public") {
            $response['success'] = false;
            $response['message'] = "You can't delete 'public'";
            $response['code'] = 401;
            return $response;
        }
        if ($commit) {
            $this->connect();
            $this->begin();
        }
        $query = "DROP SCHEMA $schema CASCADE";
        $res = $this->prepare($query);
        $res->execute();
        $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ LIKE '$schema.%'";
        $res = $this->prepare($query);
        $res->execute();
        if ($commit) {
            $this->commit();
        }
        $response['success'] = true;
        $response['message'] = "$schema dropped";
        return $response;
    }

    public function doesSchemaExist(string $name): bool
    {
        $sql = "SELECT schema_name FROM information_schema.schemata where schema_name=:name";
        $res = $this->prepare($sql);
        $res->execute(["name" => $name]);
        $row = $this->fetchRow($res);
        return (bool)$row;
    }

    public function doesRelationExist(string $name): bool
    {
        $sql = "SELECT 1 FROM " . $this->doubleQuoteQualifiedName($name) . " LIMIT 1";
        try {
            $res = $this->prepare($sql);
            $res->execute();
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
