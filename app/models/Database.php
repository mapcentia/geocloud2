<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\inc\Connection;
use app\conf\Connection as StaticConnection;
use app\inc\Model;
use PDOException;


/**
 * Class Database
 * @package app\models
 */
class Database extends Model
{
    function __construct(?Connection $connection = null)
    {
        parent::__construct($connection);
    }

    /**
     * Creates a user in the database and assigns roles, privileges, or connections depending on the parameters.
     *
     * @param string $name The name of the user to be created.
     * @param mixed $db The database name for which the user will be granted privileges (optional).
     * @param bool $isSuperUser Flag indicating if the created user should have superuser privileges (default is false).
     * @return void
     */
    public function createUser(string $name, $db = null, bool $isSuperUser = false): void
    {
        // First try to create user if not exists
        $sql = "select usename from pg_user where usename=:name";
        $res = $this->prepare($sql);
        $this->execute($res, ['name' => $name]);
        if ($res->rowCount() == 0) {
            $sql = "CREATE USER \"$name\"";
            $this->execQuery($sql);
        }
        // Set password
        $sql = "ALTER ROLE \"$name\" PASSWORD '" . $this->postgispw . "'";
        $this->execQuery($sql);
        if ($db && !$isSuperUser) {
            // We grant the owner to user
            // This can only be done by a superuser
            try {
                $sql = "GRANT \"$db\" TO \"$name\"";
                $this->execQuery($sql);
            } catch (PDOException $e) {
                error_log($e->getMessage());
            }
            // And connect
            $sql = "GRANT CONNECT ON DATABASE \"$db\" TO \"$name\"";
            $this->execQuery($sql);
        }
        if ($isSuperUser) {
            $sql = "GRANT \"$name\" to $this->postgisuser";
            $this->execQuery($sql);
            $this->execQuery($sql);
        }
    }

    /**
     * Drops a user from the database.
     *
     * @param string $name The name of the user to be dropped.
     * @return void
     */
    public function dropUser(string $name): void
    {
        $sql = "DROP USER \"$name\"";
        $this->execQuery($sql);
    }

    /**
     * Drops a database with the specified name.
     *
     * @param string $name The name of the database to drop.
     * @return void
     */
    public function dropDatabase(string $name): void
    {
        $sql = "DROP DATABASE \"$name\"";
        $this->execQuery($sql);
    }

    /**
     * Creates a new schema in the database.
     *
     * @param string $name The name of the schema to create.
     * @param Model|null $model Optional. A model instance to use for preparing the SQL statement.
     * @return array An associative array containing the success status, message, and the name of the created schema.
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
        $this->execute($res);
        $response['success'] = true;
        $response['message'] = "Schema created";
        $response['schema'] = $saveName;
        return $response;
    }

    /**
     * Creates a database with the specified properties if it does not already exist.
     *
     * @param string $screenName The name of the database to create, which also serves as the username.
     * @param string $template The template database to use for creating the new database.
     * @param string $encoding The character encoding to set for the database. Defaults to "UTF8".
     * @return void
     */
    public function createdb(string $screenName, string $template, string $encoding = "UTF8"): void
    {
        $sql = "select datname from pg_database where datname=:db";
        $res = $this->prepare($sql);
        $this->execute($res, ['db' => $screenName]);
        if ($res->rowCount() == 0) {
            $sql = "CREATE DATABASE $screenName WITH ENCODING='$encoding' TEMPLATE=$template CONNECTION LIMIT=-1";
            $this->execQuery($sql, 'PG');
        }
        // We revoke connect from public, so other users can't connect to this database
        $sql = "REVOKE connect ON DATABASE $screenName FROM PUBLIC";
        $this->execQuery($sql);
        // Change ownership on all objects in the database
        $this->changeOwner($screenName, $screenName);
    }

    /**
     * Checks if a specified database exists.
     *
     * @param string $name The name of the database to check for existence.
     * @return array An associative array indicating whether the database exists with a 'success' key set to true or false.
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
     * Retrieves a list of all database names.
     *
     * @return array Returns an associative array with a success status and a list of database names.
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
     * Retrieves a list of all schemas in the database along with the count of their geometry columns.
     *
     * @return array Returns an associative array containing the success status, relevant data, and,
     *               in case of an error, the error message and code.
     */
    public function listAllSchemas(): array
    {
        $arr = [];
        $sql = "SELECT count(*) AS count,f_table_schema FROM geometry_columns where f_table_schema not like 'pg_%' GROUP BY f_table_schema";
        $res = $this->prepare($sql);
        $this->execute($res);
        while ($row = $this->fetchRow($res)) {
            $count[$row['f_table_schema']] = $row['count'];
        }
        $sql = "SELECT nspname AS schema_name FROM pg_catalog.pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname<>'settings' AND nspname<>'information_schema' AND nspname<>'sqlapi' ORDER BY nspname";
        $res = $this->prepare($sql);
        try {
            $this->execute($res);
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
     * Changes the owner of a specified database, its schemas, tables, views, and sequences to a new owner.
     *
     * @param string $db The name of the database to change the ownership for.
     * @param string $newOwner The name of the new owner to assign to the database and its objects.
     * @return void
     */
    public function changeOwner(string $db, string $newOwner): void
    {
//        $this->postgisdb = $db;
//
//        $this->connect();
//        $this->begin();

        //Database
        $sql = "ALTER DATABASE $db OWNER TO $newOwner";
        $this->execQuery($sql);

        // Schema
        $sql = "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows1 = $this->fetchAll($res);

        // tables
        $sql = "SELECT '\"'||schemaname||'\".\"'||tablename||'\"' AS \"table\" FROM pg_tables WHERE schemaname NOT LIKE 'pg_%' AND schemaname<>'information_schema'";
        $this->execQuery($sql);
        $res = $this->execQuery($sql);
        $rows2 = $this->fetchAll($res);

        $sql = "SELECT '\"'||table_schema||'\".\"'||table_name||'\"' AS \"table\" FROM information_schema.views WHERE table_schema NOT LIKE 'pg_%' AND table_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows3 = $this->fetchAll($res);

        $sql = "SELECT '\"'||sequence_schema||'\".\"'||sequence_name||'\"' AS \"table\" FROM information_schema.sequences WHERE sequence_schema NOT LIKE 'pg_%' AND sequence_schema<>'information_schema'";
        $res = $this->execQuery($sql);
        $rows4 = $this->fetchAll($res);

        foreach ($rows1 as $row) {
            $sql = "ALTER SCHEMA {$row["schema_name"]} OWNER TO $newOwner";
            $this->execQuery($sql);
        }
        foreach ($rows1 as $row) {
            $sql = "GRANT USAGE ON SCHEMA {$row["schema_name"]} TO $newOwner";
            $this->execQuery($sql);
        }
        foreach ($rows2 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
            $this->execQuery($sql);
        }
        foreach ($rows3 as $row) {
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
            $this->execQuery($sql);
        }
        foreach ($rows4 as $row) {
            $this->execQuery($sql);
            $sql = "ALTER TABLE {$row["table"]} OWNER TO $newOwner";
        }
        $this->commit();
    }

    /**
     * Sets the database name for the PostGIS connection.
     *
     * @param string|null $db The name of the database to set. Null if no database is specified.
     * @return void
     */
    static function setDb(?string $db): void
    {
        StaticConnection::$param["postgisdb"] = $db;
    }

    /**
     * Sets connection parameters based on the provided JWT data.
     *
     * @param array $jwt The JWT containing connection data, including the database name and user ID.
     * @return void
     */
    static function setFromJwt(array $jwt): void
    {
        $data = $jwt['data'];
        // Set connection params
        StaticConnection::$param["postgisdb"] = $data['database'];
        StaticConnection::$param["postgisuser"] = $data['uid'];
    }

    /**
     * @return string
     */
    static function getDb(): string
    {
        return StaticConnection::$param["postgisdb"];
    }

    /**
     * Renames a specified database schema and updates related configurations.
     *
     * @param string $schema The name of the schema to be renamed.
     * @param string $name The new name to assign to the schema.
     * @return array An associative array containing the success status, message, and updated schema information.
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
        $this->execute($res);
        while ($row = $this->fetchRow($res)) {
            $query = "UPDATE settings.geometry_columns_join SET _key_ = '$newName.{$row['f_table_name']}.{$row['f_geometry_column']}' WHERE _key_ ='{$row['f_table_schema']}.{$row['f_table_name']}.{$row['f_geometry_column']}'";
            $resUpdate = $this->prepare($query);
            $resUpdate->execute();
            $this->execute($resUpdate);
        }
        $query = "ALTER SCHEMA $schema RENAME TO $newName";
        $res = $this->prepare($query);
        $this->execute($res);
        $setObj = new Setting(connection: $this->connection);
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
            $this->execute($res);
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "$schema renamed to $newName";
        $response['data']['name'] = $newName;
        return $response;
    }

    /**
     * Deletes a specified schema from the database.
     *
     * @param string $schema The name of the schema to delete.
     * @param bool $commit Whether to commit the changes after executing the deletion. Defaults to true.
     * @return array An associative array containing the keys 'success' (bool) indicating the operation result,
     *               'message' (string) providing details about the operation, and optionally 'code' (int) in case of failure.
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
        $this->execute($res);
        $query = "DELETE FROM settings.geometry_columns_join WHERE _key_ LIKE '$schema.%'";
        $res = $this->prepare($query);
        $this->execute($res);
        if ($commit) {
            $this->commit();
        }
        $response['success'] = true;
        $response['message'] = "$schema dropped";
        return $response;
    }

    /**
     * Checks if a specified schema exists in the database.
     *
     * @param string $name The name of the schema to check for existence.
     * @return bool Returns true if the schema exists, false otherwise.
     */
    public function doesSchemaExist(string $name): bool
    {
        $sql = "SELECT schema_name FROM information_schema.schemata where schema_name=:name";
        $res = $this->prepare($sql);
        $this->execute($res, ["name" => $name]);
        $row = $this->fetchRow($res);
        return (bool)$row;
    }

    /**
     * Checks if a specified relation exists in the database.
     *
     * @param string $name The name of the relation to check for existence.
     * @return bool Returns true if the relation exists, false otherwise.
     */
    public function doesRelationExist(string $name): bool
    {
        $sql = "SELECT 1 FROM " . $this->doubleQuoteQualifiedName($name) . " LIMIT 1";
        try {
            $res = $this->prepare($sql);
            $this->execute($res);
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
